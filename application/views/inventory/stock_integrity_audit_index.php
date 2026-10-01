<?php
defined('BASEPATH') OR exit('No direct script access allowed');

$baseUrl = site_url('inventory/stock/integrity-audit');
$monthValue = substr((string)($month ?? date('Y-m-01')), 0, 7);
$auditData = is_array($audit ?? null) ? $audit : [];
$summary = (array)($auditData['summary'] ?? []);
$checks = (array)($auditData['checks'] ?? []);
$hasRun = !empty($run_audit);

$urlWithMonth = static function (string $path) use ($month): string {
    return site_url($path) . '?' . http_build_query(['month' => (string)$month]);
};
$nextStep = static function (string $code) use ($urlWithMonth): array {
    $steps = [
        'REQUIRED_SCHEMA' => ['Periksa deploy dan migration inventory sebelum memakai writer stok.', ''],
        'POS_DEFICIT_HPP_SCHEMA' => ['Jalankan migration HPP defisit yang disebut pada pesan audit, lalu audit ulang.', ''],
        'ACTIVE_STOCK_HEALTH' => ['Periksa selisih jumlah atau nilai pada Kesehatan Stok Aktif.', $urlWithMonth('inventory/stock/health')],
        'POSTED_MATERIAL_ADJUSTMENT_TRACE' => ['Telusuri dokumen adjustment bahan baku dan movement-nya sebelum membuat koreksi.', $urlWithMonth('inventory/stock/health')],
        'POSTED_COMPONENT_ADJUSTMENT_TRACE' => ['Telusuri adjustment component dan movement-nya sebelum membuat koreksi.', $urlWithMonth('production/component-reconcile')],
        'POSTED_COMPONENT_BATCH_TRACE' => ['Buka rekonsiliasi component dan telusuri batch sumbernya.', $urlWithMonth('production/component-reconcile')],
        'POSTED_TRANSFER_TRACE' => ['Telusuri dokumen transfer dan sisi asal/tujuannya.', $urlWithMonth('inventory/stock/health')],
        'POS_COMMIT_LINE_TRACE' => ['Buka audit stock commit POS untuk melihat transaksi sumber.', $urlWithMonth('pos/stock-commit-audit')],
        'POS_COMMIT_CANCELLED_BEFORE_POSTING' => ['Tidak perlu tindakan stok. Commit ini sudah dibatalkan sebelum worker membuat movement, lot, atau defisit.', ''],
        'POS_FULL_DEFICIT_PROVISIONAL_HPP' => ['Periksa HPP defisit POS dan jalankan repair aktif yang telah dipreflight.', $urlWithMonth('pos/stock-commit-audit')],
        'POS_PARTIAL_DEFICIT_PROVISIONAL_HPP' => ['Periksa HPP defisit POS dan jalankan repair aktif yang telah dipreflight.', $urlWithMonth('pos/stock-commit-audit')],
        'POS_FULL_DEFICIT_REFERENCE_LABEL' => ['Jalankan migration label defisit POS, lalu audit ulang.', ''],
        'ACTIVE_DEFICIT_ARITHMETIC' => ['Buka Defisit Stok dan telusuri dokumen sumber sebelum melakukan penyelesaian.', $urlWithMonth('inventory/stock/deficits')],
        'DEFICIT_COGS_FOUNDATION' => ['Jalankan migration fondasi koreksi HPP defisit, lalu audit ulang.', ''],
        'POS_DEFICIT_SETTLEMENT_COGS' => ['Telusuri settlement defisit dan koreksi HPP sumbernya.', $urlWithMonth('inventory/stock/deficits')],
        'POS_DEFICIT_SETTLEMENT_ZERO_COST' => ['Lengkapi biaya receipt, adjustment, atau katalog sumber sebelum koreksi HPP.', $urlWithMonth('inventory/stock/deficits')],
        'DEFICIT_COGS_ARITHMETIC' => ['Telusuri koreksi HPP dan pembalikannya sebelum melakukan repair terkontrol.', $urlWithMonth('inventory/stock/deficits')],
        'ACTIVE_NEGATIVE_MONTHLY_BALANCE' => ['Buka Kesehatan Stok Aktif dan telusuri sumber historisnya.', $urlWithMonth('inventory/stock/health')],
        'CLOSED_PERIOD_WRITE' => ['Buka Tutup Periode Stok dan telusuri dokumen yang menulis setelah penutupan.', $urlWithMonth('inventory/stock/periods')],
    ];

    return $steps[$code] ?? ['Telusuri dokumen sumber dan lakukan tindakan melalui modul resmi yang sesuai.', ''];
};
$fieldLabel = static function (string $key): string {
    $labels = [
        'id' => 'ID', 'deficit_id' => 'Defisit', 'settlement_id' => 'Penyelesaian', 'commit_id' => 'Commit POS',
        'commit_line_id' => 'Line commit', 'cogs_adjustment_id' => 'Koreksi HPP', 'adjustment_no' => 'No. adjustment',
        'batch_no' => 'No. batch', 'transfer_no' => 'No. transfer', 'commit_no' => 'No. commit',
        'deficit_date' => 'Tanggal defisit', 'settlement_date' => 'Tanggal penyelesaian',
        'adjustment_date' => 'Tanggal adjustment', 'batch_date' => 'Tanggal batch', 'transfer_date' => 'Tanggal transfer',
        'movement_date' => 'Tanggal movement', 'stock_domain' => 'Jenis stok', 'location_scope' => 'Lokasi',
        'location_type' => 'Tipe lokasi', 'destination_type' => 'Tujuan', 'division_id' => 'Divisi',
        'source_name_snapshot' => 'Barang / sumber', 'profile_key' => 'Profil', 'qty' => 'Qty',
        'requested_qty' => 'Kebutuhan', 'issued_qty' => 'Lot keluar', 'qty_remaining' => 'Sisa defisit',
        'written_off_qty' => 'Tutup administratif', 'expected_remaining' => 'Sisa seharusnya',
        'qty_settled' => 'Qty selesai', 'unit_cost' => 'Biaya/unit', 'total_cost_live' => 'HPP tersimpan',
        'estimated_unit_cost' => 'Biaya sementara', 'movement_ref_type' => 'Referensi',
        'movement_ref_id' => 'ID referensi', 'cost_source' => 'Sumber biaya', 'status' => 'Status',
        'committed_at' => 'Diposting pada', 'created_at' => 'Dibuat pada', 'line_count' => 'Jumlah line',
        'persisted_trace_count' => 'Jejak stok',
    ];
    return $labels[$key] ?? ucwords(str_replace('_', ' ', $key));
};
$sampleText = static function ($sample) use ($fieldLabel): string {
    if (!is_array($sample)) {
        return (string)$sample;
    }
    $preferred = [
        'adjustment_no', 'batch_no', 'transfer_no', 'commit_no', 'deficit_id', 'settlement_id', 'cogs_adjustment_id', 'id',
        'deficit_date', 'settlement_date', 'adjustment_date', 'batch_date', 'transfer_date', 'movement_date',
        'source_name_snapshot', 'stock_domain', 'location_scope', 'location_type', 'destination_type', 'division_id',
        'requested_qty', 'issued_qty', 'written_off_qty', 'qty_remaining', 'expected_remaining', 'qty_settled', 'unit_cost', 'estimated_unit_cost',
        'total_cost_live', 'movement_ref_type', 'movement_ref_id', 'cost_source', 'status',
    ];
    $parts = [];
    foreach ($preferred as $key) {
        if (!array_key_exists($key, $sample) || $sample[$key] === null || $sample[$key] === '') {
            continue;
        }
        $value = is_scalar($sample[$key]) ? (string)$sample[$key] : json_encode($sample[$key]);
        $parts[] = $fieldLabel($key) . ': ' . $value;
        if (count($parts) >= 8) {
            break;
        }
    }
    if (empty($parts)) {
        foreach ($sample as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $parts[] = $fieldLabel((string)$key) . ': ' . (is_scalar($value) ? (string)$value : json_encode($value));
            if (count($parts) >= 8) {
                break;
            }
        }
    }
    return implode(' | ', $parts);
};
$statusClass = static function (string $status): string {
    $status = strtoupper($status);
    if ($status === 'ERROR') {
        return 'is-error';
    }
    if ($status === 'WARNING') {
        return 'is-warning';
    }
    if ($status === 'INFO') {
        return 'is-info';
    }
    return 'is-pass';
};
?>

<style>
.integrity-audit{--ia-ink:#402620;--ia-muted:#936f64;--ia-line:#efdcd3;--ia-paper:#fffdfb;--ia-red:#ad1024;--ia-red-dark:#820817;--ia-green:#167447;--ia-gold:#a46705}.integrity-audit .ia-hero,.integrity-audit .ia-filter,.integrity-audit .ia-card{border:1px solid var(--ia-line);border-radius:18px;background:var(--ia-paper);box-shadow:0 10px 26px rgba(75,42,32,.06)}.integrity-audit .ia-hero{padding:1rem 1.1rem;background:linear-gradient(135deg,#fffdfa,#fff1eb)}.integrity-audit .ia-hero h4{color:var(--ia-ink)}.integrity-audit .ia-hero p,.integrity-audit .ia-muted{color:var(--ia-muted)}.integrity-audit .ia-filter{padding:.85rem .95rem}.integrity-audit .ia-filter-grid{display:grid;grid-template-columns:minmax(160px,220px) minmax(130px,180px) auto;gap:.65rem;align-items:end}.integrity-audit label{display:block;font-size:.7rem;font-weight:850;color:#76564d;margin-bottom:.25rem}.integrity-audit .ia-notice{border:1px solid #f0d9ad;background:#fff9ed;color:#80541f;border-radius:14px;padding:.72rem .85rem;font-size:.8rem}.integrity-audit .ia-summary{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.7rem}.integrity-audit .ia-kpi{padding:.8rem .9rem;border:1px solid var(--ia-line);border-radius:15px;background:#fff}.integrity-audit .ia-kpi span{display:block;font-size:.66rem;font-weight:850;letter-spacing:.05em;text-transform:uppercase;color:var(--ia-muted)}.integrity-audit .ia-kpi strong{display:block;margin-top:.2rem;font-size:1.2rem;color:var(--ia-ink)}.integrity-audit .ia-kpi.error strong{color:#b42318}.integrity-audit .ia-kpi.warning strong{color:var(--ia-gold)}.integrity-audit .ia-kpi.pass strong{color:var(--ia-green)}.integrity-audit .ia-card{overflow:hidden}.integrity-audit .ia-card-head{padding:.85rem 1rem;border-bottom:1px solid var(--ia-line)}.integrity-audit .ia-card-head h5{margin:0;color:var(--ia-ink);font-size:.98rem}.integrity-audit .ia-card-head p{margin:.2rem 0 0;color:var(--ia-muted);font-size:.76rem}.integrity-audit .ia-check{border:0;border-bottom:1px solid var(--ia-line);background:#fff}.integrity-audit .ia-check:last-child{border-bottom:0}.integrity-audit .ia-check summary{list-style:none;display:grid;grid-template-columns:auto minmax(0,1fr) auto auto;gap:.65rem;align-items:center;padding:.85rem 1rem;cursor:pointer}.integrity-audit .ia-check summary::-webkit-details-marker{display:none}.integrity-audit .ia-check summary:after{content:'+';font-weight:900;color:var(--ia-muted);font-size:1rem;order:5}.integrity-audit .ia-check[open] summary:after{content:'-';}.integrity-audit .ia-code{font-size:.62rem;font-weight:850;letter-spacing:.04em;color:var(--ia-muted);word-break:break-word}.integrity-audit .ia-title{font-weight:850;color:var(--ia-ink);font-size:.86rem}.integrity-audit .ia-count{font-size:.75rem;font-weight:850;color:var(--ia-ink);white-space:nowrap}.integrity-audit .ia-pill{border-radius:999px;padding:.22rem .52rem;font-size:.62rem;font-weight:900;white-space:nowrap}.integrity-audit .ia-pill.is-error{background:#fde9e8;color:#b42318}.integrity-audit .ia-pill.is-warning{background:#fff2d7;color:#9b5b00}.integrity-audit .ia-pill.is-pass{background:#e8f7ed;color:#147447}.integrity-audit .ia-check-body{padding:0 1rem 1rem;background:#fffaf7}.integrity-audit .ia-message{padding:.68rem .76rem;border-left:3px solid #d6b5aa;background:#fff;border-radius:0 10px 10px 0;color:#5e4036;font-size:.8rem;line-height:1.5}.integrity-audit .ia-next{margin-top:.7rem;display:flex;gap:.55rem;align-items:center;justify-content:space-between;flex-wrap:wrap;padding:.68rem .76rem;border:1px solid var(--ia-line);border-radius:12px;background:#fff}.integrity-audit .ia-next span{font-size:.77rem;color:var(--ia-ink)}.integrity-audit .ia-sample-wrap{overflow:auto;max-height:260px;margin-top:.75rem;border:1px solid var(--ia-line);border-radius:12px}.integrity-audit .ia-sample-table{margin:0;min-width:760px;border-collapse:separate;border-spacing:0}.integrity-audit .ia-sample-table th{position:sticky;top:0;z-index:2;background:linear-gradient(180deg,var(--ia-red),var(--ia-red-dark));color:#fff7f3;font-size:.68rem;letter-spacing:.04em;white-space:nowrap}.integrity-audit .ia-sample-table td{font-size:.76rem;color:var(--ia-ink);vertical-align:top;border-color:#f0dfd7;line-height:1.45}.integrity-audit .ia-sample-table tbody tr:nth-child(even) td{background:#fffaf7}.integrity-audit .ia-empty{padding:2rem 1rem;text-align:center;color:var(--ia-muted)}.integrity-audit .ia-error{border:1px solid #f2beb9;background:#fff1f0;color:#a5291e;border-radius:13px;padding:.8rem .9rem}.integrity-audit .ia-run-note{font-size:.74rem;color:var(--ia-muted)}@media(max-width:768px){.integrity-audit .ia-filter-grid,.integrity-audit .ia-summary{grid-template-columns:repeat(2,minmax(0,1fr))}.integrity-audit .ia-filter-grid .ia-button{grid-column:span 2}.integrity-audit .ia-check summary{grid-template-columns:auto minmax(0,1fr) auto}.integrity-audit .ia-code{grid-column:2}.integrity-audit .ia-check summary:after{display:none}}@media(max-width:460px){.integrity-audit .ia-summary{grid-template-columns:1fr}.integrity-audit .ia-filter-grid{grid-template-columns:1fr}.integrity-audit .ia-filter-grid .ia-button{grid-column:auto}}
</style>
<style>
.integrity-audit{--ia-blue:#2d6597}.integrity-audit .ia-summary{grid-template-columns:repeat(5,minmax(0,1fr))}.integrity-audit .ia-kpi.info strong{color:var(--ia-blue)}.integrity-audit .ia-pill.is-info{background:#e8f2fa;color:#285f90}@media(max-width:992px){.integrity-audit .ia-summary{grid-template-columns:repeat(3,minmax(0,1fr))}}@media(max-width:768px){.integrity-audit .ia-summary{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:460px){.integrity-audit .ia-summary{grid-template-columns:1fr}}
</style>

<div class="integrity-audit">
  <section class="ia-hero mb-3 d-flex justify-content-between align-items-start gap-3 flex-wrap">
    <div>
      <h4 class="mb-1"><i class="ri-shield-check-line page-title-icon"></i><?php echo html_escape((string)($page_title ?? 'Audit Integritas Stok')); ?></h4>
      <p class="mb-0">Pemeriksaan baca-saja untuk bulan yang dipilih. Audit memeriksa jejak adjustment, batch, transfer, POS, defisit, HPP, lot, dan periode. Halaman ini tidak memperbaiki atau mengubah data.</p>
    </div>
    <a class="btn btn-outline-danger btn-sm" href="<?php echo html_escape(site_url('inventory/stock/health') . '?' . http_build_query(['month' => (string)$month])); ?>">Buka Kesehatan Stok</a>
  </section>

  <form method="get" action="<?php echo html_escape($baseUrl); ?>" class="ia-filter mb-3">
    <input type="hidden" name="run" value="1">
    <div class="ia-filter-grid">
      <div><label for="integrityAuditMonth">Bulan yang diperiksa</label><input id="integrityAuditMonth" class="form-control" type="month" name="month" value="<?php echo html_escape($monthValue); ?>"></div>
      <div><label for="integrityAuditLimit">Contoh data per temuan</label><select id="integrityAuditLimit" class="form-select" name="limit"><?php foreach ([5, 10, 20, 50] as $option): ?><option value="<?php echo $option; ?>" <?php echo (int)$limit === $option ? 'selected' : ''; ?>><?php echo $option; ?> baris</option><?php endforeach; ?></select></div>
      <div class="ia-button"><button class="btn btn-danger w-100" type="submit"><i class="ri-search-eye-line"></i> Jalankan Audit</button></div>
    </div>
    <div class="ia-run-note mt-2">Audit hanya berjalan setelah tombol ditekan agar membuka halaman tetap ringan.</div>
  </form>

  <?php if (!$hasRun): ?>
    <div class="ia-notice">Pilih bulan lalu tekan <strong>Jalankan Audit</strong>. Bila muncul temuan, gunakan langkah aman pada setiap baris. Jangan langsung melakukan adjustment massal hanya untuk membuat audit terlihat bersih.</div>
  <?php elseif (!empty($audit_error)): ?>
    <div class="ia-error"><?php echo html_escape((string)$audit_error); ?></div>
  <?php else: ?>
    <section class="ia-summary mb-3">
      <div class="ia-kpi"><span>Total pemeriksaan</span><strong><?php echo number_format((int)($summary['check_count'] ?? 0), 0, ',', '.'); ?></strong></div>
      <div class="ia-kpi error"><span>Perlu perhatian</span><strong><?php echo number_format((int)($summary['error_count'] ?? 0), 0, ',', '.'); ?></strong></div>
      <div class="ia-kpi warning"><span>Perlu ditelusuri</span><strong><?php echo number_format((int)($summary['warning_count'] ?? 0), 0, ',', '.'); ?></strong></div>
      <div class="ia-kpi info"><span>Info terminal</span><strong><?php echo number_format((int)($summary['info_count'] ?? 0), 0, ',', '.'); ?></strong></div>
      <div class="ia-kpi"><span>Total catatan audit</span><strong><?php echo number_format((int)($summary['issue_count'] ?? 0), 0, ',', '.'); ?></strong></div>
    </section>

    <section class="ia-card">
      <div class="ia-card-head"><h5>Hasil audit <?php echo html_escape((string)($auditData['as_of_date'] ?? '')); ?></h5><p><?php echo html_escape((string)($auditData['message'] ?? 'Audit selesai.')); ?></p></div>
      <?php if (empty($checks)): ?>
        <div class="ia-empty">Audit tidak menghasilkan baris pemeriksaan.</div>
      <?php else: foreach ($checks as $check): ?>
        <?php
          $code = (string)($check['code'] ?? 'AUDIT');
          $status = strtoupper((string)($check['status'] ?? 'PASS'));
          $step = $nextStep($code);
          $samples = (array)($check['sample_rows'] ?? []);
          $statusLabel = $status === 'PASS'
              ? 'AMAN'
              : ($status === 'ERROR'
                  ? 'PERLU TINDAKAN'
                  : ($status === 'WARNING'
                      ? 'PERLU CEK'
                      : 'INFO TERMINAL'));
        ?>
        <details class="ia-check" <?php echo $status === 'ERROR' ? 'open' : ''; ?>>
          <summary>
            <span class="ia-pill <?php echo $statusClass($status); ?>"><?php echo html_escape($statusLabel); ?></span>
            <span><span class="ia-title d-block"><?php echo html_escape((string)($check['title'] ?? $code)); ?></span><span class="ia-code d-block"><?php echo html_escape($code); ?></span></span>
            <span class="ia-count"><?php echo number_format((int)($check['issue_count'] ?? 0), 0, ',', '.'); ?> temuan</span>
          </summary>
          <div class="ia-check-body">
            <div class="ia-message"><?php echo html_escape((string)($check['message'] ?? '')); ?></div>
            <?php if (in_array($status, ['ERROR', 'WARNING'], true)): ?>
              <div class="ia-next"><span><strong>Langkah aman:</strong> <?php echo html_escape((string)$step[0]); ?></span><?php if ($step[1] !== ''): ?><a class="btn btn-outline-danger btn-sm" href="<?php echo html_escape((string)$step[1]); ?>">Buka Halaman Terkait</a><?php endif; ?></div>
            <?php endif; ?>
            <?php if (!empty($samples)): ?>
              <div class="ia-sample-wrap"><table class="table table-sm ia-sample-table"><thead><tr><th style="width:70px">No.</th><th>Contoh data sumber</th></tr></thead><tbody><?php foreach ($samples as $index => $sample): ?><tr><td><?php echo (int)$index + 1; ?></td><td><?php echo html_escape($sampleText($sample)); ?></td></tr><?php endforeach; ?></tbody></table></div>
            <?php endif; ?>
          </div>
        </details>
      <?php endforeach; endif; ?>
    </section>
  <?php endif; ?>
</div>
