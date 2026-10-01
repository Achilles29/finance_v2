<?php
$logoPrimaryFs = FCPATH . 'assets/uploads/logo/logo.png';
$logoFallbackFs = FCPATH . 'assets/img/logo.png';
$businessProfile = is_array($business_profile ?? null) ? $business_profile : [];
$businessName = trim((string)($businessProfile['display_name'] ?? ''));
$businessName = $businessName !== '' ? $businessName : 'Finance';
$businessLogo = trim((string)($businessProfile['logo_url'] ?? ''));
$logoUrl = $businessLogo !== '' ? $businessLogo : (file_exists($logoPrimaryFs)
    ? base_url('assets/uploads/logo/logo.png')
    : (is_file($logoFallbackFs) ? base_url('assets/img/logo.png') : base_url('assets/img/business-placeholder.svg')));
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Login — <?= html_escape($businessName) ?></title>
<link rel="icon" href="<?= (is_file(FCPATH . 'assets/img/favicon.ico') ? base_url('assets/img/favicon.ico') : base_url('assets/img/business-placeholder.svg')) ?>">
<style>
:root{
  --bg:#efe7dd;
  --bg2:#f8f4ee;
  --card:#fffdfa;
  --line:#e5d6c8;
  --ink:#2f2326;
  --muted:#7f6b70;
  --brand:#9c1729;
  --brand2:#6f1120;
}
*{box-sizing:border-box}
html,body{height:100%}
body{
  margin:0;
  font-family:"Plus Jakarta Sans","Segoe UI",Arial,sans-serif;
  color:var(--ink);
  background:
    radial-gradient(680px 340px at -8% -10%, rgba(224,164,171,.30) 0%, rgba(224,164,171,0) 68%),
    radial-gradient(640px 340px at 106% 108%, rgba(236,183,141,.30) 0%, rgba(236,183,141,0) 68%),
    linear-gradient(145deg,var(--bg2),var(--bg));
}
.auth{
  min-height:100dvh;
  display:grid;
  place-items:center;
  padding:20px;
}
.shell{
  width:min(1080px,100%);
  display:grid;
  grid-template-columns:44% 56%;
  border-radius:24px;
  overflow:hidden;
  box-shadow:0 20px 54px rgba(38,20,20,.16);
  background:var(--card);
}
.showcase{
  padding:26px;
  color:#fff;
  background:
    radial-gradient(260px 200px at 84% 18%, rgba(255,255,255,.24), rgba(255,255,255,0) 70%),
    linear-gradient(160deg,var(--brand2),var(--brand));
  display:flex;
  flex-direction:column;
  justify-content:space-between;
}
.brand{
  display:flex;
  align-items:center;
  gap:10px;
}
.brand .mark{
  width:58px;
  height:58px;
  border-radius:14px;
  background:#fff;
  display:grid;
  place-items:center;
  box-shadow:0 10px 24px rgba(0,0,0,.2);
}
.brand .mark img{
  width:42px;height:42px;object-fit:contain;
}
.brand strong{font-size:21px; font-weight:800}
.chip{
  display:inline-block;
  margin-top:14px;
  border:1px solid rgba(255,255,255,.24);
  background:rgba(255,255,255,.12);
  padding:6px 11px;
  border-radius:999px;
  font-size:11px;
  font-weight:700;
  letter-spacing:.25px;
}
.showcase h1{
  margin:14px 0 8px;
  font-size:44px;
  line-height:1.06;
  letter-spacing:.2px;
  font-family:"DM Serif Display",Georgia,"Times New Roman",serif;
}
.showcase p{
  margin:0;
  opacity:.95;
  max-width:330px;
  line-height:1.65;
  font-size:16px;
}
.showcase small{
  font-size:12px;
  opacity:.86;
}
.panel{
  padding:26px 28px 22px;
  background:var(--card);
}
.mobile-brand{
  display:none;
  align-items:center;
  gap:9px;
  margin-bottom:12px;
}
.mobile-brand .mark{
  width:40px;height:40px;border-radius:10px;background:#fff;border:1px solid #ecded1;
  display:grid;place-items:center;
}
.mobile-brand .mark img{width:28px;height:28px;object-fit:contain}
.mobile-brand strong{font-size:18px}
.kicker{
  display:inline-block;
  font-size:11px;
  font-weight:700;
  color:#972233;
  border:1px solid #f1d0c6;
  background:#fff5f1;
  padding:6px 10px;
  border-radius:999px;
}
.panel h2{
  margin:12px 0 4px;
  font-size:42px;
  line-height:1;
  font-family:"DM Serif Display",Georgia,"Times New Roman",serif;
}
.panel .sub{
  margin:0 0 18px;
  color:var(--muted);
  font-size:15px;
}
.alert{
  border:1px solid #f4c8cb;
  background:#fff2f3;
  color:#8e2230;
  border-radius:12px;
  padding:10px 12px;
  margin-bottom:12px;
  font-size:13px;
}
.field{margin-bottom:12px}
label{
  display:block;
  margin-bottom:6px;
  font-size:14px;
  font-weight:700;
  color:#513e42;
}
.input{
  width:100%;
  border:1px solid var(--line);
  border-radius:13px;
  min-height:44px;
  padding:10px 12px;
  font-size:16px;
  color:#302326;
  background:#fff;
  outline:none;
  transition:all .16s ease;
}
.input:focus{
  border-color:#c89ca0;
  box-shadow:0 0 0 4px rgba(156,23,41,.1);
}
.pw{
  display:grid;
  grid-template-columns:1fr 46px;
  gap:8px;
}
.toggle{
  border:1px solid var(--line);
  border-radius:12px;
  background:#fff;
  cursor:pointer;
}
.btn{
  width:100%;
  border:0;
  border-radius:13px;
  min-height:46px;
  margin-top:2px;
  background:linear-gradient(135deg,var(--brand),var(--brand2));
  color:#fff;
  font-size:18px;
  font-weight:800;
  box-shadow:0 10px 22px rgba(127,17,32,.24);
  cursor:pointer;
}
.btn:hover{filter:brightness(1.03)}
.foot{
  margin-top:12px;
  text-align:center;
  color:#8b757a;
  font-size:12px;
}
@media (max-width:960px){
  .shell{
    grid-template-columns:1fr;
    max-width:470px;
  }
  .showcase{display:none}
  .panel{
    padding:18px 16px 16px;
  }
  .mobile-brand{display:flex}
  .panel h2{font-size:36px}
  .panel .sub{font-size:14px;margin-bottom:14px}
}
@media (max-width:480px){
  .auth{padding:10px}
  .shell{
    border-radius:18px;
    box-shadow:0 14px 34px rgba(38,20,20,.13);
  }
  .panel h2{font-size:32px}
  .input{min-height:42px;font-size:15px}
  .btn{min-height:44px;font-size:16px}
}
</style>
</head>
<body>
<main class="auth">
  <section class="shell">
    <aside class="showcase">
      <div>
        <div class="brand">
          <span class="mark"><img src="<?= html_escape($logoUrl) ?>" alt="<?= html_escape($businessName) ?>"></span>
          <strong><?= html_escape($businessName) ?></strong>
        </div>
        <span class="chip">CORE BUSINESS SUITE</span>
        <h1>Control Tower<br>Bisnis Harian.</h1>
        <p>Absensi, purchase, keuangan, kasir, dan inventory dalam satu sistem yang konsisten dan siap audit.</p>
      </div>
      <small><?= html_escape($businessName) ?></small>
    </aside>

    <aside class="panel">
      <div class="mobile-brand">
        <span class="mark"><img src="<?= html_escape($logoUrl) ?>" alt="<?= html_escape($businessName) ?>"></span>
        <strong><?= html_escape($businessName) ?></strong>
      </div>
      <span class="kicker">Secure Access</span>
      <h2>Masuk</h2>
      <p class="sub">Gunakan akun yang sudah terdaftar untuk melanjutkan.</p>

      <?php if (!empty($error_msg)): ?>
        <div class="alert"><?= $error_msg ?></div>
      <?php endif; ?>

      <?= form_open('auth/do_login', ['autocomplete' => 'off']) ?>
        <div class="field">
          <label for="identifier">Username atau Email</label>
          <input class="input" id="identifier" type="text" name="identifier" maxlength="150" value="<?= set_value('identifier') ?>" placeholder="contoh: superadmin" required autofocus>
        </div>
        <div class="field">
          <label for="password">Password</label>
          <div class="pw">
            <input class="input" id="password" type="password" name="password" maxlength="72" placeholder="••••••••" required>
            <button class="toggle" type="button" id="togglePass" aria-label="Tampilkan/Sembunyikan password">◉</button>
          </div>
        </div>
        <button class="btn" type="submit">Masuk ke Dashboard</button>
      <?= form_close() ?>
      <div class="foot">© <?= date('Y') ?> <?= html_escape($businessName) ?></div>
    </aside>
  </section>
</main>

<script>
(function () {
  var btn = document.getElementById('togglePass');
  var input = document.getElementById('password');
  if (!btn || !input) return;
  btn.addEventListener('click', function () {
    input.type = input.type === 'password' ? 'text' : 'password';
  });
})();
</script>
</body>
</html>
