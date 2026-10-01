<?php
$row = $row ?? null;
$token = $token ?? '';
$approvalMap = $approval_map ?? [];
$signatureMap = $signature_map ?? [];
?><!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Verifikasi Kontrak</title>
  <style>
    body { font-family: Arial, sans-serif; background:#f4f5f8; margin:0; color:#111; }
    .wrap { max-width: 860px; margin: 22px auto; padding: 0 14px; }
    .card { background:#fff; border-radius:10px; box-shadow:0 2px 12px rgba(0,0,0,.08); padding:16px; margin-bottom:12px; }
    .grid { display:grid; grid-template-columns:1fr 1fr; gap:8px 18px; font-size:14px; }
    .label { color:#666; }
    .ok { color:#1b7f3a; font-weight:bold; }
    .bad { color:#b42318; font-weight:bold; }
    .badge { display:inline-block; padding:4px 8px; border:1px solid #ccc; border-radius:999px; font-size:11px; }
    .doc { font-family:"Times New Roman", Times, serif; font-size:14px; line-height:1.75; color:#000; border:1px solid #ddd; border-radius:8px; padding:18px; overflow:auto; background:#fff; }
    .sign-grid { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
    .sign-box { border:1px solid #e4e5e8; border-radius:8px; padding:10px; min-height:110px; }
    .sign-img { max-width:220px; max-height:80px; display:block; margin:8px 0; }
    @media (max-width: 720px) {
      .grid, .sign-grid { grid-template-columns:1fr; }
    }
  </style>
</head>
<body>
  <div class="wrap">
    <div class="card">
      <h2 style="margin:0 0 8px;">Verifikasi TTE Kontrak</h2>
      <div style="font-size:13px; color:#666;">Token: <code><?php echo htmlspecialchars((string)$token, ENT_QUOTES, 'UTF-8'); ?></code></div>
    </div>

    <?php if (!$row): ?>
      <div class="card">
        <div class="bad">Dokumen tidak ditemukan atau token tidak valid.</div>
      </div>
    <?php else: ?>
      <div class="card">
        <div class="ok">Dokumen valid dan terdaftar di sistem.</div>
        <div class="grid" style="margin-top:10px;">
          <div><span class="label">Nomor:</span> <?php echo htmlspecialchars((string)($row['contract_number'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></div>
          <div><span class="label">Status:</span> <span class="badge"><?php echo htmlspecialchars((string)($row['status'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></span></div>
          <div><span class="label">Pegawai:</span> <?php echo htmlspecialchars((string)($row['employee_name'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?> (<?php echo htmlspecialchars((string)($row['employee_code'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?>)</div>
          <div><span class="label">Jenis:</span> <?php echo htmlspecialchars((string)($row['contract_type'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></div>
          <div><span class="label">Periode:</span> <?php echo htmlspecialchars((string)($row['start_date'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?> s/d <?php echo htmlspecialchars((string)($row['end_date'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></div>
          <div><span class="label">Issued:</span> <?php echo htmlspecialchars((string)($row['document_issued_at'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></div>
        </div>
      </div>

      <?php $contractBodyHtml = (string)($row['body_html_rendered'] ?? $row['body_html'] ?? ''); ?>
      <?php if ($contractBodyHtml !== ''): ?>
        <div class="card">
          <h4 style="margin-top:0;">Dokumen Kontrak</h4>
          <div class="doc">
            <?php echo $contractBodyHtml; ?>
          </div>
        </div>
      <?php endif; ?>

      <div class="card">
        <h4 style="margin-top:0;">Approval & Tanda Tangan</h4>
        <div class="sign-grid">
          <?php foreach (['EMPLOYEE', 'COMPANY'] as $role): ?>
            <?php $approval = $approvalMap[$role] ?? null; $signature = $signatureMap[$role] ?? null; ?>
            <div class="sign-box">
              <div><strong><?php echo htmlspecialchars($role, ENT_QUOTES, 'UTF-8'); ?></strong></div>
              <div style="font-size:13px;">Approval: <?php echo htmlspecialchars((string)($approval['approval_status'] ?? 'PENDING'), ENT_QUOTES, 'UTF-8'); ?></div>
              <div style="font-size:13px;">Signed: <?php echo !empty($signature) ? 'YA' : 'BELUM'; ?></div>
              <?php if (!empty($signature['signature_data'])): ?>
                <img class="sign-img" src="<?php echo htmlspecialchars((string)$signature['signature_data'], ENT_QUOTES, 'UTF-8'); ?>" alt="Signature <?php echo htmlspecialchars($role, ENT_QUOTES, 'UTF-8'); ?>">
              <?php endif; ?>
              <?php if (!empty($signature['signer_name'])): ?>
                <div style="font-size:12px; color:#666;"><?php echo htmlspecialchars((string)$signature['signer_name'], ENT_QUOTES, 'UTF-8'); ?> - <?php echo htmlspecialchars((string)($signature['signed_at'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></div>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="card">
        <div style="font-size:13px;"><span class="label">Hash Dokumen:</span> <code><?php echo htmlspecialchars((string)($row['final_document_hash'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></code></div>
      </div>
    <?php endif; ?>
  </div>
</body>
</html>
