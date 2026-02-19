<?php
require_once '../config/Database.php';
session_start();

header('Content-Type: application/json');

// 1. Security Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "Unauthorized Access"]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => "error", "message" => "Invalid request method."]);
    exit;
}

$pdo = Database::getInstance()->getConnection();

// 2. Capture Data
$id = $_POST['id'] ?? 0;
$name = trim($_POST['name'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$email = trim($_POST['email'] ?? '');
$aadhaar = trim($_POST['aadhaar_number'] ?? '');
$status = $_POST['verification_status'] ?? 'pending';

$bioId = trim($_POST['biometric_id'] ?? '');
$cardId = trim($_POST['card_id'] ?? '');
$bioEnable = isset($_POST['biometric_enable']) ? 1 : 0;
$sync_instantly = isset($_POST['sync_instantly']) && $_POST['sync_instantly'] == '1';

$subStart = !empty($_POST['subscription_startdate']) ? $_POST['subscription_startdate'] : null;
$subEnd = !empty($_POST['subscription_enddate']) ? $_POST['subscription_enddate'] : null;

if (!$id) {
    echo json_encode(["status" => "error", "message" => "Missing User ID."]);
    exit;
}

try {
    // 3. Fetch existing user for old images
    $stmt = $pdo->prepare("SELECT profile_image, doc_proof FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo json_encode(["status" => "error", "message" => "User not found."]);
        exit;
    }

    // 4. Handle File Uploads
    $uploadDir = '../uploads/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

    function handleUpload($inputName, $targetDir)
    {
        if (!empty($_FILES[$inputName]['name'])) {
            $fileName = time() . '_' . preg_replace("/[^a-zA-Z0-9.-]/", "_", basename($_FILES[$inputName]['name']));
            $target = $targetDir . $fileName;
            if (move_uploaded_file($_FILES[$inputName]['tmp_name'], $target)) {
                return $fileName;
            }
        }
        return false;
    }

    $profilePath = $user['profile_image'];
    $newProfile = handleUpload('profile_image', $uploadDir);
    if ($newProfile) $profilePath = $newProfile;

    $docPath = $user['doc_proof'];
    $newDoc = handleUpload('doc_proof', $uploadDir);
    if ($newDoc) $docPath = $newDoc;

    $pdo->beginTransaction();

    // 5. Update Database
    $sql = "UPDATE users SET 
            name=?, phone=?, email=?, aadhaar_number=?, verification_status=?, 
            profile_image=?, doc_proof=?,
            biometric_id=?, card_id=?, biometric_enable=?,
            subscription_startdate=?, subscription_enddate=?
            WHERE id=?";

    $pdo->prepare($sql)->execute([
        $name,
        $phone,
        $email,
        $aadhaar,
        $status,
        $profilePath,
        $docPath,
        $bioId,
        $cardId,
        $bioEnable,
        $subStart,
        $subEnd,
        $id
    ]);

    // 6. Handle Biometric Sync
    $syncMessage = "Locally updated.";
    $triggerQueue = false;

    if (!empty($bioId)) {
        $deviceId = getenv('BIOMETRIC_DEVICE_ID');
        $baseUrl = getenv('BIOMETRIC_URL_BASE');

        // Check if date limits should be enforced
        $validityEnabled = ($bioEnable && $subStart && $subEnd) ? true : false;

        if ($sync_instantly) {
            // --- 1. DIRECT INSTANT API CALL (PUT) ---
            // Using your new Node.js API route
            $apiUrl = rtrim($baseUrl, '/') . "/api/device/$deviceId/user/" . urlencode($bioId);

            // Build cleaner payload (Node.js handles bitwise calculations now)
            $payloadArray = [
                "name" => substr($name, 0, 24),
                "card" => $cardId,
                "enabled" => (bool)$bioEnable,
                "validity_enabled" => $validityEnabled
            ];

            // Only append dates if validity is enabled
            if ($validityEnabled) {
                $payloadArray["valid_start"] = $subStart;
                $payloadArray["valid_end"] = $subEnd;
            }

            $directPayload = json_encode($payloadArray);

            $ch = curl_init();
            curl_setopt_array($ch, array(
                CURLOPT_URL => $apiUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 5,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'PUT',
                CURLOPT_POSTFIELDS => $directPayload,
                CURLOPT_HTTPHEADER => array('Content-Type: application/json'),
            ));

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err = curl_error($ch);
            //curl_close($ch);

            $json = json_decode($response, true);
            $cmdId = $json['command_id'] ?? null;

            if ($err || $httpCode >= 400 || (isset($json['success']) && !$json['success'])) {
                $jobStatus = 'failed';
                $logResponse = $err ? "CURL Error: $err" : "HTTP $httpCode: $response";
                $syncMessage = "Updated locally, but direct device API rejected the request.";
            } else {
                // If success, the Node API queued it in MongoDB. We mark as processing.
                $jobStatus = 'processing';
                $logResponse = $response;
                $syncMessage = "Updated locally and queued instantly on the device API!";
            }

            // Log it
            $logSql = "INSERT INTO biometric_jobs (biometric_id, device_command_id, command, payload, status, device_response, created_at) VALUES (?, ?,'EDIT_USER', ?, ?, ?, NOW())";
            $pdo->prepare($logSql)->execute([$deviceId, $cmdId, $directPayload, $jobStatus, $logResponse]);
        } else {
            // --- 2. QUEUE FOR BACKGROUND WORKER ---
            // If queued locally, we keep the raw SetUserData format because ajax_push_batch.php expects it.
            function dateToDeviceInt($dateStr)
            {
                if (!$dateStr) return 0;
                $ts = strtotime($dateStr);
                $year  = (int)date('Y', $ts);
                if ($year < 2000) return 0;
                return (($year - 2000) << 16) + ((int)date('m', $ts) << 8) + (int)date('d', $ts);
            }

            $rawPayloadData = [
                "device_id" => $deviceId,
                "cmd_code" => "SetUserData",
                "params" => [
                    "UserID" => (int)$bioId,
                    "Type" => "Set",
                    "Name" => substr($name, 0, 24),
                    "Privilege" => "User",
                    "Enabled" => ($bioEnable ? "Yes" : "No"),
                    "Card" => (!empty($cardId) ? base64_encode($cardId) : ""),
                    "UserPeriod_Used" => $validityEnabled ? "Yes" : "No",
                    "UserPeriod_Start" => $validityEnabled ? dateToDeviceInt($subStart) : 0,
                    "UserPeriod_End" => $validityEnabled ? dateToDeviceInt($subEnd) : 0
                ]
            ];

            $logSql = "INSERT INTO biometric_jobs (biometric_id, device_command_id, payload, status, created_at) VALUES (?, 'ADD_USER', ?, 'pending', NOW())";
            $pdo->prepare($logSql)->execute([$deviceId, json_encode($rawPayloadData)]);

            $syncMessage = "Updated locally and added to background sync queue.";
            $triggerQueue = true;
        }
    }

    $pdo->commit();

    echo json_encode([
        "status" => "success",
        "message" => $syncMessage,
        "trigger_queue" => $triggerQueue
    ]);
} catch (PDOException $e) {
    $pdo->rollBack();
    echo json_encode(["status" => "error", "message" => "Database Error: " . $e->getMessage()]);
}
