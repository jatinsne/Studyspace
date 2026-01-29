<?php
require_once 'admin_check.php';
require_once '../config/Database.php';

$pdo = Database::getInstance()->getConnection();

// 1. Report: Revenue by Payment Method
$methodSql = "SELECT payment_method, SUM(final_amount) as total, COUNT(id) as count FROM subscriptions WHERE payment_status='paid' GROUP BY payment_method";
$methods = $pdo->query($methodSql)->fetchAll();

// 2. Report: Revenue by Shift
$shiftSql = "SELECT sh.name, SUM(s.final_amount) as total FROM subscriptions s JOIN shifts sh ON s.shift_id = sh.id WHERE s.payment_status='paid' GROUP BY sh.name";
$shifts = $pdo->query($shiftSql)->fetchAll();

// 3. Report: Monthly Trend (Last 6 Months)
$trendSql = "
    SELECT DATE_FORMAT(created_at, '%Y-%m') as month, SUM(final_amount) as total 
    FROM subscriptions 
    WHERE payment_status='paid' 
    GROUP BY month 
    ORDER BY month DESC LIMIT 6
";
$trend = $pdo->query($trendSql)->fetchAll();

// 1. Get Total Revenue (This Month)
$monthStart = date('Y-m-01');
$monthEnd = date('Y-m-t');

$revStmt = $pdo->prepare("SELECT SUM(final_amount) as total FROM subscriptions WHERE payment_status='paid' AND created_at BETWEEN ? AND ?");
$revStmt->execute([$monthStart . ' 00:00:00', $monthEnd . ' 23:59:59']);
$monthlyRevenue = $revStmt->fetch()['total'] ?? 0;

// 2. Get Total Expenses (This Month)
$expStmt = $pdo->prepare("SELECT SUM(amount) as total FROM expenses WHERE date BETWEEN ? AND ?");
$expStmt->execute([$monthStart, $monthEnd]);
$monthlyExpense = $expStmt->fetch()['total'] ?? 0;

// 3. Net Profit
$netProfit = $monthlyRevenue - $monthlyExpense;
$profitColor = $netProfit >= 0 ? 'text-emerald-400' : 'text-red-500';

$breadcrump = "Reports";
$headerTitle = "Financial Reports";
?>

<!DOCTYPE html>
<html lang="en" class="dark">

<head>
    <meta charset="UTF-8">
    <title>Financial Reports</title>
    <? require_once 'includes/common.php' ?>

</head>

<body class="p-4 md:p-8 max-w-6xl mx-auto">
    <? require_once 'includes/loader.php' ?>
    <? require_once 'includes/breadcrump.php' ?>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">

        <div class="bg-zinc-900 border border-zinc-800 p-6 rounded-xl">
            <p class="text-xs uppercase text-zinc-500 font-bold">Income (<?= date('M') ?>)</p>
            <p class="text-2xl font-mono text-white mt-1">₹<?= number_format($monthlyRevenue) ?></p>
            <div class="h-1 bg-zinc-800 mt-2 rounded overflow-hidden">
                <div class="h-full bg-emerald-500" style="width: 100%"></div>
            </div>
        </div>

        <div class="bg-zinc-900 border border-zinc-800 p-6 rounded-xl">
            <p class="text-xs uppercase text-zinc-500 font-bold">Expenses (<?= date('M') ?>)</p>
            <p class="text-2xl font-mono text-red-400 mt-1">₹<?= number_format($monthlyExpense) ?></p>
            <div class="h-1 bg-zinc-800 mt-2 rounded overflow-hidden">
                <?php $expWidth = ($monthlyRevenue > 0) ? ($monthlyExpense / $monthlyRevenue) * 100 : 0; ?>
                <div class="h-full bg-red-500" style="width: <?= min($expWidth, 100) ?>%"></div>
            </div>
        </div>

        <div class="bg-surface border border-zinc-800 p-6 rounded-xl relative overflow-hidden">
            <div class="absolute right-0 top-0 p-4 opacity-10">
                <svg class="w-16 h-16 <?= $profitColor ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
            <p class="text-xs uppercase text-zinc-500 font-bold">Net Profit</p>
            <p class="text-3xl font-mono <?= $profitColor ?> font-bold mt-1">
                <?= $netProfit >= 0 ? '+' : '' ?>₹<?= number_format($netProfit) ?>
            </p>
            <p class="text-[10px] text-zinc-500 mt-1">After all deductions</p>
        </div>

    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">

        <div class="bg-surface border border-zinc-900 p-8 rounded-2xl">
            <h3 class="text-lg font-bold mb-6 text-zinc-300">Revenue Source</h3>
            <div class="space-y-4">
                <?php foreach ($methods as $m):
                    $width = ($m['total'] > 0) ? ($m['total'] / ($methods[0]['total'] + ($methods[1]['total'] ?? 0))) * 100 : 0;
                ?>
                    <div>
                        <div class="flex justify-between text-sm mb-1">
                            <span class="uppercase tracking-wider text-xs font-bold text-zinc-500"><?= $m['payment_method'] ?></span>
                            <span class="font-mono">₹<?= number_format($m['total']) ?></span>
                        </div>
                        <div class="w-full bg-zinc-800 rounded-full h-2">
                            <div class="bg-accent h-2 rounded-full" style="width: <?= $width ?>%"></div>
                        </div>
                        <p class="text-[10px] text-zinc-600 mt-1"><?= $m['count'] ?> transactions</p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="bg-surface border border-zinc-900 p-8 rounded-2xl">
            <h3 class="text-lg font-bold mb-6 text-zinc-300">Income by Shift</h3>
            <table class="w-full text-sm">
                <thead class="text-xs uppercase text-zinc-500 text-left">
                    <tr>
                        <th class="pb-2">Shift</th>
                        <th class="pb-2 text-right">Revenue</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-800">
                    <?php foreach ($shifts as $s): ?>
                        <tr>
                            <td class="py-3"><?= $s['name'] ?></td>
                            <td class="py-3 text-right font-mono text-emerald-400">₹<?= number_format($s['total']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">

        <a href="export.php?type=bookings" class="bg-zinc-900 border border-zinc-800 p-4 rounded-xl flex items-center justify-between hover:bg-zinc-800 transition group">
            <div>
                <p class="text-white font-bold text-sm">All Bookings</p>
                <p class="text-zinc-500 text-xs">Full Ledger CSV</p>
            </div>
            <div class="h-8 w-8 bg-zinc-800 rounded flex items-center justify-center text-zinc-400 group-hover:text-white group-hover:bg-zinc-700">↓</div>
        </a>

        <a href="export.php?type=attendance" class="bg-zinc-900 border border-zinc-800 p-4 rounded-xl flex items-center justify-between hover:bg-zinc-800 transition group">
            <div>
                <p class="text-white font-bold text-sm">Attendance Log</p>
                <p class="text-zinc-500 text-xs">Check-in History</p>
            </div>
            <div class="h-8 w-8 bg-zinc-800 rounded flex items-center justify-center text-zinc-400 group-hover:text-white group-hover:bg-zinc-700">↓</div>
        </a>

        <a href="export.php?type=dues" class="bg-zinc-900 border border-zinc-800 p-4 rounded-xl flex items-center justify-between hover:bg-zinc-800 transition group">
            <div>
                <p class="text-red-400 font-bold text-sm">Pending Dues</p>
                <p class="text-zinc-500 text-xs">Unpaid Balance Sheet</p>
            </div>
            <div class="h-8 w-8 bg-zinc-800 rounded flex items-center justify-center text-zinc-400 group-hover:text-white group-hover:bg-zinc-700">↓</div>
        </a>

    </div>

    <div class="mt-8 bg-surface border border-zinc-900 p-8 rounded-2xl">
        <h3 class="text-lg font-bold mb-6 text-zinc-300">Monthly Growth</h3>
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead class="bg-zinc-900 text-zinc-500 uppercase text-xs">
                    <tr>
                        <th class="px-6 py-3">Month</th>
                        <th class="px-6 py-3 text-right">Total Revenue</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-800">
                    <?php foreach ($trend as $t): ?>
                        <tr>
                            <td class="px-6 py-4 font-mono"><?= $t['month'] ?></td>
                            <td class="px-6 py-4 text-right font-mono font-bold text-white">₹<?= number_format($t['total']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</body>

</html>