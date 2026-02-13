<?php
require_once '../config/Database.php';
session_start();

// 1. SECURITY CHECK
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    exit(json_encode(["status" => "error", "message" => "Unauthorized Access"]));
}

$pdo = Database::getInstance()->getConnection();
$action = $_POST['action'] ?? '';

// ---------------------------------------------------------
// HELPER: DATE CONVERSION
// ---------------------------------------------------------
function dateToDeviceInt($dateStr)
{
    if (!$dateStr) return 0;
    $ts = strtotime($dateStr);
    $year = (int)date('Y', $ts);
    if ($year < 2000) return 0;
    return (($year - 2000) << 16) + ((int)date('m', $ts) << 8) + (int)date('d', $ts);
}

// ---------------------------------------------------------
// ACTION 1: QUEUE & PUSH (POST)
// ---------------------------------------------------------
if ($action === 'queue_sync') {
    $userId = $_POST['user_id'] ?? 0;

    // A. Fetch User Data
    $stmt = $pdo->prepare("SELECT name, biometric_id, card_id, biometric_enable, subscription_startdate, subscription_enddate FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || empty($user['biometric_id'])) {
        exit(json_encode(["status" => "error", "message" => "User has no Biometric ID linked."]));
    }

    // B. Prepare Payload
    $usePeriod = ($user['biometric_enable'] && $user['subscription_startdate'] && $user['subscription_enddate']) ? "Yes" : "No";
    $pStart = dateToDeviceInt($user['subscription_startdate']);
    $pEnd   = dateToDeviceInt($user['subscription_enddate']);
    $enabledStatus = ($user['biometric_enable']) ? "Yes" : "No";

    $payloadData = [
        "device_id" => getenv('BIOMETRIC_DEVICE_ID') ?: 'RSS202508126365',
        "cmd_code" => "SetUserData",
        "params" => [
            "UserID" => (int)$user['biometric_id'],
            "Type" => "Set",
            "Name" => substr($user['name'], 0, 24),
            "Privilege" => "User",
            "Enabled" => $enabledStatus,
            "Card" => (!empty($user['card_id']) ? base64_encode($user['card_id']) : ""),
            "UserPeriod_Used" => $usePeriod,
            "UserPeriod_Start" => $pStart,
            "UserPeriod_End" => $pEnd
        ]
    ];
    $payloadJson = json_encode($payloadData);

    try {
        // C. Insert Job
        $sql = "INSERT INTO biometric_jobs (biometric_id, command, payload, status, created_at) VALUES (?, 'ADD_USER', ?, 'pending', NOW())";
        $q = $pdo->prepare($sql);
        $q->execute([$user['biometric_id'], $payloadJson]);
        $jobId = $pdo->lastInsertId();

        // D. Immediate Push
        $deviceUrl = getenv('BIOMETRIC_URL');
        if ($deviceUrl) {
            $pdo->prepare("UPDATE biometric_jobs SET status = 'processing' WHERE job_id = ?")->execute([$jobId]);

            $ch = curl_init();
            curl_setopt_array($ch, array(
                CURLOPT_URL => $deviceUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 3,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => $payloadJson,
                CURLOPT_HTTPHEADER => array('Content-Type: application/json'),
            ));

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err = curl_error($ch);
            //curl_close($ch);

            $status = 'processing';
            $logMsg = $response;
            $cmdId = null;

            if ($err || $httpCode >= 400) {
                $status = 'failed';
                $logMsg = $err ? "Conn Error: $err" : "HTTP $httpCode: $response";
            } else {
                $json = json_decode($response, true);
                $cmdId = $json['command_id'] ?? $json['job_id'] ?? null;
                // If device confirms immediately
                if (isset($json['status']) && $json['status'] == 'RESULT') {
                    $status = ($json['return_code'] == 'OK') ? 'completed' : 'failed';
                }
            }

            $upd = "UPDATE biometric_jobs SET status=?, device_response=?, device_command_id=?, updated_at=NOW() WHERE job_id=?";
            $pdo->prepare($upd)->execute([$status, $logMsg, $cmdId, $jobId]);
        }

        echo json_encode(["status" => "queued", "job_id" => $jobId, "message" => "Sync initiated..."]);
    } catch (Exception $e) {
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }
}

// ---------------------------------------------------------
// ACTION 2: CHECK STATUS (GET - Active Verification)
// ---------------------------------------------------------
if ($action === 'check_status') {
    $jobId = $_POST['job_id'] ?? 0;

    // 1. Fetch Current DB Status
    $stmt = $pdo->prepare("SELECT status, device_response, device_command_id FROM biometric_jobs WHERE job_id = ?");
    $stmt->execute([$jobId]);
    $job = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$job) {
        exit(json_encode(["status" => "error", "message" => "Job not found"]));
    }

    // 2. If 'processing' and we have an ID, ACTUALLY CHECK DEVICE
    if ($job['status'] === 'processing' && !empty($job['device_command_id'])) {

        $baseUrl = getenv('BIOMETRIC_URL'); // e.g., localhost:3000/api/command
        $checkUrl = rtrim($baseUrl, '/') . '/' . $job['device_command_id'];

        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_URL => $checkUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 4,
            CURLOPT_CUSTOMREQUEST => 'GET',
        ));

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        //curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300 && $response) {
            $json = json_decode($response, true);

            // Check if result is available
            if (isset($json['status']) && $json['status'] === 'RESULT') {
                $newStatus = ($json['return_code'] === 'OK') ? 'completed' : 'failed';
                $newResponse = json_encode($json['result'] ?? $json);

                // Update DB so we don't have to check again
                $pdo->prepare("UPDATE biometric_jobs SET status=?, device_response=?, updated_at=NOW() WHERE job_id=?")
                    ->execute([$newStatus, $newResponse, $jobId]);

                // Return updated data
                $job['status'] = $newStatus;
                $job['device_response'] = $newResponse;
            }
        }
    }

    // 3. Return Final Status
    echo json_encode([
        "status" => $job['status'],
        "response" => $job['device_response']
    ]);
}
