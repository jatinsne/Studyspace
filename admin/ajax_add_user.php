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

// 2. Capture & Sanitize Data
$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$aadhaar = trim($_POST['aadhaar_number'] ?? '');
$biometric_id = trim($_POST['biometric_id'] ?? '');
$card_id = trim($_POST['card_id'] ?? '');
$sync_instantly = isset($_POST['sync_instantly']) && $_POST['sync_instantly'] == '1';

$password = password_hash('123456', PASSWORD_DEFAULT);
$status = 'verified';

// 3. Handle File Uploads
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
    return null;
}

$profilePath = handleUpload('profile_image', $uploadDir);
$docPath = handleUpload('doc_proof', $uploadDir);

try {
    $pdo->beginTransaction();

    // 4. Insert User
    $sql = "INSERT INTO users (name, email, phone, password_hash, role, aadhaar_number, profile_image, doc_proof, verification_status, biometric_id, card_id, biometric_enable) 
            VALUES (?, ?, ?, ?, 'student', ?, ?, ?, ?, ?, ?, 1)";
    $stmt = $pdo->prepare($sql);

    $initial_bio_id = empty($biometric_id) ? null : $biometric_id;
    $stmt->execute([$name, $email, $phone, $password, $aadhaar, $profilePath, $docPath, $status, $initial_bio_id, $card_id]);

    $newUserId = $pdo->lastInsertId();

    // 5. Auto-assign Biometric ID if empty
    if (empty($biometric_id)) {
        $biometric_id = (string)$newUserId;
        $pdo->prepare("UPDATE users SET biometric_id = ? WHERE id = ?")->execute([$biometric_id, $newUserId]);
    }

    // 6. Handle Biometric Sync
    $deviceId = getenv('BIOMETRIC_DEVICE_ID');
    $baseUrl = getenv('BIOMETRIC_URL_BASE');
    $syncMessage = "Added to background queue.";
    $triggerQueue = false;

    if ($sync_instantly) {
        // --- DIRECT INSTANT API CALL ---
        $apiUrl = rtrim($baseUrl, '/') . "/api/device/$deviceId/test-user";

        $directPayload = json_encode([
            "userID" => (string)$biometric_id,
            "name" => substr($name, 0, 24),
            "card" => $card_id,
            "password" => "123456"
        ]);

        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_URL => $apiUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $directPayload,
            CURLOPT_HTTPHEADER => array('Content-Type: application/json'),
        ));

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        // curl_close($ch);

        $json = json_decode($response, true);
        $cmdId = $json['command_id'] ?? null;

        if ($err || $httpCode >= 400) {
            $jobStatus = 'failed';
            $logResponse = $err ? "CURL Error: $err" : "HTTP $httpCode: $response";
            $syncMessage = "Local account created, but direct device sync failed.";
        } else {
            $jobStatus = 'completed';
            $logResponse = $response;
            $syncMessage = "Account created & pushed to hardware instantly!";
        }

        $logSql = "INSERT INTO biometric_jobs (biometric_id, device_command_id, command, payload, status, device_response, created_at) VALUES (?, ?, 'ADD_USER', ?, ?, ?, NOW())";
        $pdo->prepare($logSql)->execute([$deviceId, $cmdId, $directPayload, $jobStatus, $logResponse]);
    } else {
        // --- QUEUE FOR BACKGROUND WORKER ---
        $payloadData = [
            "device_id" => $deviceId,
            "cmd_code" => "SetUserData",
            "params" => [
                "UserID" => (int)$biometric_id,
                "Type" => "Set",
                "Name" => substr($name, 0, 24),
                "Privilege" => "User",
                "Enabled" => "Yes",
                "Card" => (!empty($card_id) ? base64_encode($card_id) : ""),
                "UserPeriod_Used" => "No"
            ]
        ];

        $jobSql = "INSERT INTO biometric_jobs (biometric_id, device_command_id, payload, status, created_at) VALUES (?, 'ADD_USER', ?, 'pending', NOW())";
        $pdo->prepare($jobSql)->execute([$deviceId, json_encode($payloadData)]);
        $triggerQueue = true;
    }

    $pdo->commit();

    echo json_encode([
        "status" => "success",
        "message" => "User created successfully! " . $syncMessage,
        "trigger_queue" => $triggerQueue,
        "biometric_id" => $biometric_id
    ]);
} catch (PDOException $e) {
    $pdo->rollBack();
    if ($e->getCode() == 23000) {
        echo json_encode(["status" => "error", "message" => "Email, Phone, Biometric ID, or Card ID already exists."]);
    } else {
        echo json_encode(["status" => "error", "message" => "Database Error: " . $e->getMessage()]);
    }
}
