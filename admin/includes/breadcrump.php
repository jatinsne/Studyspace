<div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
    <div>
        <nav class="flex text-xs text-zinc-500 mb-1" aria-label="Breadcrumb">
            <ol class="inline-flex items-center space-x-1 md:space-x-3">
                <li><a href="index.php" class="hover:text-white transition">Dashboard</a></li>
                <li>/</li>
                <li><span class="text-zinc-400"><?= $breadcrump ?></span></li>
            </ol>
        </nav>
        <h1 class="text-2xl font-bold text-<?= $headerTitle == "Outstanding Dues" ? "red" : "yellow" ?>-500"><?= $headerTitle ?></h1>
    </div>
    <a href="index.php" class="text-zinc-500 hover:text-white">← Back</a>
</div>