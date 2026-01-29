<?php
session_start();
require_once 'core/SeatManager.php';

// 1. Security Gates
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: dashboard.php");
    exit;
}

// 2. Collect Data
$userId   = $_SESSION['user_id'];
$seatId   = $_POST['seat_id'];
$shiftId  = $_POST['shift_id'];
$date     = $_POST['date'];
$duration = $_POST['duration'] ?? 1; // Default to 1 month

// 3. Validate Inputs
if (!$seatId || !$shiftId || !$date) {
    $_SESSION['flash_error'] = "Invalid booking data provided.";
    header("Location: dashboard.php");
    exit;
}

// 4. Process Booking
$manager = new SeatManager();
$result = $manager->createBooking(
    $userId,
    $seatId,
    $shiftId,
    $date,
    $duration,
    'online',
    'Online Request - Waiting for Approval',
    null,
    0 // Paid Amount = 0
);

// 5. Handle Result
if ($result['success']) {
    header("Location: dashboard.php?msg=request_sent");
} else {
    header("Location: dashboard.php?error=" . urlencode($result['message']));
}
exit;
