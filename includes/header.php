<?php
// 1. START SESSION
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. AUTHENTICATION CHECK (Security Gate)
// If user is not logged in, kick them back to login page
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// 3. DATABASE CONNECTION
// We use __DIR__ to find the config folder regardless of where this header is included from
require_once __DIR__ . '/../config/Database.php';

try {
    $pdo = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database Connection Failed: " . $e->getMessage());
}

// 4. FETCH USER DATA (For Avatar & Name)
// We fetch fresh data here so the header always shows the latest photo/name
$h_userId = $_SESSION['user_id'];
$h_stmt = $pdo->prepare("SELECT name, role, profile_image FROM users WHERE id = ?");
$h_stmt->execute([$h_userId]);
$h_currentUser = $h_stmt->fetch();

if (!$h_currentUser) {
    // If session ID exists but user is deleted from DB, kill session
    session_destroy();
    header("Location: index.php");
    exit;
}

// 5. VARIABLES FOR HTML
$displayName = htmlspecialchars($h_currentUser['name']);
$userRole = $h_currentUser['role']; // 'student' or 'admin'
$userInitial = strtoupper(substr($displayName, 0, 1));

// Avatar Logic (Check if custom image exists in uploads folder)
$avatarPath = !empty($h_currentUser['profile_image']) ? 'uploads/' . $h_currentUser['profile_image'] : '';
$hasAvatar = !empty($avatarPath) && file_exists(__DIR__ . '/../' . $avatarPath);

// 6. ACTIVE PAGE HELPER
$currentPage = basename($_SERVER['PHP_SELF']);
function isActive($pageName, $currentPage)
{
    return $currentPage === $pageName
        ? 'text-white bg-white/10 border-white/20'
        : 'text-zinc-400 hover:text-white hover:bg-white/5 border-transparent';
}
?>

<!DOCTYPE html>
<html lang="en" class="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StudySpace OS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="icon" href="https://cdn-icons-png.flaticon.com/512/2232/2232688.png" type="image/png">

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
                        accent: '#FFE5B4',
                        danger: '#ef4444'
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
    </script>

    <nav class="border-b border-border sticky top-0 z-50 bg-[#09090b]/80 backdrop-blur-xl">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 h-16 flex items-center justify-between">

            <a href="dashboard.php" class="flex items-center gap-3 group focus:outline-none">
                <div class="relative w-8 h-8 flex items-center justify-center">
                    <div class="absolute inset-0 bg-white rounded-lg transform group-hover:rotate-6 transition-transform duration-300"></div>
                    <span class="relative font-bold text-black text-sm">S</span>
                </div>
                <span class="font-mono text-sm font-bold tracking-widest text-zinc-300 group-hover:text-white transition-colors">
                    STUDY<span class="text-accent">SPACE</span>
                </span>
            </a>

            <div class="hidden md:flex items-center gap-2">
                <?php if ($userRole === 'student'): ?>
                    <a href="dashboard.php" class="px-3 py-1.5 rounded-md text-sm font-medium border transition-all <?= isActive('dashboard.php', $currentPage) ?>">Dashboard</a>
                    <a href="my_bookings.php" class="px-3 py-1.5 rounded-md text-sm font-medium border transition-all <?= isActive('my_bookings.php', $currentPage) ?>">My Bookings</a>
                <?php endif; ?>

                <?php if ($userRole === 'admin'): ?>
                    <a href="admin/index.php" class="px-3 py-1.5 rounded-md text-sm font-bold text-black bg-accent hover:bg-yellow-200 transition-colors shadow-[0_0_15px_rgba(255,229,180,0.3)]">
                        Admin Console
                    </a>
                <?php endif; ?>
            </div>

            <div class="flex items-center gap-4">

                <div class="relative ml-2" id="user-menu-container">
                    <button onclick="toggleUserMenu()" class="flex items-center gap-3 focus:outline-none group">
                        <div class="text-right hidden sm:block">
                            <p class="text-xs text-zinc-400 group-hover:text-zinc-300 transition-colors">Signed in as</p>
                            <p class="text-sm text-white font-medium leading-none"><?= $displayName ?></p>
                        </div>

                        <div class="h-9 w-9 rounded-full bg-zinc-800 border border-border flex items-center justify-center text-white overflow-hidden ring-2 ring-transparent group-hover:ring-accent/50 transition-all">
                            <?php if ($hasAvatar): ?>
                                <img src="<?= $avatarPath ?>" alt="Profile" class="h-full w-full object-cover">
                            <?php else: ?>
                                <span class="font-bold text-xs font-mono"><?= $userInitial ?></span>
                            <?php endif; ?>
                        </div>
                    </button>

                    <div id="user-menu" class="hidden absolute right-0 mt-2 w-48 bg-surface border border-border rounded-xl shadow-2xl py-1 transform transition-all origin-top-right z-50">
                        <div class="px-4 py-3 border-b border-border sm:hidden">
                            <p class="text-sm text-white font-medium"><?= $displayName ?></p>
                            <p class="text-xs text-zinc-500 capitalize"><?= $userRole ?></p>
                        </div>
                        <a href="profile.php" class="block px-4 py-2 text-sm text-zinc-400 hover:text-white hover:bg-white/5">Profile Settings</a>
                        <a href="report_issue.php" class="block px-4 py-2 text-sm text-zinc-400 hover:text-white hover:bg-white/5">Help & Support</a>
                        <div class="border-t border-border my-1"></div>
                        <a href="logout.php" class="block px-4 py-2 text-sm text-red-400 hover:text-red-300 hover:bg-red-500/10 transition-colors">
                            Sign Out
                        </a>
                    </div>
                </div>

                <button onclick="toggleMobileMenu()" class="md:hidden text-zinc-400 hover:text-white p-2">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                    </svg>
                </button>
            </div>
        </div>

        <div id="mobile-menu" class="hidden md:hidden border-t border-border bg-surface">
            <div class="px-4 pt-2 pb-4 space-y-1">
                <?php if ($userRole === 'student'): ?>
                    <a href="dashboard.php" class="block px-3 py-2 rounded-md text-base font-medium text-white bg-white/5">Dashboard</a>
                    <a href="my_bookings.php" class="block px-3 py-2 rounded-md text-base font-medium text-zinc-400 hover:text-white hover:bg-white/5">My Bookings</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <main class="flex-grow">
        <script>
            function toggleUserMenu() {
                document.getElementById('user-menu').classList.toggle('hidden');
            }

            function toggleMobileMenu() {
                document.getElementById('mobile-menu').classList.toggle('hidden');
            }

            // Close dropdown when clicking outside
            window.addEventListener('click', function(e) {
                const container = document.getElementById('user-menu-container');
                const menu = document.getElementById('user-menu');
                if (!container.contains(e.target) && !menu.classList.contains('hidden')) {
                    menu.classList.add('hidden');
                }
            });
        </script>

        <script>
            const urlParams = new URLSearchParams(window.location.search);
            const msg = urlParams.get('msg');
            const error = urlParams.get('error');

            if (msg) {
                Swal.fire({
                    icon: 'success',
                    title: 'Success',
                    text: msg.replace(/_/g, ' '), // Replace underscores with spaces
                    background: '#18181b',
                    color: '#fff',
                    confirmButtonColor: '#d4b106',
                    timer: 3000,
                    timerProgressBar: true
                });
                window.history.replaceState(null, '', window.location.pathname);
            }

            if (error) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: error.replace(/_/g, ' '),
                    background: '#18181b',
                    color: '#fff',
                    confirmButtonColor: '#ef4444'
                });
                window.history.replaceState(null, '', window.location.pathname);
            }
        </script>