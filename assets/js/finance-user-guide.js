(function () {
    'use strict';
    const root = document.getElementById('finance-user-guide');
    if (!root) return;
    const status = root.querySelector('[data-guide-status]');
    root.querySelectorAll('[data-guide-print]').forEach(function (button) {
        button.hidden = false;
        button.addEventListener('click', function () { window.print(); });
    });
    root.querySelectorAll('[data-guide-copy]').forEach(function (button) {
        button.hidden = false;
        button.addEventListener('click', async function () {
            const code = document.getElementById(button.dataset.guideCopy);
            if (!code || !root.contains(code)) return;
            try {
                if (!navigator.clipboard || !navigator.clipboard.writeText) throw new Error('clipboard-unavailable');
                await navigator.clipboard.writeText(code.textContent);
                status.textContent = 'Contoh tersalin. Sesuaikan path dan nilai instance sebelum dipakai. Tidak ada perintah yang dijalankan.';
            } catch (_) {
                status.textContent = 'Browser tidak mengizinkan penyalinan otomatis. Pilih teks contoh, lalu salin secara manual.';
            }
        });
    });
}());
