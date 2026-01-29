<?php
// 1. SECURITY & SESSION
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php?error=Session expired");
    exit;
}

// 2. LOAD DEPENDENCIES
require_once 'core/SeatManager.php';
require_once 'config/Database.php';

$pdo = Database::getInstance()->getConnection();
$manager = new SeatManager();
$shifts = $manager->getShifts();

// 3. GET FILTERS
$selectedDate = $_GET['date'] ?? date('Y-m-d');
// Default to first shift if none selected
$selectedShiftId = $_GET['shift'] ?? ($shifts[0]['id'] ?? 0);

// 4. FETCH DATA
$seatMap = $manager->getSeatMap($selectedShiftId, $selectedDate);

// 5. CALCULATE STATS (Fixed Logic)
$totalSeats = count($seatMap);
$availableSeats = 0;

foreach ($seatMap as $s) {
    // Safety: Default to 'available' if key is missing
    $status = $s['status'] ?? 'available';

    // Count as available if explicitly 'available'
    if ($status === 'available') {
        $availableSeats++;
    }
}

$occupiedCount = $totalSeats - $availableSeats;
$occupancyRate = $totalSeats > 0 ? round(($occupiedCount / $totalSeats) * 100) : 0;

// Check for Active Dues (Confirmed Seat, but Money Owed)
$duesStmt = $pdo->prepare("
    SELECT s.id, st.label, s.due_amount, s.end_date
    FROM subscriptions s
    JOIN seats st ON s.seat_id = st.id
    WHERE s.user_id = ? 
    AND s.payment_status = 'paid' 
    AND s.due_amount > 0
    AND s.end_date >= CURDATE()
");
$duesStmt->execute([$_SESSION['user_id']]);
$duesRecord = $duesStmt->fetch();

// 6. UI HEADER
$pageTitle = "Dashboard";
require_once 'includes/header.php';
?>

<?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
    <div class="bg-accent text-black font-bold text-center text-xs py-2 sticky top-0 z-[60]">
        👁️ You are viewing this page as an Administrator.
        <a href="admin/index.php" class="underline ml-2">Return to Admin Console</a>
    </div>
<?php endif; ?>

<?php if (isset($_GET['msg']) && $_GET['msg'] == 'request_sent'): ?>
    <div id="statusAlert" class="max-w-7xl mx-auto px-6 mt-6">
        <div class="bg-yellow-900/30 border border-yellow-600 text-yellow-500 p-4 rounded-xl flex items-center gap-3 shadow-lg">
            <svg class="w-6 h-6 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <div>
                <b class="block">Request Sent!</b>
                <span class="text-sm opacity-90">Your seat is reserved. Please visit the desk to pay and confirm.</span>
            </div>
        </div>
    </div>
<?php endif; ?>



<div class="max-w-7xl mx-auto p-6 lg:p-12">

    <?php if ($duesRecord): ?>
        <div class="mb-8 bg-red-900/20 border border-red-600/50 p-6 rounded-2xl flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 shadow-[0_0_30px_rgba(220,38,38,0.1)]">

            <div class="flex items-center gap-4">
                <div class="bg-red-500/10 p-3 rounded-full text-red-500 animate-bounce">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                    </svg>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-white">Outstanding Dues</h3>
                    <p class="text-red-400 text-sm mt-1">
                        Your seat <strong><?= $duesRecord['label'] ?></strong> is active, but you have a pending balance.
                    </p>
                    <p class="text-zinc-500 text-xs mt-1">
                        Please clear this before <?= date('d M', strtotime($duesRecord['end_date'])) ?> to avoid entry restrictions.
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-4 bg-black/40 px-6 py-3 rounded-xl border border-red-900/30">
                <div class="text-right">
                    <p class="text-[10px] uppercase text-zinc-500 font-bold">Balance Due</p>
                    <p class="font-mono text-2xl font-bold text-white">₹<?= number_format($duesRecord['due_amount']) ?></p>
                </div>
                <div class="h-10 w-px bg-zinc-700 mx-2"></div>

                <a href="https://wa.me/919876543210?text=Hi%20Admin,%20I%20want%20to%20clear%20my%20due%20of%20Rs.<?= $duesRecord['due_amount'] ?>" target="_blank" class="text-xs font-bold text-red-500 hover:text-white uppercase tracking-widest transition">
                    Pay Now
                </a>
            </div>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-10">
        <a href="my_bookings.php" class="bg-zinc-900/50 border border-zinc-800 p-4 rounded-xl flex items-center gap-3 hover:border-emerald-500/50 transition group">
            <div class="p-2 bg-blue-500/10 text-blue-400 rounded-lg group-hover:bg-blue-500 group-hover:text-white transition">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
            <div>
                <p class="font-bold text-sm text-white">History</p>
                <p class="text-[10px] text-zinc-500">Past Bookings</p>
            </div>
        </a>

        <a href="report_issue.php" class="bg-zinc-900/50 border border-zinc-800 p-4 rounded-xl flex items-center gap-3 hover:border-red-500/50 transition group">
            <div class="p-2 bg-red-500/10 text-red-400 rounded-lg group-hover:bg-red-500 group-hover:text-white transition">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                </svg>
            </div>
            <div>
                <p class="font-bold text-sm text-white">Report Issue</p>
                <p class="text-[10px] text-zinc-500">Complaints</p>
            </div>
        </a>

        <a href="profile.php" class="bg-zinc-900/50 border border-zinc-800 p-4 rounded-xl flex items-center gap-3 hover:border-accent/50 transition group">
            <div class="p-2 bg-purple-500/10 text-purple-400 rounded-lg group-hover:bg-purple-500 group-hover:text-white transition">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                </svg>
            </div>
            <div>
                <p class="font-bold text-sm text-white">Profile</p>
                <p class="text-[10px] text-zinc-500">Edit Details</p>
            </div>
        </a>

        <a href="id_card.php" class="bg-zinc-900/50 border border-zinc-800 p-4 rounded-xl flex items-center gap-3 hover:border-emerald-500/50 transition group">
            <div class="p-2 bg-emerald-500/10 text-emerald-400 rounded-lg group-hover:bg-emerald-500 group-hover:text-white transition">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"></path>
                </svg>
            </div>
            <div>
                <p class="font-bold text-sm text-white">Digital ID</p>
                <p class="text-[10px] text-zinc-500">View Pass</p>
            </div>
        </a>
    </div>

    <div class="flex flex-col md:flex-row justify-between items-end mb-10 gap-4">
        <div>
            <h2 class="text-3xl font-bold text-white tracking-tight">Operations Console</h2>
            <p class="text-zinc-500 mt-1">Real-time seat availability and booking status.</p>
        </div>

        <div class="flex gap-3">
            <a href="id_card.php" target="_blank" class="px-4 py-2 bg-zinc-800 border border-zinc-700 rounded-lg text-xs font-bold text-white hover:bg-zinc-700 transition flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"></path>
                </svg>
                Digital ID
            </a>
            <a href="logout.php" class="px-5 py-2 rounded-lg border border-zinc-700 text-sm font-medium hover:bg-zinc-800 transition text-zinc-400 hover:text-white">
                Logout
            </a>
        </div>
    </div>

    <?php
    $myPlanStmt = $pdo->prepare("
        SELECT s.end_date, s.payment_status, st.label, sh.name as shift_name
        FROM subscriptions s
        JOIN seats st ON s.seat_id = st.id
        JOIN shifts sh ON s.shift_id = sh.id
        WHERE s.user_id = ? 
        AND s.end_date >= CURDATE()
        AND s.payment_status != 'rejected'
        ORDER BY s.end_date DESC
    ");
    $myPlanStmt->execute([$_SESSION['user_id']]);
    $myPlans = $myPlanStmt->fetchAll();
    ?>

    <?php if ($myPlans): ?>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
            <?php foreach ($myPlans as $plan): ?>

                <div class="bg-zinc-900/50 border border-zinc-800 p-6 rounded-xl flex flex-col justify-between gap-4 backdrop-blur-sm relative overflow-hidden group hover:border-emerald-500/30 transition">

                    <div class="absolute top-0 right-0 w-20 h-20 bg-emerald-500/10 rounded-full blur-2xl -mr-10 -mt-10 pointer-events-none"></div>

                    <div class="flex items-start gap-4">
                        <div class="h-12 w-12 bg-emerald-900/30 text-emerald-500 rounded-full flex items-center justify-center border border-emerald-900 shrink-0">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"></path>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-white font-bold text-lg">Seat <?= $plan['label'] ?></h3>
                            <p class="text-zinc-400 text-sm"><?= $plan['shift_name'] ?></p>
                        </div>
                    </div>

                    <div class="flex items-end justify-between border-t border-zinc-800 pt-4 mt-2">
                        <div>
                            <p class="text-[10px] uppercase text-zinc-500 font-bold tracking-wider">Expires On</p>
                            <p class="text-emerald-400 font-mono font-bold text-lg">
                                <?= date('d M Y', strtotime($plan['end_date'])) ?>
                            </p>
                        </div>

                        <?php if ($plan['payment_status'] == 'pending'): ?>
                            <span class="bg-yellow-900/50 text-yellow-500 border border-yellow-700 px-3 py-1 rounded text-xs font-bold animate-pulse">
                                Pending
                            </span>
                        <?php else: ?>
                            <a href="id_card.php" class="text-white hover:text-emerald-400 text-xs font-bold flex items-center gap-1 transition">
                                View ID <span class="text-lg">→</span>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

            <?php endforeach; ?>
        </div>
    <?php endif; ?>


    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">

        <div class="lg:col-span-4 space-y-6">

            <div class="bg-zinc-900/50 border border-zinc-800 p-6 rounded-2xl shadow-xl">
                <form action="" method="GET" class="space-y-6">
                    <div>
                        <label class="text-xs uppercase text-zinc-500 font-bold tracking-wider mb-2 block">Start Date</label>
                        <input type="date" name="date" value="<?= $selectedDate ?>"
                            min="<?= date('Y-m-d') ?>"
                            onchange="this.form.submit()"
                            class="w-full bg-zinc-950 border border-zinc-800 rounded-lg px-4 py-3 text-white font-mono outline-none focus:border-emerald-500 transition-colors">
                    </div>

                    <div>
                        <label class="text-xs uppercase text-zinc-500 font-bold tracking-wider mb-2 block">Shift</label>
                        <select name="shift" onchange="this.form.submit()" class="w-full bg-zinc-950 border border-zinc-800 rounded-lg px-4 py-3 text-white outline-none focus:border-emerald-500">
                            <?php foreach ($shifts as $sh): ?>
                                <option value="<?= $sh['id'] ?>" <?= $selectedShiftId == $sh['id'] ? 'selected' : '' ?>>
                                    <?= $sh['name'] ?> (<?= date('H:i', strtotime($sh['start_time'])) ?> - <?= date('H:i', strtotime($sh['end_time'])) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div class="bg-zinc-900/50 border border-zinc-800 p-5 rounded-xl border-l-4 border-emerald-500">
                    <p class="text-zinc-500 text-[10px] uppercase tracking-widest">Occupancy</p>
                    <p class="text-3xl font-mono font-bold text-white mt-1"><?= $occupancyRate ?>%</p>
                </div>
                <div class="bg-zinc-900/50 border border-zinc-800 p-5 rounded-xl border-l-4 border-zinc-700">
                    <p class="text-zinc-500 text-[10px] uppercase tracking-widest">Available</p>
                    <p class="text-3xl font-mono font-bold text-white mt-1"><?= $availableSeats ?></p>
                </div>
            </div>
        </div>

        <div class="lg:col-span-8">
            <div class="bg-zinc-900/30 border border-zinc-800 p-8 rounded-3xl min-h-[600px] flex flex-col relative">

                <div class="flex justify-between items-center mb-8 pb-4 border-b border-zinc-800">
                    <h3 class="font-semibold text-lg text-white">Seat Layout</h3>
                    <div class="flex gap-4 text-[10px] uppercase tracking-wider text-zinc-400">
                        <span class="flex items-center gap-1">
                            <div class="w-2 h-2 rounded-full bg-emerald-500"></div> Free
                        </span>
                        <span class="flex items-center gap-1">
                            <div class="w-2 h-2 rounded-full bg-yellow-500"></div> Pending
                        </span>
                        <span class="flex items-center gap-1">
                            <div class="w-2 h-2 rounded-full bg-red-600"></div> Booked
                        </span>
                    </div>
                </div>

                <div class="grid grid-cols-4 sm:grid-cols-6 lg:grid-cols-8 gap-4 mb-12">
                    <?php foreach ($seatMap as $seat):
                        $status = $seat['status'] ?? 'available';
                        $label = htmlspecialchars($seat['label']);
                        $seatId = $seat['id'];
                        $occupant = htmlspecialchars($seat['occupant_name'] ?? 'Unknown');

                        // Visual Logic
                        $isClickable = false;
                        $tooltip = "";
                        $colorClass = "";
                        $icon = "";

                        switch ($status) {
                            case 'occupied': // RED
                                $colorClass = "bg-red-900/20 border-red-900/50 text-red-500 cursor-not-allowed opacity-80";
                                $icon = '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>';
                                $tooltip = "Booked by " . $occupant;
                                break;

                            case 'pending': // YELLOW
                                $colorClass = "bg-yellow-900/20 border-yellow-600/50 text-yellow-500 cursor-not-allowed";
                                $icon = '<svg class="w-5 h-5 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>';
                                $tooltip = "Reserved - Awaiting Payment";
                                break;

                            case 'maintenance': // GREY
                                $colorClass = "bg-zinc-800 border-zinc-700 text-zinc-600 cursor-not-allowed";
                                $icon = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>';
                                $tooltip = "Maintenance";
                                break;

                            case 'available':
                            default: // GREEN
                                $colorClass = "bg-emerald-900/20 border-emerald-500/30 text-emerald-400 hover:bg-emerald-500 hover:text-white cursor-pointer transition-all shadow-[0_0_15px_rgba(16,185,129,0.1)] hover:shadow-[0_0_20px_rgba(16,185,129,0.4)]";
                                $icon = '<span class="text-lg tracking-tighter">' . $label . '</span>';
                                $isClickable = true;
                                $tooltip = "Available - Click to Book";
                                break;
                        }
                    ?>
                        <div
                            <?= $isClickable ? "onclick=\"openBooking('$seatId', '$label')\"" : "" ?>
                            class="relative h-16 rounded-xl border flex items-center justify-center font-mono font-bold select-none overflow-hidden transition-transform hover:scale-105 <?= $colorClass ?>"
                            title="<?= $tooltip ?>">
                            <?= $icon ?>

                            <?php if ($status === 'pending'): ?>
                                <span class="absolute top-2 right-2 w-1.5 h-1.5 bg-yellow-500 rounded-full animate-ping"></span>
                                <span class="absolute top-2 right-2 w-1.5 h-1.5 bg-yellow-500 rounded-full"></span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

            </div>
        </div>
    </div>
</div>

<div id="bookingPanel" class="fixed bottom-0 left-0 right-0 bg-zinc-950 border-t border-emerald-500/30 translate-y-full transition-transform duration-300 z-50 p-6 shadow-[0_-10px_40px_rgba(0,0,0,0.8)]">
    <div class="max-w-4xl mx-auto flex flex-col md:flex-row justify-between items-center gap-6">
        <div>
            <p class="text-xs text-emerald-500 uppercase tracking-widest mb-1">Confirm Selection</p>
            <h3 class="text-2xl font-bold text-white">
                Booking Seat <span id="displayLabel" class="font-mono text-emerald-400">--</span>
            </h3>
            <p class="text-sm text-zinc-500 mt-1">
                Date: <span class="text-zinc-300"><?= $selectedDate ?></span>
            </p>
        </div>

        <form action="book_seat.php" method="POST" class="flex flex-col md:flex-row gap-4 w-full md:w-auto items-end">
            <input type="hidden" name="seat_id" id="inputSeatId">
            <input type="hidden" name="shift_id" value="<?= $selectedShiftId ?>">
            <input type="hidden" name="date" value="<?= $selectedDate ?>">

            <div class="w-full md:w-32">
                <label class="text-[10px] uppercase text-zinc-500 font-bold mb-1 block">Duration</label>
                <select name="duration" class="w-full bg-zinc-900 border border-zinc-800 rounded-lg px-3 py-3 text-white text-sm outline-none focus:border-emerald-500">
                    <option value="1">1 Month</option>
                    <option value="2">2 Months</option>
                    <option value="3">3 Months</option>
                    <option value="6">6 Months</option>
                </select>
            </div>

            <div class="flex gap-4 w-full">
                <button type="button" onclick="closePanel()" class="px-6 py-3 rounded-lg border border-zinc-700 hover:bg-zinc-800 text-white font-medium transition w-full md:w-auto">Cancel</button>
                <button type="submit" class="px-8 py-3 rounded-lg bg-white text-black font-bold hover:bg-zinc-200 transition w-full md:w-auto whitespace-nowrap">Confirm Request</button>
            </div>
        </form>
    </div>
</div>

<script>
    // 1. OPEN PANEL (Matched function name to HTML onclick)
    function openBooking(id, label) {
        document.getElementById('inputSeatId').value = id;
        document.getElementById('displayLabel').innerText = label;
        document.getElementById('bookingPanel').classList.remove('translate-y-full');
    }

    // 2. CLOSE PANEL
    function closePanel() {
        document.getElementById('bookingPanel').classList.add('translate-y-full');
    }

    // 3. AUTO HIDE ALERTS
    const alertBox = document.getElementById('statusAlert');
    if (alertBox) {
        setTimeout(() => {
            alertBox.style.opacity = '0';
            setTimeout(() => alertBox.remove(), 500);
        }, 4000);

        // Clean URL
        if (window.history.replaceState) {
            const url = new URL(window.location.href);
            url.searchParams.delete('msg');
            window.history.replaceState(null, '', url.toString());
        }
    }
</script>

<?php require_once 'includes/footer.php'; ?>