<?php
$baseUrl = site_url('finance/accounts');
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div>
    <h4 class="mb-1"><i class="ri ri-bank-line page-title-icon"></i><?php echo html_escape($title); ?></h4>
    <small class="text-muted">Daftar rekening perusahaan untuk channel pembayaran purchase.</small>
  </div>
  <div class="d-flex gap-2">
    <a href="<?php echo site_url('purchase/receipt'); ?>" class="btn btn-primary">Receipt Purchase</a>
    <a href="<?php echo site_url('purchase'); ?>" class="btn btn-outline-secondary">Purchase</a>
    <a href="<?php echo site_url('inventory/stock/warehouse'); ?>" class="btn btn-outline-secondary">Stok Gudang</a>
    <a href="<?php echo site_url('inventory/stock/division'); ?>" class="btn btn-outline-secondary">Stok Divisi</a>
  </div>
</div>

<div class="card mb-3">
  <div class="card-body py-3">
    <form method="get" action="<?php echo $baseUrl; ?>" class="row g-2 align-items-end">
      <div class="col-md-8">
        <label class="form-label mb-1">Cari Rekening</label>
        <input type="text" class="form-control" name="q" value="<?php echo html_escape((string)$q); ?>" placeholder="Kode / Nama / Bank / No Rekening">
      </div>
      <div class="col-md-2">
        <label class="form-label mb-1">Limit</label>
        <input type="number" min="1" max="300" class="form-control" name="limit" value="<?php echo (int)$limit; ?>">
      </div>
      <div class="col-md-2 d-flex gap-2">
        <button type="submit" class="btn btn-outline-primary w-100">Filter</button>
        <a href="<?php echo $baseUrl; ?>" class="btn btn-outline-danger w-100">Clear</a>
      </div>
    </form>
  </div>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table table-striped table-hover mb-0">
      <thead>
        <tr>
          <th>Kode</th>
          <th>Nama</th>
          <th>Tipe</th>
          <th>Bank</th>
          <th>No Rekening</th>
          <th class="text-end">Saldo Saat Ini</th>
          <th>Default</th>
          <th>Aktif</th>
          <th>Update</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($rows)): ?>
          <tr><td colspan="9" class="text-center text-muted py-4">Belum ada data rekening.</td></tr>
        <?php else: ?>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td><?php echo html_escape((string)$r['account_code']); ?></td>
              <td><?php echo html_escape((string)$r['account_name']); ?></td>
              <td><?php echo html_escape((string)$r['account_type']); ?></td>
              <td><?php echo html_escape((string)($r['bank_name'] ?? '-')); ?></td>
              <td><?php echo html_escape((string)($r['account_no'] ?? '-')); ?></td>
              <td class="text-end"><?php echo number_format((float)$r['current_balance'], 2, ',', '.'); ?></td>
              <td><?php echo ((int)$r['is_default'] === 1) ? 'Ya' : '-'; ?></td>
              <td><?php echo ((int)$r['is_active'] === 1) ? 'Aktif' : 'Nonaktif'; ?></td>
              <td><?php echo html_escape((string)($r['updated_at'] ?? '-')); ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
