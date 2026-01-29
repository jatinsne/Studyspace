<?php
// profile.php
require_once 'includes/header.php';

// 1. Fetch User Details & Stats
$userId = $_SESSION['user_id'];
$stmt = $pdo->prepare("
    SELECT u.*, 
    (SELECT COUNT(*) FROM attendance WHERE user_id = u.id) as total_days_present,
    (SELECT COUNT(*) FROM subscriptions WHERE user_id = u.id AND payment_status = 'paid') as total_bookings
    FROM users u 
    WHERE u.id = ?
");
$stmt->execute([$userId]);
$user = $stmt->fetch();

// 2. Fetch Active Subscription
$subStmt = $pdo->prepare("
    SELECT s.*, st.label, sh.name as shift_name 
    FROM subscriptions s
    JOIN seats st ON s.seat_id = st.id
    JOIN shifts sh ON s.shift_id = sh.id
    WHERE s.user_id = ? AND s.end_date >= CURDATE() AND s.payment_status = 'paid'
    ORDER BY s.end_date DESC LIMIT 1
");
$subStmt->execute([$userId]);
$activeSub = $subStmt->fetch();
?>

<div class="max-w-4xl mx-auto px-4 py-10">

    <div class="bg-surface border border-zinc-800 rounded-2xl p-8 mb-8 flex flex-col md:flex-row items-center gap-8 relative overflow-hidden">
        <div class="absolute top-0 right-0 w-64 h-64 bg-accent/5 rounded-full blur-3xl -translate-y-1/2 translate-x-1/2"></div>

        <div class="relative group">
            <div class="w-32 h-32 rounded-full border-4 border-zinc-900 shadow-2xl overflow-hidden">
                <img src="<?= !empty($user['profile_image']) ? 'uploads/' . $user['profile_image'] : 'https://ui-avatars.com/api/?name=' . urlencode($user['name']) ?>" class="w-full h-full object-cover">
            </div>
            <a href="edit_profile.php" class="absolute bottom-0 right-0 bg-zinc-800 text-white p-2 rounded-full border border-zinc-700 hover:bg-accent hover:text-black transition shadow-lg">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path>
                </svg>
            </a>
        </div>

        <div class="text-center md:text-left z-10">
            <h1 class="text-3xl font-bold text-white mb-1"><?= htmlspecialchars($user['name']) ?></h1>
            <p class="text-zinc-500 font-mono text-sm mb-4"><?= $user['phone'] ?> • <?= $user['email'] ?></p>

            <div class="flex flex-wrap justify-center md:justify-start gap-3">
                <?php if ($user['verification_status'] === 'verified'): ?>
                    <span class="px-3 py-1 rounded-full bg-emerald-900/30 text-emerald-400 border border-emerald-900/50 text-xs font-bold uppercase tracking-wider flex items-center gap-1">
                        <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                        </svg>
                        Verified Student
                    </span>
                <?php else: ?>
                    <a href="edit_profile.php" class="px-3 py-1 rounded-full bg-yellow-900/30 text-yellow-400 border border-yellow-900/50 text-xs font-bold uppercase tracking-wider animate-pulse">
                        Verification Pending
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

        <div class="bg-zinc-900/50 border border-zinc-800 p-6 rounded-xl">
            <h3 class="text-xs uppercase text-zinc-500 font-bold mb-4 tracking-widest">Current Membership</h3>
            <?php if ($activeSub): ?>
                <div class="flex justify-between items-start mb-4">
                    <div>
                        <p class="text-2xl font-bold text-white mb-1">Seat <?= $activeSub['label'] ?></p>
                        <p class="text-sm text-accent"><?= $activeSub['shift_name'] ?></p>
                    </div>
                    <div class="text-right">
                        <p class="text-xs text-zinc-500 uppercase">Expires On</p>
                        <p class="text-white font-mono font-bold"><?= date('d M Y', strtotime($activeSub['end_date'])) ?></p>
                    </div>
                </div>
                <?php
                $start = strtotime($activeSub['start_date']);
                $end = strtotime($activeSub['end_date']);
                $now = time();
                $pct = min(100, max(0, (($now - $start) / ($end - $start)) * 100));
                ?>
                <div class="w-full bg-zinc-800 h-2 rounded-full overflow-hidden">
                    <div class="bg-emerald-500 h-full" style="width: <?= $pct ?>%"></div>
                </div>
                <p class="text-right text-[10px] text-zinc-600 mt-2"><?= round($pct) ?>% Consumed</p>
            <?php else: ?>
                <div class="text-center py-6">
                    <p class="text-zinc-500 mb-4">No active plan.</p>
                    <a href="dashboard.php" class="text-sm font-bold text-emerald-400 hover:text-emerald-300">Book a Seat →</a>
                </div>
            <?php endif; ?>
        </div>

        <div class="bg-zinc-900/50 border border-zinc-800 p-6 rounded-xl">
            <h3 class="text-xs uppercase text-zinc-500 font-bold mb-4 tracking-widest">Library Stats</h3>
            <div class="grid grid-cols-2 gap-4">
                <div class="p-4 bg-black rounded-lg border border-zinc-800 text-center">
                    <p class="text-2xl font-bold text-white"><?= $user['total_days_present'] ?></p>
                    <p class="text-[10px] uppercase text-zinc-500 mt-1">Days Attended</p>
                </div>
                <div class="p-4 bg-black rounded-lg border border-zinc-800 text-center">
                    <p class="text-2xl font-bold text-accent"><?= $user['total_bookings'] ?></p>
                    <p class="text-[10px] uppercase text-zinc-500 mt-1">Total Subscriptions</p>
                </div>
            </div>
            <div class="mt-4 pt-4 border-t border-zinc-800 text-center">
                <a href="id_card.php" class="text-xs font-bold text-zinc-400 hover:text-white transition">View Digital ID Card →</a>
            </div>
        </div>
    </div>
</div>