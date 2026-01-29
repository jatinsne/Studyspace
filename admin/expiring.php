<?php
require_once 'admin_check.php';
require_once '../config/Database.php';

$pdo = Database::getInstance()->getConnection();

// Logic: Find active subscriptions ending between TODAY and TODAY + 5 DAYS
$sql = "
    SELECT s.id, s.end_date, u.name, u.phone, st.label, sh.name as shift
    FROM subscriptions s
    JOIN users u ON s.user_id = u.id
    JOIN seats st ON s.seat_id = st.id
    JOIN shifts sh ON s.shift_id = sh.id
    WHERE s.payment_status = 'paid'
    AND s.end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 5 DAY)
    ORDER BY s.end_date ASC
";
$expiring = $pdo->query($sql)->fetchAll();

$breadcrump = "Renewal Students";
$headerTitle = "Expiring Subscriptions";
?>

<!DOCTYPE html>
<html lang="en" class="dark">

<head>
    <meta charset="UTF-8">
    <title>Expiring Soon | Admin</title>
    <? require_once 'includes/common.php' ?>

</head>

<body class="p-4 md:p-8 max-w-4xl mx-auto">
    <? require_once 'includes/loader.php' ?>
    <? require_once 'includes/breadcrump.php' ?>

    <div class="bg-surface border border-zinc-900 rounded-xl overflow-hidden">
        <table class="w-full text-left text-sm">
            <thead class="bg-zinc-900 text-zinc-500 uppercase text-xs">
                <tr>
                    <th class="px-6 py-4">Student</th>
                    <th class="px-6 py-4">Seat Details</th>
                    <th class="px-6 py-4">Expires On</th>
                    <th class="px-6 py-4 text-right">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-800">
                <?php foreach ($expiring as $row):
                    $daysLeft = (strtotime($row['end_date']) - time()) / (60 * 60 * 24);
                    $daysLeft = ceil($daysLeft);
                ?>
                    <tr>
                        <td class="px-6 py-4">
                            <div class="font-bold text-white"><?= htmlspecialchars($row['name']) ?></div>
                            <div class="text-xs text-zinc-500"><?= $row['phone'] ?></div>
                        </td>
                        <td class="px-6 py-4">
                            <span class="text-white"><?= $row['label'] ?></span>
                            <span class="text-zinc-600">|</span>
                            <span class="text-zinc-400"><?= $row['shift'] ?></span>
                        </td>
                        <td class="px-6 py-4">
                            <div class="text-orange-400 font-bold"><?= date('M d', strtotime($row['end_date'])) ?></div>
                            <div class="text-[10px] uppercase tracking-wide text-zinc-500">
                                <?= $daysLeft <= 0 ? 'Ends Today' : $daysLeft . ' Days Left' ?>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-right flex justify-end gap-2">
                            <?php
                            $msg = "Hi {$row['name']}, your library subscription for Seat {$row['label']} expires on " . date('d M', strtotime($row['end_date'])) . ". Please renew to keep your spot.";
                            $waLink = "https://wa.me/91{$row['phone']}?text=" . urlencode($msg);
                            ?>
                            <a href="<?= $waLink ?>" target="_blank" class="bg-green-600 hover:bg-green-500 text-white px-3 py-2 rounded text-xs font-bold transition flex items-center gap-2">
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z" />
                                </svg>
                            </a>

                            <a href="renew_seat.php?id=<?= $row['id'] ?>" class="bg-white text-black font-bold px-3 py-2 rounded text-xs hover:bg-zinc-200 transition">
                                Renew
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if (empty($expiring)): ?>
            <div class="p-12 text-center text-zinc-500">
                <div class="text-emerald-500 mb-2">●</div>
                All active subscriptions are healthy. No immediate expirations.
            </div>
        <?php endif; ?>
    </div>

</body>

</html>