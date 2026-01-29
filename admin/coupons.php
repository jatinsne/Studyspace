<?php
require_once 'admin_check.php';
require_once '../config/Database.php';

$pdo = Database::getInstance()->getConnection();
$message = "";

// Handle Add
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = strtoupper($_POST['code']);
    $label = $_POST['label'];
    $type = $_POST['type'];
    $value = $_POST['value'];

    $stmt = $pdo->prepare("INSERT INTO coupons (code, label, type, value) VALUES (?, ?, ?, ?)");
    $stmt->execute([$code, $label, $type, $value]);
    $message = "Coupon $code Created!";
}

// 2. Handle Toggle Status (Activate/Deactivate)
if (isset($_GET['toggle'])) {
    $id = $_GET['toggle'];
    // This SQL flips the status: If 1->0, If 0->1
    $pdo->prepare("UPDATE coupons SET status = !status WHERE id = ?")->execute([$id]);

    header("Location: coupons.php");
    exit;
}

// Fetch All
$coupons = $pdo->query("SELECT * FROM coupons ORDER BY id DESC")->fetchAll();

$breadcrump = "Coupons";
$headerTitle = "Discount Codes";
?>

<!DOCTYPE html>
<html lang="en" class="dark">

<head>
    <title>Manage Coupons | Admin</title>
    <? require_once 'includes/common.php' ?>

</head>

<body class="p-8 max-w-5xl mx-auto bg-black text-white">
    <? require_once 'includes/loader.php' ?>
    <? require_once 'includes/breadcrump.php' ?>
    

    <?php if ($message): ?><div class="bg-emerald-900/30 text-emerald-400 p-3 rounded mb-4"><?= $message ?></div><?php endif; ?>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-8">

        <div class="bg-surface border border-zinc-900 p-6 rounded-xl h-fit">
            <h2 class="font-bold mb-4 text-accent">Create New Offer</h2>
            <form method="POST" class="space-y-4">
                <div>
                    <label class="text-xs uppercase text-zinc-500 font-bold">Code (Short)</label>
                    <input type="text" name="code" placeholder="e.g. SIBLING20" required class="w-full bg-black border border-zinc-800 p-2 rounded text-white uppercase">
                </div>
                <div>
                    <label class="text-xs uppercase text-zinc-500 font-bold">Description</label>
                    <input type="text" name="label" placeholder="e.g. Sibling Discount" required class="w-full bg-black border border-zinc-800 p-2 rounded text-white">
                </div>
                <div class="flex gap-4">
                    <div class="w-1/2">
                        <label class="text-xs uppercase text-zinc-500 font-bold">Type</label>
                        <select name="type" class="w-full bg-black border border-zinc-800 p-2 rounded text-white">
                            <option value="fixed">Flat Amount (₹)</option>
                            <option value="percent">Percentage (%)</option>
                        </select>
                    </div>
                    <div class="w-1/2">
                        <label class="text-xs uppercase text-zinc-500 font-bold">Value</label>
                        <input type="number" name="value" placeholder="10 or 500" required class="w-full bg-black border border-zinc-800 p-2 rounded text-white">
                    </div>
                </div>
                <button type="submit" class="w-full bg-white text-black font-bold py-2 rounded hover:bg-zinc-200">Create</button>
            </form>
        </div>

        <div class="md:col-span-2 space-y-4">
            <?php foreach ($coupons as $c):
                // Check if Active
                $isActive = $c['status'] == 1;
                $opacityClass = $isActive ? "opacity-100" : "opacity-40 grayscale";
            ?>
                <div class="bg-zinc-900/50 border border-zinc-800 p-4 rounded-xl flex items-center justify-between group hover:border-zinc-700 transition <?= $opacityClass ?>">

                    <div class="flex items-center gap-4">
                        <div class="h-10 w-10 <?= $isActive ? 'bg-emerald-900/20 text-emerald-500' : 'bg-red-900/20 text-red-500' ?> rounded-lg flex items-center justify-center border border-white/10">
                            <?= $isActive ? '✔' : '✕' ?>
                        </div>
                        <div>
                            <h3 class="font-bold text-white text-lg tracking-wide"><?= $c['code'] ?></h3>
                            <p class="text-xs text-zinc-500">
                                <?= $isActive ? 'Active' : 'Inactive' ?> •
                                <?= $c['type'] == 'fixed' ? '₹' : '' ?><?= intval($c['value']) ?><?= $c['type'] == 'percent' ? '%' : '' ?> Off
                            </p>
                        </div>
                    </div>

                    <?php if ($isActive): ?>
                        <a href="?toggle=<?= $c['id'] ?>" class="text-zinc-600 hover:text-red-500 transition px-3 py-2" title="Deactivate">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"></path>
                            </svg>
                        </a>
                    <?php else: ?>
                        <a href="?toggle=<?= $c['id'] ?>" class="text-zinc-600 hover:text-emerald-500 transition px-3 py-2" title="Reactivate">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                            </svg>
                        </a>
                    <?php endif; ?>

                </div>
            <?php endforeach; ?>
        </div>


    </div>
</body>

</html>