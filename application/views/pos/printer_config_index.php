<?php
$eventLabels = (array)($event_type_labels ?? []);
$documentLabels = (array)($document_type_labels ?? []);
$attemptLabels = ['GENERATED' => 'Disiapkan', 'SENT' => 'Terkirim ke agent', 'FAILED' => 'Gagal', 'SKIPPED' => 'Dilewati'];
?>
<div class="container-xxl py-3 print-config-page">
  <div class="fin-page-header mb-3">
    <div>
      <h4 class="fin-page-title mb-1">Printer POS</h4>
      <p class="fin-page-subtitle mb-0">Atur dari atas ke bawah: koneksi fisik, tampilan umum, layout dokumen, lalu aturan cetak.</p>
    </div>
    <a class="btn btn-primary" href="<?= site_url('pos/printers/rules') ?>"><?php $this->load->view('pos/_icon', ['name' => 'eye', 'label' => '']); ?><span class="ms-1">Buka Aturan Cetak</span></a>
  </div>
  <?php $this->load->view('pos/_printer_config_common', ['printer_config_tab' => 'overview']); ?>

  <div class="print-config-note mb-3">
    <strong>Sumber cetak sekarang jelas.</strong> Koneksi menentukan perangkat yang dituju. Tampilan umum menyimpan nama outlet, logo, Wi-Fi, serta pesan footer. Layout menentukan data apa yang tampil. Aturan cetak menghubungkan event POS ke koneksi dan layout tersebut.
  </div>

  <div class="row g-3 mb-3">
    <div class="col-md-4"><div class="print-config-stat"><div class="label">Koneksi Printer</div><div class="value"><?= (int)($connection_total ?? 0) ?></div><a href="<?= site_url('pos/printers/connections') ?>" class="small">Atur koneksi</a></div></div>
    <div class="col-md-4"><div class="print-config-stat"><div class="label">Layout Dokumen</div><div class="value"><?= (int)($layout_total ?? 0) ?></div><a href="<?= site_url('pos/printers/layouts') ?>" class="small">Atur data tampil</a></div></div>
    <div class="col-md-4"><div class="print-config-stat"><div class="label">Aturan Aktif / Semua</div><div class="value"><?= (int)($route_active_total ?? 0) ?> / <?= (int)($route_total ?? 0) ?></div><a href="<?= site_url('pos/printers/rules') ?>" class="small">Cek alur cetak</a></div></div>
  </div>

  <div class="card print-config-card">
    <div class="card-body">
      <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div><div class="print-config-kicker">Jejak Cetak Terbaru</div><h5 class="mb-0 mt-1">Monitor pengiriman ke printer</h5></div>
        <a href="<?= site_url('pos/printers/monitor') ?>" class="btn btn-outline-primary btn-sm">Buka Monitor</a>
      </div>
      <div class="print-config-table-wrap" style="max-height:330px">
        <table class="table print-config-table"><thead><tr><th>Waktu</th><th>Event</th><th>Order</th><th>Aturan / Printer</th><th>Status</th></tr></thead>
          <tbody>
          <?php foreach ((array)($recent_attempts ?? []) as $row): ?>
            <tr><td><?= html_escape((string)($row['requested_at'] ?? '-')) ?></td><td><strong><?= html_escape((string)($eventLabels[$row['event_code'] ?? ''] ?? ($row['event_code'] ?? '-'))) ?></strong><div class="muted"><?= html_escape((string)($documentLabels[$row['document_type'] ?? ''] ?? ($row['document_type'] ?? ''))) ?></div></td><td><?= html_escape((string)($row['order_no'] ?? '-')) ?></td><td><strong><?= html_escape((string)($row['route_name'] ?? '-')) ?></strong><div class="muted"><?= html_escape((string)($row['connection_name'] ?? '-')) ?></div></td><td><span class="print-config-status <?= strtolower((string)($row['status'] ?? 'generated')) ?>"><?= html_escape((string)($attemptLabels[$row['status'] ?? ''] ?? ($row['status'] ?? 'GENERATED'))) ?></span></td></tr>
          <?php endforeach; ?>
          <?php if (empty($recent_attempts)): ?><tr><td colspan="5" class="print-config-empty">Belum ada pengiriman cetak dari aturan baru.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
