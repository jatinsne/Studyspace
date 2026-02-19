<?php
// 1. INCLUDE ADMIN HEADER
require_once 'includes/header.php';

$success = "";
$error = "";

// 2. HANDLE SHIFT UPDATE
if (isset($_POST['update_shift'])) {
    $id = $_POST['shift_id'];
    $name = $_POST['name'];
    $startTime = $_POST['start_time'];
    $endTime = $_POST['end_time'];
    $price = $_POST['monthly_price'];

    try {
        $stmt = $pdo->prepare("UPDATE shifts SET name=?, start_time=?, end_time=?, monthly_price=? WHERE id=?");
        $stmt->execute([$name, $startTime, $endTime, $price, $id]);
        $success = "Shift '$name' updated successfully!";
    } catch (PDOException $e) {
        $error = "Error updating shift: " . $e->getMessage();
    }
}

// 3. FETCH SHIFTS
$shifts = $pdo->query("SELECT * FROM shifts ORDER BY start_time ASC")->fetchAll();
?>

<div class="max-w-5xl mx-auto">

    <div class="flex justify-between items-center mb-8">
        <div>
            <h1 class="text-2xl font-bold text-white">System Configuration</h1>
            <p class="text-zinc-500 text-sm">Manage Pricing, Shifts, and Hardware Connections.</p>
        </div>
        <div class="flex gap-2">
            <a href="sync_console.php" class="bg-zinc-900 hover:bg-zinc-800 text-white px-4 py-2 rounded-lg text-sm font-bold border border-zinc-800 transition">
                Open Sync Console
            </a>
            <a href="index.php" class="text-zinc-500 hover:text-white transition flex items-center gap-2 px-4 py-2">
                Dashboard
            </a>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="bg-emerald-900/30 text-emerald-400 p-4 rounded-xl mb-6 border border-emerald-900/50 flex items-center gap-3">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
            </svg>
            <?= $success ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="bg-red-900/30 text-red-400 p-4 rounded-xl mb-6 border border-red-900/50 flex items-center gap-3">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <?= $error ?>
        </div>
    <?php endif; ?>


    <div class="mb-12">
        <h2 class="text-lg font-bold text-white mb-4 flex items-center gap-2">
            <svg class="w-5 h-5 text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            Shift & Pricing
        </h2>

        <div class="grid gap-6">
            <?php foreach ($shifts as $shift): ?>
                <form method="POST" class="bg-surface border border-zinc-900 p-6 rounded-xl flex flex-col md:flex-row gap-4 items-end hover:border-zinc-700 transition">
                    <input type="hidden" name="update_shift" value="1">
                    <input type="hidden" name="shift_id" value="<?= $shift['id'] ?>">

                    <div class="flex-1 w-full">
                        <label class="text-[10px] uppercase text-zinc-500 font-bold mb-1 block">Shift Name</label>
                        <input type="text" name="name" value="<?= htmlspecialchars($shift['name']) ?>"
                            class="w-full bg-black border border-zinc-800 p-3 rounded-lg text-white focus:border-accent outline-none transition">
                    </div>

                    <div class="w-full md:w-32">
                        <label class="text-[10px] uppercase text-zinc-500 font-bold mb-1 block">Start</label>
                        <input type="time" name="start_time" value="<?= $shift['start_time'] ?>"
                            class="w-full bg-black border border-zinc-800 p-3 rounded-lg text-white focus:border-accent outline-none font-mono transition">
                    </div>

                    <div class="w-full md:w-32">
                        <label class="text-[10px] uppercase text-zinc-500 font-bold mb-1 block">End</label>
                        <input type="time" name="end_time" value="<?= $shift['end_time'] ?>"
                            class="w-full bg-black border border-zinc-800 p-3 rounded-lg text-white focus:border-accent outline-none font-mono transition">
                    </div>

                    <div class="w-full md:w-40">
                        <label class="text-[10px] uppercase text-emerald-500 font-bold mb-1 block">Monthly Price (₹)</label>
                        <input type="number" name="monthly_price" value="<?= $shift['monthly_price'] ?>"
                            class="w-full bg-black border border-emerald-900 p-3 rounded-lg text-emerald-400 font-bold focus:border-emerald-500 outline-none font-mono transition">
                    </div>

                    <button type="submit" class="w-full md:w-auto bg-white text-black font-bold px-6 py-3 rounded-lg hover:bg-zinc-200 transition shadow-lg shadow-white/5">
                        Save
                    </button>
                </form>
            <?php endforeach; ?>
        </div>
    </div>


    <div class="mb-12">
        <h2 class="text-lg font-bold text-white mb-4 flex items-center gap-2">
            <svg class="w-5 h-5 text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z"></path>
            </svg>
            Hardware Configuration
        </h2>

        <div class="bg-surface border border-zinc-900 p-8 rounded-xl">
            <div class="flex flex-col md:flex-row gap-8 items-center">
                <div class="flex-1">
                    <h3 class="text-white font-bold mb-2">Biometric Connection</h3>
                    <p class="text-zinc-500 text-sm leading-relaxed">
                        This URL is read from your server environment (`.env` file).
                        To manage sync jobs and push users, use the <a href="sync_console.php" class="text-accent hover:underline">Sync Console</a>.
                    </p>
                </div>

                <div class="flex-1 w-full">
                    <label class="text-[10px] uppercase text-zinc-500 font-bold mb-1 block">Active Server URL</label>
                    <div class="relative">
                        <span class="absolute left-3 top-3.5 text-zinc-500">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                            </svg>
                        </span>
                        <input type="url" value="<?= getenv('BIOMETRIC_URL_BASE') ?: 'Not Configured' ?>" readonly
                            class="w-full bg-black/50 border border-zinc-800 p-3 pl-10 rounded-lg text-zinc-400 font-mono cursor-not-allowed">
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<?php require_once 'includes/footer.php'; ?>