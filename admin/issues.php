<?php
require_once 'admin_check.php';
require_once '../config/Database.php';

$pdo = Database::getInstance()->getConnection();

// Handle "Mark Resolved"
if (isset($_POST['resolve_id'])) {
    $stmt = $pdo->prepare("UPDATE issues SET status = 'resolved' WHERE id = ?");
    $stmt->execute([$_POST['resolve_id']]);
}

// Fetch Open Issues
$issues = $pdo->query("
    SELECT i.*, u.name, u.phone 
    FROM issues i 
    JOIN users u ON i.user_id = u.id 
    WHERE i.status = 'open' 
    ORDER BY i.created_at DESC
")->fetchAll();

$breadcrump = "Issues";
$headerTitle = "Students Complaints";
?>

<!DOCTYPE html>
<html lang="en" class="dark">

<head>
    <title>Helpdesk Issues | Admin</title>
    <? require_once 'includes/common.php' ?>

</head>

<body class="p-8 max-w-5xl mx-auto bg-black text-white">
    <? require_once 'includes/loader.php' ?>
    <? require_once 'includes/breadcrump.php' ?>

    <div class="grid gap-4">
        <?php foreach ($issues as $row): ?>
            <div class="bg-surface border border-red-900/30 p-6 rounded-xl flex justify-between items-start">
                <div>
                    <span class="bg-red-900/20 text-red-400 text-xs px-2 py-1 rounded uppercase font-bold"><?= $row['type'] ?></span>
                    <p class="mt-2 text-white text-lg"><?= htmlspecialchars($row['description']) ?></p>
                    <div class="mt-4 flex gap-4 text-xs text-zinc-500">
                        <p>By: <span class="text-zinc-300"><?= htmlspecialchars($row['name']) ?></span> (<?= $row['phone'] ?>)</p>
                        <p><?= date('M d, H:i', strtotime($row['created_at'])) ?></p>
                    </div>
                </div>

                <form method="POST">
                    <input type="hidden" name="resolve_id" value="<?= $row['id'] ?>">
                    <button type="submit" class="border border-zinc-700 text-zinc-400 hover:text-white hover:bg-zinc-800 px-4 py-2 rounded text-xs transition">
                        Mark Resolved
                    </button>
                </form>
            </div>
        <?php endforeach; ?>

        <?php if (empty($issues)): ?>
            <div class="p-10 text-center text-zinc-500 border border-zinc-900 rounded-xl">
                No open issues. Peace and quiet.
            </div>
        <?php endif; ?>
    </div>

</body>

</html>