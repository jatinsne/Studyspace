<?php
require_once 'admin_check.php';
require_once '../config/Database.php';

$pdo = Database::getInstance()->getConnection();
$today = date('Y-m-d');

// 1. ADD EXPENSE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['amount'])) {
    $title = trim($_POST['title']);
    $amount = $_POST['amount'];
    $category = $_POST['category'];
    $date = $_POST['date']; // Matches 'date' column
    $notes = trim($_POST['notes']);
    $createdBy = $_SESSION['user_id']; // Matches 'created_by' column

    try {
        $stmt = $pdo->prepare("INSERT INTO expenses (title, category, amount, date, notes, created_by) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$title, $category, $amount, $date, $notes, $createdBy]);
        header("Location: expenses.php");
        exit;
    } catch (PDOException $e) {
        $error = "Error adding expense: " . $e->getMessage();
    }
}

// 2. DELETE EXPENSE
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $pdo->prepare("DELETE FROM expenses WHERE id = ?")->execute([$id]);
    header("Location: expenses.php");
    exit;
}

// 3. FETCH DATA (This Month Only)
$monthStart = date('Y-m-01');
$sql = "
    SELECT e.*, u.name as admin_name 
    FROM expenses e 
    LEFT JOIN users u ON e.created_by = u.id
    WHERE e.date >= ? 
    ORDER BY e.date DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$monthStart]);
$expenses = $stmt->fetchAll();

// 4. CALCULATE STATS (This Month)
$totalExpense = 0;
foreach ($expenses as $ex) {
    $totalExpense += $ex['amount'];
}

// Calculate Income for Comparison (Net Profit)
$totalRevenue = $pdo->query("SELECT SUM(paid_amount) FROM subscriptions WHERE payment_status != 'rejected' AND created_at >= '$monthStart'")->fetchColumn() ?: 0;
$netProfit = $totalRevenue - $totalExpense;

$breadcrump = "Report for " . date('F Y');
$headerTitle = "Financial Overview";
?>

<!DOCTYPE html>
<html lang="en" class="dark">

<head>
    <title>Expense Manager | Admin</title>
    <? require_once 'includes/common.php' ?>

</head>

<body class="p-4 md:p-8 max-w-6xl mx-auto">
    <? require_once 'includes/loader.php' ?>
    <? require_once 'includes/breadcrump.php' ?>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="bg-zinc-900/50 p-6 rounded-xl border border-zinc-800">
            <div class="flex items-center gap-3 mb-2">
                <div class="p-2 bg-emerald-500/10 rounded-lg text-emerald-500">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <p class="text-xs uppercase text-zinc-500 font-bold tracking-wider">Revenue</p>
            </div>
            <p class="text-2xl font-mono text-emerald-400 font-bold">+₹<?= number_format($totalRevenue) ?></p>
        </div>

        <div class="bg-zinc-900/50 p-6 rounded-xl border border-zinc-800">
            <div class="flex items-center gap-3 mb-2">
                <div class="p-2 bg-red-500/10 rounded-lg text-red-500">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6"></path>
                    </svg>
                </div>
                <p class="text-xs uppercase text-zinc-500 font-bold tracking-wider">Total Expenses</p>
            </div>
            <p class="text-2xl font-mono text-red-400 font-bold">-₹<?= number_format($totalExpense) ?></p>
        </div>

        <div class="bg-zinc-900/50 p-6 rounded-xl border border-zinc-800 relative overflow-hidden">
            <div class="flex items-center gap-3 mb-2 relative z-10">
                <div class="p-2 bg-accent/10 rounded-lg text-accent">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                    </svg>
                </div>
                <p class="text-xs uppercase text-zinc-500 font-bold tracking-wider">Net Profit</p>
            </div>
            <p class="text-3xl font-mono font-bold relative z-10 <?= $netProfit >= 0 ? 'text-white' : 'text-red-500' ?>">
                ₹<?= number_format($netProfit) ?>
            </p>
            <div class="absolute -right-6 -bottom-6 w-24 h-24 bg-accent/5 rounded-full blur-2xl"></div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

        <div class="bg-surface border border-zinc-900 p-6 rounded-xl h-fit shadow-lg">
            <h3 class="font-bold text-white mb-6 flex items-center gap-2">
                <span class="text-accent">+</span> Record New Expense
            </h3>

            <form method="POST" class="space-y-5">
                <div>
                    <label class="text-[10px] text-zinc-500 uppercase font-bold tracking-widest mb-1 block">Title</label>
                    <input type="text" name="title" required placeholder="e.g. Electricity Bill"
                        class="w-full bg-black border border-zinc-800 p-3 rounded-lg text-white focus:border-accent outline-none transition placeholder-zinc-700">
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="text-[10px] text-zinc-500 uppercase font-bold tracking-widest mb-1 block">Amount (₹)</label>
                        <input type="number" name="amount" required step="0.01" placeholder="0.00"
                            class="w-full bg-black border border-zinc-800 p-3 rounded-lg text-white focus:border-accent outline-none font-mono">
                    </div>
                    <div>
                        <label class="text-[10px] text-zinc-500 uppercase font-bold tracking-widest mb-1 block">Date</label>
                        <input type="date" name="date" value="<?= $today ?>" required
                            class="w-full bg-black border border-zinc-800 p-3 rounded-lg text-white focus:border-accent outline-none">
                    </div>
                </div>

                <div>
                    <label class="text-[10px] text-zinc-500 uppercase font-bold tracking-widest mb-1 block">Category</label>
                    <select name="category" required class="w-full bg-black border border-zinc-800 p-3 rounded-lg text-white outline-none focus:border-accent">
                        <option value="Rent">Rent</option>
                        <option value="Utilities">Utilities (Electricity/Water)</option>
                        <option value="Salary">Salary</option>
                        <option value="Maintenance">Maintenance</option>
                        <option value="Marketing">Marketing</option>
                        <option value="Cleaning">Cleaning</option>
                        <option value="Internet">Internet/WiFi</option>
                        <option value="Other">Other</option>
                    </select>
                </div>

                <div>
                    <label class="text-[10px] text-zinc-500 uppercase font-bold tracking-widest mb-1 block">Notes (Optional)</label>
                    <textarea name="notes" rows="2" class="w-full bg-black border border-zinc-800 p-3 rounded-lg text-white focus:border-accent outline-none text-sm"></textarea>
                </div>

                <button type="submit" class="w-full bg-white text-black font-bold py-3 rounded-lg hover:bg-zinc-200 transition transform active:scale-95">
                    Save Expense
                </button>
            </form>
        </div>

        <div class="lg:col-span-2 space-y-4">
            <?php foreach ($expenses as $ex): ?>
                <div class="bg-zinc-900/30 border border-zinc-800 p-5 rounded-xl flex items-center justify-between group hover:border-zinc-700 transition">

                    <div class="flex items-center gap-5">
                        <div class="h-12 w-12 bg-zinc-800 rounded-lg flex items-center justify-center border border-zinc-700 text-zinc-400 font-bold text-xs uppercase shadow-inner">
                            <?= substr($ex['category'], 0, 3) ?>
                        </div>

                        <div>
                            <h4 class="font-bold text-white text-lg tracking-tight"><?= htmlspecialchars($ex['title']) ?></h4>
                            <div class="flex gap-3 text-xs text-zinc-500 mt-1">
                                <span class="flex items-center gap-1">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                    </svg>
                                    <?= date('d M Y', strtotime($ex['date'])) ?>
                                </span>
                                <span class="bg-zinc-800 px-2 rounded text-zinc-400 border border-zinc-700/50">
                                    <?= $ex['category'] ?>
                                </span>
                            </div>
                            <?php if ($ex['notes']): ?>
                                <p class="text-xs text-zinc-600 mt-2 italic">"<?= htmlspecialchars($ex['notes']) ?>"</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="flex flex-col items-end gap-2">
                        <span class="font-mono font-bold text-red-400 text-xl">-₹<?= number_format($ex['amount']) ?></span>
                        <div class="flex items-center gap-3">
                            <span class="text-[10px] text-zinc-600 uppercase tracking-wide">
                                By: <?= htmlspecialchars($ex['admin_name'] ?? 'Unknown') ?>
                            </span>
                            <a href="?delete=<?= $ex['id'] ?>" onclick="return confirm('Permanently delete this expense?')"
                                class="text-zinc-600 hover:text-red-500 transition p-1 rounded hover:bg-red-900/20" title="Delete">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                </svg>
                            </a>
                        </div>
                    </div>

                </div>
            <?php endforeach; ?>

            <?php if (empty($requests)): ?>
                <div class="flex flex-col items-center justify-center py-16 text-center">
                    <div class="bg-zinc-900 p-4 rounded-full mb-3">
                        <svg class="w-8 h-8 text-zinc-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                    </div>
                    <h3 class="text-zinc-400 font-bold">All Caught Up!</h3>
                    <p class="text-zinc-600 text-sm">No expenses booked.</p>
                </div>
            <?php endif; ?>
        </div>

    </div>
</body>

</html>