<?php
// FILE: admin/ajax_push_batch.php
// ... (Standard Setup) ...
require_once '../config/Database.php';
$pdo = Database::getInstance()->getConnection();
$apiUrl = getenv('BIOMETRIC_URL');

// 1. FETCH PENDING
$sql = "SELECT * FROM biometric_jobs WHERE status = 'pending' ORDER BY created_at ASC LIMIT 5";
$jobs = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
$remaining = $pdo->query("SELECT COUNT(*) FROM biometric_jobs WHERE status = 'pending'")->fetchColumn();

if (empty($jobs)) exit(json_encode(["status" => "done", "remaining" => 0]));

$processed = 0;

foreach ($jobs as $job) {
    // 2. CURL POST (Push to Device)
    $ch = curl_init();
    curl_setopt_array($ch, array(
        CURLOPT_URL => $apiUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 5,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => $job['payload'],
        CURLOPT_HTTPHEADER => array('Content-Type: application/json'),
    ));

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    //curl_close($ch);

    // 3. UPDATE DB
    // We move to 'processing' and save the Command ID
    $newStatus = 'processing';
    $cmdId = null;
    $logMsg = $response;

    if ($err || $httpCode >= 400) {
        $newStatus = 'failed'; // Immediate fail on connection error
        $logMsg = "Push Error: " . ($err ?: $httpCode);
    } else {
        $json = json_decode($response, true);
        $cmdId = $json['command_id'] ?? $json['job_id'] ?? null;
    }

    $pdo->prepare("UPDATE biometric_jobs SET status=?, device_response=?, device_command_id=?, updated_at=NOW() WHERE job_id=?")
        ->execute([$newStatus, $logMsg, $cmdId, $job['job_id']]);

    $processed++;
}

echo json_encode(["status" => "progress", "processed" => $processed, "remaining" => $remaining - $processed]);
