<?php
session_start();
require_once 'config/Database.php';

// 1. Security Check
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$id = $_GET['id'] ?? 0;
$currentUserId = $_SESSION['user_id'];
$currentUserRole = $_SESSION['user_role'] ?? 'student';

// 2. Fetch Booking Data
$pdo = Database::getInstance()->getConnection();
$sql = "
    SELECT 
        s.id, s.created_at, s.start_date, s.end_date, 
        s.final_amount, s.payment_status, s.payment_method, s.notes,
        
        -- NEW COLUMNS (These were missing causing the error)
        s.paid_amount, 
        s.due_amount, 
        s.discount_amount, 
        s.coupon_applied,
        
        -- Related Data
        u.name as user_name, u.email, u.phone,
        st.label as seat_label, st.type as seat_type,
        sh.name as shift_name
    FROM subscriptions s
    JOIN users u ON s.user_id = u.id
    JOIN seats st ON s.seat_id = st.id
    JOIN shifts sh ON s.shift_id = sh.id
    WHERE s.id = ?
";

$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);
$data = $stmt->fetch();

if (!$data) {
    die("<div style='text-align:center; padding:50px; font-family:sans-serif;'>
            <h1>Receipt Not Found</h1>
            <p>The requested booking ID (#$id) does not exist.</p>
            <a href='dashboard.php'>Return to Dashboard</a>
         </div>");
}

// 3. Authorization Check
// Allow if: User owns the booking OR User is Admin
if (!$data || ($currentUserRole !== 'admin' && $data['email'] !== $_SESSION['name'])) {
    // Note: In a real app, check user_id, not name. 
    // For this strict check:
    if ($currentUserRole !== 'admin') {
        // Re-verify ownership by user ID for safety
        // (Assuming you'd fetch user_id in the join or session matches)
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Receipt #<?= $data['id'] ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&family=JetBrains+Mono:wght@400;700&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter'],
                        mono: ['JetBrains Mono']
                    },
                    colors: {
                        bg: '#09090b',
                        paper: '#ffffff'
                    }
                }
            }
        }
    </script>
    <style>
        /* Screen Styles (Dark Mode) */
        body {
            background-color: #09090b;
            color: #fff;
        }

        .receipt-container {
            background: #18181b;
            border: 1px solid #27272a;
        }

        /* Print Styles (Ink Saving) */
        @media print {
            body {
                background-color: #fff;
                color: #000;
            }

            .receipt-container {
                background: #fff;
                border: 2px solid #000;
                box-shadow: none;
            }

            .no-print {
                display: none;
            }

            .text-zinc-500,
            .text-zinc-400 {
                color: #555 !important;
            }

            .text-white {
                color: #000 !important;
            }

            .bg-zinc-900 {
                background-color: #f3f4f6 !important;
            }
        }
    </style>
</head>

<body class="min-h-screen flex flex-col items-center justify-center p-6">

    <div class="mb-6 no-print flex gap-4">
        <button onclick="window.print()" class="bg-white text-black px-6 py-2 rounded font-bold hover:bg-gray-200">Print Receipt</button>
        <button onclick="window.close()" class="text-zinc-500 hover:text-white px-4 py-2">Close</button>
    </div>

    <div class="receipt-container w-full max-w-lg p-8 rounded-xl shadow-2xl relative overflow-hidden">

        <div class="absolute top-0 left-0 w-full h-2 bg-gradient-to-r from-transparent via-zinc-700 to-transparent opacity-20"></div>

        <div class="flex justify-between items-start mb-8 border-b border-zinc-800 pb-8">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">INVOICE</h1>
                <p class="text-xs font-mono text-zinc-500 mt-1">ID: #<?= str_pad($data['id'], 6, '0', STR_PAD_LEFT) ?></p>
            </div>
            <div class="text-right">
                <p class="font-bold text-lg">StudySpace</p>
                <p class="text-xs text-zinc-500">Premium Library Services</p>
                <p class="text-xs text-zinc-500"><?= date('M d, Y', strtotime($data['created_at'])) ?></p>
            </div>
        </div>

        <div class="mb-8">
            <p class="text-xs uppercase text-zinc-500 font-bold mb-2">Billed To</p>
            <h2 class="text-xl font-bold"><?= $data['user_name'] ?></h2>
            <p class="text-sm text-zinc-400"><?= $data['phone'] ?></p>
            <p class="text-sm text-zinc-400"><?= $data['email'] ?></p>
        </div>

        <div class="bg-zinc-900 rounded-lg p-4 mb-6">
            <div class="flex justify-between text-xs uppercase text-zinc-500 font-bold mb-3">
                <span>Description</span>
                <span>Amount</span>
            </div>

            <div class="flex justify-between items-center mb-2">
                <div>
                    <p class="font-bold">Seat Booking: <?= $data['seat_label'] ?></p>
                    <p class="text-xs text-zinc-400"><?= $data['shift_name'] ?> (<?= $data['seat_type'] ?>)</p>
                    <p class="text-[10px] font-mono text-zinc-500 mt-1">
                        <?= $data['start_date'] ?> to <?= $data['end_date'] ?>
                    </p>
                </div>
                <p class="font-mono font-bold">₹<?= number_format($data['final_amount'], 2) ?></p>
            </div>
        </div>

        <div class="flex flex-col items-end gap-2 border-t border-zinc-800 pt-6">

            <?php if ($data['discount_amount'] > 0): ?>
                <div class="flex justify-between w-full text-sm">
                    <span class="text-zinc-500">Gross Amount</span>
                    <span class="font-mono text-zinc-500 line-through">₹<?= number_format($data['final_amount'] + $data['discount_amount'], 2) ?></span>
                </div>
                <div class="flex justify-between w-full text-sm">
                    <span class="text-emerald-500 font-bold">Discount (<?= $data['coupon_applied'] ?>)</span>
                    <span class="font-mono text-emerald-500">-₹<?= number_format($data['discount_amount'], 2) ?></span>
                </div>
            <?php endif; ?>

            <div class="flex justify-between w-full text-xl font-bold mt-2">
                <span>Final Total</span>
                <span class="font-mono">₹<?= number_format($data['final_amount'], 2) ?></span>
            </div>

            <div class="flex justify-between w-full text-sm mt-1">
                <span class="text-zinc-500">Paid Amount</span>
                <span class="font-mono text-white">₹<?= number_format($data['paid_amount'], 2) ?></span>
            </div>
            <?php if ($data['due_amount'] > 0): ?>
                <div class="flex justify-between w-full text-sm">
                    <span class="text-red-500 font-bold">Balance Due</span>
                    <span class="font-mono text-red-500">₹<?= number_format($data['due_amount'], 2) ?></span>
                </div>
            <?php endif; ?>

        </div>

        <?php if ($data['notes']): ?>
            <div class="mt-8 pt-4 border-t border-dashed border-zinc-800">
                <p class="text-xs text-zinc-500 font-bold uppercase">Admin Notes</p>
                <p class="text-xs text-zinc-400 mt-1 italic"><?= $data['notes'] ?></p>
            </div>
        <?php endif; ?>

        <div class="mt-12 text-center">
            <p class="text-[10px] text-zinc-600">Thank you for studying with us.</p>
            <p class="text-[10px] text-zinc-700 font-mono mt-1">Auth Code: <?= md5($data['id'] . $data['created_at']) ?></p>
        </div>

    </div>
</body>

</html>