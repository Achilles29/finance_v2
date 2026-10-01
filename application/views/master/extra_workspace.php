<?php $extraTabActive = 'workspace'; ?>
<?php $this->load->view('master/_extra_tabs', compact('extraTabActive')); ?>

<div class="container-xxl py-3">
  <div class="fin-page-header mb-3">
    <div>
      <h4 class="fin-page-title mb-1">Workspace Extra Produk</h4>
      <p class="fin-page-subtitle mb-0">Alur extra kita jaga tetap satu pintu: definisikan extra, tentukan sumber stoknya, kelompokkan, hubungkan ke produk, lalu pakai di kasir tanpa aturan yang tercecer.</p>
    </div>
  </div>

  <div class="row g-3 mb-3">
    <div class="col-md-3">
      <div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="small text-muted">Master Extra</div><div class="fs-4 fw-bold"><?php echo (int)($summary['total_extra'] ?? 0); ?></div></div></div>
    </div>
    <div class="col-md-3">
      <div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="small text-muted">Group Extra</div><div class="fs-4 fw-bold"><?php echo (int)($summary['total_group'] ?? 0); ?></div></div></div>
    </div>
    <div class="col-md-3">
      <div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="small text-muted">Mapping Produk</div><div class="fs-4 fw-bold"><?php echo (int)($summary['total_mapping'] ?? 0); ?></div></div></div>
    </div>
    <div class="col-md-3">
      <div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="small text-muted">Siap Tampil di Kasir</div><div class="fs-4 fw-bold"><?php echo (int)($summary['total_cashier_ready'] ?? 0); ?></div></div></div>
    </div>
  </div>

  <div class="row g-3">
    <div class="col-lg-6">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body p-4">
          <h5 class="mb-2">Alur Konsep Extra</h5>
          <div class="text-muted small mb-3">Setiap langkah punya halaman sendiri, tapi tetap saling terhubung lewat tab di atas supaya alurnya tidak meloncat-loncat.</div>
          <ol class="mb-0 ps-3">
            <li class="mb-2"><strong>Master Extra</strong>: item actual seperti extra shot, less sugar, topping, atau add on berbayar, lengkap dengan sumber stoknya.</li>
            <li class="mb-2"><strong>Group Extra</strong>: kelompok pilihan seperti level gula, topping, atau addon wajib/opsional.</li>
            <li class="mb-2"><strong>Mapping Produk</strong>: produk mana saja yang boleh memakai group extra tertentu.</li>
            <li><strong>POS Kasir</strong>: kasir memilih extra dari cart line, lalu extra ikut ke pricing, stock commit, void/refund, dan audit transaksi.</li>
          </ol>
        </div>
      </div>
    </div>
    <div class="col-lg-6">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body p-4">
          <h5 class="mb-3">Area Kerja</h5>
          <div class="alert alert-warning border-0 rounded-4 mb-3" style="background:#fff6eb; color:#7b583d;">
            <strong>Catatan arah bisnis:</strong> extra sekarang bisa mengambil sumber dari <strong>bahan baku</strong>, <strong>component</strong>, atau <strong>produk lain</strong>. Untuk mode <strong>ganti bahan/resep</strong>, field penggantinya sudah kita siapkan di master agar mapping bisnisnya rapi dan tidak tercecer.
          </div>
          <div class="d-grid gap-2">
            <a href="<?php echo site_url('master/extra'); ?>" class="btn btn-outline-primary">Master Extra</a>
            <a href="<?php echo site_url('master/extra-group'); ?>" class="btn btn-outline-primary">Master Group Extra</a>
            <a href="<?php echo site_url('master/relation/extra-item-group'); ?>" class="btn btn-outline-primary">Hubungkan Extra ke Group</a>
            <a href="<?php echo site_url('master/relation/product-extra'); ?>" class="btn btn-outline-primary">Mapping Produk ke Group Extra</a>
            <a href="<?php echo site_url('master/relation/extra-group'); ?>" class="btn btn-outline-primary">Checklist Produk per Group Extra</a>
            <a href="<?php echo site_url('pos/cashier'); ?>" class="btn btn-primary">Coba di UI Kasir</a>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
