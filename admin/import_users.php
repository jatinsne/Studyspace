<?php
require_once 'includes/header.php';

$pdo = Database::getInstance()->getConnection();
$deviceId = getenv('BIOMETRIC_DEVICE_ID');
$baseUrl = getenv('BIOMETRIC_URL_BASE');

// 1. Fetch Users from Device API
$apiUrl = rtrim($baseUrl, '/') . "/api/device/$deviceId/users";

$ch = curl_init();
curl_setopt_array($ch, array(
    CURLOPT_URL => $apiUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_CUSTOMREQUEST => 'GET',
));

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err = curl_error($ch);
//curl_close($ch);

$deviceUsers = [];
$apiError = false;

if ($err || $httpCode >= 400) {
    $apiError = "Failed to connect to device API. Please check your configuration.";
} else {
    $json = json_decode($response, true);
    // Adjust based on your API's actual response structure (e.g., $json['data'], $json['users'], or just $json)
    $deviceUsers = $json['users'] ?? $json['data'] ?? $json ?? [];
}

// 2. Fetch Local Users to prevent duplicates
$localStmt = $pdo->query("SELECT biometric_id FROM users WHERE biometric_id IS NOT NULL AND biometric_id != ''");
$localBioIds = $localStmt->fetchAll(PDO::FETCH_COLUMN);

// 3. Categorize Users
$newUsers = [];
$existingUsers = [];

foreach ($deviceUsers as $du) {
    // Standardize the ID field based on common API responses (user_id or UserID)
    $id = $du['user_id'] ?? $du['UserID'] ?? $du['id'] ?? null;
    $name = $du['name'] ?? $du['Name'] ?? 'Unknown';
    $card = $du['card'] ?? $du['Card'] ?? '';

    if (!$id) continue; // Skip invalid records

    $userData = [
        'id' => $id,
        'name' => $name,
        'card' => $card,
        'raw_json' => json_encode($du) // Store raw data for the JS to send to the backend
    ];

    if (in_array((string)$id, $localBioIds)) {
        $existingUsers[] = $userData;
    } else {
        $newUsers[] = $userData;
    }
}
?>

<div class="max-w-7xl mx-auto px-6 py-8 w-full">

    <div class="flex flex-col md:flex-row justify-between items-start md:items-end mb-8 gap-4">
        <div>
            <h1 class="text-3xl font-bold tracking-tight text-white flex items-center gap-3">
                <svg class="w-8 h-8 text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path>
                </svg>
                Import Device Users
            </h1>
            <p class="text-zinc-500 mt-1">Fetch and sync unlinked users directly from hardware device <span class="font-mono text-zinc-400"><?= $deviceId ?></span>.</p>
        </div>
        <a href="users.php" class="text-sm font-bold text-zinc-400 hover:text-white transition bg-zinc-900 px-4 py-2 rounded-lg border border-zinc-800">
            ← Back to Directory
        </a>
    </div>

    <?php if ($apiError): ?>
        <div class="bg-red-900/30 border border-red-900/50 p-4 rounded-xl text-red-400 font-bold mb-6">
            <?= $apiError ?>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="bg-zinc-900/50 border border-zinc-800 p-6 rounded-2xl">
            <p class="text-[10px] uppercase text-zinc-500 font-bold tracking-widest mb-1">Total on Device</p>
            <p class="text-3xl font-mono font-bold text-white"><?= count($deviceUsers) ?></p>
        </div>
        <div class="bg-emerald-900/10 border border-emerald-900/30 p-6 rounded-2xl">
            <p class="text-[10px] uppercase text-emerald-500/70 font-bold tracking-widest mb-1">New / Unlinked</p>
            <p class="text-3xl font-mono font-bold text-emerald-400"><?= count($newUsers) ?></p>
        </div>
        <div class="bg-zinc-900/50 border border-zinc-800 p-6 rounded-2xl">
            <p class="text-[10px] uppercase text-zinc-500 font-bold tracking-widest mb-1">Already Synced</p>
            <p class="text-3xl font-mono font-bold text-zinc-500"><?= count($existingUsers) ?></p>
        </div>
    </div>

    <div id="alertBox" class="hidden p-4 rounded-xl mb-6 text-sm font-bold items-center gap-3 border transition-all">
        <div id="alertIcon"></div>
        <div id="alertText"></div>
    </div>

    <form id="importForm" class="bg-zinc-900/30 border border-zinc-800 rounded-2xl overflow-hidden shadow-2xl">

        <div class="p-4 border-b border-zinc-800 flex justify-between items-center bg-zinc-900/50">
            <div class="flex items-center gap-3">
                <label class="flex items-center gap-2 cursor-pointer group">
                    <input type="checkbox" id="selectAll" class="accent-accent w-4 h-4 cursor-pointer">
                    <span class="text-xs font-bold text-zinc-400 group-hover:text-white uppercase tracking-wider">Select All New</span>
                </label>
            </div>

            <button type="submit" id="importBtn" disabled class="bg-accent text-black font-bold px-6 py-2 rounded-lg text-sm transition hover:bg-yellow-500 disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2">
                <span id="btnText">Import Selected</span>
                <svg id="btnSpinner" class="hidden animate-spin h-4 w-4 text-black" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
            </button>
        </div>

        <div class="overflow-x-auto max-h-[600px] overflow-y-auto">
            <table class="w-full text-left text-sm relative">
                <thead class="bg-zinc-900/90 text-zinc-500 uppercase text-[10px] tracking-widest font-bold sticky top-0 z-10 backdrop-blur-md">
                    <tr>
                        <th class="px-6 py-4 w-12"></th>
                        <th class="px-6 py-4">Bio ID</th>
                        <th class="px-6 py-4">Name</th>
                        <th class="px-6 py-4">RFID Card</th>
                        <th class="px-6 py-4 text-right">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-800/50">

                    <?php foreach ($newUsers as $u): ?>
                        <tr class="hover:bg-zinc-900/50 transition">
                            <td class="px-6 py-4">
                                <input type="checkbox" name="users[]" value="<?= htmlspecialchars($u['raw_json']) ?>" class="user-checkbox accent-accent w-4 h-4 cursor-pointer">
                            </td>
                            <td class="px-6 py-4 font-mono text-zinc-400 text-xs"><?= htmlspecialchars($u['id']) ?></td>
                            <td class="px-6 py-4 font-bold text-white"><?= htmlspecialchars($u['name']) ?></td>
                            <td class="px-6 py-4 font-mono text-zinc-500 text-xs"><?= $u['card'] ?: '-' ?></td>
                            <td class="px-6 py-4 text-right">
                                <span class="bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 px-2.5 py-1 rounded text-[10px] font-bold uppercase tracking-widest">Ready</span>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <?php foreach ($existingUsers as $u): ?>
                        <tr class="opacity-50 bg-zinc-900/20">
                            <td class="px-6 py-4">
                                <input type="checkbox" disabled class="w-4 h-4 cursor-not-allowed opacity-30">
                            </td>
                            <td class="px-6 py-4 font-mono text-zinc-500 text-xs"><?= htmlspecialchars($u['id']) ?></td>
                            <td class="px-6 py-4 font-bold text-zinc-400"><?= htmlspecialchars($u['name']) ?></td>
                            <td class="px-6 py-4 font-mono text-zinc-600 text-xs"><?= $u['card'] ?: '-' ?></td>
                            <td class="px-6 py-4 text-right">
                                <span class="text-zinc-600 text-[10px] font-bold uppercase tracking-widest">Already Linked</span>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (empty($newUsers) && empty($existingUsers) && !$apiError): ?>
                        <tr>
                            <td colspan="5" class="p-12 text-center text-zinc-600">No users found on the device.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </form>
</div>

<script>
    const selectAll = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.user-checkbox');
    const importBtn = document.getElementById('importBtn');
    const form = document.getElementById('importForm');

    // Handle Select All
    selectAll.addEventListener('change', (e) => {
        checkboxes.forEach(cb => {
            if (!cb.disabled) cb.checked = e.target.checked;
        });
        updateBtnState();
    });

    // Handle Individual Checks
    checkboxes.forEach(cb => {
        cb.addEventListener('change', updateBtnState);
    });

    function updateBtnState() {
        const checkedCount = document.querySelectorAll('.user-checkbox:checked').length;
        importBtn.disabled = checkedCount === 0;
        document.getElementById('btnText').innerText = checkedCount > 0 ? `Import ${checkedCount} Users` : 'Import Selected';
    }

    // Handle Form Submit (AJAX)
    form.addEventListener('submit', function(e) {
        e.preventDefault();

        const checkedCount = document.querySelectorAll('.user-checkbox:checked').length;
        if (checkedCount === 0) return;

        // UI Loading State
        importBtn.disabled = true;
        document.getElementById('btnText').innerText = "Processing...";
        document.getElementById('btnSpinner').classList.remove('hidden');

        const formData = new FormData(form);

        fetch('ajax_import_users.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                const alertBox = document.getElementById('alertBox');
                const alertIcon = document.getElementById('alertIcon');
                const alertText = document.getElementById('alertText');

                // Reset styles
                alertBox.classList.remove('hidden', 'bg-red-900/30', 'border-red-900', 'text-red-400', 'bg-emerald-900/30', 'border-emerald-900', 'text-emerald-400');
                alertBox.classList.add('flex');

                if (data.status === 'success') {
                    alertBox.classList.add('bg-emerald-900/30', 'border-emerald-900', 'text-emerald-400');
                    alertIcon.innerHTML = `<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>`;
                    alertText.innerHTML = `Successfully imported ${data.imported} users. Reloading...`;

                    setTimeout(() => window.location.reload(), 2000);
                } else {
                    // RESET BUTTON
                    importBtn.disabled = false;
                    document.getElementById('btnText').innerText = "Import Selected";
                    document.getElementById('btnSpinner').classList.add('hidden');

                    // SHOW DB ERROR
                    alertBox.classList.add('bg-red-900/30', 'border-red-900', 'text-red-400');
                    alertIcon.innerHTML = `<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>`;
                    alertText.innerHTML = `Import Failed: ${data.message}`;
                }
            })
            .catch(err => {
                console.error(err);
                importBtn.disabled = false;
                document.getElementById('btnText').innerText = "Import Failed";
                document.getElementById('btnSpinner').classList.add('hidden');

                const alertBox = document.getElementById('alertBox');
                alertBox.classList.remove('hidden');
                alertBox.classList.add('flex', 'bg-red-900/30', 'border-red-900', 'text-red-400');
                document.getElementById('alertText').innerHTML = "A network error occurred.";
            });
    });
</script>

<?php require_once 'includes/footer.php'; ?>