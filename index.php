<?php
session_start();

// 1. Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'admin') {
        header("Location: admin/index.php");
    } else {
        header("Location: dashboard.php");
    }
    exit;
}

require_once 'config/Database.php';

$pdo = Database::getInstance()->getConnection();
$error = "";

// 1. HANDLE LOGIN
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone = trim($_POST['phone']);
    $password = trim($_POST['password']);

    if (empty($phone) || empty($password)) {
        $error = "Please enter both phone and password.";
    } else {
        // Fetch User
        $stmt = $pdo->prepare("SELECT * FROM users WHERE phone = ?");
        $stmt->execute([$phone]);
        $user = $stmt->fetch();

        // Verify Password (In production, use password_verify())
        // For now, assuming plain text or simple comparison as per previous context
        if ($user && password_verify($password, $user['password_hash'])) {

            // Set Session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['name'] = $user['name'];

            // Redirect based on Role
            if ($user['role'] === 'admin') {
                header("Location: admin/index.php");
            } else {
                header("Location: dashboard.php");
            }
            exit;
        } else {
            $error = "Invalid phone number or password.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | StudySpace</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap');

        body {
            font-family: 'Inter', sans-serif;
            background-color: #000;
            color: white;
        }

        .glass {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
    </style>
</head>

<body class="min-h-screen flex items-center justify-center p-4 relative overflow-hidden">

    <div class="absolute top-0 left-0 w-96 h-96 bg-emerald-500/10 rounded-full blur-3xl -translate-x-1/2 -translate-y-1/2"></div>
    <div class="absolute bottom-0 right-0 w-96 h-96 bg-accent/10 rounded-full blur-3xl translate-x-1/2 translate-y-1/2"></div>

    <div class="w-full max-w-md relative z-10">

        <div class="text-center mb-8">
            <div class="w-16 h-16 bg-white rounded-xl mx-auto flex items-center justify-center mb-4 shadow-[0_0_30px_rgba(255,255,255,0.2)]">
                <svg class="w-8 h-8 text-black" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                </svg>
            </div>
            <h1 class="text-3xl font-bold tracking-tight">StudySpace</h1>
            <p class="text-zinc-500 mt-2">Library Management System</p>
        </div>

        <div class="glass p-8 rounded-2xl shadow-2xl">

            <?php if ($error): ?>
                <div class="bg-red-500/10 border border-red-500/20 text-red-500 text-sm p-3 rounded-lg mb-6 text-center">
                    <?= $error ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-5">

                <div>
                    <label class="block text-xs uppercase text-zinc-500 font-bold mb-2">Phone Number</label>
                    <div class="relative">
                        <span class="absolute left-4 top-3.5 text-zinc-500">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                            </svg>
                        </span>
                        <input type="tel" name="phone" required placeholder="9876543210"
                            class="w-full bg-black/50 border border-zinc-700 rounded-xl py-3 pl-12 pr-4 text-white placeholder-zinc-600 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 outline-none transition">
                    </div>
                </div>

                <div>
                    <label class="block text-xs uppercase text-zinc-500 font-bold mb-2">Password</label>
                    <div class="relative">
                        <span class="absolute left-4 top-3.5 text-zinc-500">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                            </svg>
                        </span>
                        <input type="password" name="password" required placeholder="••••••••"
                            class="w-full bg-black/50 border border-zinc-700 rounded-xl py-3 pl-12 pr-4 text-white placeholder-zinc-600 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 outline-none transition">
                    </div>
                    <div class="flex justify-end my-4">
                        <button type="button" onclick="showForgotHelp()" class="text-xs text-zinc-400 hover:text-white hover:underline">
                            Forgot Password?
                        </button>
                    </div>
                </div>

                <button type="submit" class="w-full bg-white text-black font-bold py-3.5 rounded-xl hover:bg-zinc-200 transition transform active:scale-[0.98]">
                    Sign In
                </button>


            </form>

            <div class="mt-8 text-center">
                <p class="text-zinc-600 text-xs">
                    Staff? <a href="admin/attendance.php" class="text-zinc-400 hover:text-white underline">Open Kiosk Mode</a>
                </p>
            </div>
        </div>

        <p class="text-center text-zinc-700 text-xs mt-8">
            &copy; <?= date('Y') ?> StudySpace Library. All rights reserved.
        </p>
    </div>

    <script>
        function showForgotHelp() {
            Swal.fire({
                icon: 'info',
                title: 'Reset Password',
                text: 'Please visit the Library Desk or contact the Admin at +91-98765-43210 to reset your password.',
                background: '#18181b',
                color: '#fff',
                confirmButtonColor: '#d4b106'
            });
        }
    </script>

</body>

</html>