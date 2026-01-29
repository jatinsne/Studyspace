<?php
require_once 'admin_check.php';
require_once '../config/Database.php';

$pdo = Database::getInstance()->getConnection();
$search = $_GET['search'] ?? '';
$filter = $_GET['filter'] ?? 'all';

// 1. BUILD QUERY
$sql = "SELECT * FROM users WHERE role = 'student'";

// Add Search
if ($search) {
    $sql .= " AND (name LIKE :search OR phone LIKE :search OR aadhaar_number LIKE :search)";
}

// Add Filter (Verification Status)
if ($filter !== 'all') {
    $sql .= " AND verification_status = :filter";
}

$sql .= " ORDER BY created_at DESC";

$stmt = $pdo->prepare($sql);

if ($search) $stmt->bindValue(':search', "%$search%");
if ($filter !== 'all') $stmt->bindValue(':filter', $filter);

$stmt->execute();
$users = $stmt->fetchAll();

// Count Pending Verifications
$pendingCount = $pdo->query("SELECT COUNT(*) FROM users WHERE verification_status = 'pending'")->fetchColumn();

$breadcrump = "Students";
$headerTitle = "Student Directory";
?>

<!DOCTYPE html>
<html lang="en" class="dark">

<head>
    <title>Student Directory | Admin</title>
    <? require_once 'includes/common.php' ?>

</head>

<body class="p-4 md:p-8 max-w-6xl mx-auto">
    <? require_once 'includes/loader.php' ?>
    <? require_once 'includes/breadcrump.php' ?>

    <div class=" mb-8 gap-4">
        <?php if ($pendingCount > 0): ?>
            <a href="?filter=pending" class="bg-yellow-900/20 border border-yellow-700/50 text-yellow-500 px-4 py-2 rounded-lg text-sm font-bold flex items-center gap-2 animate-pulse w-100">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                </svg>
                <?= $pendingCount ?> Pending Verification
            </a>
        <?php endif; ?>
    </div>

    <div class="bg-surface border border-zinc-900 p-4 rounded-xl mb-6 flex flex-col md:flex-row gap-4">

        <form class="flex-1 relative">
            <span class="absolute left-3 top-3 text-zinc-500">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                </svg>
            </span>
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search by Name, Phone or Aadhaar..."
                class="w-full bg-black border border-zinc-800 pl-10 p-3 rounded-lg text-white focus:border-accent outline-none">
        </form>

        <form>
            <select name="filter" onchange="this.form.submit()" class="bg-black border border-zinc-800 p-3 rounded-lg text-white focus:border-accent outline-none cursor-pointer">
                <option value="all" <?= $filter == 'all' ? 'selected' : '' ?>>All Students</option>
                <option value="verified" <?= $filter == 'verified' ? 'selected' : '' ?>>Verified Only</option>
                <option value="pending" <?= $filter == 'pending' ? 'selected' : '' ?>>Pending Verification</option>
                <option value="rejected" <?= $filter == 'rejected' ? 'selected' : '' ?>>Rejected</option>
            </select>
        </form>

        <a href="index.php" class="bg-zinc-800 hover:bg-zinc-700 text-white px-6 py-3 rounded-lg font-bold transition flex items-center gap-2">
            Dashboard
        </a>
        <a href="add_user.php" class="bg-accent text-black hover:bg-yellow-400 px-6 py-3 rounded-lg font-bold transition flex items-center gap-2 shadow-lg shadow-accent/10">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path>
            </svg>
            Add Student
        </a>
    </div>

    <div class="bg-surface border border-zinc-900 rounded-xl overflow-hidden shadow-2xl">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-zinc-900/50 text-zinc-500 uppercase text-xs tracking-wider">
                    <tr>
                        <th class="px-6 py-4">Identity</th>
                        <th class="px-6 py-4">Contact</th>
                        <th class="px-6 py-4">Aadhaar / ID</th>
                        <th class="px-6 py-4 text-center">Verification</th>
                        <th class="px-6 py-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-800">
                    <?php foreach ($users as $u):
                        // Avatar Logic
                        $avatar = $u['profile_image'] ? "../uploads/" . $u['profile_image'] : "https://ui-avatars.com/api/?name=" . urlencode($u['name']) . "&background=18181b&color=71717a";

                        // Status Colors
                        $statusColors = [
                            'verified' => 'text-emerald-400 bg-emerald-900/20 border-emerald-900/50',
                            'pending' => 'text-yellow-400 bg-yellow-900/20 border-yellow-900/50',
                            'rejected' => 'text-red-400 bg-red-900/20 border-red-900/50'
                        ];
                        $statusClass = $statusColors[$u['verification_status']] ?? 'text-zinc-500 bg-zinc-800';
                    ?>
                        <tr class="hover:bg-zinc-900/30 transition group">

                            <td class="px-6 py-4">
                                <div class="flex items-center gap-4">
                                    <img src="<?= $avatar ?>" class="w-10 h-10 rounded-full object-cover border border-zinc-800 group-hover:border-accent transition">
                                    <div>
                                        <p class="font-bold text-white"><?= htmlspecialchars($u['name']) ?></p>
                                        <p class="text-[10px] text-zinc-500 uppercase tracking-wider">Since <?= date('M Y', strtotime($u['created_at'])) ?></p>
                                    </div>
                                </div>
                            </td>

                            <td class="px-6 py-4">
                                <p class="text-zinc-300 font-mono"><?= $u['phone'] ?></p>
                                <p class="text-zinc-500 text-xs"><?= $u['email'] ?: '-' ?></p>
                            </td>

                            <td class="px-6 py-4">
                                <?php if ($u['aadhaar_number']): ?>
                                    <div class="flex items-center gap-2">
                                        <span class="font-mono text-zinc-300 tracking-wider"><?= $u['aadhaar_number'] ?></span>
                                        <?php if ($u['doc_proof']): ?>
                                            <a href="../uploads/<?= $u['doc_proof'] ?>" target="_blank" class="text-zinc-500 hover:text-accent" title="View Document">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                                </svg>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <span class="text-zinc-600 text-xs italic">Not provided</span>
                                <?php endif; ?>
                            </td>

                            <td class="px-6 py-4 text-center">
                                <span class="px-3 py-1 rounded-full text-[10px] font-bold uppercase border <?= $statusClass ?>">
                                    <?= $u['verification_status'] ?>
                                </span>
                            </td>

                            <td class="px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="user_details.php?id=<?= $u['id'] ?>" class="p-2 text-zinc-400 hover:text-white hover:bg-zinc-800 rounded-lg transition" title="View History">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                        </svg>
                                    </a>

                                    <a href="edit_user.php?id=<?= $u['id'] ?>" class="p-2 text-zinc-400 hover:text-accent hover:bg-zinc-800 rounded-lg transition" title="Edit Profile">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                        </svg>
                                    </a>
                                </div>
                            </td>

                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php if (empty($users)): ?>
                <div class="text-center py-12">
                    <p class="text-zinc-500">No students found.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

</body>

</html>