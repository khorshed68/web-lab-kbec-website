<?php
/**
 * KBEC Shared Footer – Member & Admin pages
 */
function renderFooter(): void { ?>
</main><!-- /.kbec-main -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Global CSRF token for AJAX requests
const KBEC_CSRF = document.querySelector('meta[name="csrf-token"]')?.content || '';
// Helper: POST JSON to a PHP API endpoint
async function kbecApi(url, data = {}) {
    const res = await fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': KBEC_CSRF
        },
        body: JSON.stringify(data)
    });
    const json = await res.json();
    if (!json.ok) throw new Error(json.message || 'Request failed');
    return json;
}
// Auto-dismiss alerts after 4 s
document.querySelectorAll('.kbec-alert').forEach(el => {
    setTimeout(() => el.style.opacity = '0', 3500);
    setTimeout(() => el.remove(), 4000);
});
</script>
</body>
</html>
<?php } ?>
