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
    $status = $_POST['verification_status'];

    // Access Fields
    $bioId = ($_POST['biometric_id']);
    $cardId = trim($_POST['card_id']);
    $bioEnable = isset($_POST['biometric_enable']) ? 1 : 0;

    // Subscription Manual Override
    $subStart = !empty($_POST['subscription_startdate']) ? $_POST['subscription_startdate'] : null;
    $subEnd = !empty($_POST['subscription_enddate']) ? $_POST['subscription_enddate'] : null;

    // -- FILE UPLOAD LOGIC --
    $uploadDir = '../uploads/';
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

    try {
        // A. UPDATE USER IN DB
        $sql = "UPDATE users SET 
                name=?, phone=?, email=?, aadhaar_number=?, verification_status=?, 
                profile_image=?, doc_proof=?,
                biometric_id=?, card_id=?, biometric_enable=?,
                subscription_startdate=?, subscription_enddate=?
                WHERE id=?";

        $pdo->prepare($sql)->execute([
            $name,
            $phone,
            $email,
            $aadhaar,
            $status,
            $profilePath,
            $docPath,
            $bioId,
            $cardId,
            $bioEnable,
            $subStart,
            $subEnd,
            $id
        ]);

        // B. QUEUE BIOMETRIC JOB (If Bio ID exists)
        if (!empty($bioId)) {
            $deviceId = getenv('BIOMETRIC_DEVICE_ID') ?: 'RSS202508126365';

            // Formula: (Year-2000) << 16 + (Month << 8) + Day
            function dateToDeviceInt($dateStr)
            {
                if (!$dateStr) return 0;
                $ts = strtotime($dateStr);
                $year  = (int)date('Y', $ts);
                $month = (int)date('m', $ts);
                $day   = (int)date('d', $ts);
                if ($year < 2000) return 0;
                return (($year - 2000) << 16) + ($month << 8) + $day;
            }

            $usePeriod = "No";
            $pStart = 0;
            $pEnd = 0;

            // Only enable period if we have valid dates and Access is Enabled
            if ($bioEnable && $subStart && $subEnd) {
                $usePeriod = "Yes";
                $pStart = dateToDeviceInt($subStart);
                $pEnd = dateToDeviceInt($subEnd);
            }

            // Prepare Payload
            $payloadData = [
                "device_id" => $deviceId,
                "cmd_code" => "SetUserData",
                "params" => [
                    "UserID" => (int)$bioId,
                    "Type" => "Set",
                    "Name" => $name,
                    "Privilege" => "User",
                    "Enabled" => ($bioEnable ? "Yes" : "No"),
                    "Card" => (!empty($cardId) ? base64_encode($cardId) : ""),
                    "UserPeriod_Used" => $usePeriod,
                    "UserPeriod_Start" => $pStart,
                    "UserPeriod_End" => $pEnd
                ]
            ];
            $payloadJson = json_encode($payloadData);

            // Queue Job
            try {
                $logSql = "INSERT INTO biometric_jobs 
                           (biometric_id, command, payload, status, created_at) 
                           VALUES (?, 'ADD_USER', ?, 'pending', NOW())";
                $pdo->prepare($logSql)->execute([$deviceId, $payloadJson]);
            } catch (Exception $e) {
                error_log("Queue Error: " . $e->getMessage());
            }
        }

        header("Location: user_details.php?id=$id&msg=updated_queued");
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

    <div class="w-full max-w-4xl bg-surface border border-zinc-900 p-8 rounded-2xl shadow-2xl h-fit">

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

        <form method="POST" enctype="multipart/form-data" class="space-y-8">

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

            <div class="bg-zinc-900/30 border border-zinc-800 p-6 rounded-xl">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-sm font-bold text-accent uppercase tracking-widest">Hardware Access</h3>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="biometric_enable" value="1" <?= $user['biometric_enable'] ? 'checked' : '' ?> class="accent-emerald-500 w-4 h-4">
                        <span class="text-sm font-bold text-zinc-400">Enable Access</span>
                    </label>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-xs uppercase text-zinc-500 font-bold mb-2">Biometric ID</label>
                        <input type="text" name="biometric_id" value="<?= htmlspecialchars($user['biometric_id'] ?? '') ?>" placeholder="Fingerprint ID"
                            class="w-full bg-black border border-zinc-700 p-3 rounded text-white focus:border-accent outline-none font-mono">
                    </div>
                    <div>
                        <label class="block text-xs uppercase text-zinc-500 font-bold mb-2">RFID Card ID</label>
                        <input type="text" name="card_id" value="<?= htmlspecialchars($user['card_id'] ?? '') ?>" placeholder="Card/Tag ID"
                            class="w-full bg-black border border-zinc-700 p-3 rounded text-white focus:border-accent outline-none font-mono">
                    </div>
                </div>
            </div>

            <div class="bg-zinc-900/30 border border-zinc-800 p-6 rounded-xl">
                <h3 class="text-sm font-bold text-accent uppercase tracking-widest mb-4">Subscription Override</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-xs uppercase text-zinc-500 font-bold mb-2">Access Start Date</label>
                        <input type="date" name="subscription_startdate" value="<?= $user['subscription_startdate'] ?>"
                            class="w-full bg-black border border-zinc-700 p-3 rounded text-white focus:border-accent outline-none">
                    </div>
                    <div>
                        <label class="block text-xs uppercase text-zinc-500 font-bold mb-2">Access End Date</label>
                        <input type="date" name="subscription_enddate" value="<?= $user['subscription_enddate'] ?>"
                            class="w-full bg-black border border-zinc-700 p-3 rounded text-white focus:border-accent outline-none">
                    </div>
                </div>
                <p class="text-[10px] text-zinc-500 mt-2">* These dates are automatically updated by the Subscription system.</p>
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
                    </div>
                </div>
            </div>

            <button type="submit" class="w-full bg-white text-black font-bold py-4 rounded-xl hover:bg-zinc-200 transition text-lg shadow-lg">
                Save Changes
            </button>

        </form>
    </div>

    <script>
        // document.addEventListener("DOMContentLoaded", function() {
        //     console.log("Checking sync queue in background...");
        //     fetch('ajax_process_queue.php')
        //         .then(response => response.text())
        //         .then(data => console.log("Sync Worker:", data))
        //         .catch(err => console.error("Sync Trigger Failed", err));
        // });
    </script>
</body>

</html>