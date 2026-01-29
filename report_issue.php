<?php
session_start();
require_once 'config/Database.php';
if (!isset($_SESSION['user_id'])) header("Location: index.php");

$message = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo = Database::getInstance()->getConnection();
    $stmt = $pdo->prepare("INSERT INTO issues (user_id, type, description) VALUES (?, ?, ?)");
    $stmt->execute([$_SESSION['user_id'], $_POST['type'], $_POST['description']]);
    $message = "Issue reported. Admin will check shortly.";
}
?>
<!DOCTYPE html>
<html lang="en" class="dark">

<head>
    <title>Report Issue</title>
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

<body class="bg-black text-white p-6 flex items-center justify-center min-h-screen">
    <div class="w-full max-w-md bg-surface border border-zinc-900 p-6 rounded-xl">
        <h1 class="text-xl font-bold mb-4 text-red-400">Report an Issue</h1>
        <?php if ($message): ?><div class="bg-zinc-800 p-3 rounded mb-4 text-sm text-green-400"><?= $message ?></div><?php endif; ?>

        <form method="POST" class="space-y-4">
            <div>
                <label class="text-xs uppercase text-zinc-500 font-bold">Category</label>
                <select name="type" class="w-full bg-black border border-zinc-800 p-3 rounded text-white outline-none focus:border-red-500">
                    <option value="Wifi">Wifi</option>
                    <option value="AC">AC / Fan</option>
                    <option value="Noise">Noise Complaint</option>
                    <option value="Cleanliness">Cleanliness</option>
                    <option value="Other">Other</option>
                </select>
            </div>

            <div>
                <label class="text-xs uppercase text-zinc-500 font-bold">Details</label>
                <textarea name="description" rows="3" required class="w-full bg-black border border-zinc-800 p-3 rounded text-white outline-none focus:border-red-500"></textarea>
            </div>

            <div class="flex gap-4">
                <a href="dashboard.php" class="w-1/3 py-3 text-center text-zinc-500 hover:text-white border border-zinc-800 rounded">Cancel</a>
                <button type="submit" class="w-2/3 bg-red-600 text-white font-bold py-3 rounded hover:bg-red-500 transition">Submit Report</button>
            </div>
        </form>
    </div>
</body>

</html>