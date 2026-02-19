<?php
// 1. INITIALIZE & LOGIC (Before HTML Output)
require_once '../config/Database.php';
require_once 'admin_check.php'; // Ensures valid admin session

$pdo = Database::getInstance()->getConnection();

// ---------------------------------------------------------
// ACTION 1: QUEUE ALL ACTIVE USERS
// ---------------------------------------------------------
if (isset($_POST['queue_all'])) {
    try {
        $pdo->beginTransaction();

        // Fetch valid users
        $sql = "SELECT id, name, biometric_id, card_id, subscription_startdate, subscription_enddate 
                FROM users 
                WHERE biometric_enable = 1 
                AND biometric_id IS NOT NULL 
                AND biometric_id != ''
                AND subscription_validation_check = 1 
                AND subscription_enddate >= CURDATE()";

        $users = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        $count = 0;

        // Note: Changed command from 'ADD_USER' to 'SYNC_USER' 
        $insertSql = "INSERT INTO biometric_jobs (biometric_id, command, payload, status, created_at) VALUES (?, 'SYNC_USER', ?, 'pending', NOW())";
        $stmt = $pdo->prepare($insertSql);

        foreach ($users as $u) {
            // Simplified Payload mapped to your Node.js PUT route
            $payloadData = [
                "name" => substr($u['name'], 0, 24),
                "card" => $u['card_id'],
                "enabled" => true,
                "validity_enabled" => true,
                "valid_start" => $u['subscription_startdate'],
                "valid_end" => $u['subscription_enddate']
            ];

            // Execute using the user's specific biometric_id
            $stmt->execute([$u['biometric_id'], json_encode($payloadData)]);
            $count++;
        }

        $pdo->commit();
        header("Location: sync_console.php?msg=queued&count=$count");
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        $errorMsg = "Failed to queue: " . $e->getMessage();
    }
}

// ---------------------------------------------------------
// ACTION 2: CLEAR HISTORY
// ---------------------------------------------------------
if (isset($_POST['clear_logs'])) {
    try {
        $pdo->exec("DELETE FROM biometric_jobs WHERE status IN ('completed', 'failed')");
        header("Location: sync_console.php?msg=cleared");
        exit;
    } catch (Exception $e) {
        $errorMsg = "Error clearing logs: " . $e->getMessage();
    }
}

// ---------------------------------------------------------
// DATA FETCHING
// ---------------------------------------------------------
$stats = $pdo->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
    FROM biometric_jobs
")->fetch(PDO::FETCH_ASSOC);

$jobs = $pdo->query("SELECT * FROM biometric_jobs ORDER BY field(status, 'processing', 'pending', 'failed', 'completed'), created_at DESC LIMIT 50")->fetchAll();

// ---------------------------------------------------------
// START HTML OUTPUT
// ---------------------------------------------------------
require_once 'includes/header.php';
?>

<div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
    <div>
        <h1 class="text-3xl font-bold text-white">Biometric Sync Console</h1>
        <p class="text-zinc-500 text-sm">Monitor and control device synchronization.</p>
    </div>

    <div class="flex gap-2">
        <form method="POST" onsubmit="return confirm('Delete all Completed and Failed logs?');">
            <button type="submit" name="clear_logs" class="bg-zinc-900 hover:bg-zinc-800 text-zinc-400 px-4 py-2 rounded-lg text-sm font-bold border border-zinc-800 transition hover:text-white">
                Clear History
            </button>
        </form>
        <a href="settings.php" class="bg-zinc-900 hover:bg-zinc-800 text-white px-4 py-2 rounded-lg text-sm font-bold border border-zinc-800 transition">
            Settings
        </a>
    </div>
</div>

<?php if (isset($_GET['msg'])): ?>
    <?php if ($_GET['msg'] == 'queued'): ?>
        <div class="bg-emerald-900/30 text-emerald-400 p-4 rounded-xl mb-6 border border-emerald-900/50 flex items-center gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
            </svg>
            <span>Successfully queued <strong><?= htmlspecialchars($_GET['count'] ?? 0) ?></strong> users. Proceed to Step 2.</span>
        </div>
    <?php elseif ($_GET['msg'] == 'cleared'): ?>
        <div class="bg-blue-900/30 text-blue-400 p-4 rounded-xl mb-6 border border-blue-900/50 flex items-center gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
            </svg>
            <span>History logs cleared.</span>
        </div>
    <?php endif; ?>
<?php endif; ?>

<?php if (isset($errorMsg)): ?>
    <div class="bg-red-900/30 text-red-400 p-4 rounded-xl mb-6 border border-red-900/50 flex items-center gap-2">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
        </svg>
        <span><?= $errorMsg ?></span>
    </div>
<?php endif; ?>

<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
    <div class="bg-surface border border-zinc-900 p-6 rounded-xl relative overflow-hidden">
        <p class="text-xs uppercase text-zinc-500 font-bold tracking-wider">Queue Pending</p>
        <p class="text-3xl font-mono text-yellow-500 font-bold mt-2"><?= $stats['pending'] ?? 0 ?></p>
        <div class="absolute right-0 top-0 p-4 opacity-10"><svg class="w-12 h-12 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg></div>
    </div>
    <div class="bg-surface border border-zinc-900 p-6 rounded-xl relative overflow-hidden">
        <p class="text-xs uppercase text-zinc-500 font-bold tracking-wider">Processing</p>
        <p class="text-3xl font-mono text-blue-400 font-bold mt-2"><?= $stats['processing'] ?? 0 ?></p>
        <div class="absolute right-0 top-0 p-4 opacity-10"><svg class="w-12 h-12 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
            </svg></div>
    </div>
    <div class="bg-surface border border-zinc-900 p-6 rounded-xl relative overflow-hidden">
        <p class="text-xs uppercase text-zinc-500 font-bold tracking-wider">Completed</p>
        <p class="text-3xl font-mono text-emerald-500 font-bold mt-2"><?= $stats['completed'] ?? 0 ?></p>
        <div class="absolute right-0 top-0 p-4 opacity-10"><svg class="w-12 h-12 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg></div>
    </div>
    <div class="bg-surface border border-zinc-900 p-6 rounded-xl relative overflow-hidden">
        <p class="text-xs uppercase text-zinc-500 font-bold tracking-wider">Failed</p>
        <p class="text-3xl font-mono text-red-500 font-bold mt-2"><?= $stats['failed'] ?? 0 ?></p>
        <div class="absolute right-0 top-0 p-4 opacity-10"><svg class="w-12 h-12 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
            </svg></div>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">

    <div class="bg-zinc-900/30 border border-zinc-800 p-6 rounded-2xl flex flex-col justify-between hover:border-zinc-700 transition">
        <div>
            <span class="text-xs font-bold text-zinc-500 uppercase tracking-widest border border-zinc-700 px-2 py-1 rounded">Step 1</span>
            <h3 class="text-lg font-bold text-white mt-3">Prepare Queue</h3>
            <p class="text-zinc-500 text-xs mt-2 leading-relaxed">Fetch active users from DB and load them into the sync queue. Status: <span class="text-yellow-500">Pending</span>.</p>
        </div>
        <form method="POST" class="mt-4">
            <button type="submit" name="queue_all" class="w-full bg-zinc-800 hover:bg-zinc-700 text-white font-bold px-4 py-3 rounded-xl transition text-sm flex items-center justify-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16m-7 6h7"></path>
                </svg>
                Queue All Users
            </button>
        </form>
    </div>

    <div class="bg-zinc-900/30 border border-zinc-800 p-6 rounded-2xl flex flex-col justify-between hover:border-zinc-700 transition">
        <div>
            <span class="text-xs font-bold text-blue-500 uppercase tracking-widest border border-blue-900/50 px-2 py-1 rounded">Step 2</span>
            <h3 class="text-lg font-bold text-white mt-3">Push to Device</h3>
            <p class="text-zinc-500 text-xs mt-2 leading-relaxed">Send all 'Pending' jobs to the device API. Status becomes: <span class="text-blue-400">Processing</span>.</p>
        </div>
        <div class="mt-4">
            <button onclick="startBatch('push')" id="btnPush" class="w-full bg-blue-600 hover:bg-blue-500 text-white font-bold px-4 py-3 rounded-xl transition text-sm flex items-center justify-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                </svg>
                Push Pending
            </button>
        </div>
    </div>

    <div class="bg-zinc-900/30 border border-zinc-800 p-6 rounded-2xl flex flex-col justify-between hover:border-zinc-700 transition">
        <div>
            <span class="text-xs font-bold text-emerald-500 uppercase tracking-widest border border-emerald-900/50 px-2 py-1 rounded">Step 3</span>
            <h3 class="text-lg font-bold text-white mt-3">Verify Results</h3>
            <p class="text-zinc-500 text-xs mt-2 leading-relaxed">Check device status for 'Processing' jobs. Status becomes: <span class="text-emerald-500">Completed</span>.</p>
        </div>
        <div class="mt-4">
            <button onclick="startBatch('verify')" id="btnVerify" class="w-full bg-emerald-600 hover:bg-emerald-500 text-white font-bold px-4 py-3 rounded-xl transition text-sm flex items-center justify-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                Verify Results
            </button>
        </div>
    </div>
</div>

<div id="progressArea" class="hidden mb-8 bg-zinc-900 p-4 rounded-xl border border-zinc-800 shadow-inner">
    <div class="flex justify-between text-[10px] uppercase text-zinc-400 font-bold mb-2">
        <span id="progressText">Processing...</span>
        <span id="progressPercent" class="text-white">0%</span>
    </div>
    <div class="w-full bg-zinc-800 rounded-full h-3 overflow-hidden">
        <div id="progressBar" class="bg-blue-500 h-3 rounded-full transition-all duration-300" style="width: 0%"></div>
    </div>
</div>

<div class="flex items-center justify-between mb-4">
    <h3 class="text-lg font-bold text-white flex items-center gap-2">
        <span class="w-2 h-2 bg-emerald-500 rounded-full animate-pulse"></span>
        Live Job Queue
    </h3>
    <button onclick="window.location.reload()" class="text-xs text-zinc-400 hover:text-white flex items-center gap-1 bg-zinc-900 px-3 py-1.5 rounded-lg border border-zinc-800 transition">
        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
        </svg>
        Refresh List
    </button>
</div>

<div class="bg-surface border border-zinc-900 rounded-xl overflow-hidden shadow-xl mb-12">
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="bg-zinc-900/80 text-zinc-500 uppercase text-xs font-bold border-b border-zinc-800">
                <tr>
                    <th class="px-6 py-4">Job ID</th>
                    <th class="px-6 py-4">Cmd ID</th>
                    <th class="px-6 py-4">User</th>
                    <th class="px-6 py-4">Type</th>
                    <th class="px-6 py-4">Status</th>
                    <th class="px-6 py-4">Response Snippet</th>
                    <th class="px-6 py-4 text-right">Time</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-800">
                <?php foreach ($jobs as $job):
                    $statusColor = 'text-zinc-500 bg-zinc-800';
                    if ($job['status'] == 'pending') $statusColor = 'text-yellow-500 bg-yellow-500/10 border border-yellow-500/20';
                    if ($job['status'] == 'processing') $statusColor = 'text-blue-400 bg-blue-500/10 border border-blue-500/20 animate-pulse';
                    if ($job['status'] == 'completed') $statusColor = 'text-emerald-400 bg-emerald-500/10 border border-emerald-500/20';
                    if ($job['status'] == 'failed') $statusColor = 'text-red-400 bg-red-500/10 border border-red-500/20';

                    $payload = json_decode($job['payload'], true);
                    $userName = $payload['params']['Name'] ?? $payload['name'] ?? 'Unknown';

                    $cmdIdDisplay = $job['device_command_id']
                        ? '<span class="text-zinc-300 font-mono">' . substr($job['device_command_id'], -6) . '</span>'
                        : '<span class="text-zinc-700">-</span>';
                ?>
                    <tr class="hover:bg-zinc-900/50 transition">
                        <td class="px-6 py-4 font-mono text-zinc-500 text-xs">#<?= $job['job_id'] ?></td>
                        <td class="px-6 py-4 text-xs"><?= $cmdIdDisplay ?></td>
                        <td class="px-6 py-4">
                            <div class="font-bold text-white"><?= htmlspecialchars($userName) ?></div>
                            <div class="text-[10px] text-zinc-600 font-mono tracking-wide">ID: <?= $job['biometric_id'] ?></div>
                        </td>
                        <td class="px-6 py-4">
                            <span class="font-mono text-[10px] font-bold text-zinc-400 border border-zinc-800 px-1.5 py-0.5 rounded bg-zinc-900"><?= $job['command'] ?></span>
                        </td>
                        <td class="px-6 py-4">
                            <span class="px-2.5 py-1 rounded text-[10px] uppercase font-bold tracking-wider <?= $statusColor ?>">
                                <?= $job['status'] ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 max-w-xs truncate text-zinc-500 text-xs font-mono" title="<?= htmlspecialchars($job['device_response']) ?>">
                            <?= htmlspecialchars(substr($job['device_response'] ?: '-', 0, 50)) ?>
                        </td>
                        <td class="px-6 py-4 text-right text-zinc-600 text-[10px]">
                            <?= date('M d, H:i:s', strtotime($job['created_at'])) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($jobs)): ?>
                    <tr>
                        <td colspan="7" class="p-12 text-center text-zinc-600 text-sm">No jobs found in the queue.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    let isRunning = false;

    function startBatch(type) {
        if (isRunning) return;
        isRunning = true;

        // UI Setup
        const area = document.getElementById('progressArea');
        const bar = document.getElementById('progressBar');
        const txt = document.getElementById('progressText');
        const pct = document.getElementById('progressPercent');

        area.classList.remove('hidden');
        bar.style.width = '5%';

        // Determine Script URL & Label
        const scriptUrl = (type === 'push') ? 'ajax_push_batch.php' : 'ajax_verify_batch.php';
        const actionLabel = (type === 'push') ? 'Pushing to Device' : 'Verifying Results';

        // Lock Buttons
        document.getElementById('btnPush').disabled = true;
        document.getElementById('btnVerify').disabled = true;
        document.getElementById('btnPush').classList.add('opacity-50', 'cursor-not-allowed');
        document.getElementById('btnVerify').classList.add('opacity-50', 'cursor-not-allowed');

        function runBatch(prevProcessed) {
            fetch(scriptUrl)
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'done' || (data.remaining === 0 && data.processed === 0)) {
                        // FINISHED
                        bar.style.width = '100%';
                        bar.classList.replace('bg-blue-500', 'bg-emerald-500');
                        txt.innerText = `${actionLabel} Complete!`;
                        txt.classList.add('text-emerald-400');
                        pct.innerText = "100%";

                        setTimeout(() => window.location.reload(), 1500);
                    } else {
                        // CONTINUE RECURSION
                        const totalEst = prevProcessed + data.remaining + data.processed;
                        const current = prevProcessed + data.processed;
                        let percent = totalEst > 0 ? Math.round((current / totalEst) * 100) : 5;
                        if (percent > 95) percent = 95;

                        bar.style.width = `${percent}%`;
                        txt.innerText = `${actionLabel}... (${current} items processed)`;
                        pct.innerText = `${percent}%`;

                        runBatch(current);
                    }
                })
                .catch(err => {
                    console.error(err);
                    txt.innerText = "Connection Error. Check Console.";
                    txt.classList.add('text-red-500');
                    bar.classList.replace('bg-blue-500', 'bg-red-500');
                    isRunning = false;
                });
        }

        // Start recursion
        runBatch(0);
    }
</script>

<?php require_once 'includes/footer.php'; ?>