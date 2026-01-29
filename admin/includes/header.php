<?php
// admin/includes/header.php

// 1. Session & Security
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'admin_check.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}

// 2. Database
require_once __DIR__ . '/../../config/Database.php';
$pdo = Database::getInstance()->getConnection();


$adminName = $_SESSION['name'] ?? 'Administrator';
$pageName = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en" class="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Console | StudySpace</title>
    <link rel="icon" href="https://cdn-icons-png.flaticon.com/512/2232/2232688.png" type="image/png">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;700&display=swap" rel="stylesheet">

    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                        mono: ['JetBrains Mono', 'monospace']
                    },
                    colors: {
                        bg: '#09090b',
                        surface: '#18181b',
                        border: '#27272a',
                        accent: '#d4b106'
                    }
                }
            }
        }
    </script>
    <style>
        body {
            background-color: #09090b;
            color: #e4e4e7;
        }

        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: #09090b;
        }

        ::-webkit-scrollbar-thumb {
            background: #27272a;
            border-radius: 4px;
        }

        .glass-panel {
            background: rgba(24, 24, 27, 0.4);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.05);
        }
    </style>
</head>

<body class="min-h-screen flex flex-col antialiased selection:bg-accent selection:text-black">

    <div id="page-loader" class="fixed inset-0 bg-black z-[100] flex items-center justify-center transition-opacity duration-500">
        <div class="animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-accent"></div>
    </div>
    <script>
        window.addEventListener('load', function() {
            const loader = document.getElementById('page-loader');
            loader.classList.add('opacity-0');
            setTimeout(() => {
                loader.style.display = 'none';
            }, 500);
        });

        // Auto-Handle URL Messages
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('msg')) {
                Swal.fire({
                    icon: 'success',
                    title: 'Success',
                    text: urlParams.get('msg'),
                    background: '#18181b',
                    color: '#fff',
                    confirmButtonColor: '#d4b106',
                    timer: 3000,
                    timerProgressBar: true
                });
                window.history.replaceState(null, '', window.location.pathname);
            }
            if (urlParams.has('error')) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: urlParams.get('error'),
                    background: '#18181b',
                    color: '#fff',
                    confirmButtonColor: '#ef4444'
                });
            }
        });
    </script>

    <nav class="border-b border-border sticky top-0 z-50 bg-[#09090b]/90 backdrop-blur-xl">
        <div class="max-w-7xl mx-auto px-6 h-16 flex items-center justify-between">

            <a href="index.php" class="flex items-center gap-3 group">
                <div class="w-8 h-8 bg-accent text-black flex items-center justify-center font-bold rounded-lg group-hover:scale-110 transition">A</div>
                <span class="font-mono text-sm tracking-widest text-zinc-300 group-hover:text-white transition-colors">ADMIN CONSOLE</span>
            </a>

            <div class="hidden md:flex items-center gap-6 text-sm font-medium">
                <a href="index.php" class="<?= $pageName == 'index.php' ? 'text-white' : 'text-zinc-400 hover:text-white' ?> transition">Dashboard</a>
                <a href="users.php" class="<?= $pageName == 'users.php' ? 'text-white' : 'text-zinc-400 hover:text-white' ?> transition">Students</a>
                <a href="attendance.php" class="<?= $pageName == 'attendance.php' ? 'text-white' : 'text-zinc-400 hover:text-white' ?> transition">Attendance</a>
                <a href="expenses.php" class="<?= $pageName == 'expenses.php' ? 'text-white' : 'text-zinc-400 hover:text-white' ?> transition">Finance</a>
            </div>

            <div class="flex items-center gap-4">
                <a href="../dashboard.php" target="_blank" class="hidden sm:flex items-center gap-2 text-xs font-bold bg-zinc-800 hover:bg-zinc-700 text-white px-3 py-1.5 rounded-lg border border-zinc-700 transition">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                    </svg>
                    Student View
                </a>

                <div class="h-8 w-px bg-zinc-800"></div>

                <div class="flex items-center gap-3">
                    <span class="text-xs text-zinc-400 hidden sm:block"><?= htmlspecialchars($adminName) ?></span>
                    <a href="../logout.php" class="text-zinc-500 hover:text-red-500 transition" title="Logout">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                        </svg>
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <main class="flex-grow p-6 lg:p-10 max-w-7xl mx-auto w-full">