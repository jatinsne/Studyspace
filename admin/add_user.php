<?php
require_once 'admin_check.php';
// No database connection needed here anymore, it's all handled via API
?>
<!DOCTYPE html>
<html lang="en" class="dark">

<head>
    <meta charset="UTF-8">
    <title>New Walk-in | Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        bg: '#000000',
                        surface: '#111111',
                        accent: '#d4b106'
                    }
                }
            }
        }
    </script>
</head>

<body class="min-h-screen flex items-center justify-center p-6 bg-black text-white">

    <div class="w-full max-w-2xl bg-surface border border-zinc-900 p-8 rounded-2xl shadow-2xl relative overflow-hidden">

        <div class="mb-8 flex justify-between items-start">
            <div>
                <h1 class="text-2xl font-bold">New Walk-in Registration</h1>
                <p class="text-zinc-500 text-sm">Create account and upload documents instantly.</p>
            </div>
            <div class="px-3 py-1 bg-zinc-900 border border-zinc-800 rounded text-xs text-zinc-400 shadow-inner">
                Default Pass: <span class="font-mono text-accent font-bold tracking-widest">123456</span>
            </div>
        </div>

        <div id="alertBox" class="hidden p-4 rounded-xl mb-6 text-sm font-bold flex items-center gap-3 border transition-all">
            <div id="alertIcon"></div>
            <div id="alertText"></div>
        </div>

        <form id="addUserForm" enctype="multipart/form-data" class="space-y-6">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="text-[10px] uppercase text-zinc-500 font-bold tracking-widest block mb-1">Full Name</label>
                    <input type="text" name="name" required class="w-full bg-black border border-zinc-800 p-3 rounded-lg text-white focus:border-accent outline-none transition">
                </div>
                <div>
                    <label class="text-[10px] uppercase text-zinc-500 font-bold tracking-widest block mb-1">Phone Number</label>
                    <input type="text" name="phone" required class="w-full bg-black border border-zinc-800 p-3 rounded-lg text-white focus:border-accent outline-none transition font-mono">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="text-[10px] uppercase text-zinc-500 font-bold tracking-widest block mb-1">Email Address</label>
                    <input type="email" name="email" required class="w-full bg-black border border-zinc-800 p-3 rounded-lg text-white focus:border-accent outline-none transition">
                </div>
                <div>
                    <label class="text-[10px] uppercase text-zinc-500 font-bold tracking-widest block mb-1">Aadhaar Number</label>
                    <input type="text" name="aadhaar_number" placeholder="12-digit number" class="w-full bg-black border border-zinc-800 p-3 rounded-lg text-white focus:border-accent outline-none transition font-mono tracking-wider">
                </div>
            </div>

            <div class="bg-zinc-900/50 p-5 rounded-xl border border-zinc-800">
                <h3 class="text-xs uppercase text-accent font-bold tracking-widest mb-4">Access Control & Biometrics</h3>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-5">
                    <div>
                        <label class="text-[10px] uppercase text-zinc-500 font-bold tracking-widest block mb-1">Biometric ID</label>
                        <div class="relative">
                            <span class="absolute left-3 top-3 text-zinc-600"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11c0 3.517-1.009 6.799-2.753 9.571m-3.44-2.04l.054-.09A13.916 13.916 0 008 11a4 4 0 118 0c0 1.017-.07 2.019-.203 3m-2.118 6.844A21.88 21.88 0 0015.171 17m3.839 1.132c.645-2.266.99-4.659.99-7.132A8 8 0 008 4.07M3 15.364c.64-1.319 1-2.8 1-4.364 0-1.457.2-2.848.578-4.13m4.474 12.872a3.91 3.91 0 01-1.306-1.558M20.25 15.364c-.64-1.319-1-2.8-1-4.364 0-1.457-.2-2.848-.578-4.13"></path>
                                </svg></span>
                            <input type="text" name="biometric_id" placeholder="Leave empty for auto-assign" class="w-full bg-black border border-zinc-700 p-3 pl-10 rounded-lg text-white focus:border-accent outline-none transition font-mono placeholder:text-zinc-600">
                        </div>
                    </div>

                    <div>
                        <label class="text-[10px] uppercase text-zinc-500 font-bold tracking-widest block mb-1">RFID Card ID</label>
                        <div class="relative">
                            <span class="absolute left-3 top-3 text-zinc-600"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                                </svg></span>
                            <input type="text" name="card_id" placeholder="Card/Tag ID" class="w-full bg-black border border-zinc-700 p-3 pl-10 rounded-lg text-white focus:border-accent outline-none transition font-mono">
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-between bg-black border border-zinc-700 p-4 rounded-lg">
                    <div>
                        <h4 class="text-sm font-bold text-white">Sync Immediately</h4>
                        <p class="text-[10px] text-zinc-500">Push to hardware device on save via direct API.</p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="sync_instantly" value="1" class="sr-only peer" checked>
                        <div class="w-11 h-6 bg-zinc-800 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-zinc-300 after:border-zinc-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-accent peer-checked:after:bg-black"></div>
                    </label>
                </div>
            </div>

            <hr class="border-zinc-800">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="text-[10px] uppercase text-zinc-500 font-bold tracking-widest block mb-1">Profile Photo</label>
                    <div class="relative group">
                        <input type="file" name="profile_image" accept="image/*" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10">
                        <div class="bg-zinc-900 border border-zinc-800 border-dashed rounded-lg p-4 flex items-center justify-center gap-2 group-hover:border-zinc-600 transition">
                            <svg class="w-5 h-5 text-zinc-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                            <span class="text-xs text-zinc-400">Upload Image</span>
                        </div>
                    </div>
                </div>
                <div>
                    <label class="text-[10px] uppercase text-zinc-500 font-bold tracking-widest block mb-1">ID Proof (Aadhaar)</label>
                    <div class="relative group">
                        <input type="file" name="doc_proof" accept="image/*,.pdf" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10">
                        <div class="bg-zinc-900 border border-zinc-800 border-dashed rounded-lg p-4 flex items-center justify-center gap-2 group-hover:border-zinc-600 transition">
                            <svg class="w-5 h-5 text-zinc-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            <span class="text-xs text-zinc-400">Upload Doc/PDF</span>
                        </div>
                    </div>
                </div>
            </div>

            <button type="submit" id="submitBtn" class="w-full relative flex items-center justify-center bg-accent text-black font-bold py-4 rounded-lg hover:bg-yellow-500 transition mt-4 shadow-lg shadow-accent/10">
                <span id="btnText">Create & Verify Account</span>
                <svg id="btnSpinner" class="hidden animate-spin h-5 w-5 absolute right-4 text-black" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
            </button>
        </form>

        <div class="mt-8 text-center border-t border-zinc-900 pt-6">
            <a href="users.php" class="text-zinc-500 text-xs font-bold uppercase tracking-wider hover:text-white transition">
                ← Return to Directory
            </a>
        </div>
    </div>

    <script>
        const form = document.getElementById('addUserForm');
        const btn = document.getElementById('submitBtn');
        const btnText = document.getElementById('btnText');
        const btnSpinner = document.getElementById('btnSpinner');
        const alertBox = document.getElementById('alertBox');
        const alertText = document.getElementById('alertText');
        const alertIcon = document.getElementById('alertIcon');

        form.addEventListener('submit', function(e) {
            e.preventDefault(); // Prevent page reload

            // 1. UI Loading State
            btn.disabled = true;
            btnText.innerText = "Processing...";
            btnSpinner.classList.remove('hidden');
            alertBox.classList.add('hidden'); // Hide old alerts
            btn.classList.add('opacity-80', 'cursor-not-allowed');

            // 2. Prepare Data (handles files automatically)
            const formData = new FormData(form);

            // 3. API Call
            fetch('ajax_add_user.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    // Reset UI
                    btn.disabled = false;
                    btnText.innerText = "Create Another Account";
                    btnSpinner.classList.add('hidden');
                    btn.classList.remove('opacity-80', 'cursor-not-allowed');

                    // 4. Handle Result
                    alertBox.classList.remove('hidden', 'bg-emerald-900/30', 'border-emerald-900', 'text-emerald-400', 'bg-red-900/30', 'border-red-900', 'text-red-400');

                    if (data.status === 'success') {
                        // Success Styling
                        alertBox.classList.add('bg-emerald-900/30', 'border-emerald-900', 'text-emerald-400');
                        alertIcon.innerHTML = `<svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>`;
                        alertText.innerHTML = data.message;

                        form.reset(); // Clear the form for the next user

                        // If queue was used instead of instant, trigger background worker
                        if (data.trigger_queue) {
                            fetch('ajax_push_batch.php').catch(err => console.error("Worker failed", err));
                        }
                    } else {
                        // Error Styling
                        alertBox.classList.add('bg-red-900/30', 'border-red-900', 'text-red-400');
                        alertIcon.innerHTML = `<svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>`;
                        alertText.innerHTML = data.message;
                    }

                    // Scroll to alert
                    alertBox.scrollIntoView({
                        behavior: 'smooth',
                        block: 'center'
                    });
                })
                .catch(error => {
                    console.error('Error:', error);
                    btn.disabled = false;
                    btnText.innerText = "Create & Verify Account";
                    btnSpinner.classList.add('hidden');
                    btn.classList.remove('opacity-80', 'cursor-not-allowed');

                    alertBox.classList.remove('hidden');
                    alertBox.className = 'p-4 rounded-xl mb-6 text-sm font-bold flex items-center gap-3 border transition-all bg-red-900/30 border-red-900 text-red-400';
                    alertText.innerText = "A network error occurred. Please try again.";
                });
        });
    </script>
</body>

</html>