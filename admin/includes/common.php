<script src="https://cdn.tailwindcss.com"></script>
<link rel="icon" href="https://cdn-icons-png.flaticon.com/512/2232/2232688.png" type="image/png">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;700&display=swap" rel="stylesheet">

<script>
    tailwind.config = {
        darkMode: 'class',
        theme: {
            extend: {
                fontFamily: {
                    sans: ['Inter', 'sans-serif'],
                    mono: ['JetBrains Mono', 'monospace']
                },
                colors: {
                    bg: '#09090b',
                    surface: '#18181b',
                    border: '#27272a',
                    accent: '#d4b106'
                }
            }
        }
    }
</script>
<style>
    body {
        background-color: #000;
        color: #fff;
    }

    ::-webkit-scrollbar {
        width: 8px;
    }

    ::-webkit-scrollbar-track {
        background: #09090b;
    }

    ::-webkit-scrollbar-thumb {
        background: #27272a;
        border-radius: 4px;
    }
</style>