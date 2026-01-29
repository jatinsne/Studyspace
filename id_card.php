<?php
// Include the main header (handles Session, DB, and Nav)
require_once 'includes/header.php';

// 1. Fetch User Details (Fresh Data)
$userStmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$userStmt->execute([$_SESSION['user_id']]);
$user = $userStmt->fetch();

// 2. Fetch ALL Active, Paid Subscriptions
$subStmt = $pdo->prepare("
    SELECT s.*, st.label as seat_label, sh.name as shift_name, sh.start_time, sh.end_time
    FROM subscriptions s
    JOIN seats st ON s.seat_id = st.id
    JOIN shifts sh ON s.shift_id = sh.id
    WHERE s.user_id = ? 
    AND s.payment_status = 'paid' 
    AND s.end_date >= CURDATE()
    ORDER BY s.end_date DESC
");
$subStmt->execute([$_SESSION['user_id']]);
$activeSubs = $subStmt->fetchAll();

// 3. Generate QR Content (JSON)
$qrData = json_encode([
    'id' => $user['id'],
    'name' => $user['name'],
    'phone' => $user['phone'],
    'valid' => count($activeSubs) > 0
]);
// QR API
$qrUrl = "https://quickchart.io/qr?text=" . urlencode($qrData) . "&size=300&dark=000000&light=ffffff&margin=1";

// 4. Image Logic
$profilePath = 'uploads/' . $user['profile_image'];
$hasImage = !empty($user['profile_image']) && file_exists($profilePath);
?>

<div class="min-h-[calc(100vh-64px)] flex flex-col items-center justify-center p-4">

    <div class="w-full max-w-sm mb-6 flex justify-between items-center no-print">
        <h1 class="text-xl font-bold text-white">Digital Pass</h1>
        <button onclick="window.print()" class="bg-zinc-800 hover:bg-zinc-700 text-white px-4 py-2 rounded-lg text-xs font-bold transition flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
            </svg>
            Print ID
        </button>
    </div>

    <?php if (empty($activeSubs)): ?>
        <div class="text-center text-zinc-500 py-12 bg-surface border border-zinc-800 rounded-xl p-8 w-full max-w-sm">
            <svg class="w-16 h-16 mx-auto mb-4 opacity-30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"></path>
            </svg>
            <h2 class="text-xl font-bold text-white mb-2">No Active Pass</h2>
            <p class="text-sm mb-6">You need an active subscription to generate an ID.</p>
            <a href="dashboard.php" class="inline-block bg-accent text-black font-bold px-6 py-2 rounded-lg">Book a Seat</a>
        </div>
    <?php else: ?>

        <div class="w-full max-w-sm space-y-8">

            <?php foreach ($activeSubs as $sub): ?>

                <div class="id-card bg-gradient-to-br from-zinc-900 to-black border border-zinc-800 rounded-2xl p-6 shadow-2xl relative overflow-hidden">

                    <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-transparent via-white/20 to-transparent"></div>

                    <div class="flex justify-between items-start mb-6 relative z-10">
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 rounded-lg bg-white flex items-center justify-center">
                                <span class="font-black text-black text-sm">S</span>
                            </div>
                            <span class="font-bold text-xs tracking-[0.2em] uppercase text-zinc-400">StudySpace</span>
                        </div>

                        <?php if ($user['verification_status'] == 'verified'): ?>
                            <div class="flex flex-col items-end">
                                <span class="bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider">Verified</span>
                            </div>
                        <?php else: ?>
                            <span class="bg-yellow-500/10 text-yellow-400 border border-yellow-500/20 px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider">Pending</span>
                        <?php endif; ?>
                    </div>

                    <div class="flex gap-5 relative z-10">
                        <div class="shrink-0">
                            <?php if ($hasImage): ?>
                                <img src="<?= $profilePath ?>" class="w-24 h-28 rounded-lg object-cover border border-zinc-700 bg-zinc-800">
                            <?php else: ?>
                                <div class="w-24 h-28 rounded-lg bg-zinc-800 border border-zinc-700 flex flex-col items-center justify-center text-zinc-500">
                                    <svg class="w-8 h-8 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                    </svg>
                                    <span class="text-[10px]">No Photo</span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="flex-1 flex flex-col justify-between">
                            <div>
                                <h2 class="text-lg font-bold text-white leading-tight mb-0.5"><?= htmlspecialchars($user['name']) ?></h2>
                                <p class="text-[10px] text-zinc-500 font-mono">ID: <?= str_pad($user['id'], 5, '0', STR_PAD_LEFT) ?></p>
                            </div>

                            <div class="mt-2 bg-white p-1 rounded w-fit">
                                <img src="<?= $qrUrl ?>" alt="QR" class="w-14 h-14">
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 pt-4 border-t border-zinc-800 grid grid-cols-2 gap-4 relative z-10">
                        <div>
                            <p class="text-[10px] uppercase text-zinc-500 font-bold mb-1">Seat & Shift</p>
                            <div class="flex items-baseline gap-2">
                                <span class="text-xl font-mono font-bold text-accent"><?= $sub['seat_label'] ?></span>
                                <span class="text-xs text-white truncate"><?= $sub['shift_name'] ?></span>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="text-[10px] uppercase text-zinc-500 font-bold mb-1">Expires On</p>
                            <p class="text-sm font-bold text-white"><?= date('d M Y', strtotime($sub['end_date'])) ?></p>
                        </div>
                    </div>

                </div>
                <div class="text-center no-print">
                    <p class="text-zinc-600 text-xs mb-2">Photo outdated or incorrect?</p>
                    <a href="edit_profile.php" class="text-xs font-bold text-zinc-400 hover:text-white border-b border-zinc-700 hover:border-white pb-0.5 transition">
                        Update Profile Photo
                    </a>
                </div>

            <?php endforeach; ?>

        </div>
    <?php endif; ?>

</div>

<style>
    @media print {
        @page {
            margin: 0;
        }

        /* 1. HIDE THE HEADER & BUTTONS */
        nav,
        header,
        .no-print {
            display: none !important;
        }

        /* 2. RESET BODY BACKGROUND */
        body {
            background: white;
            -webkit-print-color-adjust: exact;
            padding: 20px !important;
        }

        /* 3. CENTER THE CARD */
        .min-h-\[calc\(100vh-64px\)\] {
            min-height: auto !important;
            display: block !important;
        }

        /* 4. ID CARD PRINT STYLING */
        .id-card {
            border: 2px solid #000;
            background: #fff !important;
            color: #000 !important;
            break-inside: avoid;
            box-shadow: none !important;
            margin-bottom: 20px;
            page-break-inside: avoid;
        }

        /* 5. FORCE BLACK TEXT FOR READABILITY */
        .id-card * {
            color: #000 !important;
            border-color: #ccc !important;
        }

        /* Keep accents bold but black */
        .text-accent,
        .text-emerald-400,
        .text-yellow-400 {
            color: #000 !important;
            font-weight: 900 !important;
        }

        /* Ensure QR code and Photos print clearly */
        img {
            filter: contrast(120%);
        }
    }
</style>

</body>

</html>