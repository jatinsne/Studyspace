<?php
// 1. SET HEADERS
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

require_once '../config/Database.php';

// 2. GET RAW INPUT
$json = file_get_contents("php://input");
$data = json_decode($json, true);

// Debug Log (Optional)
file_put_contents('biometric.log', date('Y-m-d H:i:s') . " " . $json . "\n", FILE_APPEND);

// 3. VALIDATE INPUT
if (empty($data['user_id']) || empty($data['timestamp'])) {
    http_response_code(400);
    file_put_contents('biometric.log', date('Y-m-d H:i:s') . " - Incomplete Data - " . $json . "\n", FILE_APPEND);
    echo json_encode(["result" => "fail", "message" => "Incomplete Data"]);
    exit;
}

try {
    $pdo = Database::getInstance()->getConnection();

    // 4. IDENTIFY USER
    $stmt = $pdo->prepare("
        SELECT id, name, biometric_enable, subscription_validation_check, subscription_startdate, subscription_enddate 
        FROM users 
        WHERE biometric_id = ?
    ");
    $stmt->execute([$data['user_id']]);
    $user = $stmt->fetch();

    if (!$user) {
        http_response_code(404);
        echo json_encode(["result" => "fail", "message" => "User Not Found"]);
        exit;
    }

    // 5. CHECK ACCESS PERMISSIONS
    $today = date('Y-m-d', strtotime($data['timestamp']));

    // Check 1: Is Biometric Enabled?
    // if ($user['biometric_enable'] == 0) {
    //     http_response_code(403);
    //     echo json_encode(["result" => "deny", "message" => "Access Disabled"]);
    //     exit;
    // }

    // Check 2: Is Subscription Valid?
    $isSubValid = $user['subscription_validation_check'] == 1
        && $today >= $user['subscription_startdate']
        && $today <= $user['subscription_enddate'];

    if (!$isSubValid) {
        http_response_code(403);
        echo json_encode(["result" => "deny", "message" => "Subscription Expired"]);
        exit;
    }

    // 6. RECORD ATTENDANCE (First-In, Last-Out Logic)
    $logTime = date('H:i:s', strtotime($data['timestamp']));

    // A. Check if record exists for TODAY
    $attStmt = $pdo->prepare("SELECT id, check_in_time FROM attendance WHERE user_id = ? AND date = ?");
    $attStmt->execute([$user['id'], $today]);
    $attendance = $attStmt->fetch();

    if (!$attendance) {
        // --- SCENARIO A: FIRST PUNCH (CHECK IN) ---
        $sql = "INSERT INTO attendance (user_id, date, check_in_time, status, device_log) VALUES (?, ?, ?, 'present', ?)";
        $pdo->prepare($sql)->execute([$user['id'], $today, $logTime, json_encode($data)]);

        $msg = "Check-in Recorded";
        $type = "IN";
    } else {
        // --- SCENARIO B: SUBSEQUENT PUNCH (UPDATE CHECK OUT) ---
        // Record exists. This is an exit (or re-entry updates exit time).
        // We update the 'check_out_time' to the current punch time.

        // Safety: Don't update if punch is within 1 minute of Check-In (Double Scan Prevention)
        $checkInTimestamp = strtotime($attendance['check_in_time']);
        $currentTimestamp = strtotime($logTime);

        if (($currentTimestamp - $checkInTimestamp) > 60) {
            $sql = "UPDATE attendance SET check_out_time = ?, device_log = CONCAT(IFNULL(device_log, ''), ?) WHERE id = ?";
            $pdo->prepare($sql)->execute([$logTime, json_encode($data), $attendance['id']]);
            $msg = "Check-out Updated";
            $type = "OUT";
            file_put_contents('biometric.log', date('Y-m-d H:i:s') . " Check-out Updated " . $json . "\n", FILE_APPEND);
        } else {
            $msg = "Duplicate Scan Ignored";
            file_put_contents('biometric.log', date('Y-m-d H:i:s') . " Duplicate-Scan " . $json . "\n", FILE_APPEND);
            $type = "NONE";
        }
    }

    // 7. SUCCESS RESPONSE
    http_response_code(201);
    echo json_encode([
        "result" => "success",
        "user" => $user['name'],
        "type" => $type,
        "message" => $msg
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    file_put_contents('biometric.log', date('Y-m-d H:i:s') . " " . $e->getMessage() . "\n", FILE_APPEND);
    echo json_encode(["result" => "error", "message" => $e->getMessage()]);
}
