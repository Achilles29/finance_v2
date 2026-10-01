<?php
defined('BASEPATH') OR exit('No direct script access allowed');
$summary = $summary ?? [];
$dailyRows = $daily_rows ?? [];
$penaltyRows = $penalty_rows ?? [];
$status = strtoupper((string)($summary['monthly_status'] ?? $summary['payout_status'] ?? 'DRAFT'));
$scopeParts = [];
if (!empty($summary['outlet_name'])) {
    $scopeParts[] = (string)$summary['outlet_name'];
}
if (!empty($summary['division_name'])) {
    $scopeParts[] = (string)$summary['division_name'];
}
$scopeLabel = !empty($scopeParts) ? implode(' - ', $scopeParts) : 'Global';
?>

<style>
  .bonus-detail-shell { display:grid; gap:1rem; }
  .bonus-detail-hero { border:1px solid rgba(122,24,36,.08); border-radius:24px; background:linear-gradient(135deg,#fffaf4 0%,#fff 60%,#fff5ef 100%); box-shadow:0 18px 36px rgba(122,24,36,.08); padding:1.25rem; }
  .bonus-detail-kpi { border:1px solid rgba(122,24,36,.08); border-radius:18px; background:#fff; padding:1rem; }
  .bonus-detail-kpi .label { font-size:.78rem; text-transform:uppercase; letter-spacing:.04em; color:#8a7266; }
  .bonus-detail-kpi .value { font-size:1.15rem; font-weight:800; color:#6b1322; }
  .bonus-detail-table-wrap { max-height:520px; overflow:auto; }
  .bonus-detail-table-wrap thead th { position:sticky; top:0; background:#fff; z-index:1; white-space:nowrap; }
</style>

<div class="bonus-detail-shell">
  <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div>
      <h4 class="mb-1">Detail Rekap Bonus Bulanan</h4>
      <div class="text-muted small">Ringkasan bonus dan penalti pegawai untuk bulan <?php echo html_escape($month ?? date('Y-m')); ?>.</div>
    </div>
    <a href="<?php echo site_url('payroll/bonus?' . http_build_query(['tab' => 'monthly', 'month' => $month ?? date('Y-m')])); ?>" class="btn btn-outline-secondary">Kembali ke Rekap Bulanan</a>
  </div>

  <div class="bonus-detail-hero">
    <div class="row g-3 align-items-start">
      <div class="col-lg-5">
        <div class="small text-uppercase text-muted fw-semibold mb-2">Pegawai</div>
        <h5 class="mb-1"><?php echo html_escape((string)($summary['employee_name'] ?? '-')); ?></h5>
        <div class="text-muted small"><?php echo html_escape((string)($summary['employee_code'] ?? '-')); ?></div>
        <div class="text-muted small mt-2"><?php echo html_escape($scopeLabel); ?></div>
      </div>
      <div class="col-lg-4">
        <div class="small text-uppercase text-muted fw-semibold mb-2">Kebijakan Bonus</div>
        <div class="fw-semibold"><?php echo html_escape((string)($summary['config_name'] ?? '-')); ?></div>
        <div class="text-muted small"><?php echo html_escape((string)($summary['rule_name'] ?? 'Tanpa rule spesifik')); ?></div>
      </div>
      <div class="col-lg-3 text-lg-end">
        <span class="badge rounded-pill bg-light text-dark border px-3 py-2"><?php echo html_escape($status); ?></span>
        <div class="text-muted small mt-2">Update terakhir <?php echo html_escape((string)($summary['last_attendance_date'] ?? '-')); ?></div>
      </div>
    </div>
  </div>

  <div class="row g-3">
    <div class="col-md-3"><div class="bonus-detail-kpi"><div class="label">Bonus Final</div><div class="value">Rp <?php echo number_format((float)($summary['total_final_amount'] ?? 0), 2, ',', '.'); ?></div></div></div>
    <div class="col-md-3"><div class="bonus-detail-kpi"><div class="label">Penalti</div><div class="value text-danger">Rp <?php echo number_format((float)($summary['total_penalty_amount'] ?? 0), 2, ',', '.'); ?></div></div></div>
    <div class="col-md-3"><div class="bonus-detail-kpi"><div class="label">Poin Final</div><div class="value"><?php echo number_format((float)($summary['total_final_point'] ?? 0), 4, ',', '.'); ?></div></div></div>
    <div class="col-md-3"><div class="bonus-detail-kpi"><div class="label">Peer / Layanan / Target</div><div class="value"><?php echo number_format((float)($summary['peer_avg_star'] ?? 0), 2, ',', '.'); ?> / <?php echo number_format((float)($summary['service_avg_score'] ?? 0), 2, ',', '.'); ?>% / <?php echo number_format((float)($summary['target_avg_score'] ?? 0), 2, ',', '.'); ?>%</div></div></div>
  </div>

  <div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-0 pb-0">
      <h6 class="mb-1">Audit Bonus Harian</h6>
      <div class="small text-muted">Setiap hari menampilkan hasil bonus dari irisan transaksi yang mengenai pegawai ini. Detail jam per jam bisa dibuka lewat tombol audit.</div>
    </div>
    <div class="card-body">
      <div class="table-responsive bonus-detail-table-wrap">
        <table class="table align-middle mb-0">
          <thead>
            <tr>
              <th>Tanggal</th>
              <th>Shift Kerja</th>
              <th class="text-center">Irisan</th>
              <th class="text-end">Omzet Porsi Saya</th>
              <th class="text-end">Bonus Kotor Saya</th>
              <th class="text-end">Poin Raw</th>
              <th class="text-end">Poin Penalti</th>
              <th class="text-end">Poin Final</th>
              <th class="text-end">Bonus Final</th>
              <th class="text-end">Target</th>
              <th class="text-end">Layanan</th>
              <th>Status</th>
              <th>Aksi</th>
            </tr>
          </thead>
          <tbody>
          <?php if (empty($dailyRows)): ?>
            <tr><td colspan="14" class="text-center text-muted py-4">Belum ada detail bonus harian.</td></tr>
          <?php else: foreach ($dailyRows as $row): ?>
            <tr>
              <td><?php echo html_escape((string)($row['attendance_date'] ?? $row['bonus_date'] ?? '-')); ?></td>
              <td><?php echo html_escape(trim((string)(($row['shift_code'] ?? '') . ' ' . ($row['shift_name'] ?? '')))); ?></td>
              <td class="text-center"><?php echo number_format((int)($row['slice_count'] ?? 0)); ?>x</td>
              <td class="text-end">Rp <?php echo number_format((float)($row['revenue_in_shift'] ?? 0), 2, ',', '.'); ?></td>
              <td class="text-end">Rp <?php echo number_format((float)($row['raw_amount'] ?? 0), 2, ',', '.'); ?></td>
              <td class="text-end"><?php echo number_format((float)($row['raw_point'] ?? 0), 4, ',', '.'); ?></td>
              <td class="text-end text-danger"><?php echo number_format((float)($row['penalty_point'] ?? 0), 4, ',', '.'); ?></td>
              <td class="text-end fw-semibold"><?php echo number_format((float)($row['final_point'] ?? 0), 4, ',', '.'); ?></td>
              <td class="text-end fw-semibold">Rp <?php echo number_format((float)($row['final_amount'] ?? 0), 2, ',', '.'); ?></td>
              <td class="text-end"><?php echo number_format((float)($row['target_score_percent'] ?? 0), 2, ',', '.'); ?>%</td>
              <td class="text-end"><?php echo number_format((float)($row['service_score_percent'] ?? 0), 2, ',', '.'); ?>%</td>
              <td><span class="badge bg-light text-dark border"><?php echo html_escape((string)($row['approval_status'] ?? 'DRAFT')); ?></span></td>
              <td><a href="<?php echo site_url('payroll/bonus/daily-detail/' . (int)($row['id'] ?? 0)); ?>" class="btn btn-sm btn-outline-secondary">Audit</a></td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-0 pb-0">
      <h6 class="mb-1">Detail Penalti Bulanan</h6>
      <div class="small text-muted">Semua penalti di bawah ini dibaca dari sumber yang sama, yaitu kejadian penalti pegawai pada bulan ini. Jadi nominal di sini seharusnya sejalan dengan rekap penalti bulanan.</div>
    </div>
    <div class="card-body">
      <div class="table-responsive bonus-detail-table-wrap">
        <table class="table align-middle mb-0">
          <thead>
            <tr>
              <th>Tanggal</th>
              <th>Jenis</th>
              <th>Shift</th>
              <th class="text-end">Poin</th>
              <th class="text-end">Nominal</th>
              <th>Status</th>
              <th>Sumber</th>
              <th>Alasan</th>
            </tr>
          </thead>
          <tbody>
          <?php if (empty($penaltyRows)): ?>
            <tr><td colspan="8" class="text-center text-muted py-4">Belum ada penalti bulan ini.</td></tr>
          <?php else: foreach ($penaltyRows as $row): ?>
            <tr>
              <td><?php echo html_escape((string)($row['penalty_date'] ?? '-')); ?></td>
              <td>
                <strong><?php echo html_escape((string)($row['penalty_name'] ?? '-')); ?></strong>
                <div class="small text-muted"><?php echo html_escape((string)($row['penalty_code'] ?? '-')); ?></div>
              </td>
              <td><?php echo html_escape(trim((string)(($row['shift_code'] ?? '') . ' ' . ($row['shift_name'] ?? '')))); ?></td>
              <td class="text-end"><?php echo number_format((float)($row['points_deducted'] ?? 0), 2, ',', '.'); ?></td>
              <td class="text-end text-danger">Rp <?php echo number_format((float)($row['amount_deducted'] ?? 0), 2, ',', '.'); ?></td>
              <td><span class="badge bg-light text-dark border"><?php echo html_escape((string)($row['status'] ?? '-')); ?></span></td>
              <td><?php echo html_escape((string)($row['source_type'] ?? 'MANUAL')); ?></td>
              <td><?php echo html_escape((string)($row['reason_text'] ?? '-')); ?></td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
