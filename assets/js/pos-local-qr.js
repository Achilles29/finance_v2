(function (window) {
  'use strict';

  function clear(container) {
    while (container && container.firstChild) {
      container.removeChild(container.firstChild);
    }
  }

  function fallback(container, value) {
    clear(container);
    container.classList.add('qr-render-fallback');
    container.textContent = value ? 'QR belum dapat dimuat. Muat ulang halaman.' : 'Alamat QR belum tersedia.';
  }

  window.PosLocalQr = {
    clear: function (container) {
      if (!container) {
        return;
      }
      clear(container);
      container.classList.remove('qr-render-fallback');
    },

    render: function (container, value, options) {
      if (!container) {
        return false;
      }

      var text = String(value || '').trim();
      if (!text || typeof window.QRCode !== 'function') {
        fallback(container, text);
        return false;
      }

      var settings = options || {};
      var size = Math.max(48, Math.min(900, Number(settings.size || 220)));
      clear(container);
      container.classList.remove('qr-render-fallback');

      try {
        new window.QRCode(container, {
          text: text,
          width: size,
          height: size,
          correctLevel: window.QRCode.CorrectLevel.H
        });

        // qrcode.js membuat canvas lalu juga membuat image cadangan yang
        // semula tersembunyi. Sisakan satu saja agar CSS halaman tidak
        // menampilkan QR dobel ketika image cadangan ikut dipaksa terlihat.
        var visual = container.querySelector('canvas') || container.querySelector('img') || container.querySelector('table');
        var visuals = container.querySelectorAll('canvas, img, table');
        for (var index = 0; index < visuals.length; index += 1) {
          if (visuals[index] !== visual) {
            visuals[index].remove();
          }
        }
        if (!visual) {
          fallback(container, text);
          return false;
        }
        visual.setAttribute('data-pos-qr-visual', 'true');
        visual.style.display = 'block';
        visual.setAttribute('aria-label', settings.label || 'QR code');
        visual.setAttribute('role', 'img');
        return true;
      } catch (error) {
        fallback(container, text);
        return false;
      }
    }
  };
}(window));
