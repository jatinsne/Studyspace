<?php
// require_once 'admin_check.php';
require_once '../config/Database.php';

$pdo = Database::getInstance()->getConnection();
$today = date('Y-m-d');
$message = "";

// 1. HANDLE ACTIONS (Check In / Check Out)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $uid = $_POST['user_id'];
    $adminId = $_SESSION['user_id'];
    $time = date('H:i:s');

    if ($_POST['action'] === 'check_in') {
        // Check In Logic
        try {
            $stmt = $pdo->prepare("INSERT INTO attendance (user_id, date, check_in_time, status) VALUES (?, ?, ?, 'present')");
            $stmt->execute([$uid, $today, $time]);
            $message = "Student checked in.";
        } catch (PDOException $e) {
            $message = "Already checked in.";
        }
    } elseif ($_POST['action'] === 'check_out') {
        // Check Out Logic
        $stmt = $pdo->prepare("UPDATE attendance SET check_out_time = ? WHERE user_id = ? AND date = ?");
        $stmt->execute([$time, $uid, $today]);
        $message = "Student checked out.";
    }

    // Redirect to prevent form resubmission
    header("Location: attendance.php");
    exit;
}

// 2. FETCH DATA
// We join to get Start/End times to calculate duration
$sql = "
    SELECT 
        u.id, u.name, u.phone,
        st.label, 
        sh.name as shift_name, 
        sh.start_time as shift_start,
        sh.end_time as shift_end,
        a.check_in_time, 
        a.check_out_time
    FROM users u
    JOIN subscriptions s ON u.id = s.user_id
    JOIN seats st ON s.seat_id = st.id
    JOIN shifts sh ON s.shift_id = sh.id
    LEFT JOIN attendance a ON u.id = a.user_id AND a.date = ?
    WHERE s.payment_status = 'paid' 
    AND ? BETWEEN s.start_date AND s.end_date
    ORDER BY 
        CASE WHEN a.check_in_time IS NOT NULL AND a.check_out_time IS NULL THEN 0 ELSE 1 END, -- Active first
        st.label ASC
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$today, $today]);
$list = $stmt->fetchAll();

// 3. CALCULATE STATS
$totalStudents = count($list);
$currentlySeated = 0;
$completedSessions = 0;

foreach ($list as $row) {
    if (!empty($row['check_in_time']) && empty($row['check_out_time'])) {
        $currentlySeated++;
    }
    if (!empty($row['check_out_time'])) {
        $completedSessions++;
    }
}
$occupancyPct = $totalStudents > 0 ? round(($currentlySeated / $totalStudents) * 100) : 0;

// Helper to calculate duration
function getDuration($start, $end = null)
{
    if (!$start) return '-';
    $startTime = new DateTime($start);
    $endTime = $end ? new DateTime($end) : new DateTime(); // If no end, use NOW
    $interval = $startTime->diff($endTime);
    return $interval->format('%hh %im');
}

$breadcrump = "Pending Requests";
$headerTitle = "Students waiting for approval & payment";
?>

<!DOCTYPE html>
<html lang="en" class="dark">

<head>
    <title>Live Attendance | Admin</title>
    <? require_once 'includes/common.php' ?>

</head>

<body class="p-6 lg:p-10 max-w-7xl mx-auto bg-black text-white">
    <? require_once 'includes/loader.php' ?>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8 items-end">
        <div>
            <h1 class="text-3xl font-bold text-white tracking-tight">Attendance Console</h1>
            <p class="text-zinc-500 mt-1"><?= date('l, d F Y') ?> <span class="bg-green-500 p-1 rounded text-white text-[10px]">• Live</span> </p>
        </div>

        <div class="bg-zinc-900/50 p-4 rounded-xl border border-zinc-800">
            <div class="flex justify-between text-xs font-bold uppercase text-zinc-500 mb-2">
                <span>Current Occupancy</span>
                <span class="text-emerald-400"><?= $currentlySeated ?> / <?= $totalStudents ?></span>
            </div>
            <div class="w-full bg-zinc-800 rounded-full h-2 overflow-hidden">
                <div class="bg-emerald-500 h-2 rounded-full transition-all duration-500" style="width: <?= $occupancyPct ?>%"></div>
            </div>
        </div>

        <div class="flex gap-4 justify-end">
            <div class="text-right">
                <p class="text-[10px] uppercase text-zinc-500 font-bold">Active</p>
                <p class="text-2xl font-mono text-white"><?= $currentlySeated ?></p>
            </div>
            <div class="w-px bg-zinc-800"></div>
            <div class="text-right">
                <p class="text-[10px] uppercase text-zinc-500 font-bold">Completed</p>
                <p class="text-2xl font-mono text-zinc-500"><?= $completedSessions ?></p>
            </div>
        </div>
    </div>

    <div class="flex flex-col md:flex-row gap-4 mb-6">
        <div class="relative flex-1">
            <span class="absolute left-3 top-3 text-zinc-500">🔍</span>
            <input type="text" id="searchInput" onkeyup="filterTable()" placeholder="Search student name or seat..."
                class="w-full bg-surface border border-zinc-800 pl-10 p-3 rounded-lg text-white focus:border-accent outline-none">
        </div>
        <select id="shiftFilter" onchange="filterTable()" class="bg-surface border border-zinc-800 p-3 rounded-lg text-white focus:border-accent outline-none">
            <option value="all">All Shifts</option>
            <option value="Morning">Morning</option>
            <option value="Evening">Evening</option>
            <option value="Full Day">Full Day</option>
        </select>
    </div>

    <div class="bg-surface border border-zinc-900 rounded-xl overflow-hidden shadow-2xl">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm" id="attendanceTable">
                <thead class="bg-zinc-900/50 text-zinc-500 uppercase text-xs tracking-wider">
                    <tr>
                        <th class="px-6 py-4">Seat</th>
                        <th class="px-6 py-4">Student</th>
                        <th class="px-6 py-4">Shift Details</th>
                        <th class="px-6 py-4">Timings</th>
                        <th class="px-6 py-4 text-center">Status</th>
                        <th class="px-6 py-4 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-800">
                    <?php foreach ($list as $row):
                        $inTime = $row['check_in_time'];
                        $outTime = $row['check_out_time'];

                        // Determine State
                        if ($inTime && !$outTime) {
                            $status = 'active';
                            $rowClass = "bg-emerald-900/10";
                        } elseif ($inTime && $outTime) {
                            $status = 'completed';
                            $rowClass = "opacity-60";
                        } else {
                            $status = 'absent';
                            $rowClass = "";
                        }
                    ?>
                        <tr class="hover:bg-zinc-900/50 transition group <?= $rowClass ?>">

                            <td class="px-6 py-4">
                                <div class="w-10 h-10 rounded-lg bg-zinc-800 border border-zinc-700 flex items-center justify-center font-mono font-bold text-accent">
                                    <?= $row['label'] ?>
                                </div>
                            </td>

                            <td class="px-6 py-4">
                                <p class="font-bold text-white text-base student-name"><?= htmlspecialchars($row['name']) ?></p>
                                <p class="text-zinc-500 text-xs"><?= $row['phone'] ?></p>
                            </td>

                            <td class="px-6 py-4 shift-name">
                                <span class="bg-zinc-800 text-zinc-400 px-2 py-1 rounded text-xs font-bold border border-zinc-700">
                                    <?= $row['shift_name'] ?>
                                </span>
                            </td>

                            <td class="px-6 py-4">
                                <?php if ($inTime): ?>
                                    <div class="flex flex-col gap-1">
                                        <span class="text-emerald-400 text-xs font-bold">IN: <?= date('h:i A', strtotime($inTime)) ?></span>
                                        <?php if ($outTime): ?>
                                            <span class="text-red-400 text-xs font-bold">OUT: <?= date('h:i A', strtotime($outTime)) ?></span>
                                            <span class="text-zinc-500 text-[10px]">Duration: <?= getDuration($inTime, $outTime) ?></span>
                                        <?php else: ?>
                                            <span class="text-accent text-[10px] animate-pulse">Running: <?= getDuration($inTime) ?></span>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <span class="text-zinc-600 text-xs">-</span>
                                <?php endif; ?>
                            </td>

                            <td class="px-6 py-4 text-center">
                                <?php if ($status === 'active'): ?>
                                    <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 text-xs font-bold uppercase tracking-wider">
                                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span> Present
                                    </span>
                                <?php elseif ($status === 'completed'): ?>
                                    <span class="text-zinc-500 text-xs font-bold uppercase tracking-wider">Completed</span>
                                <?php else: ?>
                                    <span class="text-zinc-600 text-xs font-bold uppercase tracking-wider">Absent</span>
                                <?php endif; ?>
                            </td>

                            <td class="px-6 py-4 text-right">
                                <form method="POST">
                                    <input type="hidden" name="user_id" value="<?= $row['id'] ?>">

                                    <?php if ($status === 'absent'): ?>
                                        <input type="hidden" name="action" value="check_in">
                                        <button type="submit" class="bg-white text-black hover:bg-zinc-200 px-4 py-2 rounded-lg text-xs font-bold transition shadow-lg shadow-white/10">
                                            Mark In
                                        </button>
                                    <?php elseif ($status === 'active'): ?>
                                        <input type="hidden" name="action" value="check_out">
                                        <button type="submit" class="bg-zinc-800 text-red-400 hover:bg-zinc-700 border border-zinc-700 px-4 py-2 rounded-lg text-xs font-bold transition">
                                            Mark Out
                                        </button>
                                    <?php else: ?>
                                        <button disabled class="text-zinc-600 cursor-not-allowed opacity-50 text-xs font-bold">Closed</button>
                                    <?php endif; ?>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        function filterTable() {
            const search = document.getElementById('searchInput').value.toLowerCase();
            const shift = document.getElementById('shiftFilter').value.toLowerCase();
            const rows = document.querySelectorAll('#attendanceTable tbody tr');

            rows.forEach(row => {
                const name = row.querySelector('.student-name').innerText.toLowerCase();
                const shiftName = row.querySelector('.shift-name').innerText.toLowerCase();

                const matchesSearch = name.includes(search);
                const matchesShift = shift === 'all' || shiftName.includes(shift);

                if (matchesSearch && matchesShift) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }
    </script>

</body>

</html>