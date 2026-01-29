<?php
require_once 'admin_check.php';
require_once '../config/Database.php';

$pdo = Database::getInstance()->getConnection();
$id = $_GET['id'] ?? 0;
$message = "";

// 1. Fetch Request
$stmt = $pdo->prepare("
    SELECT s.*, u.name, st.label, sh.name as shift_name 
    FROM subscriptions s
    JOIN users u ON s.user_id = u.id
    JOIN seats st ON s.seat_id = st.id
    JOIN shifts sh ON s.shift_id = sh.id
    WHERE s.id = ? AND s.payment_status = 'pending'
");
$stmt->execute([$id]);
$booking = $stmt->fetch();

if (!$booking) die("Request not found or already approved.");

// 2. Handle Approval
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cashReceived = $_POST['amount_received'];
    $adminNotes = "Approved by Admin. " . $_POST['notes'];

    // Logic: Update Payment Info
    $paidAmount = $cashReceived;
    $dueAmount = $booking['final_amount'] - $paidAmount;
    $status = 'paid'; // Now it becomes active

    $update = $pdo->prepare("
        UPDATE subscriptions 
        SET paid_amount = ?, due_amount = ?, payment_status = ?, payment_method = 'cash', notes = ?, collected_by = ?
        WHERE id = ?
    ");
    $update->execute([$paidAmount, $dueAmount, $status, $adminNotes, $_SESSION['user_id'], $id]);

    header("Location: user_details.php?id=" . $booking['user_id']);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en" class="dark">

<head>
    <title>Approve Booking | Admin</title>
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

    <div class="w-full max-w-lg bg-surface border border-zinc-900 p-8 rounded-2xl shadow-2xl">
        <div class="mb-6">
            <h1 class="text-xl font-bold text-emerald-400">Confirm Booking</h1>
            <p class="text-zinc-500 text-sm">Collect payment to activate seat.</p>
        </div>

        <div class="bg-zinc-900 p-4 rounded-lg border border-zinc-800 mb-6">
            <div class="flex justify-between mb-2">
                <span class="text-zinc-500 text-xs uppercase">Student</span>
                <span class="font-bold"><?= $booking['name'] ?></span>
            </div>
            <div class="flex justify-between mb-2">
                <span class="text-zinc-500 text-xs uppercase">Seat</span>
                <span class="font-bold text-accent"><?= $booking['label'] ?></span>
            </div>
            <div class="flex justify-between border-t border-zinc-700 pt-2 mt-2">
                <span class="text-zinc-400 text-sm">Total Bill</span>
                <span class="font-mono font-bold text-lg">₹<?= number_format($booking['final_amount']) ?></span>
            </div>
        </div>

        <form method="POST" class="space-y-6">
            <div>
                <label class="block text-xs uppercase text-emerald-500 font-bold mb-2">Cash Received Now</label>
                <input type="number" name="amount_received" required value="<?= intval($booking['final_amount']) ?>"
                    class="w-full bg-black border border-emerald-900 p-3 rounded text-white focus:border-emerald-500 outline-none font-mono text-lg">
                <p class="text-[10px] text-zinc-500 mt-2">Any unpaid amount will be added to Dues automatically.</p>
            </div>

            <div>
                <label class="block text-xs uppercase text-zinc-500 font-bold mb-2">Notes</label>
                <textarea name="notes" rows="2" class="w-full bg-zinc-900 border border-zinc-800 p-3 rounded text-sm text-white focus:border-accent outline-none"></textarea>
            </div>

            <div class="flex gap-4">
                <a href="approvals.php" class="w-1/3 py-3 text-center border border-zinc-800 rounded-lg text-zinc-500 hover:text-white">Cancel</a>
                <button type="submit" class="w-2/3 bg-white text-black font-bold py-3 rounded-lg hover:bg-zinc-200">
                    Confirm & Activate
                </button>
            </div>
        </form>
    </div>

</body>

</html>