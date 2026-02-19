<?php
require_once '../config/Database.php';
session_start();

// Security Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    exit(json_encode(["success" => false, "status" => "UNAUTHORIZED"]));
}

// Configuration
$deviceId = getenv('BIOMETRIC_DEVICE_ID');
$baseUrl = getenv('BIOMETRIC_URL_BASE');
$endpoint = rtrim($baseUrl, '/') . "/api/device/$deviceId/status";

// Execute cURL (Exactly as provided)
$curl = curl_init();
curl_setopt_array($curl, array(
    CURLOPT_URL => $endpoint,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 5,
    CURLOPT_TIMEOUT => 3, // Fast timeout
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'GET',
    CURLOPT_POSTFIELDS => json_encode([
        "user_id" => $_SESSION['user_id'],
        "source_device_id" => $deviceId
    ]),
    CURLOPT_HTTPHEADER => array('Content-Type: application/json'),
));

$response = curl_exec($curl);
$err = curl_error($curl);
//curl_close($curl);

if ($err) {
    echo json_encode([
        "success" => false,
        "online" => false,
        "status" => "ERROR",
        "message" => "Connection failed"
    ]);
} else {
    echo $response;
}
