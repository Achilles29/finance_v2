<?php
$templateFilters = is_array($template_filters ?? null) ? $template_filters : [];
$profileFilters = is_array($profile_filters ?? null) ? $profile_filters : [];
$deviceFilters = is_array($device_filters ?? null) ? $device_filters : [];
$filterOptions = is_array($filter_options ?? null) ? $filter_options : [];
$templates = is_array($filterOptions['templates'] ?? null) ? $filterOptions['templates'] : [];
$outlets = is_array($filterOptions['outlets'] ?? null) ? $filterOptions['outlets'] : [];
$printers = is_array($filterOptions['printers'] ?? null) ? $filterOptions['printers'] : [];
?>

<style> 
  .pos-printer-grid { display:grid; gap:1rem; } 
  .pos-printer-card { border:0; border-radius:20px; box-shadow:0 12px 34px rgba(55, 38, 30, .08); background:linear-gradient(180deg,#fffdfb 0%,#fff 100%); } 
  .pos-printer-hero { display:grid; grid-template-columns:1.5fr 1fr; gap:1rem; margin-bottom:1rem; }
  .pos-printer-hero-card { border:1px solid rgba(188, 44, 69, .12); border-radius:22px; background:linear-gradient(135deg,#fff8f3 0%,#fff 60%,#fff7fa 100%); box-shadow:0 14px 36px rgba(71, 34, 34, .08); padding:1.2rem 1.3rem; }
  .pos-printer-mini-grid { display:grid; grid-template-columns:repeat(3, minmax(0,1fr)); gap:.75rem; }
  .pos-printer-mini-card { border-radius:16px; background:#fff; border:1px solid rgba(191, 163, 145, .22); padding:.85rem .95rem; }
  .pos-printer-mini-label { font-size:.72rem; letter-spacing:.04em; text-transform:uppercase; color:#9c7d70; font-weight:800; }
  .pos-printer-mini-value { font-size:1rem; font-weight:800; color:#3b2d31; margin-top:.2rem; }
  .pos-printer-mini-note { font-size:.78rem; color:#8a786c; margin-top:.15rem; }
  .pos-printer-guide-list { display:grid; gap:.65rem; margin:0; padding:0; list-style:none; }
  .pos-printer-guide-item { display:flex; gap:.7rem; align-items:flex-start; padding:.7rem .75rem; border-radius:16px; background:#fff; border:1px solid rgba(191, 163, 145, .2); }
  .pos-printer-guide-item i { width:1.9rem; height:1.9rem; display:inline-flex; align-items:center; justify-content:center; border-radius:999px; background:#fff2ef; color:#b4233c; flex:0 0 auto; }
  .pos-printer-toolbar { display:flex; flex-wrap:wrap; gap:.6rem; align-items:center; justify-content:space-between; margin-bottom:1rem; } 
  .pos-printer-toolbar .btn { white-space:nowrap; } 
  .pos-printer-kicker { display:inline-flex; align-items:center; gap:.45rem; font-size:.72rem; font-weight:800; letter-spacing:.06em; text-transform:uppercase; color:#8a786c; } 
  .pos-printer-kicker i { color:#b4233c; } 
  .pos-printer-empty { border:1px dashed rgba(179, 161, 147, .55); border-radius:16px; padding:1rem 1.1rem; color:#8b7c71; background:#fffaf6; } 
  .pos-printer-code { font-weight:800; color:#3b2d31; } 
  .pos-printer-inline-note { font-size:.78rem; color:#8a786c; } 
  .pos-printer-badge-soft { display:inline-flex; align-items:center; gap:.3rem; padding:.22rem .55rem; border-radius:999px; background:#f6eee8; color:#7f5d4f; font-size:.74rem; font-weight:700; } 
  .pos-printer-source-note { font-size:.76rem; color:#8a786c; line-height:1.45; }
  .pos-printer-modal-note { border:1px solid rgba(188,44,69,.12); border-radius:16px; background:#fff8f4; padding:.8rem .9rem; color:#7d5c53; }
  .pos-printer-section-stat { display:flex; gap:.55rem; flex-wrap:wrap; margin-top:.85rem; }
  .pos-printer-section-stat .badge { border-radius:999px; padding:.45rem .75rem; font-weight:700; }
  @media (max-width: 991.98px) { 
    .pos-printer-hero { grid-template-columns:1fr; }
    .pos-printer-mini-grid { grid-template-columns:1fr; }
    .pos-printer-toolbar { align-items:stretch; } 
    .pos-printer-toolbar > div:last-child { width:100%; display:grid; } 
  } 
</style> 

<div class="container-xxl py-3">
  <div class="fin-page-header"> 
    <div>
      <h4 class="fin-page-title mb-1">Printer POS</h4>
      <p class="fin-page-subtitle mb-0">Kelola template, pengaturan output, dan device printer desktop POS dalam satu workbench yang rapi dan cepat dibaca.</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
      <a href="<?php echo site_url('pos/printers/settings'); ?>" class="btn btn-outline-secondary"><i class="ri-settings-4-line me-1"></i>Pengaturan Umum</a>
      <a href="<?php echo site_url('pos/printers/guide'); ?>" class="btn btn-outline-primary"><i class="ri-book-open-line me-1"></i>Panduan Printer</a>
    </div>
  </div> 

  <?php $this->load->view('pos/_master_tabs', ['pos_master_tab_active' => 'printer']); ?> 

  <div class="pos-printer-hero">
    <div class="pos-printer-hero-card">
      <div class="pos-printer-kicker"><i class="ri-printer-cloud-line"></i> Workbench Printer</div>
      <h5 class="mt-2 mb-2">Satu tempat untuk template, output, dan device kasir</h5>
      <p class="text-muted mb-3">Tim operasional bisa cek alur cetak dari atas ke bawah: pilih template dokumen, atur output printer, lalu pastikan device bluetooth atau LAN aktif dan siap dipakai.</p>
      <div class="pos-printer-mini-grid">
        <div class="pos-printer-mini-card">
          <div class="pos-printer-mini-label">Template</div>
          <div class="pos-printer-mini-value">Receipt, KOT, Refund</div>
            <div class="pos-printer-mini-note">Sumber: <strong>pos_printer_template</strong> untuk bentuk dokumen.</div>
        </div>
        <div class="pos-printer-mini-card">
          <div class="pos-printer-mini-label">Output</div>
          <div class="pos-printer-mini-value">Paper, copy, footer</div>
            <div class="pos-printer-mini-note">Sumber: <strong>pos_printer_profile</strong> + <strong>pos_printer_content_setting</strong>.</div>
        </div>
        <div class="pos-printer-mini-card">
          <div class="pos-printer-mini-label">Device</div>
          <div class="pos-printer-mini-value">Bluetooth / LAN</div>
            <div class="pos-printer-mini-note">Sumber: <strong>pos_printer</strong> untuk koneksi, role, scope, host, dan port.</div>
        </div>
      </div>
    </div>
    <div class="pos-printer-hero-card">
      <div class="pos-printer-kicker"><i class="ri-information-line"></i> Checklist Operasional</div>
      <ul class="pos-printer-guide-list mt-3">
        <li class="pos-printer-guide-item">
          <i class="ri-file-list-3-line"></i>
          <div><strong>Template dulu</strong><div class="pos-printer-inline-note">Simpan payload dokumen yang ingin dicetak: receipt, KOT, refund, atau void.</div></div>
        </li>
        <li class="pos-printer-guide-item">
          <i class="ri-layout-4-line"></i>
          <div><strong>Atur output</strong><div class="pos-printer-inline-note">Set ukuran kertas, jumlah copy, footer, dan visibilitas harga per printer.</div></div>
        </li>
        <li class="pos-printer-guide-item">
          <i class="ri-bluetooth-line"></i>
          <div><strong>Pasangkan device</strong><div class="pos-printer-inline-note">Isi host agent, MAC, dan python port untuk bluetooth. LAN cukup IP dan port.</div></div>
        </li>
      </ul>
    </div>
  </div>

  <div class="pos-printer-grid"> 
    <div class="card pos-printer-card"> 
      <div class="card-body p-4"> 
        <div class="pos-printer-toolbar"> 
          <div>
            <div class="pos-printer-kicker"><i class="ri-file-list-3-line"></i> Template Printer</div>
            <h5 class="mb-1 mt-2">Template Dokumen</h5>
            <p class="mb-0 text-muted">Template payload JSON untuk receipt, KOT, refund, void, deposit receipt, dan dokumen cetak POS lainnya.</p>
          </div>
          <div> 
            <a href="<?php echo site_url('pos/printers/templates/create'); ?>" class="btn btn-primary btn-sm"><i class="ri-add-line me-1"></i>Tambah Template</a> 
          </div> 
        </div> 
        <div class="pos-printer-section-stat">
          <span class="badge text-bg-light border">Receipt & Payment</span>
          <span class="badge text-bg-light border">Kitchen Ticket</span>
          <span class="badge text-bg-light border">Refund / Void</span>
        </div>

        <form class="row g-2 mb-3"> 
          <div class="col-lg-6">
            <input id="template_q" class="form-control" placeholder="Cari kode / nama template / jenis dokumen">
          </div>
          <div class="col-lg-2">
            <select id="document_type" class="form-select">
              <option value="ALL">Semua Dokumen</option>
              <?php foreach (['RECEIPT','KITCHEN_TICKET','REFUND_SLIP','VOID_SLIP','DEPOSIT_RECEIPT'] as $doc): ?>
                <option value="<?php echo $doc; ?>"><?php echo $doc; ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-lg-2">
            <select id="template_limit" class="form-select">
              <option value="5">5</option>
              <option value="10" selected>10</option>
              <option value="20">20</option>
            </select>
          </div>
          <div class="col-lg-2 d-grid">
            <button type="button" id="btn-clear-template" class="btn btn-outline-danger">Clear</button>
          </div>
        </form>

        <div class="d-flex gap-2 flex-wrap mb-3">
          <button class="btn btn-sm btn-outline-primary template-status-tab" data-status="ACTIVE">Aktif</button>
          <button class="btn btn-sm btn-outline-primary template-status-tab" data-status="INACTIVE">Nonaktif</button>
          <button class="btn btn-sm btn-outline-primary template-status-tab" data-status="ALL">Semua</button>
        </div>

        <div class="table-responsive">
          <table class="table table-sm align-middle table-hover">
            <thead>
              <tr>
                <th>Kode</th>
                <th>Template</th>
                <th>Dokumen</th>
                <th class="text-center">Default</th>
                <th class="text-center">Status</th>
                <th class="text-center" style="width:220px;">Aksi</th>
              </tr>
            </thead>
            <tbody id="template-table-body"></tbody>
          </table>
        </div>
        <div id="template-empty-state" class="pos-printer-empty d-none">Belum ada template printer yang cocok dengan filter ini.</div>
        <div class="d-flex justify-content-between align-items-center mt-3">
          <small id="template-pagination-info" class="text-muted"></small>
          <div class="d-flex gap-1" id="template-pagination"></div>
        </div>
      </div>
    </div>

    <div class="row g-3">
      <div class="col-12 col-xl-6">
        <div class="card pos-printer-card h-100">
          <div class="card-body p-4">
            <div class="pos-printer-toolbar">
              <div>
                <div class="pos-printer-kicker"><i class="ri-layout-4-line"></i> Profile Printer</div>
            <h5 class="mb-1 mt-2">Pengaturan Output</h5>
            <p class="mb-0 text-muted">Atur ukuran kertas, copy, cut mode, logo, footer, dan visibilitas harga per printer.</p>
              </div>
              <div> 
                <button type="button" class="btn btn-warning btn-sm" id="btn-new-profile"><i class="ri-equalizer-line me-1"></i>Tambah Profile</button> 
              </div> 
            </div> 
            <div class="pos-printer-section-stat">
              <span class="badge text-bg-light border">Ukuran Kertas</span>
              <span class="badge text-bg-light border">Footer & Logo</span>
              <span class="badge text-bg-light border">Visibilitas Harga</span>
            </div>

            <form class="row g-2 mb-3"> 
              <div class="col-md-9">
                <input id="profile_q" class="form-control" placeholder="Cari nama printer / role / outlet">
              </div>
              <div class="col-md-3">
                <button type="button" id="btn-clear-profile" class="btn btn-outline-danger w-100">Clear</button>
              </div>
            </form>

            <div class="d-flex gap-2 flex-wrap mb-3">
              <button class="btn btn-sm btn-outline-primary profile-status-tab" data-status="ACTIVE">Aktif</button>
              <button class="btn btn-sm btn-outline-primary profile-status-tab" data-status="INACTIVE">Nonaktif</button>
              <button class="btn btn-sm btn-outline-primary profile-status-tab" data-status="ALL">Semua</button>
            </div>

            <div class="table-responsive">
              <table class="table table-sm align-middle table-hover">
                <thead>
                  <tr>
                    <th>Printer</th>
                    <th>Role / Scope</th>
                    <th class="text-center">Paper</th>
                    <th class="text-center">Copy</th>
                    <th class="text-center">Output</th>
                    <th class="text-center">Status</th>
                    <th class="text-center" style="width:220px;">Aksi</th>
                  </tr>
                </thead>
                <tbody id="profile-table-body"></tbody>
              </table>
            </div>
            <div id="profile-empty-state" class="pos-printer-empty d-none">Belum ada profile printer yang bisa ditampilkan.</div>
            <div class="d-flex justify-content-between align-items-center mt-3">
              <small id="profile-pagination-info" class="text-muted"></small>
              <div class="d-flex gap-1" id="profile-pagination"></div>
            </div>
          </div>
        </div>
      </div>

      <div class="col-12 col-xl-6">
        <div class="card pos-printer-card h-100">
          <div class="card-body p-4">
            <div class="pos-printer-toolbar">
              <div>
                <div class="pos-printer-kicker"><i class="ri-printer-line"></i> Device Desktop</div>
            <h5 class="mb-1 mt-2">Device Desktop</h5>
            <p class="mb-0 text-muted">Konfigurasi printer fisik desktop. Untuk printer bluetooth, gunakan <strong>LOCAL_AGENT</strong> dengan Python helper.</p>
              </div>
              <div class="d-flex gap-2 flex-wrap"> 
                <a href="<?php echo site_url('pos/printers/guide'); ?>" class="btn btn-outline-primary btn-sm">Panduan</a> 
                <button type="button" class="btn btn-success btn-sm" id="btn-new-device"><i class="ri-add-line me-1"></i>Tambah Device</button> 
              </div> 
            </div> 
            <div class="pos-printer-section-stat">
              <span class="badge text-bg-light border">Bluetooth + Python Agent</span>
              <span class="badge text-bg-light border">LAN Printer</span>
              <span class="badge text-bg-light border">Status Aktif</span>
            </div>

            <form class="row g-2 mb-3">
              <div class="col-md-5">
                <input id="device_q" class="form-control" placeholder="Cari printer / agent / MAC / IP">
              </div>
              <div class="col-md-4">
                <select id="device_outlet_id" class="form-select">
                  <option value="0">Semua Outlet</option>
                  <?php foreach ($outlets as $outlet): ?>
                    <option value="<?php echo (int)$outlet['id']; ?>"><?php echo html_escape((string)$outlet['outlet_name']); ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-3">
                <button type="button" id="btn-clear-device" class="btn btn-outline-danger w-100">Clear</button>
              </div>
            </form>

            <div class="d-flex gap-2 flex-wrap mb-3">
              <button class="btn btn-sm btn-outline-primary device-status-tab" data-status="ACTIVE">Aktif</button>
              <button class="btn btn-sm btn-outline-primary device-status-tab" data-status="INACTIVE">Nonaktif</button>
              <button class="btn btn-sm btn-outline-primary device-status-tab" data-status="ALL">Semua</button>
            </div>

            <div class="table-responsive">
              <table class="table table-sm align-middle table-hover">
                <thead>
                  <tr>
                    <th>Printer</th>
                    <th>Outlet</th>
                    <th>Role / Scope</th>
                    <th>Connection</th>
                    <th>Bluetooth Agent</th>
                    <th class="text-center">Status</th>
                    <th class="text-center" style="width:240px;">Aksi</th>
                  </tr>
                </thead>
                <tbody id="device-table-body"></tbody>
              </table>
            </div>
            <div id="device-empty-state" class="pos-printer-empty d-none">Belum ada device printer desktop yang sesuai filter.</div>
            <div class="d-flex justify-content-between align-items-center mt-3">
              <small id="device-pagination-info" class="text-muted"></small>
              <div class="d-flex gap-1" id="device-pagination"></div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="modal fade finance-ui-modal" id="printerTemplateModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title" id="printerTemplateModalLabel">Tambah Template Printer</h5>
          <div class="small text-muted">Simpan payload JSON template printer. Pastikan format JSON valid sebelum disimpan.</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form id="template-form" class="row g-3">
          <input type="hidden" name="id" value="">
          <div class="col-md-3">
            <label class="form-label mb-1 small text-muted">Kode</label>
            <input class="form-control" name="template_code" placeholder="Otomatis saat simpan">
          </div>
          <div class="col-md-5">
            <label class="form-label mb-1 small text-muted">Nama Template</label>
            <input class="form-control" name="template_name" required>
          </div>
          <div class="col-md-4">
            <label class="form-label mb-1 small text-muted">Jenis Dokumen</label>
            <select class="form-select" name="document_type" required>
              <?php foreach (['RECEIPT','KITCHEN_TICKET','REFUND_SLIP','VOID_SLIP','DEPOSIT_RECEIPT'] as $doc): ?>
                <option value="<?php echo $doc; ?>"><?php echo $doc; ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-12">
            <label class="form-label mb-1 small text-muted">Template Payload (JSON)</label>
            <textarea class="form-control font-monospace pos-printer-json" rows="12" name="template_payload" placeholder='{"header":{},"body":{},"footer":{}}'></textarea>
          </div>
          <div class="col-md-3">
            <label class="form-label mb-1 small text-muted">Default</label>
            <select class="form-select" name="is_default">
              <option value="0">Tidak</option>
              <option value="1">Ya</option>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label mb-1 small text-muted">Status</label>
            <select class="form-select" name="is_active">
              <option value="1">Aktif</option>
              <option value="0">Nonaktif</option>
            </select>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
        <button type="button" class="btn btn-primary" id="btn-save-template">Simpan Template</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade finance-ui-modal" id="printerProfileModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title" id="printerProfileModalLabel">Tambah Profile Printer</h5>
          <div class="small text-muted">Profile ini menyimpan pengaturan output printer yang dipakai runtime POS.</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form id="profile-form" class="row g-3">
          <input type="hidden" name="id" value="">
          <div class="col-md-6">
            <label class="form-label mb-1 small text-muted">Printer</label>
            <select class="form-select" name="printer_id" required>
              <option value="">Pilih Printer</option>
              <?php foreach ($printers as $printer): ?>
                <option value="<?php echo (int)$printer['id']; ?>"><?php echo html_escape((string)$printer['printer_name']); ?> | <?php echo html_escape((string)($printer['printer_role'] ?? 'CUSTOM')); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label mb-1 small text-muted">Template Runtime</label>
            <select class="form-select" name="template_id">
              <option value="">Default sesuai dokumen</option>
              <?php foreach ($templates as $template): ?>
                <option value="<?php echo (int)$template['id']; ?>"><?php echo html_escape((string)$template['template_name']); ?> | <?php echo html_escape((string)($template['document_type'] ?? 'OTHER')); ?></option>
              <?php endforeach; ?>
            </select>
            <div class="pos-printer-source-note mt-1">Kalau dipilih, service runtime akan memakai template ini dulu. Kalau kosong, fallback ke template default sesuai dokumen cetak.</div>
          </div>
          <div class="col-md-3">
            <label class="form-label mb-1 small text-muted">Paper (mm)</label>
            <select class="form-select" name="paper_width_mm">
              <option value="80">80</option>
              <option value="58">58</option>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label mb-1 small text-muted">Copy</label>
            <input type="number" class="form-control" name="copy_count" min="1" max="10" value="1">
          </div>
          <div class="col-md-4">
            <label class="form-label mb-1 small text-muted">Cut Mode</label>
            <select class="form-select" name="cut_mode">
              <option value="PARTIAL">PARTIAL</option>
              <option value="FULL">FULL</option>
              <option value="NONE">NONE</option>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label mb-1 small text-muted">Show Logo</label>
            <select class="form-select" name="show_logo"><option value="1">Ya</option><option value="0">Tidak</option></select>
          </div>
          <div class="col-md-4">
            <label class="form-label mb-1 small text-muted">Show Price</label>
            <select class="form-select" name="show_price"><option value="1">Ya</option><option value="0">Tidak</option></select>
          </div>
          <div class="col-md-4">
            <label class="form-label mb-1 small text-muted">Show Footer</label>
            <select class="form-select" name="show_footer"><option value="1">Ya</option><option value="0">Tidak</option></select>
          </div>
          <div class="col-md-4">
            <label class="form-label mb-1 small text-muted">Open Drawer</label>
            <select class="form-select" name="open_drawer"><option value="0">Tidak</option><option value="1">Ya</option></select>
          </div>
          <div class="col-md-4">
            <label class="form-label mb-1 small text-muted">Status</label>
            <select class="form-select" name="is_active"><option value="1">Aktif</option><option value="0">Nonaktif</option></select>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
        <button type="button" class="btn btn-primary" id="btn-save-profile">Simpan Profile</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade finance-ui-modal" id="printerDeviceModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title" id="printerDeviceModalLabel">Tambah Device Printer</h5>
          <div class="small text-muted">Data device fisik printer desktop. Gunakan <strong>LOCAL_AGENT</strong> untuk printer bluetooth yang dicetak lewat Python helper.</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form id="device-form" class="row g-3">
          <input type="hidden" name="id" value="">
          <div class="col-md-3">
            <label class="form-label mb-1 small text-muted">Kode</label>
            <input class="form-control" name="device_code" placeholder="Otomatis saat simpan">
          </div>
          <div class="col-md-5">
            <label class="form-label mb-1 small text-muted">Nama Device</label>
            <input class="form-control" name="device_name" required>
          </div>
          <div class="col-md-4">
            <label class="form-label mb-1 small text-muted">Outlet</label>
            <select class="form-select" name="outlet_id">
              <option value="">Semua / Global</option>
              <?php foreach ($outlets as $outlet): ?>
                <option value="<?php echo (int)$outlet['id']; ?>"><?php echo html_escape((string)$outlet['outlet_name']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label mb-1 small text-muted">Printer Role</label>
            <select class="form-select" name="printer_role">
              <option value="KASIR">KASIR</option>
              <option value="BAR">BAR</option>
              <option value="KITCHEN">KITCHEN</option>
              <option value="CHECKER">CHECKER</option>
              <option value="CUSTOM">CUSTOM</option>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label mb-1 small text-muted">Print Scope</label>
            <select class="form-select" name="print_scope">
              <option value="DIVISION">DIVISION</option>
              <option value="ALL">ALL</option>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label mb-1 small text-muted">Connection</label>
            <select class="form-select" name="connection_type">
              <option value="LOCAL_AGENT">LOCAL_AGENT</option>
              <option value="USB">USB</option>
              <option value="LAN">LAN</option>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label mb-1 small text-muted">Agent OS</label>
            <select class="form-select" name="agent_os">
              <option value="WINDOWS">WINDOWS</option>
              <option value="UBUNTU">UBUNTU</option>
              <option value="OTHER">OTHER</option>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label mb-1 small text-muted">Agent Host</label>
            <input class="form-control" name="agent_host" placeholder="POS-PRINTER-AGENT-01">
          </div>
          <div class="col-md-4">
            <label class="form-label mb-1 small text-muted">Nama Device OS</label>
            <input class="form-control" name="system_device_name" placeholder="EPSON-TM-M30">
          </div>
          <div class="col-md-4">
            <label class="form-label mb-1 small text-muted">MAC Address</label>
            <input class="form-control font-monospace" name="mac_address" placeholder="86677A7B9914">
          </div>
          <div class="col-md-4">
            <label class="form-label mb-1 small text-muted">IP Address</label>
            <input class="form-control" name="ip_address" placeholder="192.168.1.25">
          </div>
          <div class="col-md-2">
            <label class="form-label mb-1 small text-muted">Port</label>
            <input type="number" class="form-control" name="port" min="0" max="65535">
          </div>
          <div class="col-md-2">
            <label class="form-label mb-1 small text-muted">Python Port</label>
            <input type="number" class="form-control" name="python_port" min="1" max="65535" placeholder="3000">
          </div>
          <div class="col-md-4">
            <div class="pos-printer-modal-note h-100">
              <div class="fw-semibold mb-1">Output tidak diatur di form device</div>
              <div class="small">Ukuran kertas, jumlah copy, template runtime, footer, dan visibilitas harga dibaca dari pengaturan output printer. Form device ini hanya untuk koneksi fisik, role, scope, host agent, MAC, IP, dan port.</div>
            </div>
          </div>
          <div class="col-md-2">
            <label class="form-label mb-1 small text-muted">Status</label>
            <select class="form-select" name="is_active"><option value="1">Aktif</option><option value="0">Nonaktif</option></select>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
        <button type="button" class="btn btn-primary" id="btn-save-device">Simpan Device</button>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const initialTemplateFilters = <?php echo json_encode($templateFilters, JSON_INVALID_UTF8_SUBSTITUTE); ?>;
  const initialProfileFilters = <?php echo json_encode($profileFilters, JSON_INVALID_UTF8_SUBSTITUTE); ?>;
  const initialDeviceFilters = <?php echo json_encode($deviceFilters, JSON_INVALID_UTF8_SUBSTITUTE); ?>;

  const templateState = { q: initialTemplateFilters.q || '', status: initialTemplateFilters.status || 'ACTIVE', document_type: initialTemplateFilters.document_type || 'ALL', page: parseInt(initialTemplateFilters.page || 1, 10), limit: parseInt(initialTemplateFilters.limit || 10, 10) || 10 };
  const profileState = { q: initialProfileFilters.q || '', status: initialProfileFilters.status || 'ACTIVE', page: parseInt(initialProfileFilters.page || 1, 10), limit: parseInt(initialProfileFilters.limit || 10, 10) || 10 };
  const deviceState = { q: initialDeviceFilters.q || '', status: initialDeviceFilters.status || 'ACTIVE', outlet_id: parseInt(initialDeviceFilters.outlet_id || 0, 10) || 0, page: parseInt(initialDeviceFilters.page || 1, 10), limit: parseInt(initialDeviceFilters.limit || 10, 10) || 10 };

  const templateModal = new bootstrap.Modal(document.getElementById('printerTemplateModal'));
  const profileModal = new bootstrap.Modal(document.getElementById('printerProfileModal'));
  const deviceModal = new bootstrap.Modal(document.getElementById('printerDeviceModal'));

  const templateForm = document.getElementById('template-form');
  const profileForm = document.getElementById('profile-form');
  const deviceForm = document.getElementById('device-form');

  function escapeHtml(v) { return String(v ?? '').replace(/[&<>\"']/g, (m) => ({'&':'&amp;','<':'&lt;','>':'&gt;','\"':'&quot;',"'":'&#039;'}[m])); }
  function yesNoBadge(flag, textYes='Ya', textNo='Tidak') { return Number(flag || 0) === 1 ? `<span class="badge bg-success-subtle text-success-emphasis">${textYes}</span>` : `<span class="badge bg-secondary-subtle text-secondary-emphasis">${textNo}</span>`; }
  function statusBadge(flag) { return Number(flag || 0) === 1 ? '<span class="badge bg-success-subtle text-success-emphasis">Aktif</span>' : '<span class="badge bg-danger-subtle text-danger-emphasis">Nonaktif</span>'; }
  function rowPayload(row) { return encodeURIComponent(JSON.stringify(row || {})); }
  function parseRow(payload) { return JSON.parse(decodeURIComponent(payload)); }

  async function getJson(url) { 
    const r = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } }); 
    const t = await r.text(); 
    let j = null; 
    try { j = JSON.parse(t); } catch (e) { 
      const snippet = String(t || '').replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 220);
      throw new Error(snippet ? `Response backend tidak valid: ${snippet}` : 'Response backend tidak valid dan bukan JSON.'); 
    } 
    if (!r.ok || !j.ok) throw new Error(j.message || 'Gagal memuat data'); 
    return j; 
  } 
 
  async function postJson(url, payload) { 
    const r = await fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body: JSON.stringify(payload) }); 
    const t = await r.text(); 
    let j = null; 
    try { j = JSON.parse(t); } catch (e) { 
      const snippet = String(t || '').replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 220);
      throw new Error(snippet ? `Response save backend tidak valid: ${snippet}` : 'Response save backend tidak valid dan bukan JSON.'); 
    } 
    if (!r.ok || !j.ok) throw new Error(j.message || 'Gagal menyimpan data'); 
    return j; 
  } 

  function pager(meta, stateRef, targetId, infoId, noun, loadFn) {
    const total = Number(meta.total || 0);
    const page = Number(meta.page || 1);
    const totalPages = Number(meta.total_pages || 1);
    const limit = Number(meta.limit || stateRef.limit || 10);
    const start = total === 0 ? 0 : ((page - 1) * limit) + 1;
    const end = Math.min(total, page * limit);
    document.getElementById(infoId).textContent = total ? `Menampilkan ${start}-${end} dari ${total} ${noun}` : `Belum ada ${noun}`;
    document.getElementById(targetId).innerHTML = Array.from({ length: totalPages }, (_, idx) => {
      const p = idx + 1;
      return `<button type="button" class="btn btn-sm ${p === page ? 'btn-dark' : 'btn-outline-secondary'}" data-page="${p}">${p}</button>`;
    }).join('');
    document.querySelectorAll(`#${targetId} button`).forEach((btn) => btn.addEventListener('click', () => { stateRef.page = Number(btn.dataset.page || 1); loadFn(); }));
  }

  function syncTemplateControls() {
    document.getElementById('template_q').value = templateState.q;
    document.getElementById('document_type').value = templateState.document_type;
    document.getElementById('template_limit').value = String(templateState.limit);
    document.querySelectorAll('.template-status-tab').forEach((btn) => btn.classList.toggle('active', btn.dataset.status === templateState.status));
  }
  function syncProfileControls() {
    document.getElementById('profile_q').value = profileState.q;
    document.querySelectorAll('.profile-status-tab').forEach((btn) => btn.classList.toggle('active', btn.dataset.status === profileState.status));
  }
  function syncDeviceControls() {
    document.getElementById('device_q').value = deviceState.q;
    document.getElementById('device_outlet_id').value = String(deviceState.outlet_id || 0);
    document.querySelectorAll('.device-status-tab').forEach((btn) => btn.classList.toggle('active', btn.dataset.status === deviceState.status));
  }

  function templateQs() {
    const p = new URLSearchParams();
    p.set('template_q', templateState.q); p.set('template_status', templateState.status); p.set('document_type', templateState.document_type); p.set('template_page', templateState.page); p.set('template_limit', templateState.limit);
    return p.toString();
  }
  function profileQs() {
    const p = new URLSearchParams();
    p.set('profile_q', profileState.q); p.set('profile_status', profileState.status); p.set('profile_page', profileState.page); p.set('profile_limit', profileState.limit);
    return p.toString();
  }
  function deviceQs() {
    const p = new URLSearchParams();
    p.set('device_q', deviceState.q); p.set('device_status', deviceState.status); p.set('device_outlet_id', deviceState.outlet_id); p.set('device_page', deviceState.page); p.set('device_limit', deviceState.limit);
    return p.toString();
  }

  function openTemplate(row = null) {
    templateForm.reset();
    templateForm.elements.id.value = row?.id || '';
    templateForm.elements.template_code.value = row?.template_code || '';
    templateForm.elements.template_name.value = row?.template_name || '';
    templateForm.elements.document_type.value = row?.document_type || 'RECEIPT';
    templateForm.elements.template_payload.value = row?.template_payload || '{}';
    templateForm.elements.is_default.value = Number(row?.is_default || 0);
    templateForm.elements.is_active.value = Number(row?.is_active ?? 1);
    document.getElementById('printerTemplateModalLabel').textContent = row ? `Edit Template: ${row.template_name}` : 'Tambah Template Printer';
    templateModal.show();
  }

  function openProfile(row = null) {
    profileForm.reset();
    profileForm.elements.id.value = row?.id || '';
    profileForm.elements.printer_id.value = row?.id || row?.printer_id || '';
    profileForm.elements.template_id.value = row?.template_id || '';
    profileForm.elements.paper_width_mm.value = String(row?.paper_width_mm || 80);
    profileForm.elements.copy_count.value = row?.copy_count || 1;
    profileForm.elements.cut_mode.value = row?.cut_mode || 'PARTIAL';
    profileForm.elements.open_drawer.value = Number(row?.open_drawer || 0);
    profileForm.elements.show_logo.value = Number(row?.show_logo ?? 1);
    profileForm.elements.show_price.value = (String(row?.price_visibility || 'always').toLowerCase() === 'never') ? 0 : 1;
    profileForm.elements.show_footer.value = Number(row?.show_footer ?? 1);
    profileForm.elements.is_active.value = Number(row?.is_active ?? 1);
    document.getElementById('printerProfileModalLabel').textContent = row ? `Edit Profile: ${row.profile_name}` : 'Tambah Profile Printer';
    profileModal.show();
  }

  function openDevice(row = null) {
    deviceForm.reset();
    deviceForm.elements.id.value = row?.id || '';
    deviceForm.elements.device_code.value = row?.device_code || '';
    deviceForm.elements.device_name.value = row?.device_name || '';
    deviceForm.elements.outlet_id.value = row?.outlet_id || '';
    deviceForm.elements.printer_role.value = row?.printer_role || 'CUSTOM';
    deviceForm.elements.print_scope.value = row?.print_scope || 'DIVISION';
    deviceForm.elements.connection_type.value = row?.connection_type || 'USB';
    deviceForm.elements.agent_os.value = row?.agent_os || 'WINDOWS';
    deviceForm.elements.agent_host.value = row?.agent_host || '';
    deviceForm.elements.system_device_name.value = row?.system_device_name || '';
    deviceForm.elements.mac_address.value = row?.mac_address || '';
    deviceForm.elements.ip_address.value = row?.ip_address || '';
    deviceForm.elements.port.value = row?.port || '';
    deviceForm.elements.python_port.value = row?.python_port || '';
    deviceForm.elements.is_active.value = Number(row?.is_active ?? 1);
    document.getElementById('printerDeviceModalLabel').textContent = row ? `Edit Device: ${row.device_name}` : 'Tambah Device Printer';
    deviceModal.show();
  }

  async function loadTemplates() {
    syncTemplateControls();
    const json = await getJson('<?php echo site_url('pos/printers/templates/data'); ?>?' + templateQs());
    const rows = json.rows || [];
    const body = document.getElementById('template-table-body');
    const empty = document.getElementById('template-empty-state');
    if (!rows.length) {
      body.innerHTML = ''; empty.classList.remove('d-none');
    } else {
      empty.classList.add('d-none');
      body.innerHTML = rows.map((r) => `
        <tr>
          <td><div class="pos-printer-code">${escapeHtml(r.template_code || '-')}</div></td>
          <td><div class="fw-semibold">${escapeHtml(r.template_name || '-')}</div><div class="pos-printer-inline-note">JSON template printer</div></td>
          <td><span class="pos-printer-badge-soft">${escapeHtml(r.document_type || 'OTHER')}</span></td>
          <td class="text-center">${yesNoBadge(r.is_default)}</td>
          <td class="text-center">${statusBadge(r.is_active)}</td>
          <td class="text-center"><div class="d-inline-flex gap-1 flex-wrap justify-content-center"><a class="btn btn-sm btn-outline-primary" href="<?php echo site_url('pos/printers/templates/edit'); ?>/${Number(r.id||0)}">Edit Live</a><a class="btn btn-sm btn-outline-secondary" href="<?php echo site_url('pos/printers/templates/preview'); ?>/${Number(r.id||0)}">Preview</a><button type="button" class="btn btn-sm ${Number(r.is_active||0)===1?'btn-outline-danger':'btn-outline-success'} btn-template-toggle" data-id="${Number(r.id||0)}">${Number(r.is_active||0)===1?'Nonaktifkan':'Aktifkan'}</button></div></td>
        </tr>`).join('');
    }
    pager(json.meta || {}, templateState, 'template-pagination', 'template-pagination-info', 'template', loadTemplates);
    body.querySelectorAll('.btn-template-toggle').forEach((btn) => btn.addEventListener('click', async () => { await postJson('<?php echo site_url('pos/printers/templates/toggle'); ?>/' + btn.dataset.id, {}); await loadTemplates(); }));
  }

  async function loadProfiles() {
    syncProfileControls();
    const json = await getJson('<?php echo site_url('pos/printers/profiles/data'); ?>?' + profileQs());
    const rows = json.rows || [];
    const body = document.getElementById('profile-table-body');
    const empty = document.getElementById('profile-empty-state');
    if (!rows.length) {
      body.innerHTML = ''; empty.classList.remove('d-none');
    } else {
      empty.classList.add('d-none');
      body.innerHTML = rows.map((r) => `
        <tr>
          <td><div class="fw-semibold">${escapeHtml(r.profile_name || '-')}</div><div class="pos-printer-inline-note">${escapeHtml(r.profile_code || '-')} | ${escapeHtml(r.outlet_name || 'Global')}</div></td>
          <td><div>${escapeHtml(r.printer_role || 'CUSTOM')}</div><div class="pos-printer-inline-note">${escapeHtml(r.print_scope || 'DIVISION')}</div></td>
          <td class="text-center">${escapeHtml(r.paper_width_mm || 80)}mm</td>
          <td class="text-center">${escapeHtml(r.copy_count || 1)}x</td>
          <td class="text-center"><div class="pos-printer-inline-note">Template ${escapeHtml(r.template_name || 'Default dokumen')}</div><div class="pos-printer-inline-note">Logo ${Number(r.show_logo||0)?'On':'Off'} | Footer ${Number(r.show_footer||0)?'On':'Off'} | Harga ${String(r.price_visibility || 'always').toLowerCase()==='never'?'Hide':'Show'}</div></td>
          <td class="text-center">${statusBadge(r.is_active)}</td>
          <td class="text-center"><div class="d-inline-flex gap-1"><button type="button" class="btn btn-sm btn-outline-primary btn-profile-edit" data-row="${rowPayload(r)}">Edit</button><button type="button" class="btn btn-sm ${Number(r.is_active||0)===1?'btn-outline-danger':'btn-outline-success'} btn-profile-toggle" data-id="${Number(r.id||0)}">${Number(r.is_active||0)===1?'Nonaktifkan':'Aktifkan'}</button></div></td>
        </tr>`).join('');
    }
    pager(json.meta || {}, profileState, 'profile-pagination', 'profile-pagination-info', 'profile', loadProfiles);
    body.querySelectorAll('.btn-profile-edit').forEach((btn) => btn.addEventListener('click', () => openProfile(parseRow(btn.dataset.row))));
    body.querySelectorAll('.btn-profile-toggle').forEach((btn) => btn.addEventListener('click', async () => { await postJson('<?php echo site_url('pos/printers/profiles/toggle'); ?>/' + btn.dataset.id, {}); await loadProfiles(); }));
  }

  async function loadDevices() {
    syncDeviceControls();
    const json = await getJson('<?php echo site_url('pos/printers/devices/data'); ?>?' + deviceQs());
    const rows = json.rows || [];
    const body = document.getElementById('device-table-body');
    const empty = document.getElementById('device-empty-state');
    if (!rows.length) {
      body.innerHTML = ''; empty.classList.remove('d-none');
    } else {
      empty.classList.add('d-none');
      body.innerHTML = rows.map((r) => `
          <tr>
            <td><div class="fw-semibold">${escapeHtml(r.device_name || '-')}</div><div class="pos-printer-inline-note">${escapeHtml(r.device_code || '-')}</div></td>
            <td>${escapeHtml(r.outlet_name || 'Global')}</td>
            <td><div>${escapeHtml(r.printer_role || 'CUSTOM')}</div><div class="pos-printer-inline-note">${escapeHtml(r.print_scope || 'DIVISION')}</div></td>
            <td><div class="pos-printer-badge-soft">${escapeHtml(r.connection_type || 'USB')}</div><div class="pos-printer-inline-note mt-1">${escapeHtml(r.system_device_name || r.ip_address || '-')}</div><div class="pos-printer-inline-note">Output ${escapeHtml(r.paper_width_mm || 80)}mm | ${escapeHtml(r.copies || 1)}x | ${escapeHtml(r.template_name || 'Default dokumen')}</div></td>
            <td><div class="fw-semibold">${escapeHtml(r.agent_host || '-')}</div><div class="pos-printer-inline-note">${escapeHtml(r.mac_address || '-')} ${r.python_port ? '| Port ' + escapeHtml(r.python_port) : ''}</div></td>
            <td class="text-center">${statusBadge(r.is_active)}</td>
            <td class="text-center"><div class="d-inline-flex gap-1 flex-wrap justify-content-center"><a class="btn btn-sm btn-outline-secondary" href="<?php echo site_url('pos/printers/preview'); ?>/${Number(r.id||0)}">Preview</a><button type="button" class="btn btn-sm btn-outline-success btn-device-test" data-id="${Number(r.id||0)}">Test</button><button type="button" class="btn btn-sm btn-outline-primary btn-device-edit" data-row="${rowPayload(r)}">Edit</button><button type="button" class="btn btn-sm ${Number(r.is_active||0)===1?'btn-outline-danger':'btn-outline-success'} btn-device-toggle" data-id="${Number(r.id||0)}">${Number(r.is_active||0)===1?'Nonaktifkan':'Aktifkan'}</button></div></td>
          </tr>`).join('');
      }
      pager(json.meta || {}, deviceState, 'device-pagination', 'device-pagination-info', 'device', loadDevices);
      body.querySelectorAll('.btn-device-edit').forEach((btn) => btn.addEventListener('click', () => openDevice(parseRow(btn.dataset.row))));
      body.querySelectorAll('.btn-device-test').forEach((btn) => btn.addEventListener('click', async () => {
        const id = Number(btn.dataset.id || 0);
        if (!id) return;
        const originalText = btn.textContent;
        btn.disabled = true;
        btn.textContent = 'Testing...';
        try {
          const json = await getJson('<?php echo site_url('pos/printers/test'); ?>/' + id);
          const payload = json.print_payload || {};
          const printer = json.printer || {};
          const pythonPort = Number(printer.python_port || 0);
          if (!pythonPort) {
            throw new Error('Python port printer belum diisi. Lengkapi device printer dulu sebelum test print.');
          }
          const res = await fetch('http://127.0.0.1:' + pythonPort + '/cetak', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
              text: String(payload.text || ''),
              printer_code: String(printer.device_code || ''),
              printer_name: String(printer.device_name || ''),
              paper_width_mm: Number(payload.paper_width_mm || 80),
              chars_per_line: Number(payload.chars_per_line || 48)
            })
          });
          if (!res.ok) {
            throw new Error('HTTP ' + res.status);
          }
          try {
            await res.json();
          } catch (e) {}
          alert('Test print berhasil dikirim ke printer lokal.');
        } catch (e) {
          alert(e && e.message ? e.message : 'Gagal test print printer lokal.');
        } finally {
          btn.disabled = false;
          btn.textContent = originalText;
        }
      }));
      body.querySelectorAll('.btn-device-toggle').forEach((btn) => btn.addEventListener('click', async () => {
        await postJson('<?php echo site_url('pos/printers/devices/toggle'); ?>/' + btn.dataset.id, {});
        await loadDevices();
        await loadProfiles();
      }));
  }

  document.getElementById('btn-new-profile').addEventListener('click', () => openProfile());
  document.getElementById('btn-new-device').addEventListener('click', () => openDevice());

  document.querySelectorAll('.template-status-tab').forEach((btn) => btn.addEventListener('click', () => { templateState.status = btn.dataset.status; templateState.page = 1; loadTemplates(); }));
  document.querySelectorAll('.profile-status-tab').forEach((btn) => btn.addEventListener('click', () => { profileState.status = btn.dataset.status; profileState.page = 1; loadProfiles(); }));
  document.querySelectorAll('.device-status-tab').forEach((btn) => btn.addEventListener('click', () => { deviceState.status = btn.dataset.status; deviceState.page = 1; loadDevices(); }));

  document.getElementById('template_q').addEventListener('input', (e) => { templateState.q = e.target.value; templateState.page = 1; loadTemplates(); });
  document.getElementById('document_type').addEventListener('change', (e) => { templateState.document_type = e.target.value; templateState.page = 1; loadTemplates(); });
  document.getElementById('template_limit').addEventListener('change', (e) => { templateState.limit = Number(e.target.value || 10); templateState.page = 1; loadTemplates(); });
  document.getElementById('btn-clear-template').addEventListener('click', () => { templateState.q = ''; templateState.status = 'ACTIVE'; templateState.document_type = 'ALL'; templateState.page = 1; templateState.limit = 10; loadTemplates(); });

  document.getElementById('profile_q').addEventListener('input', (e) => { profileState.q = e.target.value; profileState.page = 1; loadProfiles(); });
  document.getElementById('btn-clear-profile').addEventListener('click', () => { profileState.q = ''; profileState.status = 'ACTIVE'; profileState.page = 1; loadProfiles(); });

  document.getElementById('device_q').addEventListener('input', (e) => { deviceState.q = e.target.value; deviceState.page = 1; loadDevices(); });
  document.getElementById('device_outlet_id').addEventListener('change', (e) => { deviceState.outlet_id = Number(e.target.value || 0); deviceState.page = 1; loadDevices(); });
  document.getElementById('btn-clear-device').addEventListener('click', () => { deviceState.q = ''; deviceState.status = 'ACTIVE'; deviceState.outlet_id = 0; deviceState.page = 1; loadDevices(); });

  document.getElementById('btn-save-profile').addEventListener('click', async () => {
    const payload = Object.fromEntries(new FormData(profileForm).entries());
    payload.paper_width_mm = Number(payload.paper_width_mm || 80);
    payload.copy_count = Number(payload.copy_count || 1);
    payload.open_drawer = Number(payload.open_drawer || 0);
    payload.show_logo = Number(payload.show_logo || 0);
    payload.show_price = Number(payload.show_price || 0);
    payload.show_footer = Number(payload.show_footer || 0);
    payload.is_active = Number(payload.is_active || 0);
    await postJson('<?php echo site_url('pos/printers/profiles/save'); ?>', payload);
    profileModal.hide();
    await loadProfiles();
  });

  document.getElementById('btn-save-device').addEventListener('click', async () => {
    const payload = Object.fromEntries(new FormData(deviceForm).entries());
    payload.port = payload.port === '' ? '' : Number(payload.port);
    payload.python_port = payload.python_port === '' ? '' : Number(payload.python_port);
    payload.is_active = Number(payload.is_active || 0);
    await postJson('<?php echo site_url('pos/printers/devices/save'); ?>', payload);
    deviceModal.hide();
    await loadDevices();
    await loadProfiles();
  });

  loadTemplates().catch((e) => alert(e.message));
  loadProfiles().catch((e) => alert(e.message));
  loadDevices().catch((e) => alert(e.message));
});
</script>



