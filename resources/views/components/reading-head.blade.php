<script>
    try {
        const size = localStorage.getItem('nexo.reading-size');
        if (['large', 'extra', 'television'].includes(size)) document.documentElement.dataset.readingSize = size;
    } catch {}
</script>
