<div id="page-loader" class="fixed inset-0 bg-black z-[100] flex items-center justify-center transition-opacity duration-500">
    <div class="animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-accent"></div>
</div>
<script>
    window.addEventListener('load', function() {
        const loader = document.getElementById('page-loader');
        loader.classList.add('opacity-0');
        setTimeout(() => {
            loader.style.display = 'none';
        }, 500);
    });
</script>