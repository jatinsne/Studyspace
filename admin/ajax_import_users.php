<?php
require_once '../config/Database.php';
session_start();

header('Content-Type: application/json');

// Security Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "Unauthorized Access"]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['users'])) {
    echo json_encode(["status" => "error", "message" => "No users selected for import."]);
    exit;
}

$pdo = Database::getInstance()->getConnection();
$importedCount = 0;

$defaultPassword = password_hash('123456', PASSWORD_DEFAULT);

try {
    $pdo->beginTransaction();

    // Added email to the insert query just in case it's a required field in your DB
    $sql = "INSERT INTO users (name, phone, email, biometric_id, card_id, role, verification_status, password_hash, biometric_enable) 
            VALUES (?, ?, ?, ?, ?, 'student', 'verified', ?, 1)";
    $stmt = $pdo->prepare($sql);

    foreach ($_POST['users'] as $rawJson) {
        $du = json_decode($rawJson, true);
        if (!$du) continue;

        $id = $du['user_id'] ?? $du['UserID'] ?? $du['id'] ?? null;
        $name = $du['name'] ?? $du['Name'] ?? 'Imported User';
        $card = $du['card'] ?? $du['Card'] ?? '';

        if (!$id) continue;

        // Generate UNIQUE dummy data to prevent SQL constraint errors
        // Example: Phone becomes 0000000045, Email becomes imported_45@system.local
        $dummyPhone = substr("0000000000" . $id, -10);
        $dummyEmail = "imported_" . $id . "@system.local";

        try {
            $stmt->execute([
                $name,
                $dummyPhone,
                $dummyEmail,
                (string)$id,
                $card,
                $defaultPassword
            ]);
            $importedCount++;
        } catch (PDOException $e) {
            // If it's a duplicate entry (1062), we want to know WHICH column is duplicating
            if ($e->errorInfo[1] == 1062) {
                // You can comment this out once you confirm it works, but for now it helps debug
                throw new Exception("Duplicate Entry on user $name (ID: $id). Detail: " . $e->getMessage());
            } else {
                throw $e;
            }
        }
    }

    $pdo->commit();

    echo json_encode([
        "status" => "success",
        "imported" => $importedCount
    ]);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
