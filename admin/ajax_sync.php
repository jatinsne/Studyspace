<?php
require_once '../config/Database.php';
session_start();

header('Content-Type: application/json');

// 1. SECURITY CHECK
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    exit(json_encode(["status" => "error", "message" => "Unauthorized Access"]));
}

$pdo = Database::getInstance()->getConnection();
$action = $_POST['action'] ?? '';

// ---------------------------------------------------------
// ACTION 1: QUEUE & PUSH SYNC (Instant API Call)
// ---------------------------------------------------------
if ($action === 'queue_sync') {
    $userId = $_POST['user_id'] ?? 0;

    // A. Fetch User Data
    $stmt = $pdo->prepare("SELECT name, biometric_id, card_id, biometric_enable, subscription_startdate, subscription_enddate FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || empty($user['biometric_id'])) {
        exit(json_encode(["status" => "error", "message" => "User has no Biometric ID linked. Edit profile to add one."]));
    }

    $bioId = $user['biometric_id'];
    $deviceId = getenv('BIOMETRIC_DEVICE_ID');
    $baseUrl = getenv('BIOMETRIC_URL_BASE');

    // B. Check Date Limits
    $validityEnabled = ($user['biometric_enable'] && $user['subscription_startdate'] && $user['subscription_enddate']) ? true : false;

    // C. Prepare Payload for Node.js API
    $payloadArray = [
        "name" => substr($user['name'], 0, 24),
        "card" => $user['card_id'],
        "enabled" => (bool)$user['biometric_enable'],
        "validity_enabled" => $validityEnabled
    ];

    if ($validityEnabled) {
        $payloadArray["valid_start"] = $user['subscription_startdate'];
        $payloadArray["valid_end"] = $user['subscription_enddate'];
    }

    $directPayload = json_encode($payloadArray);

    try {
        // D. Execute Instant cURL request to Node API
        $apiUrl = rtrim($baseUrl, '/') . "/api/device/$deviceId/user/" . urlencode($bioId);

        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_URL => $apiUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => 4, // Fast fail
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

        // E. Handle Response
        if ($err || $httpCode >= 400 || (isset($json['success']) && !$json['success'])) {
            $status = 'failed';
            $logMsg = $err ? "Conn Error: $err" : "HTTP $httpCode: $response";

            // Log Failure
            $upd = "INSERT INTO biometric_jobs (biometric_id, device_command_id, command, payload, status, device_response, created_at) VALUES (?, ?, 'SYNC_USER', ?, ?, ?, NOW())";
            $pdo->prepare($upd)->execute([$deviceId, $cmdId, $directPayload, $status, $logMsg]);

            echo json_encode(["status" => "error", "message" => "Device API rejected request."]);
        } else {
            // Success! Node API Queued it. 
            // We set it to 'processing' and let the Poller handle the final check.
            $status = 'processing';
            $logMsg = $response;

            // Insert Job so we can poll it
            $sql = "INSERT INTO biometric_jobs (biometric_id, device_command_id, command, payload, status, device_response, created_at) VALUES (?, ?, 'SYNC_USER', ?, ?, ?, NOW())";
            $q = $pdo->prepare($sql);
            $q->execute([$deviceId, $cmdId, $directPayload, $status, $logMsg]);

            $jobId = $pdo->lastInsertId();

            echo json_encode(["status" => "queued", "job_id" => $jobId, "message" => "Sync initiated..."]);
        }
    } catch (Exception $e) {
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }
}

// ---------------------------------------------------------
// ACTION 2: CHECK STATUS (GET - Active Verification)
// ---------------------------------------------------------
if ($action === 'check_status') {
    $jobId = $_POST['job_id'] ?? 0;

    $stmt = $pdo->prepare("SELECT status, device_response, device_command_id FROM biometric_jobs WHERE job_id = ?");
    $stmt->execute([$jobId]);
    $job = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$job) {
        exit(json_encode(["status" => "error", "message" => "Job not found"]));
    }

    // ACTUALLY CHECK DEVICE if still processing
    if ($job['status'] === 'processing' && !empty($job['device_command_id'])) {

        $baseUrl = getenv('BIOMETRIC_URL_BASE');
        $checkUrl = rtrim($baseUrl, '/') . '/api/command/' . $job['device_command_id'];

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

            if (isset($json['status']) && $json['status'] === 'RESULT') {
                $newStatus = (isset($json['return_code']) && $json['return_code'] === 'OK') ? 'completed' : 'failed';
                $newResponse = json_encode($json['result'] ?? $json);

                $pdo->prepare("UPDATE biometric_jobs SET status=?, device_response=?, updated_at=NOW() WHERE job_id=?")
                    ->execute([$newStatus, $newResponse, $jobId]);

                $job['status'] = $newStatus;
                $job['device_response'] = $newResponse;
            }
        }
    }

    echo json_encode([
        "status" => $job['status'],
        "response" => $job['device_response']
    ]);
}
