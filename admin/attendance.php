<?php
// 1. INITIALIZE & PROCESS POST REQUESTS FIRST (Before ANY HTML is output)
require_once 'admin_check.php'; // Secures page & starts session
require_once '../config/Database.php';

$pdo = Database::getInstance()->getConnection();
$today = date('Y-m-d');
$currentTime = date('H:i:s'); // Fetch current time for shift comparisons

// 2. HANDLE ACTIONS (Check In / Check Out)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $uid = $_POST['user_id'];
    $time = date('H:i:s');

    if ($_POST['action'] === 'check_in') {
        try {
            $stmt = $pdo->prepare("INSERT INTO attendance (user_id, date, check_in_time, status) VALUES (?, ?, ?, 'present')");
            $stmt->execute([$uid, $today, $time]);
        } catch (PDOException $e) {
            // Already checked in
        }
    } elseif ($_POST['action'] === 'check_out') {
        $stmt = $pdo->prepare("UPDATE attendance SET check_out_time = ? WHERE user_id = ? AND date = ?");
        $stmt->execute([$time, $uid, $today]);
    }

    // Safe to redirect now because NO HTML has been output yet
    header("Location: attendance.php");
    exit;
}

// 3. FETCH ATTENDANCE DATA
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

// 4. CALCULATE STATS
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

function getDuration($start, $end = null)
{
    if (!$start) return '-';
    $startTime = new DateTime($start);
    $endTime = $end ? new DateTime($end) : new DateTime();
    $interval = $startTime->diff($endTime);
    return $interval->format('%hh %im');
}

// 5. NOW INCLUDE THE UI HEADER (Outputs HTML)
require_once 'includes/header.php';
?>

<div class="max-w-7xl mx-auto px-6 py-8 w-full">

    <div class="flex flex-col md:flex-row justify-between items-start md:items-end mb-8 gap-4">
        <div>
            <h1 class="text-3xl font-bold tracking-tight text-white flex items-center gap-3">
                Attendance Console
                <span class="bg-emerald-500/10 text-emerald-500 border border-emerald-500/20 px-2 py-0 rounded text-[10px] uppercase tracking-wider font-bold animate-pulse">Live</span>
            </h1>
            <p class="text-zinc-500 mt-1">Monitor manual check-ins and check-outs for <span class="text-white"><?= date('l, d F Y') ?></span>.</p>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8 items-center">

        <div class="bg-zinc-900/50 p-6 rounded-2xl border border-zinc-800 col-span-1 md:col-span-2 relative overflow-hidden">
            <div class="flex justify-between text-xs font-bold uppercase text-zinc-500 tracking-widest mb-3">
                <span>Current Seat Occupancy</span>
                <span class="text-emerald-400"><?= $currentlySeated ?> / <?= $totalStudents ?></span>
            </div>
            <div class="w-full bg-zinc-800 rounded-full h-3 overflow-hidden shadow-inner">
                <div class="bg-emerald-500 h-3 rounded-full transition-all duration-1000 ease-out relative" style="width: <?= $occupancyPct ?>%">
                    <div class="absolute inset-0 bg-white/20 w-full animate-[shimmer_2s_infinite]"></div>
                </div>
            </div>
            <p class="text-[10px] text-zinc-600 mt-2 font-mono"><?= $occupancyPct ?>% Capacity Filled</p>
        </div>

        <div class="flex justify-around bg-zinc-900/50 p-6 rounded-2xl border border-zinc-800 col-span-1 h-full items-center">
            <div class="text-center">
                <p class="text-[10px] uppercase text-zinc-500 font-bold tracking-wider mb-1">Currently Active</p>
                <p class="text-4xl font-mono text-white font-bold"><?= $currentlySeated ?></p>
            </div>
            <div class="w-px h-12 bg-zinc-800"></div>
            <div class="text-center">
                <p class="text-[10px] uppercase text-zinc-500 font-bold tracking-wider mb-1">Completed</p>
                <p class="text-4xl font-mono text-zinc-600 font-bold"><?= $completedSessions ?></p>
            </div>
        </div>
    </div>

    <div class="flex flex-col md:flex-row gap-4 mb-6">
        <div class="relative flex-1">
            <span class="absolute left-4 top-3.5 text-zinc-500">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                </svg>
            </span>
            <input type="text" id="searchInput" onkeyup="filterTable()" placeholder="Search student name or seat..."
                class="w-full bg-zinc-900/50 border border-zinc-800 pl-12 p-3.5 rounded-xl text-white focus:border-accent outline-none transition shadow-inner placeholder:text-zinc-600">
        </div>
        <select id="shiftFilter" onchange="filterTable()" class="bg-zinc-900/50 border border-zinc-800 p-3.5 rounded-xl text-white focus:border-accent outline-none transition shadow-inner font-bold text-sm min-w-[200px]">
            <option value="all">All Shifts</option>
            <option value="Morning">Morning</option>
            <option value="Evening">Evening</option>
            <option value="Full Day">Full Day</option>
        </select>
    </div>

    <div class="bg-zinc-900/30 border border-zinc-800 rounded-2xl overflow-hidden shadow-2xl">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm" id="attendanceTable">
                <thead class="bg-zinc-900/80 text-zinc-500 uppercase text-[10px] tracking-widest font-bold border-b border-zinc-800">
                    <tr>
                        <th class="px-6 py-5">Seat</th>
                        <th class="px-6 py-5">Student</th>
                        <th class="px-6 py-5">Shift Details</th>
                        <th class="px-6 py-5">Timings</th>
                        <th class="px-6 py-5 text-center">Status</th>
                        <th class="px-6 py-5 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-800/50">
                    <?php foreach ($list as $row):
                        $inTime = $row['check_in_time'];
                        $outTime = $row['check_out_time'];

                        // Determine Attendance State
                        if ($inTime && !$outTime) {
                            $status = 'active';
                            $rowClass = "bg-emerald-900/10 hover:bg-emerald-900/20";
                        } elseif ($inTime && $outTime) {
                            $status = 'completed';
                            $rowClass = "opacity-60 hover:opacity-100 hover:bg-zinc-900/50";
                        } else {
                            $status = 'absent';
                            $rowClass = "hover:bg-zinc-900/50";
                        }

                        // Determine Shift Awareness State
                        $isOutOfShift = false;
                        if ($row['shift_start'] && $row['shift_end']) {
                            if ($row['shift_start'] <= $row['shift_end']) {
                                // Normal shift (e.g. 08:00 to 14:00)
                                $isOutOfShift = ($currentTime < $row['shift_start'] || $currentTime > $row['shift_end']);
                            } else {
                                // Night shift crossing midnight (e.g. 20:00 to 06:00)
                                $isOutOfShift = ($currentTime < $row['shift_start'] && $currentTime > $row['shift_end']);
                            }
                        }
                    ?>
                        <tr class="transition-all duration-200 group <?= $rowClass ?>">

                            <td class="px-6 py-4">
                                <div class="w-10 h-10 rounded-lg bg-zinc-800/50 border border-zinc-700/50 flex items-center justify-center font-mono font-bold text-accent shadow-inner">
                                    <?= htmlspecialchars($row['label']) ?>
                                </div>
                            </td>

                            <td class="px-6 py-4">
                                <p class="font-bold text-white text-sm student-name"><?= htmlspecialchars($row['name']) ?></p>
                                <p class="text-zinc-500 font-mono text-[10px] mt-0.5"><?= htmlspecialchars($row['phone']) ?></p>
                            </td>

                            <td class="px-6 py-4 shift-name">
                                <div class="flex flex-col items-start gap-1.5">
                                    <span class="bg-zinc-800/80 text-zinc-400 px-2.5 py-1 rounded-md text-[10px] uppercase font-bold tracking-widest border border-zinc-700/50">
                                        <?= htmlspecialchars($row['shift_name']) ?>
                                    </span>

                                    <?php if ($status !== 'completed'): // Don't show warnings for already completed sessions 
                                    ?>
                                        <?php if ($isOutOfShift): ?>
                                            <?php if ($status === 'active'): ?>
                                                <span class="text-[9px] text-orange-400 font-bold uppercase tracking-widest flex items-center gap-1 bg-orange-500/10 px-1.5 py-0.5 rounded border border-orange-500/20">
                                                    ⚠️ Overstaying
                                                </span>
                                            <?php else: ?>
                                                <span class="text-[9px] text-red-500 font-bold uppercase tracking-widest flex items-center gap-1 bg-red-500/10 px-1.5 py-0.5 rounded border border-red-500/20">
                                                    ⚠️ Off-Shift Time
                                                </span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-[9px] text-emerald-500 font-bold uppercase tracking-widest flex items-center gap-1">
                                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Active Shift
                                            </span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </td>

                            <td class="px-6 py-4">
                                <?php if ($inTime): ?>
                                    <div class="flex flex-col gap-1 font-mono text-xs">
                                        <span class="text-emerald-400 font-bold tracking-wide">IN: <?= date('h:i A', strtotime($inTime)) ?></span>
                                        <?php if ($outTime): ?>
                                            <span class="text-zinc-500 font-bold tracking-wide">OUT: <?= date('h:i A', strtotime($outTime)) ?></span>
                                            <span class="text-zinc-600 text-[10px] mt-0.5">Duration: <?= getDuration($inTime, $outTime) ?></span>
                                        <?php else: ?>
                                            <span class="text-accent text-[10px] animate-pulse mt-0.5">Running: <?= getDuration($inTime) ?></span>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <span class="text-zinc-700 font-mono text-xs">-</span>
                                <?php endif; ?>
                            </td>

                            <td class="px-6 py-4 text-center">
                                <?php if ($status === 'active'): ?>
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 text-[10px] font-bold uppercase tracking-widest shadow-inner">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span> Present
                                    </span>
                                <?php elseif ($status === 'completed'): ?>
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded bg-zinc-800 text-zinc-400 border border-zinc-700 text-[10px] font-bold uppercase tracking-widest">
                                        Completed
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded text-zinc-600 text-[10px] font-bold uppercase tracking-widest">
                                        Absent
                                    </span>
                                <?php endif; ?>
                            </td>

                            <td class="px-6 py-4 text-right">
                                <form method="POST">
                                    <input type="hidden" name="user_id" value="<?= $row['id'] ?>">

                                    <?php if ($status === 'absent'): ?>
                                        <input type="hidden" name="action" value="check_in">
                                        <button type="submit" class="bg-white hover:bg-zinc-200 text-black px-4 py-2 rounded-lg text-xs font-bold transition shadow-lg shadow-white/10 w-24">
                                            Mark In
                                        </button>
                                    <?php elseif ($status === 'active'): ?>
                                        <input type="hidden" name="action" value="check_out">
                                        <button type="submit" class="bg-zinc-800 hover:bg-red-900/50 text-red-400 hover:text-red-300 border border-zinc-700 hover:border-red-900 px-4 py-2 rounded-lg text-xs font-bold transition w-24">
                                            Mark Out
                                        </button>
                                    <?php else: ?>
                                        <button disabled class="bg-transparent border border-zinc-800 text-zinc-600 cursor-not-allowed px-4 py-2 rounded-lg text-xs font-bold w-24">
                                            Closed
                                        </button>
                                    <?php endif; ?>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($list)): ?>
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-zinc-600">
                                <svg class="w-12 h-12 mx-auto mb-3 opacity-20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path>
                                </svg>
                                <p>No active students found for today.</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
    @keyframes shimmer {
        0% {
            transform: translateX(-100%);
        }

        100% {
            transform: translateX(100%);
        }
    }
</style>

<script>
    function filterTable() {
        const search = document.getElementById('searchInput').value.toLowerCase();
        const shift = document.getElementById('shiftFilter').value.toLowerCase();
        const rows = document.querySelectorAll('#attendanceTable tbody tr');

        rows.forEach(row => {
            if (row.cells.length === 1) return;

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

<?php require_once 'includes/footer.php'; ?>