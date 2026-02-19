<?php
// 1. Security & Logic
require_once 'includes/header.php';

// Fetch Device ID from ENV
$deviceId = getenv('BIOMETRIC_DEVICE_ID') ?: 'Unknown Device';

// 2. Fetch Key Metrics

// A. Lifetime Revenue
$revStmt = $pdo->query("SELECT SUM(paid_amount) as total FROM subscriptions WHERE payment_status != 'rejected'");
$totalRevenue = $revStmt->fetch()['total'] ?? 0;

// B. Lifetime Expenses
$expStmt = $pdo->query("SELECT SUM(amount) as total FROM expenses");
$totalExpenses = $expStmt->fetch()['total'] ?? 0;

// C. Calculate Lifetime Net Profit
$lifetimeProfit = $totalRevenue - $totalExpenses;

// D. Active Members
$activeStmt = $pdo->query("SELECT COUNT(DISTINCT user_id) as total FROM subscriptions WHERE end_date >= CURRENT_DATE");
$activeMembers = $activeStmt->fetch()['total'];

// E. Seat Utilization (Today)
$today = date('Y-m-d');
$occStmt = $pdo->prepare("SELECT COUNT(*) as total FROM subscriptions WHERE ? BETWEEN start_date AND end_date");
$occStmt->execute([$today]);
$occupiedToday = $occStmt->fetch()['total'];

// F. Check for Dues (Alert Banner)
$dueStmt = $pdo->query("SELECT SUM(due_amount) as total FROM subscriptions WHERE due_amount > 0");
$totalDue = $dueStmt->fetch()['total'] ?? 0;

// G. Check Expiring (Next 5 Days)
$expStmt = $pdo->query("SELECT COUNT(*) as total FROM subscriptions WHERE payment_status = 'paid' AND end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 5 DAY)");
$expCount = $expStmt->fetch()['total'];

// H. Pending Requests Count
$reqStmt = $pdo->query("SELECT COUNT(*) as total FROM subscriptions WHERE payment_status = 'pending'");
$reqCount = $reqStmt->fetch()['total'];

$displayName = $_SESSION['name'] ?? 'Administrator';
?>

<div class="max-w-7xl mx-auto px-6 py-8 w-full">

    <div class="flex flex-col md:flex-row justify-between items-start md:items-end mb-8 gap-4">
        <div>
            <h1 class="text-3xl font-bold tracking-tight text-white">Command Center</h1>
            <p class="text-zinc-500 mt-1">Welcome back, <?= htmlspecialchars($displayName) ?>.</p>
        </div>
        <div class="text-right">
            <p class="text-xs text-zinc-500 font-mono"><?= date('l, d M Y') ?></p>
        </div>
    </div>

    <?php if ($totalDue > 0): ?>
        <div class="mb-8 w-full animate-pulse">
            <a href="dues.php" class="block bg-red-900/10 border border-red-500/20 p-4 rounded-xl flex justify-between items-center hover:bg-red-900/20 transition cursor-pointer group">
                <div class="flex items-center gap-4">
                    <div class="bg-red-500/10 p-2 rounded-lg text-red-500 border border-red-500/20">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-red-400 font-bold text-sm uppercase tracking-wider">Action Required</h3>
                        <p class="text-zinc-300 text-sm">Total Pending Collections: <span class="font-mono font-bold text-white text-lg ml-1">₹<?= number_format($totalDue) ?></span></p>
                    </div>
                </div>
                <div class="flex items-center gap-2 text-red-400 text-xs font-bold uppercase tracking-wider group-hover:translate-x-1 transition-transform">
                    Collect Now <span>→</span>
                </div>
            </a>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">

        <div class="col-span-1 bg-zinc-900/50 border border-zinc-800 p-6 rounded-2xl flex flex-col justify-between relative overflow-hidden group h-full min-h-[300px]">

            <div id="statusGlow" class="absolute top-0 right-0 w-40 h-40 bg-zinc-800 rounded-full blur-[80px] opacity-20 -mr-10 -mt-10 transition-colors duration-500"></div>

            <div class="relative z-10 flex justify-between items-start mb-6">
                <div>
                    <div class="flex items-center gap-2 mb-2">
                        <span class="p-1.5 bg-zinc-800 rounded-lg border border-zinc-700 text-zinc-400 shadow-inner">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                            </svg>
                        </span>
                        <h3 class="font-bold text-white text-sm uppercase tracking-wider">Main Entrance</h3>
                    </div>
                    <p id="lastSeenText" class="text-[10px] text-zinc-500 font-mono ml-1">Checking connection...</p>
                </div>

                <div id="statusBadge" class="flex items-center gap-2 px-3 py-1.5 rounded-full border bg-zinc-900 border-zinc-700 transition-colors duration-300 shadow-lg">
                    <span class="relative flex h-2 w-2">
                        <span id="statusPing" class="hidden absolute inline-flex h-full w-full rounded-full opacity-75 animate-ping"></span>
                        <span id="statusDot" class="relative inline-flex rounded-full h-2 w-2 bg-zinc-500"></span>
                    </span>
                    <span id="statusLabel" class="text-[10px] font-bold text-zinc-500 tracking-widest">...</span>
                </div>
            </div>
            <a href="import_users.php" class="group bg-zinc-900/80 hover:bg-zinc-800 border border-zinc-800 hover:border-accent/50 text-zinc-400 hover:text-white px-5 py-2.5 rounded-xl text-sm font-bold transition-all duration-300 flex items-center gap-2 shadow-lg backdrop-blur-sm">
                <span class="bg-zinc-800 p-1 rounded-md text-accent group-hover:bg-accent group-hover:text-black transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                    </svg>
                </span>
                Import Users from Device
            </a>

            <div class="relative z-10 space-y-4">
                <button id="btnUnlock" onclick="unlockDoor()" disabled
                    class="group w-full relative overflow-hidden rounded-xl bg-zinc-800 p-5 transition-all duration-300 disabled:opacity-50 disabled:cursor-not-allowed hover:bg-zinc-700 border border-zinc-700/50 shadow-lg">
                    <div class="relative flex items-center justify-center gap-3">
                        <div id="lockIconWrapper" class="transition-transform duration-300">
                            <svg class="w-6 h-6 text-zinc-500 group-hover:text-white transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                            </svg>
                        </div>
                        <span id="unlockBtnText" class="font-bold text-sm text-zinc-400 group-hover:text-white transition-colors uppercase tracking-wide">Connecting...</span>
                    </div>
                    <div id="unlockProgress" class="absolute bottom-0 left-0 h-1 bg-white/20 w-0 transition-all duration-[2000ms]"></div>
                </button>

                <div class="flex items-center justify-between px-2 pt-2 border-t border-white/5">
                    <div class="flex items-center gap-1.5 text-[10px] text-zinc-600 font-mono">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <span id="deviceSerial"><?= htmlspecialchars($deviceId) ?></span>
                    </div>
                    <button onclick="checkDeviceStatus()" class="text-[10px] text-zinc-500 hover:text-white flex items-center gap-1.5 transition uppercase font-bold tracking-wider">
                        <svg id="refreshSpin" class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                        </svg>
                        Refresh
                    </button>
                </div>
            </div>
        </div>

        <div class="col-span-1 lg:col-span-2 grid grid-cols-1 md:grid-cols-2 gap-6 h-full">

            <div class="bg-zinc-900/50 border border-zinc-800 p-6 rounded-2xl flex flex-col justify-center">
                <div class="flex justify-between items-start mb-2">
                    <p class="text-zinc-500 text-xs uppercase tracking-widest font-bold">Total Revenue</p>
                    <span class="bg-zinc-800 text-zinc-400 text-[10px] px-2 py-0.5 rounded">Gross</span>
                </div>
                <p class="text-3xl font-mono font-bold text-white mt-1">₹<?= number_format($totalRevenue) ?></p>
            </div>

            <div class="bg-zinc-900/50 border border-emerald-500/20 p-6 rounded-2xl relative overflow-hidden flex flex-col justify-center">
                <div class="absolute top-0 right-0 p-4 opacity-10 text-emerald-500">
                    <svg class="w-16 h-16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                    </svg>
                </div>
                <div class="relative z-10">
                    <p class="text-emerald-500 text-xs uppercase tracking-widest font-bold mb-2">Net Profit</p>
                    <p class="text-3xl font-mono font-bold text-emerald-400">
                        <?= $lifetimeProfit >= 0 ? '+' : '' ?>₹<?= number_format($lifetimeProfit) ?>
                    </p>
                    <p class="text-[10px] text-emerald-600/70 mt-1 font-mono">Exp: ₹<?= number_format($totalExpenses) ?></p>
                </div>
            </div>

            <div class="bg-zinc-900/50 border border-zinc-800 p-6 rounded-2xl flex flex-col justify-center">
                <p class="text-zinc-500 text-xs uppercase tracking-widest font-bold mb-2">Active Members</p>
                <div class="flex items-end gap-2">
                    <p class="text-3xl font-mono font-bold text-blue-400"><?= $activeMembers ?></p>
                    <span class="text-zinc-600 text-xs mb-1">Users</span>
                </div>
            </div>

            <div class="bg-zinc-900/50 border border-zinc-800 p-6 rounded-2xl flex flex-col justify-center">
                <p class="text-zinc-500 text-xs uppercase tracking-widest font-bold mb-2">Occupancy Today</p>
                <div class="flex items-end gap-2">
                    <p class="text-3xl font-mono font-bold text-accent"><?= $occupiedToday ?></p>
                    <span class="text-zinc-600 text-xs mb-1">Seats Booked</span>
                </div>
            </div>

        </div>
    </div>

    <h2 class="text-sm font-bold text-zinc-500 uppercase tracking-widest mb-4 pl-1">System Modules</h2>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-12">

        <a href="approvals.php" class="group bg-zinc-900/30 border border-zinc-800 p-5 rounded-xl hover:bg-zinc-800 transition hover:border-yellow-500/50">
            <div class="h-8 w-8 bg-yellow-900/20 text-yellow-500 rounded flex items-center justify-center mb-3 group-hover:bg-yellow-500 group-hover:text-black transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                </svg>
            </div>
            <h3 class="font-bold text-white text-sm group-hover:text-yellow-400 transition">Approvals</h3>
            <p class="text-[10px] text-zinc-500 mt-1">
                <?= $reqCount > 0 ? "<span class='text-yellow-500 font-bold'>$reqCount Waiting</span>" : "No requests" ?>
            </p>
        </a>

        <a href="book_manual.php" class="group bg-zinc-900/30 border border-zinc-800 p-5 rounded-xl hover:bg-zinc-800 transition hover:border-accent/50">
            <div class="h-8 w-8 bg-accent/10 rounded flex items-center justify-center mb-3 group-hover:bg-accent group-hover:text-black transition text-accent">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
                </svg>
            </div>
            <h3 class="font-bold text-white text-sm">Cash Desk</h3>
            <p class="text-[10px] text-zinc-500 mt-1">Manual Booking</p>
        </a>

        <a href="dues.php" class="group bg-zinc-900/30 border border-zinc-800 p-5 rounded-xl hover:bg-zinc-800 transition hover:border-red-500/50">
            <div class="h-8 w-8 bg-red-900/20 text-red-500 rounded flex items-center justify-center mb-3 group-hover:bg-red-500 group-hover:text-white transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
            <h3 class="font-bold text-white text-sm">Dues</h3>
            <p class="text-[10px] text-zinc-500 mt-1">Unpaid Balances</p>
        </a>

        <a href="expiring.php" class="group bg-zinc-900/30 border border-zinc-800 p-5 rounded-xl hover:bg-zinc-800 transition hover:border-orange-500/50">
            <div class="h-8 w-8 bg-orange-900/20 text-orange-500 rounded flex items-center justify-center mb-3 group-hover:bg-orange-500 group-hover:text-white transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                </svg>
            </div>
            <h3 class="font-bold text-white text-sm">Renewals</h3>
            <p class="text-[10px] text-zinc-500 mt-1">
                <?= $expCount > 0 ? "<span class='text-orange-400 font-bold'>$expCount Expiring</span>" : "All Good" ?>
            </p>
        </a>

        <a href="expenses.php" class="group bg-zinc-900/30 border border-zinc-800 p-5 rounded-xl hover:bg-zinc-800 transition hover:border-zinc-600">
            <div class="h-8 w-8 bg-zinc-800 rounded flex items-center justify-center mb-3 group-hover:bg-white group-hover:text-black transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                </svg>
            </div>
            <h3 class="font-bold text-white text-sm">Expenses</h3>
            <p class="text-[10px] text-zinc-500 mt-1">Operational Costs</p>
        </a>

        <a href="reports.php" class="group bg-zinc-900/30 border border-zinc-800 p-5 rounded-xl hover:bg-zinc-800 transition hover:border-zinc-600">
            <div class="h-8 w-8 bg-zinc-800 rounded flex items-center justify-center mb-3 group-hover:bg-white group-hover:text-black transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                </svg>
            </div>
            <h3 class="font-bold text-white text-sm">Reports</h3>
            <p class="text-[10px] text-zinc-500 mt-1">Financial Analysis</p>
        </a>

        <a href="users.php" class="group bg-zinc-900/30 border border-zinc-800 p-5 rounded-xl hover:bg-zinc-800 transition hover:border-zinc-600">
            <div class="h-8 w-8 bg-zinc-800 rounded flex items-center justify-center mb-3 group-hover:bg-white group-hover:text-black transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                </svg>
            </div>
            <h3 class="font-bold text-white text-sm">Students</h3>
            <p class="text-[10px] text-zinc-500 mt-1">Directory & Walk-ins</p>
        </a>

        <a href="manage_seats.php" class="group bg-zinc-900/30 border border-zinc-800 p-5 rounded-xl hover:bg-zinc-800 transition hover:border-zinc-600">
            <div class="h-8 w-8 bg-zinc-800 rounded flex items-center justify-center mb-3 group-hover:bg-white group-hover:text-black transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                </svg>
            </div>
            <h3 class="font-bold text-white text-sm">Seats</h3>
            <p class="text-[10px] text-zinc-500 mt-1">Asset Mgmt</p>
        </a>

        <a href="attendance.php" class="group bg-zinc-900/30 border border-zinc-800 p-5 rounded-xl hover:bg-zinc-800 transition hover:border-zinc-600">
            <div class="h-8 w-8 bg-zinc-800 rounded flex items-center justify-center mb-3 group-hover:bg-white group-hover:text-black transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                </svg>
            </div>
            <h3 class="font-bold text-white text-sm">Attendance</h3>
            <p class="text-[10px] text-zinc-500 mt-1">Daily Log</p>
        </a>

        <a href="coupons.php" class="group bg-zinc-900/30 border border-zinc-800 p-5 rounded-xl hover:bg-zinc-800 transition hover:border-emerald-500/50">
            <div class="h-8 w-8 bg-zinc-800 rounded flex items-center justify-center mb-3 group-hover:bg-emerald-500 group-hover:text-black transition text-zinc-400">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
                </svg>
            </div>
            <h3 class="font-bold text-white text-sm group-hover:text-emerald-400 transition">Coupons</h3>
            <p class="text-[10px] text-zinc-500 mt-1">Manage Discounts</p>
        </a>

        <a href="settings.php" class="group bg-zinc-900/30 border border-zinc-800 p-5 rounded-xl hover:bg-zinc-800 transition hover:border-zinc-600">
            <div class="h-8 w-8 bg-zinc-800 rounded flex items-center justify-center mb-3 group-hover:bg-white group-hover:text-black transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                </svg>
            </div>
            <h3 class="font-bold text-white text-sm">Settings</h3>
            <p class="text-[10px] text-zinc-500 mt-1">Config & Pricing</p>
        </a>

        <a href="issues.php" class="group bg-zinc-900/30 border border-zinc-800 p-5 rounded-xl hover:bg-zinc-800 transition hover:border-zinc-600">
            <div class="h-8 w-8 bg-zinc-800 rounded flex items-center justify-center mb-3 group-hover:bg-white group-hover:text-black transition text-zinc-400">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z"></path>
                </svg>
            </div>
            <h3 class="font-bold text-white text-sm">Helpdesk</h3>
            <p class="text-[10px] text-zinc-500 mt-1">Student Complaints</p>
        </a>

    </div>

    <div class="mt-8">
        <h2 class="text-sm font-bold text-zinc-500 uppercase tracking-widest mb-4 pl-1">Live Transactions</h2>
        <div class="bg-zinc-900/30 border border-zinc-800 rounded-xl overflow-hidden shadow-xl">
            <table class="w-full text-left text-xs">
                <thead class="bg-zinc-900/80 text-zinc-500 uppercase font-bold border-b border-zinc-800">
                    <tr>
                        <th class="px-6 py-4">User</th>
                        <th class="px-6 py-4">Amount</th>
                        <th class="px-6 py-4">Method</th>
                        <th class="px-6 py-4 text-right">Timestamp</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-800 text-zinc-300">
                    <?php
                    $recStmt = $pdo->query("SELECT u.name, s.final_amount, s.payment_method, s.created_at FROM subscriptions s JOIN users u ON s.user_id = u.id ORDER BY s.created_at DESC LIMIT 5");
                    while ($row = $recStmt->fetch()):
                    ?>
                        <tr class="hover:bg-zinc-800/50 transition">
                            <td class="px-6 py-4 font-bold text-white"><?= htmlspecialchars($row['name']) ?></td>
                            <td class="px-6 py-4 font-mono text-emerald-400">₹<?= number_format($row['final_amount']) ?></td>
                            <td class="px-6 py-4">
                                <span class="px-2 py-1 bg-zinc-800 rounded uppercase text-[10px] tracking-widest font-bold text-zinc-400"><?= htmlspecialchars($row['payment_method']) ?></span>
                            </td>
                            <td class="px-6 py-4 text-zinc-500 text-right font-mono"><?= date('H:i | M d', strtotime($row['created_at'])) ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<script>
    document.addEventListener("DOMContentLoaded", () => {
        checkDeviceStatus();
        setInterval(checkDeviceStatus, 15000); // Poll every 15s
    });

    function checkDeviceStatus() {
        const ui = {
            badge: document.getElementById('statusBadge'),
            dot: document.getElementById('statusDot'),
            ping: document.getElementById('statusPing'),
            label: document.getElementById('statusLabel'),
            glow: document.getElementById('statusGlow'),
            text: document.getElementById('lastSeenText'),
            btn: document.getElementById('btnUnlock'),
            btnText: document.getElementById('unlockBtnText'),
            spin: document.getElementById('refreshSpin'),
            icon: document.getElementById('lockIconWrapper')
        };

        // Loading State
        ui.spin.classList.add('animate-spin');
        ui.label.innerText = "PING...";

        fetch('ajax_device_status.php')
            .then(res => res.json())
            .then(data => {
                ui.spin.classList.remove('animate-spin');

                if (data.online) {
                    // ONLINE
                    ui.badge.className = "flex items-center gap-2 px-3 py-1.5 rounded-full border bg-emerald-900/20 border-emerald-900/50 transition-colors duration-300 shadow-lg shadow-emerald-900/20";
                    ui.dot.className = "relative inline-flex rounded-full h-2 w-2 bg-emerald-500";
                    ui.ping.className = "absolute inline-flex h-full w-full rounded-full bg-emerald-500 opacity-75 animate-ping";
                    ui.ping.classList.remove('hidden');
                    ui.label.className = "text-[10px] font-bold text-emerald-400 tracking-widest";
                    ui.label.innerText = "ONLINE";
                    ui.glow.className = "absolute top-0 right-0 w-40 h-40 bg-emerald-600 rounded-full blur-[80px] opacity-10 -mr-10 -mt-10 transition-colors duration-500";

                    ui.btn.disabled = false;
                    ui.btn.className = "group w-full relative overflow-hidden rounded-xl bg-white hover:bg-zinc-200 p-5 transition-all duration-300 shadow-lg shadow-white/5 cursor-pointer border-0";
                    ui.btnText.className = "font-bold text-sm text-black transition-colors uppercase tracking-wide";
                    ui.btnText.innerText = "Unlock Main Door";

                    ui.icon.innerHTML = `<svg class="w-6 h-6 text-black" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 11V7a4 4 0 118 0m-4 8v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z"></path></svg>`;
                    ui.text.innerText = `Heartbeat: ${data.seconds_ago}s ago`;
                    ui.text.className = "text-[10px] text-emerald-500/70 font-mono ml-1";

                } else {
                    // OFFLINE
                    setOfflineUI(ui, data.last_seen);
                }
            })
            .catch(err => {
                ui.spin.classList.remove('animate-spin');
                setOfflineUI(ui, "Connection Error");
            });
    }

    function setOfflineUI(ui, lastSeen) {
        ui.badge.className = "flex items-center gap-2 px-3 py-1.5 rounded-full border bg-red-900/20 border-red-900/50 transition-colors duration-300";
        ui.dot.className = "relative inline-flex rounded-full h-2 w-2 bg-red-500";
        ui.ping.classList.add('hidden');
        ui.label.className = "text-[10px] font-bold text-red-500 tracking-widest";
        ui.label.innerText = "OFFLINE";
        ui.glow.className = "absolute top-0 right-0 w-40 h-40 bg-red-600 rounded-full blur-[80px] opacity-10 -mr-10 -mt-10 transition-colors duration-500";

        ui.btn.disabled = true;
        ui.btn.className = "group w-full relative overflow-hidden rounded-xl bg-zinc-800 p-5 transition-all duration-300 opacity-50 cursor-not-allowed border border-zinc-700";
        ui.btnText.className = "font-bold text-sm text-zinc-500 uppercase tracking-wide";
        ui.btnText.innerText = "Device Unreachable";

        ui.icon.innerHTML = `<svg class="w-6 h-6 text-zinc-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>`;
        ui.text.innerText = `Last Seen: ${lastSeen ?? 'OFFLINE'}`;
        ui.text.className = "text-[10px] text-red-500/60 font-mono ml-1";
    }

    function unlockDoor() {
        const btn = document.getElementById('btnUnlock');
        if (btn.disabled) return;

        if (!confirm('Are you sure you want to unlock the main entrance?')) return;

        const bar = document.getElementById('unlockProgress');
        const txt = document.getElementById('unlockBtnText');

        txt.innerText = "Sending Command...";
        bar.style.width = "100%";

        fetch('ajax_door.php')
            .then(res => res.json())
            .then(data => {
                setTimeout(() => {
                    bar.style.width = "0%";
                    if (data.status === 'success') {
                        txt.innerText = "Unlocked Successfully";
                        setTimeout(() => txt.innerText = "Unlock Main Door", 2000);
                    } else {
                        txt.innerText = "Command Failed";
                        alert(data.message);
                        setTimeout(() => txt.innerText = "Unlock Main Door", 2000);
                    }
                }, 1000);
            })
            .catch(err => {
                bar.style.width = "0%";
                txt.innerText = "Error";
                alert("Network Error");
            });
    }
</script>

<?php require_once 'includes/footer.php'; ?>