<?php
session_start();
require_once 'config/Database.php';

// Check Login
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$pdo = Database::getInstance()->getConnection();
$msg = "";
$userId = $_SESSION['user_id'];

// 1. HANDLE FORM SUBMISSION
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aadhaar = $_POST['aadhaar_number'];

    // File Upload Logic
    $uploadDir = 'uploads/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

    $profilePath = $_POST['existing_profile'];
    $docPath = $_POST['existing_doc'];

    // Function to handle single file upload
    function uploadFile($fileInput, $dir)
    {
        if (!empty($_FILES[$fileInput]['name'])) {
            $fileName = time() . '_' . basename($_FILES[$fileInput]['name']);
            $targetPath = $dir . $fileName;
            $fileType = strtolower(pathinfo($targetPath, PATHINFO_EXTENSION));

            // Allow certain formats
            if (in_array($fileType, ['jpg', 'jpeg', 'png', 'pdf'])) {
                if (move_uploaded_file($_FILES[$fileInput]['tmp_name'], $targetPath)) {
                    return $fileName;
                }
            }
        }
        return false;
    }

    // Process Uploads
    $newProfile = uploadFile('profile_image', $uploadDir);
    if ($newProfile) $profilePath = $newProfile;

    $newDoc = uploadFile('doc_proof', $uploadDir);
    if ($newDoc) $docPath = $newDoc;

    // Update DB
    $sql = "UPDATE users SET aadhaar_number = ?, profile_image = ?, doc_proof = ? WHERE id = ?";
    $pdo->prepare($sql)->execute([$aadhaar, $profilePath, $docPath, $userId]);

    $msg = "Profile updated successfully!";
}

// 2. FETCH CURRENT DATA
$user = $pdo->query("SELECT * FROM users WHERE id = $userId")->fetch();
?>

<!DOCTYPE html>
<html lang="en" class="dark">

<head>
    <title>Edit Profile</title>
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

<body class="min-h-screen bg-black text-white p-6 flex justify-center items-center">

    <div class="w-full max-w-2xl bg-surface border border-zinc-900 p-8 rounded-2xl shadow-2xl">

        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold">Update Profile</h1>
            <a href="dashboard.php" class="text-zinc-500 hover:text-white">Cancel</a>
        </div>

        <?php if ($msg): ?>
            <div class="bg-emerald-900/30 text-emerald-500 p-3 rounded mb-4 border border-emerald-900"><?= $msg ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" class="space-y-6">

            <input type="hidden" name="existing_profile" value="<?= $user['profile_image'] ?>">
            <input type="hidden" name="existing_doc" value="<?= $user['doc_proof'] ?>">

            <div>
                <label class="block text-xs uppercase text-zinc-500 font-bold mb-2">Aadhaar Number</label>
                <input type="text" name="aadhaar_number" value="<?= htmlspecialchars($user['aadhaar_number'] ?? '') ?>"
                    placeholder="XXXX-XXXX-XXXX"
                    class="w-full bg-black border border-zinc-800 p-3 rounded text-white focus:border-accent outline-none font-mono tracking-widest">
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                <div>
                    <label class="block text-xs uppercase text-zinc-500 font-bold mb-2">Profile Photo</label>
                    <div class="border border-zinc-800 bg-zinc-900/50 rounded-xl p-4 text-center">

                        <?php if ($user['profile_image']): ?>
                            <div class="relative w-24 h-24 mx-auto mb-4 group">
                                <img src="uploads/<?= $user['profile_image'] ?>" class="w-full h-full rounded-full object-cover border-2 border-zinc-700 shadow-lg">
                                <div class="absolute inset-0 rounded-full bg-black/50 hidden group-hover:flex items-center justify-center text-xs text-white font-bold">
                                    Current
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="w-24 h-24 mx-auto mb-4 rounded-full bg-zinc-800 flex items-center justify-center text-zinc-600">
                                <span class="text-xs">No Photo</span>
                            </div>
                        <?php endif; ?>

                        <input type="file" name="profile_image" accept="image/*" class="w-full text-xs text-zinc-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-zinc-800 file:text-white hover:file:bg-zinc-700 cursor-pointer" />
                    </div>
                </div>

                <div>
                    <label class="block text-xs uppercase text-zinc-500 font-bold mb-2">ID Proof (Aadhaar)</label>
                    <div class="border border-zinc-800 bg-zinc-900/50 rounded-xl p-4">

                        <?php if ($user['doc_proof']):
                            $ext = pathinfo($user['doc_proof'], PATHINFO_EXTENSION);
                            $docPath = "uploads/" . $user['doc_proof'];
                        ?>
                            <div class="mb-4 bg-black rounded-lg border border-zinc-800 overflow-hidden relative group">

                                <?php if (in_array(strtolower($ext), ['jpg', 'jpeg', 'png', 'webp'])): ?>
                                    <img src="<?= $docPath ?>" class="w-full h-32 object-cover opacity-80 group-hover:opacity-100 transition">

                                <?php else: ?>
                                    <div class="h-32 flex flex-col items-center justify-center text-zinc-500">
                                        <svg class="w-10 h-10 mb-2 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                                        </svg>
                                        <span class="text-xs font-bold uppercase">PDF Document</span>
                                    </div>
                                <?php endif; ?>

                                <a href="<?= $docPath ?>" target="_blank" class="absolute inset-0 bg-black/60 hidden group-hover:flex items-center justify-center text-white text-sm font-bold gap-2 transition">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                    </svg>
                                    View Full
                                </a>
                            </div>
                        <?php endif; ?>

                        <input type="file" name="doc_proof" accept="image/*,.pdf" class="w-full text-xs text-zinc-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-zinc-800 file:text-white hover:file:bg-zinc-700 cursor-pointer" />
                        <p class="text-[10px] text-zinc-600 mt-2">Update by uploading a new file.</p>
                    </div>
                </div>
            </div>

            <button type="submit" class="w-full bg-white text-black font-bold py-4 rounded-xl hover:bg-zinc-200 transition shadow-lg shadow-white/5">
                Save & Update Profile
            </button>

        </form>
    </div>

</body>

</html>