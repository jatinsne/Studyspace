<?php
require_once 'admin_check.php';
require_once '../config/Database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['req_id'];

    $pdo = Database::getInstance()->getConnection();

    // Mark as rejected (This automatically unblocks the seat in SeatManager)
    $stmt = $pdo->prepare("UPDATE subscriptions SET payment_status = 'rejected', notes = CONCAT(notes, ' [Rejected by Admin]') WHERE id = ?");
    $stmt->execute([$id]);

    header("Location: approvals.php?msg=rejected");
    exit;
}
