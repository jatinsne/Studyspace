</main>
<footer class="border-t border-border mt-12 bg-bg relative overflow-hidden">
    <div class="absolute top-0 left-1/2 -translate-x-1/2 w-96 h-[1px] bg-gradient-to-r from-transparent via-zinc-700 to-transparent"></div>

    <div class="max-w-7xl mx-auto px-6 py-8 flex flex-col md:flex-row justify-between items-center gap-4">

        <div class="text-center md:text-left">
            <p class="text-zinc-500 text-sm font-mono">
                &copy; <?= date('Y') ?> StudySpace Operating System.
            </p>
            <p class="text-zinc-600 text-xs mt-1">All systems nominal.</p>
        </div>

        <div class="flex items-center gap-2 px-3 py-1 bg-surface border border-border rounded-full">
            <span class="relative flex h-2 w-2">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
            </span>
            <span class="text-xs text-zinc-400 font-mono">Server Online</span>
        </div>

    </div>
</footer>

</body>

</html>