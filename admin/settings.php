<?php
require_once 'admin_check.php';
require_once '../config/Database.php';

$pdo = Database::getInstance()->getConnection();
$success = "";

// 1. Handle Update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['shift_id'];
    $name = $_POST['name'];
    $startTime = $_POST['start_time'];
    $endTime = $_POST['end_time'];
    $price = $_POST['monthly_price'];

    $stmt = $pdo->prepare("UPDATE shifts SET name=?, start_time=?, end_time=?, monthly_price=? WHERE id=?");
    $stmt->execute([$name, $startTime, $endTime, $price, $id]);
    $success = "Shift '$name' updated successfully!";
}

// 2. Fetch Shifts
$shifts = $pdo->query("SELECT * FROM shifts ORDER BY start_time ASC")->fetchAll();

$breadcrump = "Operational Hours and Subscription Costs";
$headerTitle = "Shift & Pricing Config";
?>

<!DOCTYPE html>
<html lang="en" class="dark">

<head>
    <title>System Settings | Admin</title>
    <? require_once 'includes/common.php' ?>

</head>

<body class="p-8 max-w-5xl mx-auto bg-black text-white">
    <? require_once 'includes/loader.php' ?>
    <? require_once 'includes/breadcrump.php' ?>

    <?php if ($success): ?>
        <div class="bg-emerald-900/30 text-emerald-400 p-4 rounded mb-6 border border-emerald-900"><?= $success ?></div>
    <?php endif; ?>

    <div class="grid gap-6">
        <?php foreach ($shifts as $shift): ?>
            <form method="POST" class="bg-surface border border-zinc-900 p-6 rounded-xl flex flex-col md:flex-row gap-4 items-end">
                <input type="hidden" name="shift_id" value="<?= $shift['id'] ?>">

                <div class="flex-1 w-full">
                    <label class="text-[10px] uppercase text-zinc-500 font-bold mb-1 block">Shift Name</label>
                    <input type="text" name="name" value="<?= htmlspecialchars($shift['name']) ?>"
                        class="w-full bg-black border border-zinc-800 p-3 rounded text-white focus:border-accent outline-none">
                </div>

                <div class="w-full md:w-32">
                    <label class="text-[10px] uppercase text-zinc-500 font-bold mb-1 block">Start</label>
                    <input type="time" name="start_time" value="<?= $shift['start_time'] ?>"
                        class="w-full bg-black border border-zinc-800 p-3 rounded text-white focus:border-accent outline-none font-mono">
                </div>

                <div class="w-full md:w-32">
                    <label class="text-[10px] uppercase text-zinc-500 font-bold mb-1 block">End</label>
                    <input type="time" name="end_time" value="<?= $shift['end_time'] ?>"
                        class="w-full bg-black border border-zinc-800 p-3 rounded text-white focus:border-accent outline-none font-mono">
                </div>

                <div class="w-full md:w-40">
                    <label class="text-[10px] uppercase text-emerald-500 font-bold mb-1 block">Monthly Price (₹)</label>
                    <input type="number" name="monthly_price" value="<?= $shift['monthly_price'] ?>"
                        class="w-full bg-black border border-emerald-900 p-3 rounded text-emerald-400 font-bold focus:border-emerald-500 outline-none font-mono">
                </div>

                <button type="submit" class="w-full md:w-auto bg-white text-black font-bold px-6 py-3 rounded hover:bg-zinc-200 transition">
                    Save
                </button>
            </form>
        <?php endforeach; ?>
    </div>

    <div class="mt-8 p-4 bg-zinc-900/50 rounded-lg border border-zinc-800 text-zinc-500 text-xs">
        <p class="font-bold uppercase mb-1">Note on Updates:</p>
        <ul class="list-disc pl-4 space-y-1">
            <li>Price changes only apply to <strong>new bookings</strong>. Existing subscriptions remain unchanged.</li>
            <li>Time changes affect the "Overlap Check" logic immediately. Be careful changing times if you have conflicting active bookings.</li>
        </ul>
    </div>

</body>

</html>