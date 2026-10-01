<?php
$detail = $detail ?? [];
$header = (array)($detail['header'] ?? []);
$lines = (array)($detail['lines'] ?? []);
$links = (array)($detail['links'] ?? []);
$canVerify = !empty($can_verify);
$canEdit = !empty($can_edit);
$canReject = !empty($can_reject);
$canVoid = !empty($can_void);
$isPurchaseScope = !empty($is_purchase_scope);

if (!function_exists('finance_dreq_detail_badge')) {
    function finance_dreq_detail_badge($status)
    {
        switch (strtoupper((string)$status)) {
            case 'SUBMITTED':
                return 'bg-warning text-dark';
            case 'VERIFIED':
                return 'bg-success';
            case 'REJECTED':
                return 'bg-danger';
            case 'VOID':
                return 'bg-secondary';
            default:
                return 'bg-light text-dark';
  }
}
if (!function_exists('finance_dreq_usage_label')) {
  function finance_dreq_usage_label($value)
  {
    return strtoupper(trim((string)$value)) === 'OPERASIONAL' ? 'Kebutuhan Operasional' : 'Persediaan Produksi';
  }
}
}

  if (!function_exists('finance_dreq_detail_location_label')) {
    function finance_dreq_detail_location_label($destinationType)
    {
      $destinationType = strtoupper(trim((string)$destinationType));
      if (strpos($destinationType, 'EVENT') !== false) {
        return 'Event';
      }
      if (in_array($destinationType, ['BAR', 'KITCHEN', 'OFFICE'], true)) {
        return 'Reguler';
      }
      return $destinationType !== '' ? $destinationType : '-';
    }
  }
?>
<?php $this->load->view('procurement/_stock_review_history', ['stock_review_history'=>$stock_review_history ?? []]); ?>

<style>
  .dreq-action-wrap {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
  }
  .dreq-action-btn {
    min-height: 34px;
    border-radius: 10px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    padding: 0 12px !important;
    font-weight: 600;
  }
  .dreq-action-btn.btn-outline-secondary { color: #6c757d; border-color: rgba(108,117,125,.55); }
  .dreq-action-btn.btn-outline-success { color: #198754; border-color: rgba(25,135,84,.55); }
  .dreq-action-btn.btn-outline-warning { color: #d39e00; border-color: rgba(211,158,0,.55); }
  .dreq-action-btn.btn-outline-danger { color: #dc3545; border-color: rgba(220,53,69,.55); }
</style>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <div>
    <h4 class="mb-0"><i class="ri-file-search-line page-title-icon me-1"></i><?php echo html_escape($title ?? 'Detail Pengajuan Divisi'); ?></h4>
    <small class="text-muted">
      <?php echo $isPurchaseScope
        ? 'Purchase meninjau detail pengajuan ini sebelum membentuk SR/PO final.'
        : 'Detail pengajuan untuk divisi Anda. Edit atau void masih bisa dilakukan sebelum diverifikasi purchase.'; ?>
    </small>
  </div>
  <div class="dreq-action-wrap">
    <?php $this->load->view('notifications/division_buttons', ['notification_request_id'=>(int)($header['id'] ?? 0), 'notification_status'=>$header['status'] ?? '']); ?>
    <a href="<?php echo site_url('procurement/division-po-sr'); ?>" class="btn btn-outline-secondary dreq-action-btn"><i class="ri ri-arrow-left-line"></i><span>Kembali</span></a>
    <?php if ($canEdit || $canVerify): ?>
      <a href="<?php echo site_url('procurement/division-po-sr/edit/' . (int)($header['id'] ?? 0)); ?>" class="btn <?php echo $canVerify ? 'btn-outline-success' : 'btn-outline-primary'; ?> dreq-action-btn">
        <i class="ri <?php echo $canVerify ? 'ri-check-line' : 'ri-edit-line'; ?>"></i>
        <span><?php echo $canVerify ? 'Verifikasi' : 'Edit'; ?></span>
      </a>
    <?php endif; ?>
  </div>
</div>

<?php $this->load->view('purchase/_po_sr_tabs', ['po_sr_active' => 'division-po-sr']); ?>
<?php if (!empty($notification_channels)): ?><script src="<?= base_url('assets/js/module-notifications.js') ?>?v=20260923pdf2" defer></script><?php endif; ?>

<?php if ($this->session->flashdata('success')): ?>
  <div class="alert alert-success"><?php echo html_escape((string)$this->session->flashdata('success')); ?></div>
<?php endif; ?>
<?php if ($this->session->flashdata('error')): ?>
  <div class="alert alert-danger"><?php echo html_escape((string)$this->session->flashdata('error')); ?></div>
<?php endif; ?>

<div class="row g-3 mb-3">
  <div class="col-lg-8">
    <div class="card h-100">
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-3">
            <div class="text-muted small">No Request</div>
            <div class="fw-semibold"><?php echo html_escape((string)($header['request_no'] ?? '-')); ?></div>
          </div>
          <div class="col-md-3">
            <div class="text-muted small">Tanggal</div>
            <div><?php echo html_escape((string)($header['request_date'] ?? '-')); ?></div>
          </div>
          <div class="col-md-3">
            <div class="text-muted small">Tgl Butuh</div>
            <div><?php echo html_escape((string)($header['needed_date'] ?? '-')); ?></div>
          </div>
          <div class="col-md-3">
            <div class="text-muted small">Status</div>
            <div><span class="badge <?php echo finance_dreq_detail_badge((string)($header['status'] ?? '')); ?>"><?php echo html_escape((string)($header['status'] ?? '-')); ?></span></div>
          </div>
          <div class="col-md-4">
            <div class="text-muted small">Divisi</div>
            <div><?php echo html_escape((string)($header['division_name'] ?? '-')); ?></div>
          </div>
          <div class="col-md-4">
            <div class="text-muted small">Lokasi Stok</div>
            <div><?php echo html_escape(finance_dreq_detail_location_label((string)($header['destination_type'] ?? ''))); ?></div>
          </div>
          <div class="col-md-4">
            <div class="text-muted small">Pengaju</div>
            <div><?php echo html_escape((string)($header['created_by_username'] ?? '-')); ?></div>
          </div>
          <div class="col-md-4">
            <div class="text-muted small">Update Terakhir</div>
            <div><?php echo html_escape((string)($header['updated_at'] ?? $header['created_at'] ?? '-')); ?></div>
          </div>
          <div class="col-12">
            <div class="text-muted small">Catatan</div>
            <div class="border rounded p-2 bg-light-subtle"><?php echo nl2br(html_escape((string)($header['notes'] ?? '-'))); ?></div>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card h-100">
      <div class="card-body">
        <h6 class="mb-3">Dokumen Hasil</h6>
        <?php if (empty($links)): ?>
          <div class="text-muted small">Belum ada dokumen hasil. Request masih menunggu proses berikutnya.</div>
        <?php else: ?>
          <?php foreach ($links as $link): ?>
            <div class="border rounded p-2 mb-2">
              <div class="fw-semibold"><?php echo html_escape((string)($link['doc_type'] ?? '-')); ?>: <?php echo html_escape((string)($link['doc_no'] ?? '-')); ?></div>
              <div class="small text-muted">Status: <?php echo html_escape((string)($link['doc_status'] ?? '-')); ?></div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>

        <?php if ($canReject || $canVoid): ?>
          <div class="border-top pt-3 mt-3">
            <h6 class="mb-2">Aksi</h6>
            <?php if ($canReject): ?>
              <form method="post" action="<?php echo site_url('procurement/division-po-sr/action/' . (int)($header['id'] ?? 0)); ?>" class="mb-2 dreq-confirm-form" data-confirm-message="Reject pengajuan ini?">
                <input type="hidden" name="action" value="REJECT">
                <button type="submit" class="btn btn-outline-danger w-100 dreq-action-btn"><i class="ri ri-close-line"></i><span>Reject</span></button>
              </form>
            <?php endif; ?>
            <?php if ($canVoid): ?>
              <form method="post" action="<?php echo site_url('procurement/division-po-sr/action/' . (int)($header['id'] ?? 0)); ?>" class="dreq-confirm-form" data-confirm-message="Void pengajuan ini?">
                <input type="hidden" name="action" value="VOID">
                <button type="submit" class="btn btn-outline-danger w-100 dreq-action-btn"><i class="ri ri-close-circle-line"></i><span>Void</span></button>
              </form>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-body border-bottom">
    <h6 class="mb-0">Line Pengajuan</h6>
  </div>
  <div class="table-responsive">
    <table class="table table-striped mb-0 dreq-detail-table">
      <thead><tr>
        <th>Barang / Jenis</th><th>Stok sekarang</th><th>Jumlah / Satuan</th>
        <th>Pemakaian / Vendor</th><th>Alokasi SR / PO</th><th>Status / Catatan</th><th>Aksi</th>
      </tr></thead>
      <tbody>
      <?php if (!$lines): ?><tr><td colspan="7" class="text-center py-4">Belum ada rincian.</td></tr><?php endif; ?>
      <?php foreach ($lines as $line):
        $lineStatus = $line['review_status'] ?? ($header['status']==='SUBMITTED'?'PENDING':$header['status']);
      ?>
        <tr>
          <td><strong><?= html_escape($line['profile_name'] ?? '-') ?></strong>
            <div class="small text-muted"><?= html_escape($line['profile_brand'] ?? '') ?> / <?= html_escape($line['line_kind'] ?? '') ?></div>
            <div class="small"><?= html_escape($line['profile_description'] ?? '') ?></div>
          </td>
          <td><?php $this->load->view('procurement/_current_stock', ['stock_line'=>$line]); ?></td>
          <td><strong><?= ui_num($line['qty_buy_requested']) ?> <?= html_escape($line['profile_buy_uom_code'] ?? '') ?></strong>
            <div class="small text-muted"><?= ui_num($line['qty_content_requested']) ?> <?= html_escape($line['profile_content_uom_code'] ?? '') ?></div>
          </td>
          <td><?= html_escape(finance_dreq_usage_label($line['usage_purpose'] ?? 'BAHAN_BAKU')) ?>
            <div class="small text-muted">Vendor: <?= html_escape($line['vendor_name'] ?? '-') ?></div>
            <div class="small">Rp <?= ui_num($line['estimated_unit_price'] ?? 0) ?> / <?= html_escape($line['profile_buy_uom_code'] ?? '') ?></div>
          </td>
          <td><?php if ($lineStatus==='REJECTED'): ?><span class="text-muted">Tidak diproses</span><?php else: ?>
            <div>SR: <?= ui_num($line['qty_content_to_sr']) ?> <?= html_escape($line['profile_content_uom_code'] ?? '') ?></div>
            <div>PO: <?= ui_num($line['qty_content_to_po']) ?> <?= html_escape($line['profile_content_uom_code'] ?? '') ?></div>
            <?php endif; ?>
          </td>
          <td><span class="badge <?= finance_dreq_detail_badge($lineStatus==='PENDING'?'SUBMITTED':$lineStatus) ?>"><?= html_escape(['PENDING'=>'Menunggu','VERIFIED'=>'Terverifikasi','REJECTED'=>'Ditolak','VOID'=>'Void'][$lineStatus] ?? $lineStatus) ?></span>
            <div class="small mt-1"><?= html_escape($line['review_notes'] ?? $line['notes'] ?? '') ?></div>
            <div class="small text-muted"><?= html_escape($line['reviewed_at'] ?? '') ?></div>
          </td>
          <td><?php if ($canVerify && in_array($lineStatus,['PENDING','REJECTED'],true)): ?>
            <a class="btn btn-sm btn-outline-success" href="<?= site_url('procurement/division-po-sr/edit/'.(int)$header['id']).'#dreq-line-'.(int)$line['id'] ?>">Tinjau</a>
            <?php else: ?><span class="text-muted">-</span><?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
(function () {
  'use strict';

  document.addEventListener('submit', function (event) {
    var form = event.target.closest('.dreq-confirm-form');
    if (!form || form.dataset.confirmed === '1') {
      return;
    }

    event.preventDefault();
    var message = form.getAttribute('data-confirm-message') || 'Lanjutkan aksi ini?';

    function doSubmit() {
      form.dataset.confirmed = '1';
      form.submit();
    }

    if (window.FinanceUI && typeof window.FinanceUI.confirm === 'function') {
      Promise.resolve(window.FinanceUI.confirm(message, {
        title: 'Konfirmasi',
        okText: 'Ya',
        cancelText: 'Batal'
      })).then(function (confirmed) {
        if (confirmed) {
          doSubmit();
        }
      });
      return;
    }

    if (window.FinanceUI && typeof window.FinanceUI.alert === 'function') {
      window.FinanceUI.alert('Modal konfirmasi tidak tersedia. Muat ulang halaman lalu coba lagi.', { title: 'UI Belum Siap' });
    }
  });
})();
</script>
