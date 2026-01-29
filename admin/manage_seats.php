<?php
require_once 'admin_check.php';
require_once '../config/Database.php';

$pdo = Database::getInstance()->getConnection();

// Handle Form Submission (Add Seat)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_seat'])) {
    $label = $_POST['label'];
    $type = $_POST['type'];

    $stmt = $pdo->prepare("INSERT INTO seats (label, type, status) VALUES (?, ?, 1)");
    $stmt->execute([$label, $type]);
    header("Location: manage_seats.php"); // Refresh
    exit;
}

// Handle Status Toggle (Maintenance Mode)
if (isset($_GET['toggle_id'])) {
    $id = $_GET['toggle_id'];
    // Flip status: If 1 make 0, If 0 make 1
    $stmt = $pdo->prepare("UPDATE seats SET status = NOT status WHERE id = ?");
    $stmt->execute([$id]);
    header("Location: manage_seats.php");
    exit;
}

// Fetch All Seats
$seats = $pdo->query("SELECT * FROM seats ORDER BY label ASC")->fetchAll();

$breadcrump = "Assets";
$headerTitle = "Seats Inventory";
?>

<!DOCTYPE html>
<html lang="en" class="dark">

<head>
    <meta charset="UTF-8">
    <title>Manage Seats | Admin</title>
    <? require_once 'includes/common.php' ?>

</head>

<body class="p-8 max-w-5xl mx-auto">
    <? require_once 'includes/loader.php' ?>
    <? require_once 'includes/breadcrump.php' ?>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-8">

        <div class="md:col-span-1 bg-surface p-6 rounded-xl border border-zinc-900 h-fit">
            <h2 class="text-lg font-bold mb-4 text-accent">Add New Unit</h2>
            <form method="POST" class="space-y-4">
                <div>
                    <label class="block text-xs uppercase text-zinc-500 font-bold mb-1">Seat Label</label>
                    <input type="text" name="label" placeholder="e.g. C1" required
                        class="w-full bg-black border border-zinc-800 p-2 rounded text-white focus:border-accent outline-none">
                </div>
                <div>
                    <label class="block text-xs uppercase text-zinc-500 font-bold mb-1">Type</label>
                    <select name="type" class="w-full bg-black border border-zinc-800 p-2 rounded text-white outline-none">
                        <option value="Standard">Standard</option>
                        <option value="Window">Window (Premium)</option>
                        <option value="Premium">AC / Premium</option>
                    </select>
                </div>
                <button type="submit" name="add_seat" class="w-full bg-white text-black font-bold py-2 rounded hover:bg-zinc-200">
                    Create Asset
                </button>
            </form>
        </div>

        <div class="md:col-span-2">
            <div class="grid grid-cols-3 sm:grid-cols-4 gap-4">
                <?php foreach ($seats as $seat):
                    $isAvailable = $seat['status'] == 1;
                ?>
                    <div class="bg-surface border border-zinc-800 p-4 rounded-lg flex flex-col items-center relative group">
                        <span class="text-xs text-zinc-500 uppercase"><?= $seat['type'] ?></span>
                        <span class="text-2xl font-mono font-bold mt-1 <?= !$isAvailable ? 'line-through text-zinc-600' : 'text-white' ?>">
                            <?= $seat['label'] ?>
                        </span>

                        <?php if (!$isAvailable): ?>
                            <span class="text-[10px] text-red-500 font-bold uppercase mt-1">Maintenance</span>
                        <?php endif; ?>

                        <a href="?toggle_id=<?= $seat['id'] ?>"
                            class="absolute inset-0 bg-black/80 flex items-center justify-center opacity-0 group-hover:opacity-100 transition rounded-lg text-xs font-bold border border-zinc-700 hover:border-white">
                            <?= $isAvailable ? 'Mark Broken' : 'Mark Fixed' ?>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

    </div>

</body>

</html>