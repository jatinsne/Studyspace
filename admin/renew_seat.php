<?php
require_once 'admin_check.php';
require_once '../core/SeatManager.php';

$manager = new SeatManager();
$pdo = Database::getInstance()->getConnection();

$oldId = $_GET['id'] ?? 0;
$error = null;

// 1. Fetch Old Booking & Shift Details
$stmt = $pdo->prepare("
    SELECT s.*, sh.monthly_price, sh.name as shift_name 
    FROM subscriptions s 
    JOIN shifts sh ON s.shift_id = sh.id 
    WHERE s.id = ?
");
$stmt->execute([$oldId]);
$oldBooking = $stmt->fetch();

if (!$oldBooking) die("Booking not found");

// 2. Fetch Coupons
$coupons = $pdo->query("SELECT * FROM coupons WHERE status = 1")->fetchAll();

// 3. Calculate New Start Date (Old End + 1 Day)
$newStartDate = date('Y-m-d', strtotime($oldBooking['end_date'] . ' + 1 day'));

// 4. Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $duration = $_POST['duration'];
    $amountReceived = $_POST['amount_received'];
    $couponId = $_POST['coupon_id'] ?: null;

    // NEW: Capture Manual Discount
    $manualDiscount = floatval($_POST['manual_discount'] ?? 0);

    $notes = "Renewal of #" . $oldId . ". " . $_POST['notes'];

    // Call createBooking with Manual Discount (Updated function signature)
    $result = $manager->createBooking(
        $oldBooking['user_id'],
        $oldBooking['seat_id'],
        $oldBooking['shift_id'],
        $newStartDate,
        $duration,
        'cash',
        $notes,
        $_SESSION['user_id'],
        $amountReceived,
        $couponId,
        $manualDiscount // Pass the new discount
    );

    if ($result['success']) {
        header("Location: user_details.php?id=" . $oldBooking['user_id']);
        exit;
    } else {
        $error = $result['message'];
    }
}
?>

<!DOCTYPE html>
<html lang="en" class="dark">

<head>
    <title>Renew Subscription | Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        bg: '#000',
                        surface: '#111',
                        accent: '#d4b106'
                    }
                }
            }
        }
    </script>
</head>

<body class="min-h-screen flex items-center justify-center bg-black text-white p-4">
    <div id="page-loader" class="fixed inset-0 bg-black z-[100] flex items-center justify-center transition-opacity duration-500">
        <div class="animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-accent"></div>
    </div>
    <script>
        window.addEventListener('load', function() {
            const loader = document.getElementById('page-loader');
            loader.classList.add('opacity-0');
            setTimeout(() => {
                loader.style.display = 'none';
            }, 500);
        });
    </script>
    <div class="w-full max-w-2xl bg-surface border border-zinc-900 p-8 rounded-2xl shadow-2xl">

        <div class="flex justify-between items-start mb-6">
            <div>
                <h1 class="text-xl font-bold">Renew Subscription</h1>
                <p class="text-zinc-500 text-sm">Shift: <?= $oldBooking['shift_name'] ?> (₹<?= number_format($oldBooking['monthly_price']) ?>/mo)</p>
            </div>
            <a href="expiring.php" class="text-xs text-zinc-500 hover:text-white">Cancel</a>
        </div>

        <?php if ($error): ?>
            <div class="bg-red-900/30 text-red-400 p-3 rounded mb-4 text-sm border border-red-900"><?= $error ?></div>
        <?php endif; ?>

        <form method="POST" class="space-y-6">

            <div class="grid grid-cols-2 gap-4 bg-zinc-900 p-4 rounded-lg border border-zinc-800">
                <div>
                    <p class="text-xs text-zinc-500 uppercase font-bold">Current Expiry</p>
                    <p class="text-white font-mono"><?= date('d M Y', strtotime($oldBooking['end_date'])) ?></p>
                </div>
                <div class="text-right">
                    <p class="text-xs text-zinc-500 uppercase font-bold">New Start Date</p>
                    <p class="text-accent font-mono font-bold"><?= date('d M Y', strtotime($newStartDate)) ?></p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                <div>
                    <label class="block text-xs uppercase text-zinc-500 font-bold mb-2">Duration</label>
                    <select name="duration" id="durationSelect" onchange="calculateTotal()" class="w-full bg-black border border-zinc-800 p-3 rounded text-white focus:border-accent outline-none">
                        <option value="1">1 Month</option>
                        <option value="2">2 Months</option>
                        <option value="3">3 Months</option>
                        <option value="6">6 Months</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs uppercase text-zinc-500 font-bold mb-2">Apply Coupon</label>
                    <select name="coupon_id" id="couponSelect" onchange="calculateTotal()" class="w-full bg-black border border-zinc-800 p-3 rounded text-white focus:border-accent outline-none">
                        <option value="" data-type="fixed" data-value="0">-- No Coupon --</option>
                        <?php foreach ($coupons as $c): ?>
                            <option value="<?= $c['id'] ?>" data-type="<?= $c['type'] ?>" data-value="<?= $c['value'] ?>">
                                <?= $c['code'] ?> (<?= $c['type'] == 'fixed' ? '₹' : '' ?><?= intval($c['value']) ?><?= $c['type'] == 'percent' ? '%' : '' ?> Off)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs uppercase text-accent font-bold mb-2">Manual Flat Discount (₹)</label>
                <div class="relative">
                    <span class="absolute left-3 top-3 text-zinc-500">₹</span>
                    <input type="number" name="manual_discount" id="manualDiscountInput" oninput="calculateTotal()" value="0" min="0" step="1"
                        class="w-full bg-black border border-accent/50 p-3 pl-8 rounded text-white focus:border-accent outline-none font-mono font-bold">
                </div>
                <p class="text-[10px] text-zinc-500 mt-1">Directly reduces the bill amount.</p>
            </div>

            <div class="bg-zinc-900/50 p-6 rounded-xl border border-zinc-800">
                <h3 class="text-xs font-bold text-zinc-400 uppercase mb-4 tracking-wider">Payment Breakdown</h3>

                <input type="hidden" id="basePrice" value="<?= $oldBooking['monthly_price'] ?>">

                <div class="space-y-3 mb-6 border-b border-zinc-800 pb-6">
                    <div class="flex justify-between text-sm">
                        <span class="text-zinc-500">Gross Total</span>
                        <span class="font-mono" id="txtGross">₹0</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-emerald-500 font-bold">Total Discount</span>
                        <span class="font-mono text-emerald-500" id="txtDiscount">-₹0</span>
                    </div>
                    <div class="flex justify-between text-xl font-bold mt-2">
                        <span>Net Payable</span>
                        <span class="font-mono text-white" id="txtNet">₹0</span>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-6">
                    <div>
                        <label class="block text-xs uppercase text-emerald-500 font-bold mb-2">Cash Received</label>
                        <input type="number" name="amount_received" id="cashInput" oninput="calculateDue()" step="0.01" required placeholder="0.00"
                            class="w-full bg-black border border-emerald-900 p-3 rounded text-white focus:border-emerald-500 outline-none font-mono text-lg">
                    </div>
                    <div>
                        <label class="block text-xs uppercase text-red-500 font-bold mb-2">Balance Due</label>
                        <input type="text" id="displayDue" value="0" disabled
                            class="w-full bg-red-900/10 border border-red-900/30 p-3 rounded text-red-400 font-mono font-bold text-lg">
                    </div>
                </div>
            </div>

            <div>
                <label class="block text-xs uppercase text-zinc-500 font-bold mb-2">Notes</label>
                <textarea name="notes" rows="2" class="w-full bg-zinc-900 border border-zinc-800 p-3 rounded text-sm text-white focus:border-accent outline-none"></textarea>
            </div>

            <button type="submit" class="w-full bg-accent text-black font-bold py-4 rounded-lg hover:bg-yellow-500 transition shadow-lg">
                Confirm Renewal & Payment
            </button>

        </form>
    </div>

    <script>
        // Global var to store current Net Total
        let currentNetTotal = 0;

        function calculateTotal() {
            // 1. Get Base Values
            const pricePerMonth = parseFloat(document.getElementById('basePrice').value);
            const months = parseInt(document.getElementById('durationSelect').value);
            let gross = pricePerMonth * months;

            // 2. Calculate Coupon Discount
            const couponSelect = document.getElementById('couponSelect');
            const selectedOpt = couponSelect.options[couponSelect.selectedIndex];
            const type = selectedOpt.dataset.type;
            const val = parseFloat(selectedOpt.dataset.value);

            let couponDiscount = 0;
            if (type === 'percent') {
                couponDiscount = gross * (val / 100);
            } else {
                couponDiscount = val;
            }

            // 3. Calculate Manual Discount
            const manualDiscount = parseFloat(document.getElementById('manualDiscountInput').value) || 0;

            // 4. Combine Discounts
            let totalDiscount = couponDiscount + manualDiscount;

            // Safety: Cannot discount more than the price
            if (totalDiscount > gross) totalDiscount = gross;

            // 5. Net Total
            currentNetTotal = gross - totalDiscount;

            // 6. Update UI
            document.getElementById('txtGross').innerText = '₹' + gross.toFixed(2);
            document.getElementById('txtDiscount').innerText = '-₹' + totalDiscount.toFixed(2);
            document.getElementById('txtNet').innerText = '₹' + currentNetTotal.toFixed(2);

            // 7. Recalc Due
            calculateDue();
        }

        function calculateDue() {
            const cash = parseFloat(document.getElementById('cashInput').value) || 0;
            let due = currentNetTotal - cash;

            const dueDisplay = document.getElementById('displayDue');
            dueDisplay.value = due > 0 ? due.toFixed(2) : 0;

            if (due > 0) {
                dueDisplay.classList.remove('text-zinc-500', 'bg-zinc-900', 'border-zinc-800');
                dueDisplay.classList.add('text-red-400', 'bg-red-900/10', 'border-red-900/30');
            } else {
                dueDisplay.classList.remove('text-red-400', 'bg-red-900/10', 'border-red-900/30');
                dueDisplay.classList.add('text-zinc-500', 'bg-zinc-900', 'border-zinc-800');
            }
        }

        // Initialize on Load
        calculateTotal();
    </script>

</body>

</html>