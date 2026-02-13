<?php
// FILE: admin/ajax_process_queue.php
require_once '../config/Database.php';
session_start();

// 1. SECURITY: Only allow Admins or valid sessions
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit("Unauthorized");
}

// 2. CONFIG CHECK
$pdo = Database::getInstance()->getConnection();
$apiUrl = getenv('BIOMETRIC_URL');

if (empty($apiUrl)) exit("No Device URL set");

// 3. FETCH PENDING JOBS (Batch of 5 to keep it fast)
$sql = "SELECT * FROM biometric_jobs WHERE status = 'pending' ORDER BY created_at ASC LIMIT 5";
$jobs = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

if (empty($jobs)) exit("Queue empty");

// 4. PROCESS JOBS
foreach ($jobs as $job) {
    // A. Mark as Processing
    $pdo->prepare("UPDATE biometric_jobs SET status = 'processing', updated_at = NOW() WHERE job_id = ?")->execute([$job['job_id']]);

    // B. Send to Device
    $ch = curl_init();
    curl_setopt_array($ch, array(
        CURLOPT_URL => $apiUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 4, // Fast timeout for AJAX
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => $job['payload'],
        CURLOPT_HTTPHEADER => array('Content-Type: application/json'),
    ));

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    // curl_close($ch); // PHP 8+ auto-closes

    // C. Determine Status
    $finalStatus = 'completed';
    $logMsg = $response;
    $cmdId = null;

    if ($err || $httpCode >= 400) {
        $finalStatus = 'failed';
        $logMsg = $err ? "Conn Error: $err" : "HTTP $httpCode: $response";
    } else {
        $json = json_decode($response, true);
        // Extract Command ID if available
        $cmdId = $json['command_id'] ?? $json['job_id'] ?? $json['trans_id'] ?? null;
    }

    // D. Update Job
    $updateSql = "UPDATE biometric_jobs SET 
                  status = ?, 
                  device_response = ?, 
                  device_command_id = ?, 
                  updated_at = NOW() 
                  WHERE job_id = ?";
    $pdo->prepare($updateSql)->execute([$finalStatus, $logMsg, $cmdId, $job['job_id']]);
}

echo "Processed " . count($jobs) . " jobs.";
