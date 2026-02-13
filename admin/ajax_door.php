<?php
require_once '../config/Database.php';
session_start();

// 1. SECURITY: Only Admins can open the door
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "Unauthorized Access"]);
    exit;
}

// 2. CONFIGURATION
$pdo = Database::getInstance()->getConnection();
$url = getenv('BIOMETRIC_URL');
$deviceId = getenv('BIOMETRIC_DEVICE_ID');

if (!$url and !$deviceId) {
    echo json_encode(["status" => "error", "message" => "Biometric URL or Biometric Device ID is missing"]);
    exit;
}

// 3. PREPARE PAYLOAD
$payload = json_encode([
    "device_id" => $deviceId,
    "cmd_code" => "LockControl",
    "params" => ["Mode" => 3] // Mode 3 = Pulse Open
]);

// 4. EXECUTE cURL
$curl = curl_init();
curl_setopt_array($curl, array(
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 3,
    CURLOPT_TIMEOUT => 5, // 5 Second Timeout
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_HTTPHEADER => array('Content-Type: application/json'),
));

$response = curl_exec($curl);
$httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
$err = curl_error($curl);
// curl_close($curl);

$status = 'processing';
$logMsg = $response;

// --- PARSE JSON TO FIND ID ---
$json = json_decode($response, true);
if ($json) {
    $deviceCmdId = $json['trans_id'] ?? $json['job_id'] ?? $json['command_id'] ?? $json['id'] ?? null;
}

// 5. HANDLE RESPONSE
if ($err) {
    $status = 'failed';
    $logMsg = "Connection Error: $err";
    $clientMsg = $logMsg;
    $clientStatus = "error";
} elseif ($httpCode >= 400) {
    $status = 'failed';
    $logMsg = "Device Error (HTTP $httpCode): " . $response;
    $clientMsg = $logMsg;
    $clientStatus = "error";
} else {
    $clientMsg = "Door Unlocked Successfully";
    $clientStatus = "success";
}

// --- INSERT INTO BIOMETRIC_JOBS ---
try {
    $sql = "INSERT INTO biometric_jobs 
            (biometric_id, device_command_id, command, payload, status, device_response) 
            VALUES (?, ?, 'DOOR_OPEN', ?, ?, ?)";

    $pdo->prepare($sql)->execute([
        $deviceId,
        $deviceCmdId,
        $payload,
        $status,
        $logMsg
    ]);
} catch (PDOException $e) {
    error_log("DB Log Failed: " . $e->getMessage());
}

// 6. RESPONSE TO FRONTEND
echo json_encode([
    "status" => $clientStatus,
    "message" => $clientMsg,
    "data" => json_decode($response)
]);
