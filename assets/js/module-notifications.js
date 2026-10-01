(function () {
  'use strict';
  if (window.financeModuleNotificationsBound) return;
  window.financeModuleNotificationsBound = true;
  document.addEventListener('click', async function (event) {
    const button = event.target.closest('[data-module-notify]');
    if (!button || button.disabled) return;
    event.preventDefault();
    if (!window.confirm('Kirim PDF pengajuan ke tujuan ' + button.dataset.moduleNotify + ' yang sudah diatur admin?')) return;
    button.disabled = true;
    let notice = button.parentElement.querySelector('[data-notification-result]');
    if (!notice) {
      notice = document.createElement('div');
      notice.dataset.notificationResult = '1';
      notice.setAttribute('role', 'status');
      notice.className = 'small text-wrap text-start w-100 mt-2';
      button.parentElement.appendChild(notice);
    }
    notice.textContent = 'Membuat PDF dan memasukkan pengajuan ke antrean…';
    const controller = new AbortController();
    // Chrome headless may need tens of seconds to render a large request PDF.
    const timer = setTimeout(() => controller.abort(), 75000);
    try {
      const response = await fetch(button.dataset.notifyUrl, {
        method: 'POST', credentials: 'same-origin', signal: controller.signal,
        headers: {'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-Procurement-Mutation-Csrf': button.dataset.notifyCsrf},
        body: JSON.stringify({channel: button.dataset.moduleNotify})
      });
      const responseText = await response.text();
      let data = null;
      try {
        data = responseText ? JSON.parse(responseText) : null;
      } catch (parseError) {
        const detail = responseText.replace(/\s+/g, ' ').trim().slice(0, 160);
        throw new Error('Respons server tidak valid' + (detail ? ': ' + detail : '.') + ' (HTTP ' + response.status + ').');
      }
      notice.textContent = (data && data.message) || (response.ok && data && data.ok
        ? 'PDF pengajuan masuk antrean dan akan dikirim ke grup.'
        : 'Pengajuan belum dapat dimasukkan ke antrean (HTTP ' + response.status + ').');
    } catch (error) {
      if (error && error.name === 'AbortError') {
        notice.textContent = 'Pembuatan PDF melewati 75 detik. Periksa antrean WA sebelum mencoba lagi; pengajuan yang sama tidak akan digandakan.';
      } else if (error && error.message) {
        notice.textContent = error.message;
      } else {
        notice.textContent = 'Koneksi terputus; status antrean belum pasti. Periksa status di pengaturan kanal. Klik ulang untuk pengajuan yang sama tidak menggandakan pesan.';
      }
    } finally {
      clearTimeout(timer);
      button.disabled = false;
    }
  });
})();
