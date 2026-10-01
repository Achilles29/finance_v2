(() => {
  'use strict';
  const form = document.getElementById('claim-form');
  const message = document.getElementById('form-message');
  const submit = document.getElementById('claim-button');
  const result = document.getElementById('voucher-result');
  const download = document.getElementById('download-button');
  const feedback = document.getElementById('download-message');
  const statusLabels = { OPEN: 'AKTIF - 1 KALI PAKAI', REDEEMED: 'SUDAH DIGUNAKAN', EXPIRED: 'SUDAH KEDALUWARSA', VOID: 'TIDAK AKTIF' };
  let voucher = null;
  const formatDate = value => new Intl.DateTimeFormat('id-ID', { day: '2-digit', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit', timeZone: 'Asia/Jakarta' }).format(new Date(value)) + ' WIB';

  form.addEventListener('submit', async event => {
    event.preventDefault();
    if (submit.disabled || !form.reportValidity()) return;
    submit.disabled = true;
    submit.firstElementChild.textContent = 'Memeriksa email...';
    message.hidden = true;
    try {
      const response = await fetch(form.action, { method: 'POST', body: new FormData(form), credentials: 'same-origin', headers: { Accept: 'application/json' } });
      const data = await response.json().catch(() => { throw new Error('Respons server belum dapat dibaca. Coba lagi dengan email yang sama; voucher tidak akan digandakan.'); });
      if (!response.ok || !data.ok) throw new Error(data.message || 'Klaim belum berhasil. Silakan coba lagi.');
      voucher = data.voucher;
      const code = document.getElementById('voucher-code');
      code.textContent = voucher.code;
      code.classList.toggle('is-long-code', voucher.code.length > 12);
      document.getElementById('voucher-expiry').textContent = formatDate(voucher.expires_at);
      const status = document.getElementById('voucher-status');
      status.textContent = statusLabels[voucher.status] || 'TIDAK AKTIF';
      status.classList.toggle('is-inactive', voucher.status !== 'OPEN');
      document.getElementById('result-heading').firstChild.textContent = voucher.status === 'OPEN' ? 'Hadiahmu' : 'Vouchermu';
      document.getElementById('result-heading').lastElementChild.textContent = voucher.status === 'OPEN' ? 'sudah siap!' : 'sudah diklaim.';
      document.getElementById('result-message').textContent = voucher.status !== 'OPEN'
        ? 'Ini voucher yang pernah kamu klaim. Statusnya ' + (statusLabels[voucher.status] || 'tidak aktif').toLowerCase() + '. Klaim ulang tidak menerbitkan voucher baru.'
        : data.existing ? 'Kamu sudah pernah klaim. Ini voucher yang sama, dengan masa berlaku yang tetap. Simpan sebelum jajan, ya!'
          : 'Voucher Namua 15% milikmu sudah siap. Simpan gambarnya dan tunjukkan kode ini kepada kasir Namua saat pembayaran.';
      document.getElementById('claim-intro').hidden = true;
      result.hidden = false;
      document.getElementById('result-heading').focus({ preventScroll: true });
      result.scrollIntoView({ behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth', block: 'start' });
    } catch (error) {
      message.textContent = error instanceof TypeError ? 'Koneksi terputus. Coba kembali dengan email yang sama untuk melihat voucher tanpa klaim ganda.' : error.message;
      message.hidden = false;
    } finally {
      submit.disabled = false;
      submit.firstElementChild.textContent = 'Klaim voucher 15%';
    }
  });

  document.getElementById('another-email').addEventListener('click', () => {
    result.hidden = true;
    document.getElementById('claim-intro').hidden = false;
    voucher = null;
    feedback.textContent = 'Tunjukkan kode voucher kepada kasir Namua saat pembayaran.';
    form.elements.email.value = '';
    form.elements.email.focus();
  });
  document.getElementById('copy-button').addEventListener('click', async () => {
    if (!voucher) return;
    try {
      await navigator.clipboard.writeText(voucher.code);
      feedback.textContent = 'Kode voucher berhasil disalin.';
    } catch (_) {
      const selection = window.getSelection();
      const range = document.createRange();
      range.selectNodeContents(document.getElementById('voucher-code'));
      selection.removeAllRanges(); selection.addRange(range);
      feedback.textContent = 'Kode sudah dipilih. Tekan lama atau gunakan menu Salin pada perangkatmu.';
    }
  });

  async function createVoucherImage(data) {
    await document.fonts.ready;
    const art = document.getElementById('gowes-art');
    const festival = document.getElementById('festival-art');
    const namua = document.getElementById('namua-logo');
    await Promise.all([art.decode(), festival.decode(), namua.decode(), document.fonts.load('800 198px Barlow'), document.fonts.load('700 44px "DM Sans"')]);
    const canvas = document.createElement('canvas');
    canvas.width = 1200; canvas.height = 760;
    const ctx = canvas.getContext('2d');
    if (!ctx) throw new Error('Canvas unavailable');
    const paper = '#fffcf3', ink = '#232b2e', muted = '#69685f', yellow = '#ffe14b';
    function roundedPath(x, y, width, height, radius) {
      ctx.beginPath(); ctx.moveTo(x + radius, y);
      ctx.arcTo(x + width, y, x + width, y + height, radius);
      ctx.arcTo(x + width, y + height, x, y + height, radius);
      ctx.arcTo(x, y + height, x, y, radius); ctx.arcTo(x, y, x + width, y, radius); ctx.closePath();
    }
    function box(x, y, width, height, radius, fill, stroke) {
      roundedPath(x, y, width, height, radius);
      ctx.fillStyle = fill; ctx.fill();
      if (stroke) { ctx.strokeStyle = stroke; ctx.lineWidth = 1; ctx.stroke(); }
    }
    function label(text, x, y, size, color = ink, weight = '400', family = '"DM Sans"', align = 'left') {
      ctx.font = `${weight} ${size}px ${family}`; ctx.fillStyle = color; ctx.textAlign = align; ctx.fillText(text, x, y);
    }
    function imageInside(image, x, y, width, height) {
      const scale = Math.min(width / image.naturalWidth, height / image.naturalHeight);
      const w = image.naturalWidth * scale, h = image.naturalHeight * scale;
      ctx.drawImage(image, x + (width - w) / 2, y + (height - h) / 2, w, h);
    }

    ctx.fillStyle = '#ede5d5'; ctx.fillRect(0, 0, 1200, 760);
    box(33, 36, 1144, 704, 25, '#c9bda777');
    box(28, 28, 1144, 704, 25, paper, '#c7bda9');
    ctx.save(); roundedPath(28, 28, 1144, 704, 25); ctx.clip();
    const blue = ctx.createLinearGradient(28, 28, 784, 512);
    blue.addColorStop(0, '#0085c6'); blue.addColorStop(1, '#005b88');
    ctx.fillStyle = blue; ctx.fillRect(28, 28, 756, 484);
    ctx.save(); ctx.beginPath(); ctx.rect(28, 28, 756, 484); ctx.clip();
    ctx.strokeStyle = '#ffffff21'; ctx.lineWidth = 1.5;
    for (let radius = 125; radius < 420; radius += 50) { ctx.beginPath(); ctx.arc(737, 309, radius, 0, Math.PI * 2); ctx.stroke(); }
    ctx.restore();

    box(58, 54, 94, 94, 47, paper);
    imageInside(namua, 66, 62, 78, 78);
    label('NAMUA', 169, 96, 30, paper, '700');
    label('COFFEE & EATERY', 170, 120, 11, '#f7f3d7', '700');
    box(586, 70, 154, 33, 16, yellow);
    label('GOWES VOL. 09', 663, 92, 11, ink, '700', '"DM Sans"', 'center');
    label('SEHABIS GOWES,', 72, 214, 48, paper, '800', 'Barlow');
    label('SAATNYA JAJAN.', 72, 262, 48, paper, '800', 'Barlow');
    label('15%', 66, 429, 198, yellow, '800', 'Barlow');
    const discountWidth = ctx.measureText('15%').width;
    label('OFF', 80 + discountWidth, 423, 59, yellow, '800', 'Barlow');
    label('Tanpa minimum belanja di Namua.', 76, 475, 17, paper);

    ctx.save(); ctx.translate(637, 299); ctx.rotate(-Math.PI / 30);
    box(-105, -88, 210, 176, 15, '#00345150');
    box(-109, -94, 210, 176, 15, paper);
    imageInside(art, -99, -84, 190, 156); ctx.restore();
    ctx.save(); ctx.translate(703, 426); ctx.rotate(Math.PI / 18);
    box(-43, -43, 86, 86, 43, yellow, ink);
    label('RIDE.', 0, -12, 12, ink, '700', '"DM Sans"', 'center');
    label('REFUEL.', 0, 6, 12, ink, '700', '"DM Sans"', 'center');
    label('REPEAT.', 0, 24, 12, ink, '700', '"DM Sans"', 'center'); ctx.restore();

    label('GOWES VOL9', 72, 560, 22, ink, '700');
    label('DALAM RANGKAIAN JAJAN FEST 3X', 73, 584, 11, muted, '700');
    imageInside(festival, 641, 529, 101, 70);
    ctx.strokeStyle = '#d8cfbd'; ctx.lineWidth = 1;
    ctx.beginPath(); ctx.moveTo(72, 612); ctx.lineTo(740, 612); ctx.stroke();
    label('Satu voucher untuk satu kali transaksi.', 72, 647, 16, ink);
    label('Berlaku 7 hari sejak klaim.', 72, 673, 14, muted);
    label(new URL(form.action).hostname, 72, 706, 12, '#006d9f', '700');

    label('NAMUA / RIDER REWARD', 826, 94, 11, '#8d120e', '700');
    label('TREAT PASS.', 825, 152, 63, ink, '800', 'Barlow');
    label('Simpan. Tunjukkan. Nikmati.', 826, 186, 13, muted);
    ctx.strokeStyle = '#d8cfbd'; ctx.beginPath(); ctx.moveTo(826, 216); ctx.lineTo(1130, 216); ctx.stroke();
    label('KODE VOUCHERMU', 826, 254, 11, muted, '700');
    box(818, 273, 324, 92, 12, '#ffffff', '#cfc4ad');
    // Existing long codes still fit; the actual code is never truncated or reformatted.
    let codeSize = 44;
    ctx.font = `700 ${codeSize}px "DM Sans"`;
    while (ctx.measureText(data.code).width > 282 && codeSize > 8) { codeSize--; ctx.font = `700 ${codeSize}px "DM Sans"`; }
    label(data.code, 980, 319 + codeSize * .35, codeSize, ink, '700', '"DM Sans"', 'center');
    const active = data.status === 'OPEN';
    box(826, 385, 304, 31, 5, active ? '#deede1' : '#f8ded6');
    label(statusLabels[data.status] || 'TIDAK AKTIF', 978, 405, 11, active ? '#245132' : '#8d281e', '700', '"DM Sans"', 'center');
    const expires = new Date(data.expires_at);
    const expiryDate = new Intl.DateTimeFormat('id-ID', { day: '2-digit', month: 'long', year: 'numeric', timeZone: 'Asia/Jakarta' }).format(expires);
    const expiryTime = new Intl.DateTimeFormat('id-ID', { hour: '2-digit', minute: '2-digit', hourCycle: 'h23', timeZone: 'Asia/Jakarta' }).format(expires) + ' WIB';
    label('BERLAKU SAMPAI', 826, 468, 10, muted, '700');
    label(expiryDate, 826, 502, 22, ink, '700');
    label(expiryTime, 826, 532, 17, muted);
    label('Tunjukkan kode kepada', 826, 611, 14, muted);
    label('kasir Namua saat pembayaran.', 826, 633, 14, muted);
    label('GOWES VOL9', 826, 689, 11, '#006d9f', '700');
    box(1089, 658, 44, 44, 22, paper, '#b9ad95');
    label('09', 1111, 689, 26, ink, '800', 'Barlow', 'center');

    ctx.strokeStyle = '#b9ad95'; ctx.lineWidth = 1.5; ctx.setLineDash([5, 7]);
    ctx.beginPath(); ctx.moveTo(784, 45); ctx.lineTo(784, 715); ctx.stroke(); ctx.setLineDash([]);
    ctx.restore();
    for (const y of [28, 732]) { ctx.beginPath(); ctx.arc(784, y, 15, 0, Math.PI * 2); ctx.fillStyle = '#ede5d5'; ctx.fill(); }
    return new Promise((resolve, reject) => canvas.toBlob(blob => blob ? resolve(blob) : reject(new Error('PNG unavailable')), 'image/png'));
  }

  download.addEventListener('click', async () => {
    if (!voucher || download.disabled) return;
    const data = { ...voucher };
    download.disabled = true;
    try {
      const blob = await createVoucherImage(data);
      const url = URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.href = url; link.download = 'Voucher-GOWES-VOL9-' + data.code + '.png';
      document.body.appendChild(link); link.click(); link.remove();
      setTimeout(() => URL.revokeObjectURL(url), 60000);
      feedback.textContent = 'Voucher diunduh. Periksa folder Unduhan / Files di perangkatmu.';
    } catch (_) { feedback.textContent = 'Gambar belum dapat diunduh. Coba lagi atau simpan tangkapan layar voucher ini.'; }
    finally { download.disabled = false; }
  });
})();
