<?php
require_once 'admin_check.php';
require_once '../config/Database.php';

$pdo = Database::getInstance()->getConnection();

// Handle Settle Action
if (isset($_POST['settle_id'])) {
    $subId = $_POST['settle_id'];
    $amount = $_POST['settle_amount'];

    // Update: Add to paid_amount, reduce due_amount to 0
    $stmt = $pdo->prepare("UPDATE subscriptions SET paid_amount = paid_amount + ?, due_amount = 0, notes = CONCAT(notes, ' [Settled Due]') WHERE id = ?");
    $stmt->execute([$amount, $subId]);
    $success = "Payment settled!";
}

// Fetch only people with Due Amount > 0
$sql = "
    SELECT s.id, s.final_amount, s.paid_amount, s.due_amount, u.name, u.phone, u.email
    FROM subscriptions s
    JOIN users u ON s.user_id = u.id
    WHERE s.due_amount > 0
    ORDER BY s.due_amount DESC
";
$dues = $pdo->query($sql)->fetchAll();

$breadcrump = "Pending Payments";
$headerTitle = "Outstanding Dues";
?>

<!DOCTYPE html>
<html lang="en" class="dark">

<head>
    <meta charset="UTF-8">
    <title>Outstanding Dues | Admin</title>
    <? require_once 'includes/common.php' ?>

</head>

<body class="p-4 md:p-8 max-w-4xl mx-auto">
    <? require_once 'includes/loader.php' ?>
    <? require_once 'includes/breadcrump.php' ?>

    <?php if (isset($success)): ?>
        <div class="bg-emerald-900/30 text-emerald-400 p-4 rounded mb-6"><?= $success ?></div>
    <?php endif; ?>

    <div class="bg-surface border border-zinc-900 rounded-xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-zinc-900 text-zinc-500 uppercase text-xs">
                    <tr>
                        <th class="px-6 py-4">Student</th>
                        <th class="px-6 py-4">Total Fee</th>
                        <th class="px-6 py-4">Paid So Far</th>
                        <th class="px-6 py-4 text-red-400">Due Amount</th>
                        <th class="px-6 py-4 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-800">
                    <?php foreach ($dues as $row): ?>
                        <tr>
                            <td class="px-6 py-4">
                                <div class="font-bold text-white"><?= htmlspecialchars($row['name']) ?></div>
                                <div class="text-xs text-zinc-500"><?= $row['phone'] ?></div>
                            </td>
                            <td class="px-6 py-4 font-mono">₹<?= number_format($row['final_amount']) ?></td>
                            <td class="px-6 py-4 font-mono text-emerald-600">₹<?= number_format($row['paid_amount']) ?></td>
                            <td class="px-6 py-4 font-mono text-red-500 font-bold text-lg">₹<?= number_format($row['due_amount']) ?></td>
                            <td class="px-6 py-4 text-right flex justify-end gap-2">
                                <?php
                                $msg = "Hello {$row['name']}, this is a reminder from StudySpace. You have a pending due of ₹{$row['due_amount']}. Please pay at the desk to avoid entry restrictions.";
                                $waLink = "https://wa.me/91{$row['phone']}?text=" . urlencode($msg);
                                ?>
                                <a href="<?= $waLink ?>" target="_blank" class="bg-green-600 hover:bg-green-500 text-white p-2 rounded-lg transition" title="Send Reminder">
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z" />
                                    </svg>
                                </a>

                                <form method="POST" class="inline-flex">
                                    <input type="hidden" name="settle_id" value="<?= $row['id'] ?>">
                                    <input type="hidden" name="settle_amount" value="<?= $row['due_amount'] ?>">
                                    <button type="submit" class="bg-zinc-800 hover:bg-white hover:text-black text-white p-2 rounded-lg transition" title="Mark Paid">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                        </svg>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if (empty($dues)): ?>
            <div class="p-12 text-center text-zinc-500">
                <p class="text-xl">No outstanding dues.</p>
                <p class="text-sm mt-2">All accounts are settled.</p>
            </div>
        <?php endif; ?>
    </div>

</body>

</html>