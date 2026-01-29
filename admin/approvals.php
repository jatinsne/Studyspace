<?php
require_once 'admin_check.php';
require_once '../config/Database.php';

$pdo = Database::getInstance()->getConnection();

// Fetch Pending Bookings
$sql = "
    SELECT s.id, u.name, u.phone, st.label, sh.name as shift, s.start_date, s.final_amount, s.created_at
    FROM subscriptions s
    JOIN users u ON s.user_id = u.id
    JOIN seats st ON s.seat_id = st.id
    JOIN shifts sh ON s.shift_id = sh.id
    WHERE s.payment_status = 'pending'
    ORDER BY s.created_at ASC
";
$requests = $pdo->query($sql)->fetchAll();

$breadcrump = "Pending Requests";
$headerTitle = "Students waiting for approval & payment";
?>

<!DOCTYPE html>
<html lang="en" class="dark">

<head>
    <title>Booking Requests | Admin</title>
    <? require_once 'includes/common.php' ?>

</head>

<body class="p-4 md:p-8 max-w-4xl mx-auto">
    <? require_once 'includes/loader.php' ?>
    <? require_once 'includes/breadcrump.php' ?>

    <div class="bg-surface border border-zinc-900 rounded-xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-zinc-900 text-zinc-500 uppercase text-xs">
                    <tr>
                        <th class="px-6 py-4">Student</th>
                        <th class="px-6 py-4">Seat Request</th>
                        <th class="px-6 py-4">Amount Due</th>
                        <th class="px-6 py-4 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-800">
                    <?php foreach ($requests as $row): ?>
                        <tr>
                            <td class="px-6 py-4">
                                <div class="font-bold text-white"><?= htmlspecialchars($row['name']) ?></div>
                                <div class="text-xs text-zinc-500"><?= $row['phone'] ?></div>
                                <div class="text-[10px] text-zinc-600 mt-1"><?= date('M d, H:i', strtotime($row['created_at'])) ?></div>
                            </td>
                            <td class="px-6 py-4">
                                <span class="text-accent font-bold"><?= $row['label'] ?></span>
                                <span class="text-zinc-500"> - <?= $row['shift'] ?></span>
                                <div class="text-xs text-zinc-500 mt-1">Starts: <?= $row['start_date'] ?></div>
                            </td>
                            <td class="px-6 py-4 font-mono text-white">₹<?= number_format($row['final_amount']) ?></td>
                            <td class="px-6 py-4 text-right flex justify-end gap-2">
                                <a href="process_request.php?id=<?= $row['id'] ?>" class="bg-emerald-600 text-white font-bold px-4 py-2 rounded text-xs hover:bg-emerald-500 transition">
                                    Approve
                                </a>

                                <form method="POST" action="reject_request.php" onsubmit="return confirm('Are you sure? This will free up the seat.');">
                                    <input type="hidden" name="req_id" value="<?= $row['id'] ?>">
                                    <button type="submit" class="bg-red-900/50 border border-red-900 text-red-500 hover:bg-red-600 hover:text-white font-bold px-4 py-2 rounded text-xs transition">
                                        Reject
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if (empty($requests)): ?>
            <div class="flex flex-col items-center justify-center py-16 text-center">
                <div class="bg-zinc-900 p-4 rounded-full mb-3">
                    <svg class="w-8 h-8 text-zinc-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                </div>
                <h3 class="text-zinc-400 font-bold">All Caught Up!</h3>
                <p class="text-zinc-600 text-sm">No pending booking requests.</p>
            </div>
        <?php endif; ?>
    </div>

</body>

</html>