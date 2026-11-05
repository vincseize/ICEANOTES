<?php // includes/footer.php ?>
</main>

<footer class="text-center text-xs text-gray-400 py-4 border-t border-gray-200 bg-white shrink-0">
    ICEANOTES — 2025 - <?= date('Y') ?>
</footer>

<script>
(function () {
    const LS_KEY  = 'iceanotes_per_page';
    const DEFAULT = '20';

    const url = new URL(window.location.href);
    if (!url.searchParams.has('per_page')) {
        const saved = localStorage.getItem(LS_KEY);
        if (saved && saved !== DEFAULT) {
            url.searchParams.set('per_page', saved);
            window.location.replace(url.toString());
            return;
        }
    } else {
        localStorage.setItem(LS_KEY, url.searchParams.get('per_page'));
    }

    document.querySelectorAll('select[name="per_page"]').forEach(function (sel) {
        sel.addEventListener('change', function () {
            localStorage.setItem(LS_KEY, sel.value);
            const form = sel.closest('form');
            if (form) form.submit();
        });
    });
})();
</script>
</body>
</html>