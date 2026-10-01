<?php
$station = is_array($station ?? null) ? $station : null;
$result = is_array($result ?? null) ? $result : null;
$available = $station && !empty($station['is_active']);
$businessProfile = is_array($business_profile ?? null) ? $business_profile : [];
$businessName = trim((string)($businessProfile['display_name'] ?? ''));
$businessName = $businessName !== '' ? $businessName : 'Finance';
$outletName = trim((string)($station['outlet_name'] ?? $businessName));
$outletName = $outletName !== '' ? $outletName : $businessName;
$posted = static function (string $key): string {
    $ci = get_instance();
    $value = $ci->input->post($key, false);
    return is_string($value) ? mb_substr($value, 0, $key === 'review_text' ? 1200 : 150) : '';
};
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Ulasan &amp; Member <?= html_escape($businessName) ?></title>
  <style>
    :root{--ink:#2d2523;--muted:#806e68;--wine:#a80e27;--coral:#dd5e44;--paper:#fffdf9;--line:#ecd8ce;--cream:#f7ede4;--green:#087443}
    *{box-sizing:border-box}body{margin:0;min-height:100vh;background:radial-gradient(circle at 95% 0,rgba(232,177,139,.42),transparent 34%),radial-gradient(circle at 0 100%,rgba(168,14,39,.13),transparent 32%),linear-gradient(135deg,#fbf3e9,#f3e5d9);font-family:Georgia,"Times New Roman",serif;color:var(--ink)}
    .shell{width:min(100% - 2rem,680px);margin:0 auto;padding:3.2rem 0}.card{position:relative;overflow:hidden;border:1px solid rgba(126,67,51,.18);border-radius:28px;background:rgba(255,253,249,.95);padding:2rem;box-shadow:0 24px 56px rgba(95,56,43,.14)}.card:before{content:"";position:absolute;width:240px;height:240px;right:-112px;top:-130px;border:28px solid rgba(168,14,39,.06);border-radius:50%}
    .eyebrow{position:relative;font-family:Arial,sans-serif;font-size:.72rem;letter-spacing:.15em;font-weight:800;text-transform:uppercase;color:var(--coral)}h1{position:relative;font-size:clamp(2rem,6vw,3.35rem);line-height:.95;margin:.48rem 0 1rem;letter-spacing:-.055em}p{position:relative;font-family:Arial,sans-serif;color:var(--muted);line-height:1.58}.station{position:relative;margin:1.25rem 0;padding:.85rem 1rem;border:1px dashed #d9bdae;border-radius:14px;background:#fff8f2;color:#765d54;font:13px Arial,sans-serif}.station strong{display:block;color:#4e3934;font-size:.9rem}
    .value-row{position:relative;display:grid;grid-template-columns:1fr 1fr;gap:.8rem;margin:1.2rem 0}.value{border:1px solid var(--line);border-radius:16px;padding:.85rem;background:#fff}.value b{display:block;font:700 .9rem Arial,sans-serif;color:#4d3833}.value span{display:block;margin-top:.25rem;font:12px Arial,sans-serif;line-height:1.4;color:var(--muted)}
    .stars{display:flex;flex-direction:row-reverse;justify-content:flex-end;gap:.35rem;margin:.8rem 0 1.2rem}.stars input{position:absolute;opacity:0}.stars label{width:48px;height:48px;display:grid;place-items:center;border:1px solid #eddcd3;border-radius:14px;background:#fff;color:#c7aaa0;font-family:Arial,sans-serif;font-size:1.45rem;font-weight:bold;cursor:pointer;transition:.15s}.stars input:checked~label,.stars label:hover,.stars label:hover~label{background:#fff2d9;color:#df8c00;border-color:#edc46e;transform:translateY(-2px)}
    label{display:block;font:700 .82rem Arial,sans-serif;color:#5f4b45;margin-top:.85rem}input,textarea{width:100%;margin-top:.48rem;border:1px solid #e5d2c8;border-radius:14px;padding:.82rem .88rem;font:15px Arial,sans-serif;color:var(--ink);background:#fff}textarea{min-height:114px;resize:vertical}input:focus,textarea:focus{outline:3px solid rgba(221,94,68,.16);border-color:var(--coral)}.consent{display:flex;gap:.65rem;align-items:flex-start;margin-top:1rem;padding:.85rem;border:1px solid #ebd8cd;border-radius:14px;background:#fff8f3;font:13px/1.45 Arial,sans-serif;color:#6c554e}.consent input{width:18px;height:18px;margin:.05rem 0 0;accent-color:var(--wine)}
    button{position:relative;border:0;border-radius:14px;background:linear-gradient(135deg,var(--wine),#cf4035);color:#fff;font:700 15px Arial,sans-serif;padding:.95rem 1.15rem;cursor:pointer;width:100%;margin-top:1rem;box-shadow:0 10px 20px rgba(168,14,39,.19)}button:hover{filter:brightness(1.04)}.message{position:relative;border-radius:14px;padding:.88rem 1rem;font:14px Arial,sans-serif;margin:1rem 0}.message.success{background:#eaf8ed;color:var(--green);border:1px solid #b8e0c1}.message.error{background:#fff0ee;color:#a42720;border:1px solid #f2c0ba}.closed{position:relative;padding:1.4rem 0;text-align:center}.closed-mark{width:72px;height:72px;border-radius:50%;display:grid;place-items:center;margin:0 auto 1rem;background:#f6e0d7;color:var(--wine);font:700 25px Arial,sans-serif}.member-no{display:inline-block;margin-top:.5rem;padding:.42rem .65rem;border-radius:999px;background:#f6e0d7;color:#8c1228;font:800 .85rem Arial,sans-serif;letter-spacing:.06em}.footer{position:relative;margin-top:1.5rem;text-align:center;font:12px Arial,sans-serif;color:#9c8178}@media(max-width:520px){.shell{width:min(100% - 1rem,680px);padding:1rem 0}.card{padding:1.3rem;border-radius:21px}.value-row{grid-template-columns:1fr}.stars label{width:42px;height:42px}}
  </style>
</head>
<body>
<main class="shell"><section class="card">
  <div class="eyebrow">Ulasan &amp; member <?= html_escape($businessName) ?></div>
  <h1>Bantu kami jadi lebih baik.</h1>
  <?php if (!$available): ?>
    <div class="message error">QR ulasan ini tidak ditemukan atau sedang tidak aktif. Silakan gunakan QR lain atau minta bantuan tim kami.</div>
  <?php elseif (!empty($result['ok'])): ?>
    <div class="closed"><div class="closed-mark">OK</div><h2>Terima kasih atas ulasannya.</h2><p>Ulasan Anda sudah masuk ke tim <?= html_escape($outletName) ?>. Untuk informasi keanggotaan, silakan hubungi kasir.</p></div>
  <?php else: ?>
    <p>Ceritakan pengalaman Anda di <?= html_escape($outletName) ?>. Nomor WhatsApp membantu kami mengenali Anda sebagai member tanpa perlu mengisi formulir lagi pada kunjungan berikutnya.</p>
    <div class="station"><strong><?= html_escape((string)($station['station_name'] ?? 'QR Ulasan')) ?></strong>Ulasan ini dikirim dari area <?= html_escape($outletName) ?>.</div>
    <div class="value-row"><div class="value"><b>1. Beri bintang</b><span>Nilai pelayanan, rasa, dan kenyamanan Anda.</span></div><div class="value"><b>2. Jadi member</b><span>Nomor WhatsApp dapat dihubungkan ke program member <?= html_escape($businessName) ?> untuk poin dan voucher.</span></div></div>
    <?php if ($result): ?><div class="message error"><?= html_escape((string)($result['message'] ?? 'Ulasan belum dapat dikirim.')) ?></div><?php endif; ?>
    <form method="post" action="<?= site_url('review/station/' . rawurlencode((string)$station_code) . '/submit') ?>">
      <input type="hidden" name="_review_guard" value="<?= html_escape((string)($form_guard ?? '')) ?>">
      <div hidden aria-hidden="true"><label>Biarkan kosong<input name="website" value="" tabindex="-1" autocomplete="off"></label></div>
      <label for="customer-name">Nama Anda</label><input id="customer-name" name="customer_name" required maxlength="150" value="<?= html_escape($posted('customer_name')) ?>" placeholder="Contoh: Fadila Hartono">
      <label for="mobile-phone">Nomor WhatsApp</label><input id="mobile-phone" name="mobile_phone" required inputmode="tel" maxlength="30" value="<?= html_escape($posted('mobile_phone')) ?>" placeholder="Contoh: 0812xxxx">
      <label>Berikan bintang</label><div class="stars" aria-label="Rating bintang"><input id="station-star-5" type="radio" name="rating" value="5"><label for="station-star-5" title="5 bintang">&#9733;</label><input id="station-star-4" type="radio" name="rating" value="4"><label for="station-star-4" title="4 bintang">&#9733;</label><input id="station-star-3" type="radio" name="rating" value="3"><label for="station-star-3" title="3 bintang">&#9733;</label><input id="station-star-2" type="radio" name="rating" value="2"><label for="station-star-2" title="2 bintang">&#9733;</label><input id="station-star-1" type="radio" name="rating" value="1"><label for="station-star-1" title="1 bintang">&#9733;</label></div>
      <label for="review-text">Cerita singkat Anda <span style="font-weight:normal;color:#9a837b">(opsional)</span></label><textarea id="review-text" name="review_text" maxlength="1200" placeholder="Apa yang paling Anda suka atau perlu kami perbaiki?"><?= html_escape($posted('review_text')) ?></textarea>
      <label class="consent"><input type="checkbox" name="join_member" value="1" required <?= $posted('join_member') === '1' ? 'checked' : '' ?>><span>Saya setuju nomor WhatsApp saya digunakan untuk menghubungkan atau membuat member <?= html_escape($businessName) ?> agar dapat menerima poin dan voucher.</span></label>
      <p style="font-size:12px">Nama, nomor WhatsApp, dan ulasan digunakan oleh tim outlet untuk menindaklanjuti masukan dan keanggotaan. Formulir ini tidak menampilkan profil member atau memverifikasi kepemilikan nomor. Untuk koreksi data, hubungi kasir.</p>
      <button type="submit" <?= empty($form_guard) ? 'disabled' : '' ?>>Kirim Ulasan & Lanjutkan sebagai Member</button>
    </form>
  <?php endif; ?>
  <div class="footer">Terima kasih telah berkunjung ke <?= html_escape($outletName) ?>.</div>
</section></main>
</body>
</html>
