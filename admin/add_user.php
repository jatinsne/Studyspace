<?php
require_once 'admin_check.php';
require_once '../config/Database.php';

$success = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $aadhaar = trim($_POST['aadhaar_number']);

    // Default password for walk-ins
    $password = password_hash('123456', PASSWORD_DEFAULT);
    $status = 'verified'; // Auto-verify since Admin is creating it

    // -- FILE UPLOAD LOGIC --
    $uploadDir = '../uploads/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

    $profilePath = null;
    $docPath = null;

    function handleUpload($inputName, $targetDir)
    {
        if (!empty($_FILES[$inputName]['name'])) {
            $fileName = time() . '_' . basename($_FILES[$inputName]['name']);
            $target = $targetDir . $fileName;
            if (move_uploaded_file($_FILES[$inputName]['tmp_name'], $target)) {
                return $fileName;
            }
        }
        return null;
    }

    $profilePath = handleUpload('profile_image', $uploadDir);
    $docPath = handleUpload('doc_proof', $uploadDir);
    // -----------------------

    try {
        $pdo = Database::getInstance()->getConnection();

        $sql = "INSERT INTO users (name, email, phone, password_hash, role, aadhaar_number, profile_image, doc_proof, verification_status) 
                VALUES (?, ?, ?, ?, 'student', ?, ?, ?, ?)";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$name, $email, $phone, $password, $aadhaar, $profilePath, $docPath, $status]);

        $success = "Student account created successfully! Default password is '123456'";

        // Clear form data after success
        $name = $email = $phone = $aadhaar = "";
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            $error = "Email or Phone already exists in the system.";
        } else {
            $error = "System Error: " . $e->getMessage();
        }
    }
}
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

    <div class="w-full max-w-2xl bg-surface border border-zinc-900 p-8 rounded-2xl shadow-2xl">

        <div class="mb-8 flex justify-between items-start">
            <div>
                <h1 class="text-2xl font-bold">New Walk-in Registration</h1>
                <p class="text-zinc-500 text-sm">Create account and upload documents instantly.</p>
            </div>
            <div class="px-3 py-1 bg-zinc-900 border border-zinc-800 rounded text-xs text-zinc-400">
                Default Pass: <span class="font-mono text-accent">123456</span>
            </div>
        </div>

        <?php if ($success): ?>
            <div class="bg-emerald-900/30 text-emerald-400 p-4 rounded-lg mb-6 border border-emerald-900 text-sm font-bold flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
                <?= $success ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="bg-red-900/30 text-red-400 p-4 rounded-lg mb-6 border border-red-900 text-sm font-bold">
                <?= $error ?>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" class="space-y-6">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="text-[10px] uppercase text-zinc-500 font-bold tracking-widest block mb-1">Full Name</label>
                    <input type="text" name="name" value="<?= htmlspecialchars($name ?? '') ?>" required
                        class="w-full bg-black border border-zinc-800 p-3 rounded-lg text-white focus:border-accent outline-none transition">
                </div>

                <div>
                    <label class="text-[10px] uppercase text-zinc-500 font-bold tracking-widest block mb-1">Phone Number</label>
                    <input type="text" name="phone" value="<?= htmlspecialchars($phone ?? '') ?>" required
                        class="w-full bg-black border border-zinc-800 p-3 rounded-lg text-white focus:border-accent outline-none transition font-mono">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="text-[10px] uppercase text-zinc-500 font-bold tracking-widest block mb-1">Email Address</label>
                    <input type="email" name="email" value="<?= htmlspecialchars($email ?? '') ?>" required
                        class="w-full bg-black border border-zinc-800 p-3 rounded-lg text-white focus:border-accent outline-none transition">
                </div>

                <div>
                    <label class="text-[10px] uppercase text-zinc-500 font-bold tracking-widest block mb-1">Aadhaar Number</label>
                    <input type="text" name="aadhaar_number" value="<?= htmlspecialchars($aadhaar ?? '') ?>" placeholder="12-digit number"
                        class="w-full bg-black border border-zinc-800 p-3 rounded-lg text-white focus:border-accent outline-none transition font-mono tracking-wider">
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

            <button type="submit" class="w-full bg-accent text-black font-bold py-4 rounded-lg hover:bg-yellow-500 transition mt-4 shadow-lg shadow-accent/10">
                Create & Verify Account
            </button>
        </form>

        <div class="mt-8 text-center border-t border-zinc-900 pt-6">
            <a href="users.php" class="text-zinc-500 text-xs font-bold uppercase tracking-wider hover:text-white transition">
                ← Return to Directory
            </a>
        </div>

    </div>

</body>

</html>