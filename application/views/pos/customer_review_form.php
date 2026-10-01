<?php
$review = is_array($review ?? null) ? $review : null;
$result = is_array($result ?? null) ? $result : null;
$isSubmitted = $review && (string)($review['review_status'] ?? '') === 'SUBMITTED';
$isHidden = $review && (string)($review['review_status'] ?? '') === 'HIDDEN';
$businessProfile = is_array($business_profile ?? null) ? $business_profile : [];
$businessName = trim((string)($businessProfile['display_name'] ?? ''));
$businessName = $businessName !== '' ? $businessName : 'Finance';
$outletName = trim((string)($review['outlet_name'] ?? $businessName));
$outletName = $outletName !== '' ? $outletName : $businessName;
?>
<!doctype html>
<html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Ulasan Pelanggan</title>
<style>
  :root{--ink:#34272b;--muted:#806d67;--wine:#a80e27;--coral:#dd5e44;--paper:#fffdf8;--line:#eddcd3;--cream:#f8eee5}
  *{box-sizing:border-box}body{margin:0;min-height:100vh;background:radial-gradient(circle at 90% 0,#f4d9c7 0,transparent 32%),linear-gradient(135deg,#fbf3e9,#f4e5d8);font-family:Georgia,"Times New Roman",serif;color:var(--ink)}
  .review-shell{width:min(100% - 2rem,650px);margin:0 auto;padding:3.5rem 0}.review-card{border:1px solid rgba(126,67,51,.18);border-radius:26px;background:rgba(255,253,248,.94);padding:2rem;box-shadow:0 22px 50px rgba(95,56,43,.13)}
  .eyebrow{font-family:Arial,sans-serif;font-size:.73rem;letter-spacing:.14em;font-weight:800;text-transform:uppercase;color:var(--coral)}h1{font-size:clamp(2rem,6vw,3.4rem);line-height:.95;margin:.45rem 0 1rem;letter-spacing:-.05em}p{font-family:Arial,sans-serif;color:var(--muted);line-height:1.55}.receipt{font-family:Arial,sans-serif;border:1px dashed #d9bdae;border-radius:14px;background:#fff7f0;padding:.8rem 1rem;color:#765d54;font-size:.84rem;margin:1.2rem 0}
  .stars{display:flex;flex-direction:row-reverse;justify-content:flex-end;gap:.35rem;margin:1rem 0 1.2rem}.stars input{position:absolute;opacity:0}.stars label{width:48px;height:48px;display:grid;place-items:center;border:1px solid #eddcd3;border-radius:14px;background:#fff;color:#c7aaa0;font-family:Arial,sans-serif;font-size:1.45rem;font-weight:bold;cursor:pointer;transition:.15s}.stars input:checked~label,.stars label:hover,.stars label:hover~label{background:#fff2d9;color:#df8c00;border-color:#edc46e;transform:translateY(-2px)}label{display:block;font-family:Arial,sans-serif;font-size:.82rem;font-weight:bold;color:#5f4b45}textarea{width:100%;min-height:120px;resize:vertical;border:1px solid #e5d2c8;border-radius:14px;padding:.85rem;font:15px Arial,sans-serif;color:var(--ink);margin-top:.5rem}textarea:focus{outline:3px solid rgba(221,94,68,.16);border-color:var(--coral)}button{border:0;border-radius:14px;background:linear-gradient(135deg,var(--wine),#cf4035);color:#fff;font:700 15px Arial,sans-serif;padding:.9rem 1.15rem;cursor:pointer;width:100%;margin-top:1rem;box-shadow:0 10px 20px rgba(168,14,39,.19)}button:hover{filter:brightness(1.04)}.message{border-radius:14px;padding:.85rem 1rem;font:14px Arial,sans-serif;margin:1rem 0}.message.success{background:#eaf8ed;color:#087443;border:1px solid #b8e0c1}.message.error{background:#fff0ee;color:#a42720;border:1px solid #f2c0ba}.closed{padding:1.25rem 0;text-align:center}.closed-mark{width:70px;height:70px;border-radius:50%;display:grid;place-items:center;margin:0 auto 1rem;background:#f6e0d7;color:var(--wine);font:700 30px Arial,sans-serif}.footer{margin-top:1.5rem;text-align:center;font:12px Arial,sans-serif;color:#9c8178}@media(max-width:520px){.review-shell{width:min(100% - 1rem,650px);padding:1rem 0}.review-card{padding:1.35rem;border-radius:20px}.stars label{width:42px;height:42px}}
</style></head><body><main class="review-shell"><section class="review-card"><div class="eyebrow">Ulasan pelanggan</div><h1>Bagaimana pengalaman Anda hari ini?</h1>
<?php if (!$review): ?><div class="message error">Tautan ulasan tidak ditemukan atau tidak lagi berlaku. Silakan minta bantuan tim kami bila ini terjadi setelah pembayaran.</div>
<?php elseif ($isHidden): ?><div class="closed"><div class="closed-mark">!</div><h2>Ulasan ini sudah ditutup</h2><p>Terima kasih sudah berkunjung ke <?= html_escape($outletName) ?>.</p></div>
<?php elseif ($isSubmitted): ?><div class="closed"><div class="closed-mark">OK</div><h2>Terima kasih atas ulasannya.</h2><p>Masukan Anda sudah diterima oleh <?= html_escape($outletName) ?> dan membantu kami menjadi lebih baik.</p></div>
<?php else: ?>
  <p>Ceritakan sedikit pengalaman Anda di <?= html_escape($outletName) ?>. Ulasan ini hanya dapat diisi sekali untuk satu nota.</p>
  <div class="receipt">Nota <?= html_escape((string)($review['order_no_snapshot'] ?? '-')) ?></div>
  <?php if ($result): ?><div class="message <?= !empty($result['ok']) ? 'success' : 'error' ?>"><?= html_escape((string)($result['message'] ?? (!empty($result['ok']) ? 'Ulasan berhasil dikirim.' : 'Ulasan belum dapat dikirim.'))) ?></div><?php endif; ?>
  <form method="post" action="<?= site_url('review/' . rawurlencode((string)$token) . '/submit') ?>">
  <input type="hidden" name="_review_guard" value="<?= html_escape((string)($form_guard ?? '')) ?>">
  <div hidden aria-hidden="true"><label>Biarkan kosong<input name="website" value="" tabindex="-1" autocomplete="off"></label></div>
  <label>Berikan bintang</label><div class="stars" aria-label="Rating bintang"><input id="star-5" type="radio" name="rating" value="5" required><label for="star-5" title="5 bintang">&#9733;</label><input id="star-4" type="radio" name="rating" value="4"><label for="star-4" title="4 bintang">&#9733;</label><input id="star-3" type="radio" name="rating" value="3"><label for="star-3" title="3 bintang">&#9733;</label><input id="star-2" type="radio" name="rating" value="2"><label for="star-2" title="2 bintang">&#9733;</label><input id="star-1" type="radio" name="rating" value="1"><label for="star-1" title="1 bintang">&#9733;</label></div><label for="review-text">Cerita singkat Anda <span style="font-weight:normal;color:#9a837b">(opsional)</span></label><textarea id="review-text" name="review_text" maxlength="1200" placeholder="Apa yang paling Anda suka atau perlu kami perbaiki?"></textarea>
  <p style="font-size:12px">Ulasan digunakan oleh tim outlet untuk memperbaiki pelayanan. Jangan menuliskan password atau data pribadi sensitif. Hubungi kasir bila ingin mengoreksi masukan Anda.</p>
  <button type="submit" <?= empty($form_guard) ? 'disabled' : '' ?>>Kirim Ulasan</button></form>
<?php endif; ?>
<div class="footer">Terima kasih telah berkunjung ke <?= html_escape($outletName) ?>.</div></section></main></body></html>
