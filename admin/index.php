<?php
// 1. Security & Logic
require_once 'includes/header.php';

// 2. Fetch Key Metrics

// A. Lifetime Revenue
$revStmt = $pdo->query("SELECT SUM(paid_amount) as total FROM subscriptions WHERE payment_status != 'rejected'");
$totalRevenue = $revStmt->fetch()['total'] ?? 0;

// B. Lifetime Expenses (NEW)
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

// Pending Requests Count
$reqStmt = $pdo->query("SELECT COUNT(*) as total FROM subscriptions WHERE payment_status = 'pending'");
$reqCount = $reqStmt->fetch()['total'];

$displayName = $_SESSION['name'] ?? 'Administrator';
?>

<div class="max-w-7xl mx-auto px-6 py-6 w-full">

    <?php if ($totalDue > 0): ?>
        <div class="mb-8 w-full">
            <a href="dues.php" class="block bg-red-500/10 border border-red-500/20 p-4 rounded-xl flex justify-between items-center hover:bg-red-500/20 transition cursor-pointer group">
                <div class="flex items-center gap-4">
                    <div class="bg-red-500/20 p-2 rounded-lg text-red-500">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-red-400 font-bold text-sm uppercase tracking-wider">Action Required</h3>
                        <p class="text-white text-lg">Total Pending Collections: <span class="font-mono font-bold">₹<?= number_format($totalDue) ?></span></p>
                    </div>
                </div>
                <div class="flex items-center gap-2 text-red-400 text-sm font-bold group-hover:translate-x-1 transition-transform">
                    Collect Now <span>→</span>
                </div>
            </a>
        </div>
    <?php endif; ?>

    <div class="mb-12">
        <h1 class="text-3xl font-bold tracking-tight text-white">Command Center</h1>
        <p class="text-zinc-500 mt-1">System Overview & Financial Metrics</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-12">

        <div class="glass-panel p-6 rounded-2xl">
            <p class="text-zinc-500 text-xs uppercase tracking-widest font-bold mb-4">Total Revenue</p>
            <p class="text-3xl font-mono font-bold text-white">₹<?= number_format($totalRevenue) ?></p>
            <p class="text-xs text-zinc-500 mt-2">Gross Income</p>
        </div>

        <div class="glass-panel p-6 rounded-2xl relative overflow-hidden border-emerald-500/30">
            <div class="absolute top-0 right-0 p-4 opacity-20 text-emerald-500">
                <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                </svg>
            </div>
            <p class="text-emerald-500 text-xs uppercase tracking-widest font-bold mb-4">Net Profit (Lifetime)</p>
            <p class="text-3xl font-mono font-bold text-emerald-400">
                <?= $lifetimeProfit >= 0 ? '+' : '' ?>₹<?= number_format($lifetimeProfit) ?>
            </p>
            <p class="text-xs text-emerald-600/70 mt-2">After Expenses (₹<?= number_format($totalExpenses) ?>)</p>
        </div>

        <div class="glass-panel p-6 rounded-2xl">
            <p class="text-zinc-500 text-xs uppercase tracking-widest font-bold mb-4">Active Members</p>
            <p class="text-3xl font-mono font-bold text-blue-400"><?= $activeMembers ?></p>
            <p class="text-xs text-zinc-500 mt-2">Subscriptions active today</p>
        </div>

        <div class="glass-panel p-6 rounded-2xl">
            <p class="text-zinc-500 text-xs uppercase tracking-widest font-bold mb-4">Occupied Today</p>
            <p class="text-3xl font-mono font-bold text-accent"><?= $occupiedToday ?></p>
            <p class="text-xs text-zinc-500 mt-2">Seats currently booked</p>
        </div>
    </div>

    <h2 class="text-lg font-bold text-white mb-6">System Modules</h2>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">

        <a href="approvals.php" class="group glass-panel p-6 rounded-xl hover:bg-zinc-800 transition border-yellow-900/30 hover:border-yellow-500">
            <div class="h-10 w-10 bg-yellow-900/20 text-yellow-500 rounded-lg flex items-center justify-center mb-4 group-hover:bg-yellow-500 group-hover:text-black transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                </svg>
            </div>
            <h3 class="font-bold text-white group-hover:text-yellow-400 transition">Approvals</h3>
            <p class="text-xs text-zinc-500 mt-1">
                <?php if ($reqCount > 0): ?>
                    <span class="text-yellow-400 font-bold"><?= $reqCount ?> Waiting</span>
                <?php else: ?>
                    No pending requests
                <?php endif; ?>
            </p>
        </a>

        <a href="book_manual.php" class="group glass-panel p-6 rounded-xl hover:bg-zinc-800 transition border-accent/20 hover:border-accent">
            <div class="h-10 w-10 bg-accent/10 rounded-lg flex items-center justify-center mb-4 group-hover:bg-accent group-hover:text-black transition text-accent">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
                </svg>
            </div>
            <h3 class="font-bold text-white">Cash Desk</h3>
            <p class="text-xs text-zinc-500 mt-1">Manual Cash Bookings</p>
        </a>

        <a href="dues.php" class="group glass-panel p-6 rounded-xl hover:bg-zinc-800 transition border-red-900/30 hover:border-red-500">
            <div class="h-10 w-10 bg-red-900/20 text-red-500 rounded-lg flex items-center justify-center mb-4 group-hover:bg-red-500 group-hover:text-white transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
            <h3 class="font-bold text-white">Outstanding Dues</h3>
            <p class="text-xs text-zinc-500 mt-1">Manage Unpaid Balances</p>
        </a>

        <a href="expiring.php" class="group glass-panel p-6 rounded-xl hover:bg-zinc-800 transition border-orange-900/30 hover:border-orange-500">
            <div class="h-10 w-10 bg-orange-900/20 text-orange-500 rounded-lg flex items-center justify-center mb-4 group-hover:bg-orange-500 group-hover:text-white transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                </svg>
            </div>
            <h3 class="font-bold text-white group-hover:text-orange-400 transition">Renewals</h3>
            <p class="text-xs text-zinc-500 mt-1">
                <?php if ($expCount > 0): ?>
                    <span class="text-orange-400 font-bold"><?= $expCount ?> Expiring Soon</span>
                <?php else: ?>
                    All Up to Date
                <?php endif; ?>
            </p>
        </a>

        <a href="expenses.php" class="group glass-panel p-6 rounded-xl hover:bg-zinc-800 transition">
            <div class="h-10 w-10 bg-zinc-800 rounded-lg flex items-center justify-center mb-4 group-hover:bg-white group-hover:text-black transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                </svg>
            </div>
            <h3 class="font-bold text-white">Expenses</h3>
            <p class="text-xs text-zinc-500 mt-1">Track Operational Costs</p>
        </a>

        <a href="reports.php" class="group glass-panel p-6 rounded-xl hover:bg-zinc-800 transition">
            <div class="h-10 w-10 bg-zinc-800 rounded-lg flex items-center justify-center mb-4 group-hover:bg-white group-hover:text-black transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                </svg>
            </div>
            <h3 class="font-bold text-white">Reports</h3>
            <p class="text-xs text-zinc-500 mt-1">Financial Analysis</p>
        </a>

        <a href="users.php" class="group glass-panel p-6 rounded-xl hover:bg-zinc-800 transition">
            <div class="h-10 w-10 bg-zinc-800 rounded-lg flex items-center justify-center mb-4 group-hover:bg-white group-hover:text-black transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                </svg>
            </div>
            <h3 class="font-bold text-white">Students</h3>
            <p class="text-xs text-zinc-500 mt-1">Directory & Walk-ins</p>
        </a>

        <a href="manage_seats.php" class="group glass-panel p-6 rounded-xl hover:bg-zinc-800 transition">
            <div class="h-10 w-10 bg-zinc-800 rounded-lg flex items-center justify-center mb-4 group-hover:bg-white group-hover:text-black transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                </svg>
            </div>
            <h3 class="font-bold text-white">Asset Mgmt</h3>
            <p class="text-xs text-zinc-500 mt-1">Add/Edit Seats</p>
        </a>

        <a href="attendance.php" class="group glass-panel p-6 rounded-xl hover:bg-zinc-800 transition">
            <div class="h-10 w-10 bg-zinc-800 rounded-lg flex items-center justify-center mb-4 group-hover:bg-white group-hover:text-black transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                </svg>
            </div>
            <h3 class="font-bold text-white">Attendance</h3>
            <p class="text-xs text-zinc-500 mt-1">Daily Log</p>
        </a>

        <a href="coupons.php" class="group glass-panel p-6 rounded-xl hover:bg-zinc-800 transition border-zinc-800 hover:border-emerald-500">
            <div class="h-10 w-10 bg-zinc-800 rounded-lg flex items-center justify-center mb-4 group-hover:bg-emerald-500 group-hover:text-black transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
                </svg>
            </div>
            <h3 class="font-bold text-white group-hover:text-emerald-400 transition">Coupons</h3>
            <p class="text-xs text-zinc-500 mt-1">Manage Discounts</p>
        </a>

        <a href="settings.php" class="group glass-panel p-6 rounded-xl hover:bg-zinc-800 transition">
            <div class="h-10 w-10 bg-zinc-800 rounded-lg flex items-center justify-center mb-4 group-hover:bg-white group-hover:text-black transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                </svg>
            </div>
            <h3 class="font-bold text-white">Settings</h3>
            <p class="text-xs text-zinc-500 mt-1">Config & Pricing</p>
        </a>

        <a href="issues.php" class="group glass-panel p-6 rounded-xl hover:bg-zinc-800 transition border-zinc-800 hover:border-white">
            <div class="h-10 w-10 bg-zinc-800 rounded-lg flex items-center justify-center mb-4 group-hover:bg-white group-hover:text-black transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z"></path>
                </svg>
            </div>
            <h3 class="font-bold text-white">Helpdesk</h3>
            <p class="text-xs text-zinc-500 mt-1">Student Complaints</p>
        </a>

    </div>

    <div class="mt-12">
        <h2 class="text-lg font-bold text-white mb-4">Live Ledger</h2>
        <div class="glass-panel rounded-xl overflow-hidden">
            <table class="w-full text-left text-xs">
                <thead class="bg-zinc-900/50 text-zinc-500 uppercase font-bold">
                    <tr>
                        <th class="px-6 py-4">User</th>
                        <th class="px-6 py-4">Amount</th>
                        <th class="px-6 py-4">Method</th>
                        <th class="px-6 py-4">Timestamp</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-800 text-zinc-300">
                    <?php
                    $recStmt = $pdo->query("SELECT u.name, s.final_amount, s.payment_method, s.created_at FROM subscriptions s JOIN users u ON s.user_id = u.id ORDER BY s.created_at DESC LIMIT 5");
                    while ($row = $recStmt->fetch()):
                    ?>
                        <tr class="hover:bg-zinc-800/50 transition">
                            <td class="px-6 py-4 font-medium text-white"><?= htmlspecialchars($row['name']) ?></td>
                            <td class="px-6 py-4 font-mono text-emerald-400">₹<?= number_format($row['final_amount']) ?></td>
                            <td class="px-6 py-4 uppercase text-[10px] tracking-widest"><?= htmlspecialchars($row['payment_method']) ?></td>
                            <td class="px-6 py-4 text-zinc-500"><?= date('M d, H:i', strtotime($row['created_at'])) ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<?php require_once 'includes/footer.php'; ?>