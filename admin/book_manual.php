<?php

require_once 'admin_check.php';
require_once '../core/SeatManager.php';

$manager = new SeatManager();
$pdo = Database::getInstance()->getConnection();

// 1. Fetch Data for Dropdowns
$users = $pdo->query("SELECT id, name, phone FROM users WHERE role = 'student' ORDER BY name ASC")->fetchAll();
$shifts = $manager->getShifts();
$seats = $pdo->query("SELECT * FROM seats ORDER BY label ASC")->fetchAll();
$coupons = $pdo->query("SELECT * FROM coupons WHERE status = 1")->fetchAll();

// 2. Handle Form Submission
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = $_POST['user_id'];
    $seatId = $_POST['seat_id'];
    $shiftId = $_POST['shift_id'];
    $startDate = $_POST['start_date'];
    $duration = $_POST['duration'];
    $notes = $_POST['notes'];
    $amountReceived = $_POST['amount_received'];
    $couponId = $_POST['coupon_id'] ?? null;
    // Capture Manual Discount
    $manualDiscount = floatval($_POST['manual_discount'] ?? 0);

    $adminId = $_SESSION['user_id'];

    // Call the updated function with 'cash' and notes
    $result = $manager->createBooking($userId, $seatId, $shiftId, $startDate, $duration, 'cash', $notes, $adminId, $amountReceived, $couponId, $manualDiscount);

    if ($result['success']) {
        $message = "<div class='bg-emerald-900/30 text-emerald-400 p-4 rounded mb-6 border border-emerald-900'>Payment Recorded & Seat Booked! <a href='users.php' class='underline font-bold'>View User History to Print Receipt</a> </div>";
    } else {
        $message = "<div class='bg-red-900/30 text-red-400 p-4 rounded mb-6 border border-red-900'>Error: {$result['message']}</div>";
    }
}

$breadcrump = "Cash Booking";
$headerTitle = "Manual Booking";
?>

<!DOCTYPE html>
<html lang="en" class="dark">

<head>
    <meta charset="UTF-8">
    <title>Manual Booking | Admin</title>
    <? require_once 'includes/common.php' ?>
</head>

<body class="p-4 md:p-8 max-w-4xl mx-auto">
    <? require_once 'includes/loader.php' ?>
    <? require_once 'includes/breadcrump.php' ?>

    <?= $message ?>

    <div class="bg-surface border border-zinc-900 p-8 rounded-2xl shadow-2xl">
        <div class="bg-surface border border-zinc-900 rounded-2xl shadow-2xl">
            <form method="POST" class="space-y-6">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-xs uppercase text-zinc-500 font-bold mb-2">Select Student</label>
                        <select name="user_id" required class="w-full bg-black border border-zinc-800 p-3 rounded text-white focus:border-accent outline-none">
                            <option value="">-- Choose Student --</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?= $user['id'] ?>"><?= $user['name'] ?> (<?= $user['phone'] ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs uppercase text-zinc-500 font-bold mb-2">Start Date</label>
                        <input type="date" name="start_date" value="<?= date('Y-m-d') ?>" min="<?= date('Y-m-d') ?>" required
                            class="w-full bg-black border border-zinc-800 p-3 rounded text-white focus:border-accent outline-none">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <label class="block text-xs uppercase text-zinc-500 font-bold mb-2">Select Seat</label>
                        <select name="seat_id" required class="w-full bg-black border border-zinc-800 p-3 rounded text-white focus:border-accent outline-none font-mono">
                            <?php foreach ($seats as $seat): ?>
                                <option value="<?= $seat['id'] ?>"><?= $seat['label'] ?> - <?= $seat['type'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs uppercase text-zinc-500 font-bold mb-2">Shift</label>
                        <select name="shift_id" id="shiftSelect" onchange="calculateTotal()" required class="w-full bg-black border border-zinc-800 p-3 rounded text-white focus:border-accent outline-none">
                            <option value="" data-price="0">-- Select Shift --</option>
                            <?php foreach ($shifts as $shift): ?>
                                <option value="<?= $shift['id'] ?>" data-price="<?= $shift['monthly_price'] ?>">
                                    <?= $shift['name'] ?> (₹<?= number_format($shift['monthly_price']) ?>/mo)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs uppercase text-zinc-500 font-bold mb-2">Duration</label>
                        <select name="duration" id="durationSelect" onchange="calculateTotal()" class="w-full bg-black border border-zinc-800 p-3 rounded text-white focus:border-accent outline-none">
                            <option value="1">1 Month</option>
                            <option value="2">2 Months</option>
                            <option value="3">3 Months</option>
                            <option value="6">6 Months</option>
                        </select>
                    </div>
                </div>

                <hr class="border-zinc-800 my-4">

                <div class="bg-zinc-900 p-6 rounded-xl border border-zinc-800 relative overflow-hidden">
                    <div class="absolute -right-10 -top-10 w-32 h-32 bg-accent/5 rounded-full blur-2xl"></div>

                    <h3 class="text-sm font-bold text-white mb-4 uppercase tracking-wider flex items-center gap-2">
                        <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        Cash Collection
                    </h3>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

                        <div>
                            <label class="block text-xs uppercase text-zinc-500 font-bold mb-2">Total Bill</label>
                            <div class="relative">
                                <span class="absolute left-3 top-3 text-zinc-500">₹</span>
                                <input type="text" id="displayTotal" value="0" disabled
                                    class="w-full bg-black border border-zinc-800 p-3 pl-8 rounded text-white font-mono font-bold">
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs uppercase text-emerald-500 font-bold mb-2">Cash Received</label>
                            <input type="number" name="amount_received" id="cashInput" oninput="calculateDue()" step="0.01" required placeholder="0.00"
                                class="w-full bg-black border border-emerald-900 p-3 rounded text-white focus:border-emerald-500 outline-none font-mono text-lg">
                        </div>

                        <div>
                            <label class="block text-xs uppercase text-red-500 font-bold mb-2">Balance Due</label>
                            <div class="relative">
                                <span class="absolute left-3 top-3 text-red-900">₹</span>
                                <input type="text" id="displayDue" value="0" disabled
                                    class="w-full bg-red-900/10 border border-red-900/30 p-3 pl-8 rounded text-red-400 font-mono font-bold">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-span-1 md:col-span-3 bg-zinc-900 border border-zinc-800 p-6 rounded-xl relative overflow-hidden">

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 relative z-10">
                        <div>
                            <label class="block text-xs uppercase text-zinc-400 font-bold mb-1">Coupon Code</label>
                            <select name="coupon_id" id="couponSelect" onchange="calculateTotal()" class="w-full bg-black border border-zinc-700 p-2 rounded text-white text-sm outline-none focus:border-emerald-500">
                                <option value="" data-type="fixed" data-value="0">-- No Coupon --</option>
                                <?php foreach ($coupons as $c): ?>
                                    <option value="<?= $c['id'] ?>" data-type="<?= $c['type'] ?>" data-value="<?= $c['value'] ?>">
                                        <?= $c['code'] ?> - <?= $c['label'] ?> (<?= $c['type'] == 'fixed' ? '₹' : '' ?><?= intval($c['value']) ?><?= $c['type'] == 'percent' ? '%' : '' ?> Off)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs uppercase text-accent font-bold mb-1">Manual Flat Discount (₹)</label>
                            <div class="relative">
                                <span class="absolute left-3 top-2 text-zinc-500 text-sm">₹</span>
                                <input type="number" name="manual_discount" id="manualDiscountInput" oninput="calculateTotal()" value="0" min="0"
                                    class="w-full bg-black border border-accent/50 p-2 pl-8 rounded text-white font-mono font-bold focus:border-accent outline-none placeholder-zinc-700">
                            </div>
                            <p class="text-[10px] text-zinc-500 mt-1">Directly reduce bill amount.</p>
                        </div>
                    </div>

                    <div class="mt-4 pt-4 border-t border-zinc-800 flex justify-between items-center">
                        <span class="text-xs uppercase text-zinc-500">Total Savings applied</span>
                        <p class="text-emerald-400 font-mono font-bold text-lg">-₹<span id="displayDiscount">0</span></p>
                    </div>
                </div>

                <div>
                    <label class="block text-xs uppercase text-zinc-500 font-bold mb-2">Admin Notes</label>
                    <textarea name="notes" rows="2" placeholder="Receipt #..." class="w-full bg-zinc-900 border border-zinc-800 p-3 rounded text-white focus:border-accent outline-none"></textarea>
                </div>

                <button type="submit" class="w-full bg-accent text-black font-bold py-4 rounded-lg hover:bg-yellow-500 transition shadow-lg">
                    Confirm Transaction
                </button>
            </form>
        </div>
    </div>
    <script>
        function calculateTotal() {
            // 1. Get Base Price
            const shiftSelect = document.getElementById('shiftSelect');
            const durationSelect = document.getElementById('durationSelect');
            const monthlyPrice = parseFloat(shiftSelect.options[shiftSelect.selectedIndex].dataset.price) || 0;
            const months = parseInt(durationSelect.value) || 1;
            let grossTotal = monthlyPrice * months;

            // 2. Calculate Coupon Discount
            const couponSelect = document.getElementById('couponSelect');
            const selectedCoupon = couponSelect.options[couponSelect.selectedIndex];
            const type = selectedCoupon.dataset.type;
            const couponValue = parseFloat(selectedCoupon.dataset.value) || 0;

            let couponDiscount = 0;
            if (type === 'percent') {
                couponDiscount = grossTotal * (couponValue / 100);
            } else {
                couponDiscount = couponValue;
            }

            // 3. Get Manual Discount
            const manualDiscount = parseFloat(document.getElementById('manualDiscountInput').value) || 0;

            // 4. Calculate Total Discount (Coupon + Manual)
            let totalDiscount = couponDiscount + manualDiscount;

            // Safety: Total discount cannot exceed the bill
            if (totalDiscount > grossTotal) totalDiscount = grossTotal;

            // 5. Final Net Total
            let netTotal = grossTotal - totalDiscount;

            // 6. Update UI
            document.getElementById('displayTotal').value = netTotal.toFixed(2);
            document.getElementById('displayDiscount').innerText = totalDiscount.toFixed(2);

            // Recalculate Pending Balance
            calculateDue();
        }

        function calculateDue() {
            const total = parseFloat(document.getElementById('displayTotal').value) || 0;
            const cash = parseFloat(document.getElementById('cashInput').value) || 0;
            const dueDisplay = document.getElementById('displayDue');

            const due = total - cash;

            // If user pays MORE than total (Change to return), show 0 due
            // Ideally you might want a "Change to Return" field, but for now we clamp due at 0
            dueDisplay.value = due > 0 ? due.toFixed(2) : "0.00";

            if (due > 0) {
                dueDisplay.classList.remove('text-zinc-500', 'bg-emerald-900/10', 'border-emerald-900/30');
                dueDisplay.classList.add('text-red-400', 'bg-red-900/10', 'border-red-900/30');
            } else {
                // If fully paid
                dueDisplay.classList.remove('text-red-400', 'bg-red-900/10', 'border-red-900/30');
                dueDisplay.classList.add('text-zinc-500', 'bg-zinc-900', 'border-zinc-800');
            }
        }
    </script>
</body>

</html>