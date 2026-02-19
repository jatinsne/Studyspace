<?php
require_once 'admin_check.php';
require_once '../config/Database.php';

$pdo = Database::getInstance()->getConnection();
$id = $_GET['id'] ?? 0;

// 1. FETCH USER DATA TO PRE-FILL FORM
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$id]);
$user = $stmt->fetch();

if (!$user) die("User not found.");
?>

<!DOCTYPE html>
<html lang="en" class="dark">

<head>
    <title>Edit User | Admin</title>
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

<body class="bg-black text-white p-6 flex justify-center min-h-screen">

    <div class="w-full max-w-4xl bg-surface border border-zinc-900 p-8 rounded-2xl shadow-2xl h-fit relative overflow-hidden">

        <div class="flex justify-between items-center mb-8 border-b border-zinc-800 pb-4">
            <div>
                <h1 class="text-2xl font-bold text-white">Edit Student Details</h1>
                <p class="text-zinc-500 text-sm">Update info for: <span class="text-accent font-bold"><?= htmlspecialchars($user['name']) ?></span></p>
            </div>
            <a href="user_details.php?id=<?= $id ?>" class="text-sm text-zinc-400 hover:text-white bg-zinc-800 hover:bg-zinc-700 px-4 py-2 rounded-lg transition">
                ← Back to Profile
            </a>
        </div>

        <div id="alertBox" class="hidden p-4 rounded-xl mb-6 text-sm font-bold flex items-center gap-3 border transition-all">
            <div id="alertIcon"></div>
            <div id="alertText"></div>
        </div>

        <form id="editUserForm" enctype="multipart/form-data" class="space-y-8">
            <input type="hidden" name="id" value="<?= $id ?>">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-[10px] uppercase text-zinc-500 font-bold tracking-widest mb-2">Full Name</label>
                    <input type="text" name="name" value="<?= htmlspecialchars($user['name']) ?>" required
                        class="w-full bg-black border border-zinc-800 p-3 rounded-lg text-white focus:border-accent outline-none transition">
                </div>
                <div>
                    <label class="block text-[10px] uppercase text-zinc-500 font-bold tracking-widest mb-2">Phone Number</label>
                    <input type="text" name="phone" value="<?= htmlspecialchars($user['phone']) ?>" required
                        class="w-full bg-black border border-zinc-800 p-3 rounded-lg text-white focus:border-accent outline-none font-mono transition">
                </div>
                <div>
                    <label class="block text-[10px] uppercase text-zinc-500 font-bold tracking-widest mb-2">Email Address</label>
                    <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>"
                        class="w-full bg-black border border-zinc-800 p-3 rounded-lg text-white focus:border-accent outline-none transition">
                </div>
                <div>
                    <label class="block text-[10px] uppercase text-zinc-500 font-bold tracking-widest mb-2">Aadhaar Number</label>
                    <input type="text" name="aadhaar_number" value="<?= htmlspecialchars($user['aadhaar_number']) ?>" placeholder="12-digit number"
                        class="w-full bg-black border border-zinc-800 p-3 rounded-lg text-white focus:border-accent outline-none font-mono tracking-widest transition">
                </div>
            </div>

            <div class="bg-zinc-900/30 border border-zinc-800 p-6 rounded-xl">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-xs font-bold text-accent uppercase tracking-widest">Hardware Access</h3>
                    <label class="flex items-center gap-2 cursor-pointer group">
                        <input type="checkbox" name="biometric_enable" value="1" <?= $user['biometric_enable'] ? 'checked' : '' ?> class="accent-emerald-500 w-4 h-4 cursor-pointer">
                        <span class="text-xs font-bold text-zinc-400 group-hover:text-white transition">Enable Device Access</span>
                    </label>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-[10px] uppercase text-zinc-500 font-bold tracking-widest mb-2">Biometric ID</label>
                        <div class="relative">
                            <span class="absolute left-3 top-3.5 text-zinc-600">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11c0 3.517-1.009 6.799-2.753 9.571m-3.44-2.04l.054-.09A13.916 13.916 0 008 11a4 4 0 118 0c0 1.017-.07 2.019-.203 3m-2.118 6.844A21.88 21.88 0 0015.171 17m3.839 1.132c.645-2.266.99-4.659.99-7.132A8 8 0 008 4.07M3 15.364c.64-1.319 1-2.8 1-4.364 0-1.457.2-2.848.578-4.13m4.474 12.872a3.91 3.91 0 01-1.306-1.558M20.25 15.364c-.64-1.319-1-2.8-1-4.364 0-1.457-.2-2.848-.578-4.13"></path>
                                </svg>
                            </span>
                            <input type="text" name="biometric_id" value="<?= htmlspecialchars($user['biometric_id'] ?? '') ?>" placeholder="Fingerprint ID"
                                class="w-full bg-black border border-zinc-700 p-3 pl-10 rounded-lg text-white focus:border-accent outline-none font-mono transition">
                        </div>
                    </div>
                    <div>
                        <label class="block text-[10px] uppercase text-zinc-500 font-bold tracking-widest mb-2">RFID Card ID</label>
                        <div class="relative">
                            <span class="absolute left-3 top-3.5 text-zinc-600">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                                </svg>
                            </span>
                            <input type="text" name="card_id" value="<?= htmlspecialchars($user['card_id'] ?? '') ?>" placeholder="Card/Tag ID"
                                class="w-full bg-black border border-zinc-700 p-3 pl-10 rounded-lg text-white focus:border-accent outline-none font-mono transition">
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-between bg-black border border-zinc-700 p-4 rounded-lg mt-6 shadow-inner">
                    <div>
                        <h4 class="text-sm font-bold text-white">Sync Immediately</h4>
                        <p class="text-[10px] text-zinc-500 mt-0.5">Push changes to hardware device instantly via API.</p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="sync_instantly" value="1" class="sr-only peer" checked>
                        <div class="w-11 h-6 bg-zinc-800 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-zinc-300 after:border-zinc-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-accent peer-checked:after:bg-black"></div>
                    </label>
                </div>
            </div>

            <div class="bg-zinc-900/30 border border-zinc-800 p-6 rounded-xl">
                <h3 class="text-xs font-bold text-accent uppercase tracking-widest mb-6">Subscription Override</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-[10px] uppercase text-zinc-500 font-bold tracking-widest mb-2">Access Start Date</label>
                        <input type="date" name="subscription_startdate" value="<?= $user['subscription_startdate'] ?>"
                            class="w-full bg-black border border-zinc-700 p-3 rounded-lg text-white focus:border-accent outline-none transition">
                    </div>
                    <div>
                        <label class="block text-[10px] uppercase text-zinc-500 font-bold tracking-widest mb-2">Access End Date</label>
                        <input type="date" name="subscription_enddate" value="<?= $user['subscription_enddate'] ?>"
                            class="w-full bg-black border border-zinc-700 p-3 rounded-lg text-white focus:border-accent outline-none transition">
                    </div>
                </div>
                <p class="text-[10px] text-zinc-500 mt-3">* These dates are automatically updated by the Subscription system, but can be manually overridden here.</p>
            </div>

            <div class="bg-zinc-900/50 p-5 rounded-xl border border-zinc-800">
                <label class="block text-[10px] uppercase text-zinc-500 font-bold tracking-widest mb-3">Verification Status</label>
                <div class="flex gap-6">
                    <label class="flex items-center gap-2 cursor-pointer group">
                        <input type="radio" name="verification_status" value="verified" <?= $user['verification_status'] == 'verified' ? 'checked' : '' ?> class="accent-emerald-500 cursor-pointer">
                        <span class="text-emerald-400/70 group-hover:text-emerald-400 font-bold text-sm transition">Verified</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer group">
                        <input type="radio" name="verification_status" value="pending" <?= $user['verification_status'] == 'pending' ? 'checked' : '' ?> class="accent-yellow-500 cursor-pointer">
                        <span class="text-yellow-400/70 group-hover:text-yellow-400 font-bold text-sm transition">Pending</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer group">
                        <input type="radio" name="verification_status" value="rejected" <?= $user['verification_status'] == 'rejected' ? 'checked' : '' ?> class="accent-red-500 cursor-pointer">
                        <span class="text-red-400/70 group-hover:text-red-400 font-bold text-sm transition">Rejected</span>
                    </label>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-[10px] uppercase text-zinc-500 font-bold tracking-widest mb-2">Update Profile Photo</label>
                    <div class="flex items-center gap-4 bg-zinc-900/30 p-4 rounded-xl border border-zinc-800">
                        <?php if ($user['profile_image']): ?>
                            <img src="../uploads/<?= $user['profile_image'] ?>" class="w-12 h-12 rounded-full object-cover border-2 border-zinc-700">
                        <?php else: ?>
                            <div class="w-12 h-12 rounded-full bg-zinc-800 flex items-center justify-center text-[10px] font-bold text-zinc-500">No Pic</div>
                        <?php endif; ?>
                        <input type="file" name="profile_image" accept="image/*" class="text-xs text-zinc-400 file:mr-3 file:py-1.5 file:px-4 file:rounded-full file:border-0 file:bg-zinc-800 file:text-white hover:file:bg-zinc-700 file:cursor-pointer file:transition">
                    </div>
                </div>

                <div>
                    <label class="block text-[10px] uppercase text-zinc-500 font-bold tracking-widest mb-2">Update ID Proof</label>
                    <div class="bg-zinc-900/30 p-4 rounded-xl border border-zinc-800 flex items-center h-[82px]">
                        <input type="file" name="doc_proof" accept="image/*,.pdf" class="w-full text-xs text-zinc-400 file:mr-3 file:py-1.5 file:px-4 file:rounded-full file:border-0 file:bg-zinc-800 file:text-white hover:file:bg-zinc-700 file:cursor-pointer file:transition">
                    </div>
                </div>
            </div>

            <button type="submit" id="submitBtn" class="w-full relative flex items-center justify-center bg-white text-black font-bold py-4 rounded-xl hover:bg-zinc-200 transition text-lg shadow-lg">
                <span id="btnText">Save Changes</span>

                <svg id="btnSpinner" class="hidden animate-spin h-5 w-5 absolute right-6 text-black" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
            </button>

        </form>
    </div>

    <script>
        const form = document.getElementById('editUserForm');
        const btn = document.getElementById('submitBtn');
        const btnText = document.getElementById('btnText');
        const btnSpinner = document.getElementById('btnSpinner');
        const alertBox = document.getElementById('alertBox');
        const alertText = document.getElementById('alertText');
        const alertIcon = document.getElementById('alertIcon');

        form.addEventListener('submit', function(e) {
            e.preventDefault();

            // 1. Set UI to Loading State
            btn.disabled = true;
            btnText.innerText = "Saving Changes...";
            btnSpinner.classList.remove('hidden');
            alertBox.classList.add('hidden');
            btn.classList.add('opacity-80', 'cursor-not-allowed');

            const formData = new FormData(form);

            // 2. Make API Call
            fetch('ajax_edit_user.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    // 3. Reset Button UI
                    btn.disabled = false;
                    btnText.innerText = "Save Changes";
                    btnSpinner.classList.add('hidden');
                    btn.classList.remove('opacity-80', 'cursor-not-allowed');

                    // Strip old color classes from alert box
                    alertBox.classList.remove('hidden', 'bg-emerald-900/30', 'border-emerald-900', 'text-emerald-400', 'bg-red-900/30', 'border-red-900', 'text-red-400');

                    // 4. Handle Response
                    if (data.status === 'success') {
                        // Success Styling
                        alertBox.classList.add('bg-emerald-900/30', 'border-emerald-900', 'text-emerald-400');
                        alertIcon.innerHTML = `<svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>`;
                        alertText.innerHTML = data.message;

                        // If queue was used instead of instant, silently trigger the background worker
                        if (data.trigger_queue) {
                            fetch('ajax_push_batch.php').catch(err => console.error("Worker failed", err));
                        }
                    } else {
                        // Error Styling
                        alertBox.classList.add('bg-red-900/30', 'border-red-900', 'text-red-400');
                        alertIcon.innerHTML = `<svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>`;
                        alertText.innerHTML = data.message;
                    }

                    // Scroll smoothly to the top to see the alert
                    window.scrollTo({
                        top: 0,
                        behavior: 'smooth'
                    });
                })
                .catch(error => {
                    // Handle Network Errors
                    console.error('Error:', error);
                    btn.disabled = false;
                    btnText.innerText = "Save Changes";
                    btnSpinner.classList.add('hidden');
                    btn.classList.remove('opacity-80', 'cursor-not-allowed');

                    alertBox.classList.remove('hidden');
                    alertBox.className = 'p-4 rounded-xl mb-6 text-sm font-bold flex items-center gap-3 border transition-all bg-red-900/30 border-red-900 text-red-400';
                    alertIcon.innerHTML = `<svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>`;
                    alertText.innerText = "A network error occurred. Please try again.";
                    window.scrollTo({
                        top: 0,
                        behavior: 'smooth'
                    });
                });
        });
    </script>
</body>

</html>