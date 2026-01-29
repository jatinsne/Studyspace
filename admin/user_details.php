<?php
require_once 'admin_check.php';
require_once '../config/Database.php';

$pdo = Database::getInstance()->getConnection();
$userId = $_GET['id'] ?? 0;

// 1. Fetch User Profile
$userStmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$userStmt->execute([$userId]);
$user = $userStmt->fetch();

if (!$user) {
    echo "User not found.";
    exit;
}

// 2. Fetch User Stats (Total Spend, Total Bookings)
$statsStmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_bookings, 
        SUM(final_amount) as total_spent 
    FROM subscriptions 
    WHERE user_id = ? AND payment_status = 'paid'
");
$statsStmt->execute([$userId]);
$stats = $statsStmt->fetch();

// 3. Fetch Booking History
$histStmt = $pdo->prepare("
    SELECT 
        s.*, 
        st.label as seat_label, 
        st.type as seat_type, 
        sh.name as shift_name 
    FROM subscriptions s
    JOIN seats st ON s.seat_id = st.id
    JOIN shifts sh ON s.shift_id = sh.id
    WHERE s.user_id = ?
    ORDER BY s.created_at DESC
");
$histStmt->execute([$userId]);
$history = $histStmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en" class="dark">

<head>
    <meta charset="UTF-8">
    <title>Student Profile | Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        bg: '#09090b',
                        surface: '#18181b',
                        accent: '#d4b106'
                    }
                }
            }
        }
    </script>
    <style>
        body {
            background-color: #09090b;
            color: #fff;
        }
    </style>
</head>

<body class="p-8 max-w-5xl mx-auto">

    <div class="flex items-center justify-between mb-8">
        <div class="flex items-center gap-4">
            <div class="w-12 h-12 rounded-full bg-zinc-800 flex items-center justify-center text-xl font-bold text-zinc-500">
                <?= strtoupper(substr($user['name'], 0, 1)) ?>
            </div>
            <div class="flex justify-between items-start">
                <div>
                    <h1 class="text-3xl font-bold"><?= htmlspecialchars($user['name']) ?></h1>
                    <p class="text-zinc-500"><?= $user['phone'] ?></p>
                </div>

                <a href="edit_user.php?id=<?= $user['id'] ?>" class="bg-zinc-800 hover:bg-zinc-700 text-white px-4 py-2 rounded-lg text-sm font-bold flex items-center gap-2 transition border border-zinc-700">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                    </svg>
                    Edit Details
                </a>
            </div>
        </div>
        <a href="users.php" class="text-zinc-500 hover:text-white text-sm">← Back to Directory</a>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-10">
        <div class="bg-surface border border-zinc-900 p-6 rounded-xl">
            <p class="text-xs uppercase text-zinc-500 font-bold tracking-wider">Contact Info</p>
            <p class="text-white mt-2"><?= htmlspecialchars($user['email']) ?></p>
            <p class="text-zinc-400 text-sm"><?= htmlspecialchars($user['phone'] ?? "") ?></p>
        </div>

        <div class="bg-surface border border-zinc-900 p-6 rounded-xl">
            <p class="text-xs uppercase text-zinc-500 font-bold tracking-wider">Lifetime Value</p>
            <p class="text-3xl font-mono text-emerald-400 mt-2">₹<?= number_format($stats['total_spent'] ?? 0) ?></p>
        </div>

        <div class="bg-surface border border-zinc-900 p-6 rounded-xl">
            <p class="text-xs uppercase text-zinc-500 font-bold tracking-wider">Total Bookings</p>
            <p class="text-3xl font-mono text-white mt-2"><?= $stats['total_bookings'] ?></p>
        </div>
    </div>

    <h2 class="text-lg font-bold mb-4">Subscription History</h2>
    <div class="bg-surface border border-zinc-900 rounded-xl overflow-hidden">
        <table class="w-full text-left text-sm">
            <thead class="bg-zinc-900/50 text-zinc-500 uppercase text-xs font-bold">
                <tr>
                    <th class="px-6 py-4">Status</th>
                    <th class="px-6 py-4">Seat / Shift</th>
                    <th class="px-6 py-4">Duration</th>
                    <th class="px-6 py-4">Amount</th>
                    <th colspan="2" class="px-6 py-4 text-right">Receipt</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-800">
                <?php foreach ($history as $row):
                    $isActive = (date('Y-m-d') <= $row['end_date']);
                ?>
                    <tr class="hover:bg-zinc-900 transition">
                        <td class="px-6 py-4">
                            <?php if ($isActive): ?>
                                <span class="bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 px-2 py-1 rounded text-xs uppercase font-bold">Active</span>
                            <?php else: ?>
                                <span class="bg-zinc-800 text-zinc-500 px-2 py-1 rounded text-xs uppercase font-bold">Expired</span>
                            <?php endif; ?>
                        </td>

                        <td class="px-6 py-4">
                            <div class="font-bold text-white"><?= $row['seat_label'] ?> <span class="text-zinc-500 font-normal">(<?= $row['seat_type'] ?>)</span></div>
                            <div class="text-xs text-zinc-500"><?= $row['shift_name'] ?></div>
                        </td>
                        <td class="px-6 py-4 font-mono text-zinc-400">
                            <?= $row['start_date'] ?> <span class="text-zinc-600">to</span> <?= $row['end_date'] ?>
                        </td>
                        <td class="px-6 py-4">
                            <div class="font-mono text-white">₹<?= number_format($row['final_amount']) ?></div>
                            <div class="text-[10px] uppercase text-zinc-500 tracking-wider"><?= $row['payment_method'] ?></div>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <a href="../receipt.php?id=<?= $row['id'] ?>" target="_blank" class="text-accent hover:underline text-xs font-bold uppercase tracking-wider">
                                Print Receipt
                            </a>
                        </td>
                        <?php
                        function getWhatsAppLink($booking, $studentPhone, $studentName)
                        {
                            // 1. Format Phone (Remove spaces, ensure 91)
                            $phone = preg_replace('/[^0-9]/', '', $studentPhone);
                            if (strlen($phone) == 10) $phone = "91" . $phone;

                            // 2. Build Message
                            $msg = "*Booking Confirmed!* ✅\n";
                            $msg .= "Hi $studentName, your seat *{$booking['seat_label']}* is active.\n";
                            $msg .= "📅 Valid: " . date('d M', strtotime($booking['start_date'])) . " to " . date('d M Y', strtotime($booking['end_date'])) . "\n";
                            $msg .= "💰 Amount Paid: ₹" . $booking['paid_amount'];
                            if ($booking['due_amount'] > 0) {
                                $msg .= "\n⚠️ *Due Balance: ₹{$booking['due_amount']}*";
                            }
                            $msg .= "\n\n🔗 View Digital ID: https://your-library.com/id_card.php";

                            // 3. Return Link
                            return "https://wa.me/$phone?text=" . urlencode($msg);
                        }
                        ?>

                        <td>
                            <a href="<?= getWhatsAppLink($row, $user['phone'], $user['name']) ?>"
                                target="_blank"
                                class="text-green-500 hover:text-green-400 font-bold text-xs flex items-center gap-1">
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z" />
                                </svg>
                                Send Receipt
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php if (empty($history)): ?>
            <div class="p-8 text-center text-zinc-500">No booking history available.</div>
        <?php endif; ?>
    </div>

</body>

</html>