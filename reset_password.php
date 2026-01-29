<?php
require_once 'config/Database.php';

try {
    $pdo = Database::getInstance()->getConnection();

    // 1. The password you want to use
    $newPassword = '123456';

    // 2. Hash it securely using PHP's native function
    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);

    // 3. Update the database
    $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE email = ?");
    $stmt->execute([$newHash, 'admin@library.com']);

    echo "<h1>Success!</h1>";
    echo "<p>Password for <b>admin@library.com</b> has been reset to: <b>$newPassword</b></p>";
    echo "<p><a href='index.php'>Go to Login</a></p>";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
