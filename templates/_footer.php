<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
    </main>
    <script>
    (function () {
        document.querySelectorAll('.cooked-edit-toggle').forEach(button => {
            const panel = document.getElementById(button.getAttribute('aria-controls') || '');
            if (!panel) return;
            const row = button.closest('li');
            const setOpen = open => {
                panel.hidden = !open;
                button.setAttribute('aria-expanded', open ? 'true' : 'false');
                if (row) row.classList.toggle('is-editing', open);
                if (open) {
                    const field = panel.querySelector('textarea, input, select, button');
                    if (field) field.focus();
                }
            };
            button.addEventListener('click', () => setOpen(panel.hidden));
            panel.querySelectorAll('.cooked-edit-cancel').forEach(cancel => {
                cancel.addEventListener('click', () => {
                    setOpen(false);
                    button.focus();
                });
            });
        });
    })();
    </script>
    <?php wp_app_body_close(); ?>
</body>
</html>
