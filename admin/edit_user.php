<?php
require_once 'admin_check.php';
require_once '../config/Database.php';

$pdo = Database::getInstance()->getConnection();
$id = $_GET['id'] ?? 0;
$msg = "";
$error = "";

// 1. FETCH USER DATA
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$id]);
$user = $stmt->fetch();

if (!$user) die("User not found.");

// 2. HANDLE UPDATE
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    $aadhaar = trim($_POST['aadhaar_number']);
    $status = $_POST['verification_status']; // Admin can verify/reject docs

    // -- FILE UPLOAD LOGIC --
    $uploadDir = '../uploads/'; // Note: Path is relative to admin folder
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

    $profilePath = $user['profile_image'];
    $docPath = $user['doc_proof'];

    function handleUpload($inputName, $targetDir)
    {
        if (!empty($_FILES[$inputName]['name'])) {
            $fileName = time() . '_' . basename($_FILES[$inputName]['name']);
            $target = $targetDir . $fileName;
            if (move_uploaded_file($_FILES[$inputName]['tmp_name'], $target)) {
                return $fileName;
            }
        }
        return false;
    }

    $newProfile = handleUpload('profile_image', $uploadDir);
    if ($newProfile) $profilePath = $newProfile;

    $newDoc = handleUpload('doc_proof', $uploadDir);
    if ($newDoc) $docPath = $newDoc;
    // -----------------------

    try {
        $sql = "UPDATE users SET name=?, phone=?, email=?, aadhaar_number=?, verification_status=?, profile_image=?, doc_proof=? WHERE id=?";
        $pdo->prepare($sql)->execute([$name, $phone, $email, $aadhaar, $status, $profilePath, $docPath, $id]);

        // Refresh data to show changes
        header("Location: user_details.php?id=$id&msg=updated");
        exit;
    } catch (PDOException $e) {
        $error = "Update failed: " . $e->getMessage();
    }
}
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

    <div class="w-full max-w-3xl bg-surface border border-zinc-900 p-8 rounded-2xl shadow-2xl h-fit">

        <div class="flex justify-between items-center mb-8 border-b border-zinc-800 pb-4">
            <div>
                <h1 class="text-2xl font-bold text-white">Edit Student Details</h1>
                <p class="text-zinc-500 text-sm">Update info for: <span class="text-accent"><?= htmlspecialchars($user['name']) ?></span></p>
            </div>
            <a href="user_details.php?id=<?= $id ?>" class="text-sm text-zinc-400 hover:text-white bg-zinc-800 px-4 py-2 rounded-lg transition">Cancel</a>
        </div>

        <?php if ($error): ?>
            <div class="bg-red-900/30 text-red-500 p-4 rounded mb-6 border border-red-900"><?= $error ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" class="space-y-6">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-xs uppercase text-zinc-500 font-bold mb-2">Full Name</label>
                    <input type="text" name="name" value="<?= htmlspecialchars($user['name']) ?>" required
                        class="w-full bg-black border border-zinc-800 p-3 rounded text-white focus:border-accent outline-none">
                </div>
                <div>
                    <label class="block text-xs uppercase text-zinc-500 font-bold mb-2">Phone Number</label>
                    <input type="text" name="phone" value="<?= htmlspecialchars($user['phone']) ?>" required
                        class="w-full bg-black border border-zinc-800 p-3 rounded text-white focus:border-accent outline-none font-mono">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-xs uppercase text-zinc-500 font-bold mb-2">Email</label>
                    <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>"
                        class="w-full bg-black border border-zinc-800 p-3 rounded text-white focus:border-accent outline-none">
                </div>
                <div>
                    <label class="block text-xs uppercase text-zinc-500 font-bold mb-2">Aadhaar Number</label>
                    <input type="text" name="aadhaar_number" value="<?= htmlspecialchars($user['aadhaar_number']) ?>"
                        class="w-full bg-black border border-zinc-800 p-3 rounded text-white focus:border-accent outline-none font-mono tracking-widest">
                </div>
            </div>

            <div class="bg-zinc-900/50 p-4 rounded-xl border border-zinc-800">
                <label class="block text-xs uppercase text-zinc-500 font-bold mb-2">Verification Status</label>
                <div class="flex gap-4">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="radio" name="verification_status" value="verified" <?= $user['verification_status'] == 'verified' ? 'checked' : '' ?> class="accent-emerald-500">
                        <span class="text-emerald-400 font-bold text-sm">Verified</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="radio" name="verification_status" value="pending" <?= $user['verification_status'] == 'pending' ? 'checked' : '' ?> class="accent-yellow-500">
                        <span class="text-yellow-400 font-bold text-sm">Pending</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="radio" name="verification_status" value="rejected" <?= $user['verification_status'] == 'rejected' ? 'checked' : '' ?> class="accent-red-500">
                        <span class="text-red-400 font-bold text-sm">Rejected</span>
                    </label>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-xs uppercase text-zinc-500 font-bold mb-2">Update Profile Photo</label>
                    <div class="flex items-center gap-4 bg-zinc-900/30 p-3 rounded-xl border border-zinc-800">
                        <?php if ($user['profile_image']): ?>
                            <img src="../uploads/<?= $user['profile_image'] ?>" class="w-12 h-12 rounded-full object-cover border border-zinc-600">
                        <?php else: ?>
                            <div class="w-12 h-12 rounded-full bg-zinc-800 flex items-center justify-center text-xs">No Pic</div>
                        <?php endif; ?>
                        <input type="file" name="profile_image" class="text-xs text-zinc-500 file:mr-2 file:py-1 file:px-3 file:rounded-full file:border-0 file:bg-zinc-800 file:text-white hover:file:bg-zinc-700">
                    </div>
                </div>

                <div>
                    <label class="block text-xs uppercase text-zinc-500 font-bold mb-2">Update ID Proof</label>
                    <div class="bg-zinc-900/30 p-3 rounded-xl border border-zinc-800">
                        <input type="file" name="doc_proof" class="w-full text-xs text-zinc-500 file:mr-2 file:py-1 file:px-3 file:rounded-full file:border-0 file:bg-zinc-800 file:text-white hover:file:bg-zinc-700">
                        <p class="text-[10px] text-zinc-600 mt-1">Overwrites existing document.</p>
                    </div>
                </div>
            </div>

            <button type="submit" class="w-full bg-white text-black font-bold py-4 rounded-xl hover:bg-zinc-200 transition text-lg shadow-lg">
                Save Changes
            </button>

        </form>
    </div>
</body>

</html>