<?php
session_start();
require_once 'config/Database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$pdo = Database::getInstance()->getConnection();

// Fetch all bookings for this user
$stmt = $pdo->prepare("
    SELECT s.*, st.label as seat_label, sh.name as shift_name, sh.start_time, sh.end_time
    FROM subscriptions s
    JOIN seats st ON s.seat_id = st.id
    JOIN shifts sh ON s.shift_id = sh.id
    WHERE s.user_id = ?
    ORDER BY s.created_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$bookings = $stmt->fetchAll();

$pageTitle = "My Bookings";
require_once 'includes/header.php';
?>

<div class="max-w-6xl mx-auto p-6 lg:p-12">

    <div class="flex flex-col md:flex-row justify-between items-end mb-8 gap-4">
        <div>
            <h1 class="text-3xl font-bold text-white tracking-tight">Booking History</h1>
            <p class="text-zinc-500 mt-1">Track your subscriptions, payments, and renewals.</p>
        </div>
        <a href="dashboard.php" class="text-sm font-bold text-zinc-400 hover:text-white transition">
            ← Back to Dashboard
        </a>
    </div>

    <div class="space-y-4">
        <?php foreach ($bookings as $row):
            // Determine Status Logic
            $isActive = (strtotime($row['end_date']) >= time() && $row['payment_status'] == 'paid');
            $isPending = ($row['payment_status'] == 'pending');
            $isRejected = ($row['payment_status'] == 'rejected');
            $hasDues = ($row['due_amount'] > 0);

            // Visual Styles
            $borderClass = "border-zinc-800";
            if ($isActive) $borderClass = "border-emerald-500/50 shadow-[0_0_20px_rgba(16,185,129,0.1)]";
            if ($hasDues && !$isRejected) $borderClass = "border-red-500/50";
            if ($isPending) $borderClass = "border-yellow-500/50";
        ?>

            <div class="bg-zinc-900/40 border <?= $borderClass ?> p-6 rounded-xl flex flex-col md:flex-row gap-6 items-start md:items-center justify-between transition hover:bg-zinc-900/60">

                <div class="flex items-center gap-4 min-w-[200px]">
                    <div class="h-12 w-12 rounded-lg bg-zinc-800 flex items-center justify-center font-mono font-bold text-xl text-white border border-zinc-700">
                        <?= $row['seat_label'] ?>
                    </div>
                    <div>
                        <h3 class="font-bold text-white"><?= $row['shift_name'] ?></h3>
                        <p class="text-xs text-zinc-500">
                            <?= date('h:i A', strtotime($row['start_time'])) ?> - <?= date('h:i A', strtotime($row['end_time'])) ?>
                        </p>
                    </div>
                </div>

                <div class="min-w-[150px]">
                    <p class="text-[10px] uppercase text-zinc-500 font-bold tracking-wider">Duration</p>
                    <div class="flex items-center gap-2 text-sm text-zinc-300 mt-1">
                        <span><?= date('d M Y', strtotime($row['start_date'])) ?></span>
                        <span class="text-zinc-600">➝</span>
                        <span class="<?= $isActive ? 'text-emerald-400 font-bold' : '' ?>">
                            <?= date('d M Y', strtotime($row['end_date'])) ?>
                        </span>
                    </div>
                </div>

                <div class="min-w-[150px]">
                    <p class="text-[10px] uppercase text-zinc-500 font-bold tracking-wider">Payment</p>
                    <div class="mt-1">
                        <span class="text-white font-mono font-bold">₹<?= number_format($row['paid_amount']) ?></span>
                        <span class="text-zinc-500 text-xs"> / ₹<?= number_format($row['final_amount']) ?></span>
                    </div>

                    <?php if ($hasDues && !$isRejected): ?>
                        <p class="text-xs text-red-400 font-bold mt-1">Due: ₹<?= number_format($row['due_amount']) ?></p>
                    <?php endif; ?>
                </div>

                <div class="flex items-center gap-4 w-full md:w-auto justify-between md:justify-end">

                    <div>
                        <?php if ($isRejected): ?>
                            <span class="bg-red-900/20 text-red-500 px-3 py-1 rounded-full text-xs font-bold border border-red-900/30">Rejected</span>
                        <?php elseif ($isPending): ?>
                            <span class="bg-yellow-900/20 text-yellow-500 px-3 py-1 rounded-full text-xs font-bold border border-yellow-900/30 animate-pulse">Processing</span>
                        <?php elseif ($isActive): ?>
                            <span class="bg-emerald-900/20 text-emerald-400 px-3 py-1 rounded-full text-xs font-bold border border-emerald-900/30">Active</span>
                        <?php else: ?>
                            <span class="bg-zinc-800 text-zinc-500 px-3 py-1 rounded-full text-xs font-bold">Expired</span>
                        <?php endif; ?>
                    </div>

                    <?php if ($isActive): ?>
                        <a href="id_card.php" target="_blank" class="bg-white text-black hover:bg-zinc-200 px-4 py-2 rounded-lg text-xs font-bold transition flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"></path>
                            </svg>
                            ID Card
                        </a>
                    <?php elseif ($hasDues && !$isRejected): ?>
                        <a href="https://wa.me/919876543210?text=I need to clear dues for Booking #<?= $row['id'] ?>" target="_blank" class="bg-red-600 text-white hover:bg-red-500 px-4 py-2 rounded-lg text-xs font-bold transition">
                            Pay Dues
                        </a>
                    <?php endif; ?>
                </div>

            </div>
        <?php endforeach; ?>

        <?php if (empty($bookings)): ?>
            <div class="text-center py-20 border border-zinc-800 rounded-xl bg-zinc-900/20 border-dashed">
                <p class="text-zinc-500">No bookings found.</p>
                <a href="dashboard.php" class="text-white font-bold text-sm mt-2 hover:underline inline-block">Book your first seat</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>