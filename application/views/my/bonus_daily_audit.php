<?php
$detail = $detail ?? ['header' => [], 'slice_rows' => []];
$header = $detail['header'] ?? [];
$sliceRows = $detail['slice_rows'] ?? [];
$backUrl = $back_url ?? site_url('my/bonus');
?>

<style>
  .my-bonus-audit-card { border:1px solid rgba(122,24,36,.08); border-radius:22px; box-shadow:0 16px 34px rgba(89,57,41,.06); }
  .my-bonus-audit-summary { display:grid; grid-template-columns:repeat(4, minmax(0,1fr)); gap:1rem; }
  .my-bonus-audit-box { border:1px solid rgba(122,24,36,.08); border-radius:18px; padding:1rem 1.05rem; background:linear-gradient(180deg,#fff,#fff8f5); }
  .my-bonus-audit-box .label { font-size:.76rem; text-transform:uppercase; letter-spacing:.04em; color:#8d7368; margin-bottom:.35rem; display:block; }
  .my-bonus-audit-box .value { color:#5f1720; font-weight:800; }
  .my-bonus-audit-table-wrap { max-height:68vh; overflow:auto; border:1px solid rgba(122,24,36,.08); border-radius:18px; }
  .my-bonus-audit-table-wrap thead th { position:sticky; top:0; z-index:2; background:linear-gradient(135deg,#b11226,#8f1021); color:#fff; border:0; white-space:nowrap; }
  @media (max-width: 991.98px) { .my-bonus-audit-summary { grid-template-columns:repeat(2, minmax(0,1fr)); } }
  @media (max-width: 575.98px) { .my-bonus-audit-summary { grid-template-columns:1fr; } }
</style>

<div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-3">
  <div>
    <h4 class="mb-1">Detail Bonus Harian Saya</h4>
    <div class="text-muted small">Di sini Anda bisa melihat transaksi pembentuk hari kerja tersebut, berapa orang aktif di jam yang sama, dan penalti apa yang ikut memengaruhi hasil akhirnya.</div>
  </div>
  <a href="<?php echo html_escape($backUrl); ?>" class="btn btn-outline-secondary">Kembali</a>
</div>

<div class="card my-bonus-audit-card">
  <div class="card-body">
    <div class="my-bonus-audit-summary mb-4">
      <div class="my-bonus-audit-box">
        <span class="label">Pegawai</span>
        <div class="value"><?php echo html_escape((string)($header['employee_name'] ?? '-')); ?></div>
        <div class="small text-muted"><?php echo html_escape((string)($header['division_name'] ?? '-')); ?> · <?php echo html_escape((string)($header['position_name'] ?? '-')); ?></div>
      </div>
      <div class="my-bonus-audit-box">
        <span class="label">Tanggal dan Shift</span>
        <div class="value"><?php echo html_escape((string)($header['attendance_date'] ?? '-')); ?></div>
        <div class="small text-muted"><?php echo html_escape(trim((string)(($header['shift_code'] ?? '') . ' ' . ($header['shift_name'] ?? '')))); ?></div>
      </div>
      <div class="my-bonus-audit-box">
        <span class="label">Penalti Hari Ini</span>
        <div class="value">Rp <?php echo number_format((float)($header['penalty_amount'] ?? 0), 2, ',', '.'); ?></div>
        <div class="small text-muted">Total poin potong: <?php echo number_format((float)($header['penalty_point'] ?? 0), 2, ',', '.'); ?></div>
      </div>
      <div class="my-bonus-audit-box">
        <span class="label">Status</span>
        <div class="value"><?php echo html_escape((string)($header['approval_status'] ?? 'DRAFT')); ?></div>
        <div class="small text-muted"><?php echo html_escape((string)($header['rule_name'] ?? '-')); ?></div>
      </div>
    </div>

    <div class="my-bonus-audit-table-wrap">
      <table class="table align-middle mb-0">
        <thead>
          <tr>
            <th>Jam</th>
            <th>Transaksi</th>
            <th>Shift Sumber</th>
            <th class="text-end">Omzet Slice</th>
            <th class="text-center">Orang Aktif</th>
            <th class="text-end">Bobot Saya</th>
            <th class="text-end">Omzet Porsi Saya</th>
          </tr>
        </thead>
        <tbody>
        <?php if (empty($sliceRows)): ?>
          <tr><td colspan="7" class="text-center text-muted py-4">Belum ada rincian irisan transaksi untuk bonus harian ini.</td></tr>
        <?php else: ?>
          <?php foreach ($sliceRows as $row): ?>
            <tr>
              <td><?php echo html_escape((string)($row['slice_label'] ?? substr((string)($row['slice_started_at'] ?? ''), 11, 8))); ?></td>
              <td>
                <strong><?php echo !empty($row['source_order_id']) ? ('Order #' . (int)$row['source_order_id']) : 'Pool manual'; ?></strong>
                <div class="small text-muted"><?php echo html_escape((string)($row['slice_notes'] ?? '-')); ?></div>
              </td>
              <td><?php echo html_escape(trim((string)(($row['source_shift_code'] ?? '') . ' ' . ($row['source_shift_name'] ?? '')))); ?></td>
              <td class="text-end">Rp <?php echo number_format((float)($row['net_sales_amount'] ?? 0), 2, ',', '.'); ?></td>
              <td class="text-center"><?php echo number_format((int)($row['active_employee_count'] ?? 0)); ?></td>
              <td class="text-end"><?php echo number_format((float)($row['raw_point'] ?? 0), 4, ',', '.'); ?></td>
              <td class="text-end">Rp <?php echo number_format((float)($row['attributable_revenue'] ?? 0), 2, ',', '.'); ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
