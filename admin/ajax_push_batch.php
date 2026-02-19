<?php
require_once '../config/Database.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    exit(json_encode(["status" => "error", "message" => "Unauthorized"]));
}

$pdo = Database::getInstance()->getConnection();

$baseUrl = getenv('BIOMETRIC_URL_BASE');
$deviceId = getenv('BIOMETRIC_DEVICE_ID');

// 1. Fetch pending batch
$sql = "SELECT * FROM biometric_jobs WHERE status = 'pending' ORDER BY created_at ASC LIMIT 5";
$jobs = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
$remaining = $pdo->query("SELECT COUNT(*) FROM biometric_jobs WHERE status = 'pending'")->fetchColumn();

if (empty($jobs)) exit(json_encode(["status" => "done", "remaining" => 0]));

$processed = 0;

foreach ($jobs as $job) {
    $ch = curl_init();

    // 2. Route dynamically based on command type
    if ($job['command'] === 'SYNC_USER') {
        // New API Route for Users (PUT)
        $apiUrl = rtrim($baseUrl, '/') . "/api/device/$deviceId/user/" . urlencode($job['biometric_id']);

        curl_setopt_array($ch, array(
            CURLOPT_URL => $apiUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_CUSTOMREQUEST => 'PUT',
            CURLOPT_POSTFIELDS => $job['payload'],
            CURLOPT_HTTPHEADER => array('Content-Type: application/json'),
        ));
    } else {
        // Standard Command Route (POST) e.g., for Door Unlocks
        $apiUrl = rtrim($baseUrl, '/') . '/api/command';

        curl_setopt_array($ch, array(
            CURLOPT_URL => $apiUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $job['payload'],
            CURLOPT_HTTPHEADER => array('Content-Type: application/json'),
        ));
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    //curl_close($ch);

    $newStatus = 'processing';
    $cmdId = null;
    $logMsg = $response;

    if ($err || $httpCode >= 400) {
        $newStatus = 'failed';
        $logMsg = "Push Error: " . ($err ?: $httpCode) . " - " . $response;
    } else {
        $json = json_decode($response, true);
        $cmdId = $json['command_id'] ?? null;

        if (isset($json['success']) && !$json['success']) {
            $newStatus = 'failed';
        }
    }

    $pdo->prepare("UPDATE biometric_jobs SET status=?, device_response=?, device_command_id=?, updated_at=NOW() WHERE job_id=?")
        ->execute([$newStatus, $logMsg, $cmdId, $job['job_id']]);

    $processed++;
}

echo json_encode(["status" => "progress", "processed" => $processed, "remaining" => $remaining - $processed]);
