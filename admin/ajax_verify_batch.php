<?php
// FILE: admin/ajax_verify_batch.php
require_once '../config/Database.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    exit(json_encode(["status" => "error", "message" => "Unauthorized"]));
}

$pdo = Database::getInstance()->getConnection();
$baseUrl = getenv('BIOMETRIC_URL');

// 1. FETCH JOBS THAT NEED VERIFICATION
// Status must be 'processing' and we must have a Command ID to check
$sql = "SELECT * FROM biometric_jobs 
        WHERE status = 'processing' 
        AND device_command_id IS NOT NULL 
        ORDER BY updated_at ASC 
        LIMIT 5";

$jobs = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

// Check counts for progress bar
$countSql = "SELECT COUNT(*) FROM biometric_jobs WHERE status = 'processing' AND device_command_id IS NOT NULL";
$remaining = $pdo->query($countSql)->fetchColumn();

if (empty($jobs)) {
    exit(json_encode(["status" => "done", "processed" => 0, "remaining" => 0]));
}

$processed = 0;
$completed = 0;

foreach ($jobs as $job) {
    $cmdId = $job['device_command_id'];
    $checkUrl = rtrim($baseUrl, '/') . '/' . $cmdId; // e.g. .../api/command/698...

    // 2. CURL GET REQUEST (Check Status)
    $ch = curl_init();
    curl_setopt_array($ch, array(
        CURLOPT_URL => $checkUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 4,
        CURLOPT_CUSTOMREQUEST => 'GET',
    ));

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    //curl_close($ch);

    // 3. ANALYZE RESPONSE
    $newStatus = $job['status']; // Keep existing if no change
    $logMsg = $job['device_response'];

    if ($httpCode >= 200 && $httpCode < 300 && $response) {
        $json = json_decode($response, true);

        // Logic: Check if device is done
        if (isset($json['status']) && $json['status'] === 'RESULT') {
            // It's finished. Was it successful?
            if (isset($json['return_code']) && $json['return_code'] === 'OK') {
                $newStatus = 'completed';
            } else {
                $newStatus = 'failed';
            }

            // Save the detailed result
            $logMsg = json_encode($json['result'] ?? $json);
            $completed++;
        }
        // If status is still 'PENDING' or 'RECEIVED', we do nothing and check again later
    }

    // 4. UPDATE DB
    // Only update if status changed to completed/failed
    if ($newStatus !== 'processing') {
        $upd = $pdo->prepare("UPDATE biometric_jobs SET status = ?, device_response = ?, updated_at = NOW() WHERE job_id = ?");
        $upd->execute([$newStatus, $logMsg, $job['job_id']]);
    }

    $processed++;
}

echo json_encode([
    "status" => "progress",
    "processed" => $processed,
    "completed" => $completed,
    "remaining" => $remaining - $processed
]);
