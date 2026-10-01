<?php
$detail = is_array($detail ?? null) ? $detail : [];
$header = is_array($detail['header'] ?? null) ? $detail['header'] : [];
$summary = is_array($detail['summary'] ?? null) ? $detail['summary'] : [];
$rows = is_array($detail['rows'] ?? null) ? $detail['rows'] : [];

$divisionText = trim((string)($header['division_code'] ?? ''));
if (trim((string)($header['division_name'] ?? '')) !== '') {
    $divisionText = $divisionText !== ''
        ? $divisionText . ' - ' . trim((string)$header['division_name'])
        : trim((string)$header['division_name']);
}
?>

<style>
  .component-lot-usage-shell { display:grid; gap:1rem; }
  .component-lot-usage-hero {
    border:1px solid rgba(224,209,198,.88);
    border-radius:26px;
    background:linear-gradient(135deg,#fffaf7 0%,#fff 100%);
    box-shadow:0 18px 38px rgba(58,38,30,.08);
    padding:1.1rem 1.2rem;
  }
  .component-lot-usage-grid { display:grid; gap:.75rem; grid-template-columns:repeat(4,minmax(0,1fr)); }
  .component-lot-usage-box {
    border:1px solid rgba(226,212,200,.88);
    border-radius:18px;
    background:#fff;
    padding:.85rem .95rem;
  }
  .component-lot-usage-label { font-size:.72rem; text-transform:uppercase; letter-spacing:.04em; color:#8a7a72; margin-bottom:.2rem; }
  .component-lot-usage-value { font-size:.92rem; font-weight:800; color:#312729; line-height:1.3; }
  @media (max-width: 991.98px) {
    .component-lot-usage-grid { grid-template-columns:repeat(2,minmax(0,1fr)); }
  }
</style>

<div class="component-lot-usage-shell">
  <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div>
      <h4 class="mb-1"><?php echo html_escape((string)($page_title ?? 'Pemakaian Lot Component')); ?></h4>
      <small class="text-muted">Jejak konsumsi lot base/prepare ke resep, batch, adjustment, atau transaksi lanjutan.</small>
    </div>
    <a href="<?php echo site_url('production/component-lots'); ?>" class="btn btn-outline-secondary btn-sm">Kembali ke Lot FIFO</a>
  </div>

  <div class="component-lot-usage-hero">
    <div class="component-lot-usage-grid mb-3">
      <div class="component-lot-usage-box"><div class="component-lot-usage-label">Lot</div><div class="component-lot-usage-value"><?php echo html_escape((string)($header['lot_no'] ?? '-')); ?></div></div>
      <div class="component-lot-usage-box"><div class="component-lot-usage-label">Component</div><div class="component-lot-usage-value"><?php echo html_escape((string)($header['component_name'] ?? '-')); ?></div></div>
      <div class="component-lot-usage-box"><div class="component-lot-usage-label">Divisi / Lokasi</div><div class="component-lot-usage-value"><?php echo html_escape($divisionText !== '' ? $divisionText : '-'); ?><br><small class="text-muted"><?php echo html_escape((string)($header['location_type'] ?? '-')); ?></small></div></div>
      <div class="component-lot-usage-box"><div class="component-lot-usage-label">Saldo Kini</div><div class="component-lot-usage-value"><?php echo number_format((float)($header['qty_balance'] ?? 0), 2, ',', '.'); ?> <?php echo html_escape((string)($header['uom_code'] ?? '')); ?></div></div>
    </div>
    <div class="component-lot-usage-grid">
      <div class="component-lot-usage-box"><div class="component-lot-usage-label">Jumlah Pemakaian</div><div class="component-lot-usage-value"><?php echo number_format((int)($summary['usage_count'] ?? 0)); ?></div></div>
      <div class="component-lot-usage-box"><div class="component-lot-usage-label">Qty Keluar</div><div class="component-lot-usage-value"><?php echo number_format((float)($summary['qty_out_total'] ?? 0), 2, ',', '.'); ?></div></div>
      <div class="component-lot-usage-box"><div class="component-lot-usage-label">Nilai Keluar</div><div class="component-lot-usage-value">Rp <?php echo number_format((float)($summary['cost_total'] ?? 0), 2, ',', '.'); ?></div></div>
      <div class="component-lot-usage-box"><div class="component-lot-usage-label">Status</div><div class="component-lot-usage-value"><?php echo ui_status_badge((string)($header['status'] ?? 'OPEN')); ?></div></div>
    </div>
  </div>

  <div class="card border-0 shadow-sm">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
          <thead>
            <tr>
              <th>Tanggal</th>
              <th>Dokumen</th>
              <th>Sumber</th>
              <th class="text-end">Qty</th>
              <th class="text-end">Unit Cost</th>
              <th class="text-end">Total</th>
              <th>Catatan</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($rows)): ?>
              <tr><td colspan="7" class="text-center text-muted py-4">Lot component ini belum pernah dipakai keluar.</td></tr>
            <?php else: ?>
              <?php foreach ($rows as $row): ?>
                <tr>
                  <td><div class="fw-semibold"><?php echo html_escape((string)($row['issue_date'] ?? '-')); ?></div></td>
                  <td>
                    <div class="fw-semibold"><?php echo html_escape((string)($row['issue_no'] ?? '-')); ?></div>
                    <small class="text-muted"><?php echo html_escape((string)($row['status'] ?? '-')); ?></small>
                  </td>
                  <td>
                    <div><?php echo html_escape((string)($row['source_module'] ?? '-')); ?></div>
                    <small class="text-muted"><?php echo html_escape((string)($row['source_table'] ?? '-')); ?><?php echo !empty($row['source_id']) ? ' #' . (int)$row['source_id'] : ''; ?></small>
                  </td>
                  <td class="text-end fw-semibold"><?php echo number_format((float)($row['qty_out'] ?? 0), 2, ',', '.'); ?></td>
                  <td class="text-end"><?php echo number_format((float)($row['unit_cost'] ?? 0), 2, ',', '.'); ?></td>
                  <td class="text-end">Rp <?php echo number_format((float)($row['total_cost'] ?? 0), 2, ',', '.'); ?></td>
                  <td><small><?php echo html_escape((string)($row['notes'] ?? '-')); ?></small></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
