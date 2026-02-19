<?php
// 1. Include Admin Header (Handles Session, DB, and UI Wrapper)
require_once 'includes/header.php';

// 2. Fetch User Data
$userId = $_GET['id'] ?? 0;
// $pdo is already available from header.php

$userStmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$userStmt->execute([$userId]);
$user = $userStmt->fetch();

if (!$user) {
    echo "<div class='p-20 text-center text-zinc-500'>User not found in directory.</div>";
    require_once 'includes/footer.php';
    exit;
}

// 3. Fetch Financial Stats
$statsStmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_bookings, 
        SUM(final_amount) as total_bill, 
        SUM(paid_amount) as total_paid,
        SUM(due_amount) as total_due
    FROM subscriptions 
    WHERE user_id = ? AND payment_status != 'rejected'
");
$statsStmt->execute([$userId]);
$stats = $statsStmt->fetch();

// 4. Fetch Booking History
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

// 5. Fetch Attendance Logs (NEW)
$attStmt = $pdo->prepare("
    SELECT * FROM attendance 
    WHERE user_id = ? 
    ORDER BY date DESC, check_in_time DESC 
    LIMIT 10
");
$attStmt->execute([$userId]);
$attendanceLogs = $attStmt->fetchAll();

// Helper: WhatsApp Link Generator
function generateWaLink($booking, $phone, $name)
{
    $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
    if (strlen($cleanPhone) == 10) $cleanPhone = "91" . $cleanPhone;

    $msg = "*Payment Receipt*";
    $msg .= "Hi $name,";
    $msg .= "Seat: *{$booking['seat_label']}* ({$booking['shift_name']})";
    $msg .= "Valid: " . date('d M', strtotime($booking['start_date'])) . " to " . date('d M Y', strtotime($booking['end_date']));
    $msg .= "----------------";
    $msg .= "Total: ₹{$booking['final_amount']}";
    $msg .= "Paid: ₹{$booking['paid_amount']}";

    if ($booking['due_amount'] > 0) {
        $msg .= "*Balance Due: ₹{$booking['due_amount']}*";
        $msg .= "Please clear dues to avoid access interruptions.";
    } else {
        $msg .= "*Status: Fully Paid*";
    }

    return "https://wa.me/$cleanPhone?text=" . urlencode($msg);
}
?>

<div class="mb-8 flex flex-col md:flex-row items-center justify-between gap-4">
    <div class="flex items-center gap-6 w-full">
        <div class="relative">
            <?php if (!empty($user['profile_image'])): ?>
                <img src="../uploads/<?= htmlspecialchars($user['profile_image']) ?>" class="w-20 h-20 rounded-2xl object-cover border-2 border-zinc-800 shadow-lg">
            <?php else: ?>
                <div class="w-20 h-20 rounded-2xl bg-gradient-to-br from-zinc-800 to-zinc-900 border-2 border-zinc-800 flex items-center justify-center text-2xl font-bold text-zinc-500 shadow-lg">
                    <?= strtoupper(substr($user['name'], 0, 1)) ?>
                </div>
            <?php endif; ?>
            <div class="absolute -bottom-2 -right-2 bg-zinc-900 p-1 rounded-lg border border-zinc-800">
                <?php if ($user['verification_status'] == 'verified'): ?>
                    <svg class="w-5 h-5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                <?php else: ?>
                    <svg class="w-5 h-5 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                <?php endif; ?>
            </div>
        </div>

        <div>
            <h1 class="text-3xl font-bold text-white"><?= htmlspecialchars($user['name']) ?></h1>
            <div class="flex items-center gap-3 text-sm text-zinc-400 mt-1">
                <span class="font-mono"><?= $user['phone'] ?></span>
                <span class="w-1 h-1 bg-zinc-600 rounded-full"></span>
                <span><?= $user['email'] ?></span>
            </div>
        </div>
    </div>

    <div class="flex gap-3 w-full md:w-auto">
        <a href="edit_user.php?id=<?= $user['id'] ?>" class="flex-1 md:flex-none bg-zinc-800 hover:bg-zinc-700 text-white px-5 py-3 rounded-xl text-sm font-bold flex items-center justify-center gap-2 transition border border-zinc-700">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
            </svg>
            Edit
        </a>
        <a href="users.php" class="flex-1 md:flex-none bg-zinc-900 hover:bg-zinc-800 text-zinc-400 hover:text-white px-5 py-3 rounded-xl text-sm font-bold flex items-center justify-center transition border border-zinc-800">
            Back
        </a>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">

    <div class="bg-surface border border-zinc-900 p-6 rounded-2xl relative overflow-hidden group">
        <div class="absolute top-0 right-0 p-4 opacity-10 group-hover:opacity-20 transition">
            <svg class="w-16 h-16 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
        </div>
        <p class="text-xs uppercase text-zinc-500 font-bold tracking-wider mb-2">Lifetime Value</p>
        <p class="text-3xl font-mono text-emerald-400 font-bold">₹<?= number_format($stats['total_paid'] ?? 0) ?></p>
        <p class="text-xs text-zinc-500 mt-2">from <?= $stats['total_bookings'] ?> bookings</p>
    </div>

    <div class="bg-surface border <?= ($stats['total_due'] > 0) ? 'border-red-900/50' : 'border-zinc-900' ?> p-6 rounded-2xl relative overflow-hidden group">
        <div class="absolute top-0 right-0 p-4 opacity-10 group-hover:opacity-20 transition">
            <svg class="w-16 h-16 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
            </svg>
        </div>
        <p class="text-xs uppercase text-zinc-500 font-bold tracking-wider mb-2">Pending Dues</p>
        <p class="text-3xl font-mono font-bold <?= ($stats['total_due'] > 0) ? 'text-red-500' : 'text-zinc-600' ?>">
            ₹<?= number_format($stats['total_due'] ?? 0) ?>
        </p>
        <?php if ($stats['total_due'] > 0): ?>
            <a href="dues.php" class="inline-block mt-2 text-xs font-bold text-red-400 hover:text-white border-b border-red-900 hover:border-white transition">Settle Now →</a>
        <?php else: ?>
            <p class="text-xs text-zinc-500 mt-2">All Clear</p>
        <?php endif; ?>
    </div>

    <div class="bg-surface border border-zinc-900 p-6 rounded-2xl">
        <div class="flex justify-between items-start mb-4">
            <p class="text-xs uppercase text-zinc-500 font-bold tracking-wider">Access Keys</p>
            <span class="text-[10px] <?= $user['biometric_enable'] ? 'bg-green-500/10 text-green-500' : 'bg-red-500/10 text-red-500' ?> px-2 py-0.5 rounded uppercase font-bold">
                <?= $user['biometric_enable'] ? 'Enabled' : 'Disabled' ?>
            </span>
        </div>
        <div class="space-y-3">
            <div class="flex justify-between text-sm">
                <span class="text-zinc-500">Biometric ID</span>
                <span class="font-mono text-white"><?= $user['biometric_id'] ?: '<span class="text-zinc-700">Not Set</span>' ?></span>
            </div>
            <div class="flex justify-between text-sm">
                <span class="text-zinc-500">RFID Card</span>
                <span class="font-mono text-white"><?= $user['card_id'] ?: '<span class="text-zinc-700">Not Set</span>' ?></span>
            </div>
            <div class="border-t border-zinc-800 pt-2 mt-2">
                <p class="text-[10px] text-zinc-500 uppercase mb-1">Master Access Validity</p>
                <?php if ($user['subscription_enddate'] && $user['subscription_enddate'] >= date('Y-m-d')): ?>
                    <p class="text-emerald-400 font-mono font-bold text-sm">
                        Until <?= date('d M Y', strtotime($user['subscription_enddate'])) ?>
                    </p>
                <?php else: ?>
                    <p class="text-red-500 font-mono font-bold text-sm">Expired / Inactive</p>
                <?php endif; ?>
            </div>
        </div>
        <div class="mt-4 pt-3 border-t border-zinc-800 flex justify-between items-center">
            <span class="text-[10px] text-zinc-500 uppercase">Device Sync</span>

            <button onclick="syncToDevice(<?= $user['id'] ?>)" id="syncBtn" class="bg-zinc-800 hover:bg-zinc-700 text-white text-xs font-bold px-3 py-1.5 rounded-lg border border-zinc-700 flex items-center gap-2 transition">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                </svg>
                <span>Sync Now</span>
            </button>
        </div>
        <div id="syncStatus" class="text-[10px] text-right mt-1 hidden"></div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-8">

    <div>
        <h2 class="text-lg font-bold text-white mb-4 flex items-center gap-2">
            <svg class="w-5 h-5 text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            Subscription History
        </h2>

        <div class="bg-surface border border-zinc-900 rounded-xl overflow-hidden shadow-xl">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-zinc-900/80 text-zinc-500 uppercase text-xs font-bold border-b border-zinc-800">
                        <tr>
                            <th class="px-6 py-4">Seat Info</th>
                            <th class="px-6 py-4 text-right">Paid</th>
                            <th class="px-6 py-4 text-center">Receipt</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-800">
                        <?php foreach ($history as $row):
                            $hasDue = $row['due_amount'] > 0;
                        ?>
                            <tr class="hover:bg-zinc-900/50 transition">
                                <td class="px-6 py-4">
                                    <div class="font-bold text-white text-base"><?= $row['seat_label'] ?> <span class="text-xs text-zinc-500 font-normal">/ <?= $row['shift_name'] ?></span></div>
                                    <div class="text-[10px] text-zinc-500 uppercase mt-1">
                                        <?= date('d M', strtotime($row['start_date'])) ?> - <?= date('d M Y', strtotime($row['end_date'])) ?>
                                    </div>
                                </td>

                                <td class="px-6 py-4 text-right">
                                    <div class="font-mono text-emerald-500">₹<?= number_format($row['paid_amount']) ?></div>
                                    <?php if ($hasDue): ?>
                                        <div class="text-[10px] text-red-500 font-bold">Due: ₹<?= number_format($row['due_amount']) ?></div>
                                    <?php endif; ?>
                                </td>

                                <td class="px-6 py-4">
                                    <div class="flex items-center justify-center gap-3">
                                        <a href="../receipt.php?id=<?= $row['id'] ?>" target="_blank" class="text-zinc-400 hover:text-white transition" title="Print Receipt">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
                                            </svg>
                                        </a>
                                        <a href="<?= generateWaLink($row, $user['phone'], $user['name']) ?>" target="_blank" class="text-green-500 hover:text-green-400 transition" title="WhatsApp Receipt">
                                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                                <path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z" />
                                            </svg>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($history)): ?>
                            <tr>
                                <td colspan="3" class="px-6 py-8 text-center text-zinc-600">No booking history available.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div>
        <h2 class="text-lg font-bold text-white mb-4 flex items-center gap-2">
            <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            Recent Attendance
        </h2>

        <div class="bg-surface border border-zinc-900 rounded-xl overflow-hidden shadow-xl">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-zinc-900/80 text-zinc-500 uppercase text-xs font-bold border-b border-zinc-800">
                        <tr>
                            <th class="px-6 py-4">Date</th>
                            <th class="px-6 py-4">Timings</th>
                            <th class="px-6 py-4 text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-800">
                        <?php foreach ($attendanceLogs as $log):
                            // Determine Badge Color
                            $statusColor = 'bg-zinc-500/10 text-zinc-500';
                            if ($log['status'] == 'present') $statusColor = 'bg-emerald-500/10 text-emerald-500';
                            if ($log['status'] == 'late') $statusColor = 'bg-yellow-500/10 text-yellow-500';
                            if ($log['status'] == 'absent') $statusColor = 'bg-red-500/10 text-red-500';

                            // Calculate Duration
                            $duration = '-';
                            if ($log['check_out_time']) {
                                $start = new DateTime($log['check_in_time']);
                                $end = new DateTime($log['check_out_time']);
                                $diff = $start->diff($end);
                                $duration = $diff->format('%h h %i m');
                            }
                        ?>
                            <tr class="hover:bg-zinc-900/50 transition">
                                <td class="px-6 py-4">
                                    <div class="font-bold text-white"><?= date('d M', strtotime($log['date'])) ?></div>
                                    <div class="text-[10px] text-zinc-500 uppercase"><?= date('Y', strtotime($log['date'])) ?></div>
                                </td>

                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-2 text-zinc-300 font-mono text-xs">
                                        <span class="text-emerald-500">IN: <?= date('H:i', strtotime($log['check_in_time'])) ?></span>
                                    </div>
                                    <div class="flex items-center gap-2 text-zinc-500 font-mono text-xs mt-1">
                                        <span>OUT: <?= $log['check_out_time'] ? date('H:i', strtotime($log['check_out_time'])) : '--:--' ?></span>
                                    </div>
                                    <?php if ($duration !== '-'): ?>
                                        <div class="text-[10px] text-zinc-600 mt-1">Duration: <?= $duration ?></div>
                                    <?php endif; ?>
                                </td>

                                <td class="px-6 py-4 text-center">
                                    <span class="<?= $statusColor ?> border border-white/5 px-2.5 py-1 rounded text-[10px] uppercase font-bold tracking-wider">
                                        <?= $log['status'] ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($attendanceLogs)): ?>
                            <tr>
                                <td colspan="3" class="px-6 py-8 text-center text-zinc-600">No attendance records found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>
<script>
    // --- Alert Handling from Redirects (e.g. edit_user.php) ---
    const urlParams = new URLSearchParams(window.location.search);
    const msg = urlParams.get('msg');

    if (msg) {
        const statusDiv = document.getElementById('syncStatus');
        statusDiv.classList.remove('hidden');

        if (msg === 'sync_success') {
            showResult('success', 'Profile & Device Updated!');
        } else if (msg === 'sync_failed') {
            showResult('error', 'Profile Updated, Device Sync Failed.');
        } else if (msg === 'updated_queued') {
            statusDiv.className = 'text-[10px] text-right mt-1 text-blue-400';
            statusDiv.innerText = 'Profile Updated & Queued for Sync.';
        }

        // Clean URL so it doesn't stay on refresh
        window.history.replaceState({}, document.title, window.location.pathname + "?id=<?= $userId ?>");
    }

    // --- Sync Now Button Logic ---
    let pollInterval;

    function syncToDevice(userId) {
        const btn = document.getElementById('syncBtn');
        const statusDiv = document.getElementById('syncStatus');

        // UI Loading State
        btn.disabled = true;
        btn.classList.add('opacity-50', 'cursor-not-allowed');
        btn.innerHTML = '<span class="animate-spin h-3 w-3 border-2 border-white border-t-transparent rounded-full"></span> <span>Queueing...</span>';

        statusDiv.classList.remove('hidden');
        statusDiv.className = 'text-[10px] text-right mt-1 text-zinc-400';
        statusDiv.innerText = 'Connecting to server...';

        const formData = new FormData();
        formData.append('action', 'queue_sync');
        formData.append('user_id', userId);

        fetch('ajax_sync.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'queued') {
                    statusDiv.innerText = 'Waiting for device response...';
                    pollJob(data.job_id);
                } else {
                    showResult('error', data.message);
                }
            })
            .catch(err => showResult('error', 'Connection Failed'));
    }

    function pollJob(jobId) {
        const formData = new FormData();
        formData.append('action', 'check_status');
        formData.append('job_id', jobId);

        pollInterval = setInterval(() => {
            fetch('ajax_sync.php', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    const statusDiv = document.getElementById('syncStatus');

                    if (data.status === 'completed') {
                        clearInterval(pollInterval);
                        showResult('success', 'Synced Successfully!');
                    } else if (data.status === 'failed') {
                        clearInterval(pollInterval);
                        showResult('error', 'Sync Failed (Check Console)');
                        console.error("Device Response:", data.response);
                    } else if (data.status === 'processing') {
                        statusDiv.innerText = 'Device processing command...';
                        statusDiv.className = 'text-[10px] text-right mt-1 text-blue-400 animate-pulse font-bold';
                    }
                });
        }, 2000);
    }

    function showResult(type, msg) {
        const btn = document.getElementById('syncBtn');
        const statusDiv = document.getElementById('syncStatus');

        btn.disabled = false;
        btn.classList.remove('opacity-50', 'cursor-not-allowed');
        btn.innerHTML = `
            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
            <span>Sync Now</span>
        `;

        statusDiv.innerText = msg;
        statusDiv.className = type === 'success' ?
            'text-[10px] text-right mt-1 text-emerald-400 font-bold' :
            'text-[10px] text-right mt-1 text-red-500 font-bold';
    }
</script>

<?php require_once 'includes/footer.php'; ?>