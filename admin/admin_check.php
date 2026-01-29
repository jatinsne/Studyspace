<?php
// admin/admin_check.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Check Login
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit;
}

// 2. Check Role (Strict)
if ($_SESSION['role'] !== 'admin') {
    // Log them out or send to user dashboard
    header("Location: ../dashboard.php");
    exit;
}
