<?php
$settings = is_array($settings ?? null) ? $settings : [];
$paymentMethods = is_array($payment_method_options ?? null) ? $payment_method_options : [];
$selectedMethods = array_map('intval', (array)($settings['payment_method_ids'] ?? []));
$selectedDaysCsv = trim((string)($settings['schedule_days_csv'] ?? ''));
$rawSelectedDays = $selectedDaysCsv !== '' ? $selectedDaysCsv : ($settings['schedule_days'] ?? []);
if (is_string($rawSelectedDays)) {
    $rawSelectedDays = explode(',', $rawSelectedDays);
}
$selectedDays = array_values(array_unique(array_filter(array_map(static function ($day): string {
    return trim((string)$day);
}, (array)$rawSelectedDays), static function ($day): bool {
    return in_array($day, ['1', '2', '3', '4', '5', '6', '0'], true);
})));
$selectedDaysCsv = implode(',', $selectedDays);
$dayOptions = [
    '1' => 'Senin',
    '2' => 'Selasa',
    '3' => 'Rabu',
    '4' => 'Kamis',
    '5' => 'Jumat',
    '6' => 'Sabtu',
    '0' => 'Minggu',
];
$formatNumber = static function ($value, int $decimal = 2): string {
    if ($value === '' || $value === null) {
        return '';
    }
    return rtrim(rtrim(number_format((float)$value, $decimal, '.', ''), '0'), '.');
};
$methodLabel = static function (array $method): string {
    $pieces = array_filter([
        (string)($method['method_name'] ?? '-'),
        (string)($method['method_type'] ?? ''),
        (string)($method['bank_name'] ?? ''),
        (string)($method['account_no'] ?? ''),
    ], static function ($value) {
        return trim((string)$value) !== '';
    });
    return implode(' | ', $pieces);
};
$deliveryChargeMode = strtoupper((string)($settings['delivery_fee_charge_mode'] ?? 'CUSTOMER_TO_DRIVER'));
$deliveryChargeMode = $deliveryChargeMode === 'MERCHANT_COLLECT' ? 'MERCHANT_COLLECT' : 'CUSTOMER_TO_DRIVER';
?>

<style>
  .online-food-shell { display:grid; gap:1rem; }
  .online-food-card { border:0; border-radius:18px; box-shadow:0 16px 34px rgba(52,38,30,.08); }
  .online-food-card h5 { font-size:1rem; font-weight:800; color:#342a2a; }
  .online-food-muted { color:#7b6f67; font-size:.84rem; line-height:1.45; }
  .online-food-check-grid { display:grid; grid-template-columns:repeat(2, minmax(0,1fr)); gap:.55rem; }
  .online-food-check {
    border:1px solid #e0d1c6; border-radius:12px; padding:.62rem .72rem; background:#fffdfb;
    min-height:44px; display:flex; align-items:center; gap:.45rem;
  }
  .online-food-check input { flex:0 0 auto; }
  .online-food-preview {
    border:1px dashed #ceb9aa; border-radius:14px; background:#fffaf6; padding:.8rem .9rem;
    color:#5b4c45; font-weight:700; word-break:break-word;
  }
  .online-food-tabbar {
    display:flex; flex-wrap:wrap; gap:.55rem; padding:.65rem; border:1px solid #d8c9bd;
    border-radius:14px; background:#fffaf6;
  }
  .online-food-tabbtn {
    border:1px solid #c9bbb0; background:#fff; color:#5e514a; border-radius:10px; padding:.55rem .85rem;
    font-weight:800; min-height:40px;
  }
  .online-food-tabbtn.is-active {
    background:#1f6f58; border-color:#1f6f58; color:#fff; box-shadow:0 10px 22px rgba(31,111,88,.16);
  }
  .online-food-tab-pane { display:none; }
  .online-food-tab-pane.is-active { display:block; }
  .online-food-option-grid { display:grid; grid-template-columns:repeat(2, minmax(0,1fr)); gap:1rem; }
  .online-food-pay-card {
    border:1px solid #d9c9be; border-radius:14px; padding:1rem; background:#fff;
    min-height:100%; display:grid; gap:.85rem;
  }
  .online-food-pay-card.is-primary { border-color:#1f6f58; box-shadow:0 14px 28px rgba(31,111,88,.12); }
  .online-food-pay-head { display:flex; align-items:flex-start; justify-content:space-between; gap:.75rem; }
  .online-food-pay-title { font-weight:900; color:#302827; line-height:1.2; }
  .online-food-pay-desc { color:#786d66; font-size:.86rem; line-height:1.45; margin-top:.2rem; }
  .online-food-map {
    width:100%; min-height:360px; border:1px solid #d8c9bd; border-radius:14px; overflow:hidden; background:#eef2ec;
  }
  .online-food-map-actions { display:flex; flex-wrap:wrap; gap:.5rem; align-items:center; margin-top:.7rem; }
  @media (max-width: 575.98px) {
    .online-food-check-grid { grid-template-columns:1fr; }
    .online-food-option-grid { grid-template-columns:1fr; }
  }
</style>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">

<div class="container-xxl py-3">
  <?php $this->load->view('pos/_master_tabs', ['pos_master_tab_active' => 'online-food']); ?>
  <?php $this->load->view('pos/_online_food_tabs', ['online_food_tab_active' => 'settings']); ?>

  <div class="fin-page-header mb-3">
    <div>
      <h4 class="fin-page-title mb-1">Settings Online Food</h4>
      <p class="fin-page-subtitle mb-0">Kontrol channel online order, jam buka, metode bayar, harga kemasan, pickup, dan ongkos kirim dari finance.</p>
    </div>
  </div>

  <?php if ($this->session->flashdata('success')): ?>
    <div class="alert alert-success"><?php echo html_escape((string)$this->session->flashdata('success')); ?></div>
  <?php endif; ?>
  <?php if ($this->session->flashdata('error')): ?>
    <div class="alert alert-danger"><?php echo html_escape((string)$this->session->flashdata('error')); ?></div>
  <?php endif; ?>

  <form method="post" class="online-food-shell">
    <div class="online-food-tabbar" role="tablist" aria-label="Settings Online Food">
      <button type="button" class="online-food-tabbtn is-active" data-online-food-tab="operational">Operasional</button>
      <button type="button" class="online-food-tabbtn" data-online-food-tab="payment">Pembayaran</button>
      <button type="button" class="online-food-tabbtn" data-online-food-tab="delivery">Ongkir</button>
      <button type="button" class="online-food-tabbtn" data-online-food-tab="admin">Link & Admin</button>
    </div>

    <div class="online-food-tab-pane is-active" data-online-food-pane="operational">
      <div class="row g-3">
      <div class="col-12">
        <div class="card online-food-card h-100">
          <div class="card-body">
            <h5 class="mb-3">Status Channel</h5>
            <div class="form-check form-switch border rounded-3 px-3 py-2 mb-3">
              <input class="form-check-input" type="checkbox" role="switch" id="is_enabled" name="is_enabled" value="1" <?php echo !empty($settings['is_enabled']) ? 'checked' : ''; ?>>
              <label class="form-check-label ms-2" for="is_enabled">Aktifkan Online Food</label>
            </div>

            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label small text-muted mb-1">Mode buka tutup</label>
                <select class="form-select" name="open_mode" id="online_food_open_mode">
                  <option value="MANUAL" <?php echo (string)($settings['open_mode'] ?? '') === 'MANUAL' ? 'selected' : ''; ?>>Manual</option>
                  <option value="SCHEDULE" <?php echo (string)($settings['open_mode'] ?? '') === 'SCHEDULE' ? 'selected' : ''; ?>>Otomatis jam</option>
                </select>
              </div>
              <div class="col-md-6" id="online_food_manual_status_wrap">
                <label class="form-label small text-muted mb-1">Status manual</label>
                <select class="form-select" name="manual_status">
                  <option value="OPEN" <?php echo (string)($settings['manual_status'] ?? '') === 'OPEN' ? 'selected' : ''; ?>>Buka</option>
                  <option value="CLOSED" <?php echo (string)($settings['manual_status'] ?? '') === 'CLOSED' ? 'selected' : ''; ?>>Tutup</option>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label small text-muted mb-1">Jam buka</label>
                <input type="time" class="form-control" name="open_time" value="<?php echo html_escape((string)($settings['open_time'] ?? '08:00')); ?>">
              </div>
              <div class="col-md-6">
                <label class="form-label small text-muted mb-1">Jam tutup</label>
                <input type="time" class="form-control" name="close_time" value="<?php echo html_escape((string)($settings['close_time'] ?? '22:00')); ?>">
              </div>
              <div class="col-12">
                <label class="form-label small text-muted mb-1">Hari aktif</label>
                <input type="hidden" name="schedule_days_csv" id="online_food_schedule_days_csv" value="<?php echo html_escape($selectedDaysCsv); ?>">
                <div class="online-food-check-grid">
                  <?php foreach ($dayOptions as $dayValue => $dayLabel): ?>
                    <label class="online-food-check">
                      <input type="checkbox" class="online-food-schedule-day" name="schedule_days[]" value="<?php echo html_escape($dayValue); ?>" <?php echo in_array($dayValue, $selectedDays, true) ? 'checked' : ''; ?>>
                      <span><?php echo html_escape($dayLabel); ?></span>
                    </label>
                  <?php endforeach; ?>
                </div>
              </div>
              <div class="col-12">
                <label class="form-label small text-muted mb-1">Timezone</label>
                <input type="text" class="form-control" name="timezone" value="<?php echo html_escape((string)($settings['timezone'] ?? 'Asia/Jakarta')); ?>">
              </div>
            </div>
          </div>
        </div>
      </div>

      </div>
    </div>

    <div class="online-food-tab-pane" data-online-food-pane="payment">
      <div class="row g-3">
      <div class="col-12">
        <div class="card online-food-card h-100">
          <div class="card-body">
            <h5 class="mb-3">Pembayaran</h5>
            <div class="online-food-option-grid">
              <div class="online-food-pay-card <?php echo (string)($settings['payment_default'] ?? '') === 'AUTO' ? 'is-primary' : ''; ?>">
                <div class="online-food-pay-head">
                  <div>
                    <div class="online-food-pay-title">QRIS Otomatis</div>
                    <div class="online-food-pay-desc">Customer bayar QRIS. Setelah payment terdeteksi lunas, kasir verifikasi dan order masuk pesanan terbayar.</div>
                  </div>
                  <input class="form-check-input mt-1" type="checkbox" role="switch" id="payment_auto_enabled" name="payment_auto_enabled" value="1" <?php echo !empty($settings['payment_auto_enabled']) ? 'checked' : ''; ?>>
                </div>
                <label class="form-label small text-muted mb-1">Metode POS untuk transaksi Midtrans</label>
                <select class="form-select" name="qris_payment_method_id">
                  <option value="">Pilih metode POS...</option>
                  <?php foreach ($paymentMethods as $method): ?>
                    <option value="<?php echo (int)$method['id']; ?>" <?php echo (int)($settings['qris_payment_method_id'] ?? 0) === (int)$method['id'] ? 'selected' : ''; ?>>
                      <?php echo html_escape($methodLabel($method)); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <div class="online-food-muted">Pilih metode/rekening POS yang akan dipakai untuk mencatat settlement Midtrans, misalnya MIDTRANS.</div>
                <div class="row g-2">
                  <div class="col-md-6">
                    <label class="form-label small text-muted mb-1">Midtrans Server Key</label>
                    <textarea class="form-control" name="midtrans_server_key" rows="3"><?php echo html_escape((string)($settings['midtrans_server_key'] ?? '')); ?></textarea>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label small text-muted mb-1">Midtrans Client Key</label>
                    <textarea class="form-control" name="midtrans_client_key" rows="3"><?php echo html_escape((string)($settings['midtrans_client_key'] ?? '')); ?></textarea>
                  </div>
                </div>
                <label class="online-food-check">
                  <input type="checkbox" name="midtrans_is_production" value="1" <?php echo !empty($settings['midtrans_is_production']) ? 'checked' : ''; ?>>
                  <span>Gunakan Midtrans production</span>
                </label>
              </div>

              <div class="online-food-pay-card <?php echo (string)($settings['payment_default'] ?? '') !== 'AUTO' ? 'is-primary' : ''; ?>">
                <div class="online-food-pay-head">
                  <div>
                    <div class="online-food-pay-title">Manual Admin</div>
                    <div class="online-food-pay-desc">Customer klik WhatsApp. Setelah admin/kasir deal, kasir verifikasi order agar masuk Order Aktif POS dan payment dilakukan di POS seperti biasa.</div>
                  </div>
                  <input class="form-check-input mt-1" type="checkbox" role="switch" id="payment_manual_enabled" name="payment_manual_enabled" value="1" <?php echo !empty($settings['payment_manual_enabled']) ? 'checked' : ''; ?>>
                </div>
                <label class="form-label small text-muted mb-1">Nomor WhatsApp admin</label>
                <input type="text" class="form-control" name="manual_whatsapp_number" value="<?php echo html_escape((string)($settings['manual_whatsapp_number'] ?? '')); ?>" placeholder="62812xxxx">
                <label class="form-label small text-muted mb-1">Template pesan WhatsApp</label>
                <input type="text" class="form-control" name="manual_whatsapp_template" value="<?php echo html_escape((string)($settings['manual_whatsapp_template'] ?? '')); ?>">
                <label class="form-label small text-muted mb-1">Instruksi singkat untuk customer</label>
                <textarea class="form-control" name="manual_payment_instructions" rows="3"><?php echo html_escape((string)($settings['manual_payment_instructions'] ?? '')); ?></textarea>
              </div>
            </div>

            <div class="row g-3 mt-1">
              <div class="col-md-6">
                <label class="form-label small text-muted mb-1">Payment utama yang dipilih otomatis di member</label>
                <select class="form-select" name="payment_default">
                  <option value="MANUAL" <?php echo (string)($settings['payment_default'] ?? '') === 'MANUAL' ? 'selected' : ''; ?>>Manual admin</option>
                  <option value="AUTO" <?php echo (string)($settings['payment_default'] ?? '') === 'AUTO' ? 'selected' : ''; ?>>QRIS otomatis</option>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label small text-muted mb-1">Minimal order</label>
                <input type="number" step="0.01" min="0" class="form-control" name="min_order_amount" value="<?php echo html_escape($formatNumber($settings['min_order_amount'] ?? 0)); ?>">
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    </div>

    <div class="online-food-tab-pane" data-online-food-pane="delivery">
    <div class="row g-3">
      <div class="col-12">
        <div class="card online-food-card h-100">
          <div class="card-body">
            <h5 class="mb-3">Ongkos Kirim</h5>
            <div class="row g-3">
              <div class="col-md-4">
                <label class="form-label small text-muted mb-1">Mode ongkir</label>
                <select class="form-select" name="delivery_fee_mode">
                  <option value="DISTANCE" <?php echo (string)($settings['delivery_fee_mode'] ?? '') === 'DISTANCE' ? 'selected' : ''; ?>>Berdasarkan jarak</option>
                  <option value="FLAT" <?php echo (string)($settings['delivery_fee_mode'] ?? '') === 'FLAT' ? 'selected' : ''; ?>>Flat</option>
                </select>
              </div>
              <div class="col-md-4">
                <label class="form-label small text-muted mb-1">Default pilihan ongkir di member</label>
                <select class="form-select" name="delivery_fee_charge_mode">
                  <option value="CUSTOMER_TO_DRIVER" <?php echo $deliveryChargeMode === 'CUSTOMER_TO_DRIVER' ? 'selected' : ''; ?>>Ongkir dibayar penerima</option>
                  <option value="MERCHANT_COLLECT" <?php echo $deliveryChargeMode === 'MERCHANT_COLLECT' ? 'selected' : ''; ?>>Ongkir ikut total pembayaran</option>
                </select>
                <div class="online-food-muted mt-1">Ini hanya default. Customer tetap bisa memilih saat checkout. Nilai sales POS tetap hanya menu; ongkir dicatat di delivery.</div>
              </div>
              <div class="col-md-4">
                <label class="form-label small text-muted mb-1">Ongkir flat</label>
                <input type="number" step="0.01" min="0" class="form-control" name="delivery_flat_fee" value="<?php echo html_escape($formatNumber($settings['delivery_flat_fee'] ?? 0)); ?>">
              </div>
              <div class="col-md-4">
                <label class="form-label small text-muted mb-1">Ongkir minimum</label>
                <input type="number" step="0.01" min="0" class="form-control" name="delivery_min_fee" value="<?php echo html_escape($formatNumber($settings['delivery_min_fee'] ?? 0)); ?>">
              </div>
              <div class="col-md-4">
                <label class="form-label small text-muted mb-1">Biaya dasar</label>
                <input type="number" step="0.01" min="0" class="form-control" name="delivery_base_fee" value="<?php echo html_escape($formatNumber($settings['delivery_base_fee'] ?? 0)); ?>">
              </div>
              <div class="col-md-4">
                <label class="form-label small text-muted mb-1">KM dasar</label>
                <input type="number" step="0.01" min="0" class="form-control" name="delivery_base_km" value="<?php echo html_escape($formatNumber($settings['delivery_base_km'] ?? 0)); ?>">
              </div>
              <div class="col-md-4">
                <label class="form-label small text-muted mb-1">Biaya per KM</label>
                <input type="number" step="0.01" min="0" class="form-control" name="delivery_per_km_fee" value="<?php echo html_escape($formatNumber($settings['delivery_per_km_fee'] ?? 0)); ?>">
              </div>
              <div class="col-md-4">
                <label class="form-label small text-muted mb-1">Maks jarak KM</label>
                <input type="number" step="0.01" min="0" class="form-control" name="delivery_max_distance_km" value="<?php echo html_escape($formatNumber($settings['delivery_max_distance_km'] ?? 0)); ?>">
              </div>
              <div class="col-md-4">
                <label class="form-label small text-muted mb-1">Gratis ongkir min belanja</label>
                <input type="number" step="0.01" min="0" class="form-control" name="free_delivery_min_order" value="<?php echo html_escape($formatNumber($settings['free_delivery_min_order'] ?? 0)); ?>">
              </div>
              <div class="col-md-4">
                <label class="form-label small text-muted mb-1">Gratis ongkir sampai KM</label>
                <input type="number" step="0.01" min="0" class="form-control" name="free_delivery_distance_km" value="<?php echo html_escape($formatNumber($settings['free_delivery_distance_km'] ?? 0)); ?>">
              </div>
              <div class="col-md-4">
                <label class="form-label small text-muted mb-1">Biaya kemasan default</label>
                <input type="number" step="0.01" min="0" class="form-control" name="packaging_fee_default" value="<?php echo html_escape($formatNumber($settings['packaging_fee_default'] ?? 0)); ?>">
              </div>
            </div>
          </div>
        </div>
      </div>

    </div>
    </div>

    <div class="online-food-tab-pane" data-online-food-pane="admin">
    <div class="row g-3">
      <div class="col-12">
        <div class="card online-food-card h-100">
          <div class="card-body">
            <h5 class="mb-3">Outlet & Link Member</h5>
            <div class="row g-3">
              <div class="col-12">
                <label class="form-label small text-muted mb-1">URL Online Food</label>
                <input type="url" class="form-control" name="online_order_url" id="online_order_url" value="<?php echo html_escape((string)($settings['online_order_url'] ?? '')); ?>" placeholder="https://member.domain.com/online-order">
                <input type="hidden" name="member_base_url" id="member_base_url" value="<?php echo html_escape((string)($settings['member_base_url'] ?? '')); ?>">
              </div>
              <div class="col-12">
                <label class="form-label small text-muted mb-1">Titik outlet untuk hitung jarak</label>
                <div id="online_food_map" class="online-food-map"></div>
                <div class="online-food-map-actions">
                  <button type="button" class="btn btn-sm btn-outline-primary" id="online_food_use_browser_location">Gunakan Lokasi Browser</button>
                  <span class="online-food-muted" id="online_food_map_status">Geser pin pada map untuk menentukan titik outlet.</span>
                </div>
              </div>
              <div class="col-md-6">
                <label class="form-label small text-muted mb-1">Latitude outlet</label>
                <input type="number" step="0.0000001" class="form-control" name="outlet_lat" id="outlet_lat" value="<?php echo html_escape((string)($settings['outlet_lat'] ?? '')); ?>">
              </div>
              <div class="col-md-6">
                <label class="form-label small text-muted mb-1">Longitude outlet</label>
                <input type="number" step="0.0000001" class="form-control" name="outlet_lng" id="outlet_lng" value="<?php echo html_escape((string)($settings['outlet_lng'] ?? '')); ?>">
              </div>
              <div class="col-12">
                <label class="form-label small text-muted mb-1">Catatan internal</label>
                <textarea class="form-control" name="notes" rows="3"><?php echo html_escape((string)($settings['notes'] ?? '')); ?></textarea>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    </div>

    <div class="d-flex justify-content-end gap-2">
      <a href="<?php echo site_url('master/product'); ?>" class="btn btn-light">Master Product</a>
      <button type="submit" class="btn btn-primary">Simpan Pengaturan</button>
    </div>
  </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const buttons = Array.from(document.querySelectorAll('[data-online-food-tab]'));
  const panes = Array.from(document.querySelectorAll('[data-online-food-pane]'));
  let mapInit = false;
  let map = null;
  let marker = null;
  function restoreScheduleDaysFromCsv() {
    const csv = document.getElementById('online_food_schedule_days_csv');
    if (!csv) return;
    const selected = new Set(String(csv.value || '').split(',').map(function (value) {
      return value.trim();
    }).filter(Boolean));
    if (!selected.size) return;
    document.querySelectorAll('.online-food-schedule-day').forEach(function (input) {
      input.checked = selected.has(String(input.value || '').trim());
    });
  }
  function syncOperationalUi() {
    const mode = document.getElementById('online_food_open_mode');
    const manualWrap = document.getElementById('online_food_manual_status_wrap');
    if (mode && manualWrap) {
      manualWrap.style.display = String(mode.value || '').toUpperCase() === 'SCHEDULE' ? 'none' : '';
    }
    const csv = document.getElementById('online_food_schedule_days_csv');
    if (csv) {
      csv.value = Array.from(document.querySelectorAll('.online-food-schedule-day:checked'))
        .map(function (item) { return item.value; })
        .join(',');
    }
  }
  function syncBaseUrlFromOnlineUrl() {
    const onlineUrl = document.getElementById('online_order_url');
    const baseUrl = document.getElementById('member_base_url');
    if (!onlineUrl || !baseUrl) return;
    let value = String(onlineUrl.value || '').trim();
    if (value === '') return;
    value = value.replace(/\/online-order\/?$/i, '/');
    if (!/\/$/.test(value)) value += '/';
    baseUrl.value = value;
  }
  function initOutletMap() {
    if (mapInit || typeof L === 'undefined') return;
    const latEl = document.getElementById('outlet_lat');
    const lngEl = document.getElementById('outlet_lng');
    const statusEl = document.getElementById('online_food_map_status');
    const mapEl = document.getElementById('online_food_map');
    if (!latEl || !lngEl || !mapEl) return;
    const initialLat = parseFloat(latEl.value || '-6.2000000');
    const initialLng = parseFloat(lngEl.value || '106.8166667');
    const setPoint = function (lat, lng, moveMap) {
      latEl.value = Number(lat).toFixed(7);
      lngEl.value = Number(lng).toFixed(7);
      if (marker) marker.setLatLng([lat, lng]);
      if (moveMap && map) map.setView([lat, lng], Math.max(map.getZoom(), 15));
      if (statusEl) statusEl.textContent = 'Titik outlet: ' + Number(lat).toFixed(7) + ', ' + Number(lng).toFixed(7);
    };
    map = L.map(mapEl).setView([initialLat, initialLng], 15);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 19,
      attribution: '&copy; OpenStreetMap'
    }).addTo(map);
    marker = L.marker([initialLat, initialLng], { draggable: true }).addTo(map);
    marker.on('dragend', function () {
      const point = marker.getLatLng();
      setPoint(point.lat, point.lng, false);
    });
    map.on('click', function (event) {
      setPoint(event.latlng.lat, event.latlng.lng, false);
    });
    latEl.addEventListener('change', function () {
      const lat = parseFloat(latEl.value);
      const lng = parseFloat(lngEl.value);
      if (!Number.isNaN(lat) && !Number.isNaN(lng)) setPoint(lat, lng, true);
    });
    lngEl.addEventListener('change', function () {
      const lat = parseFloat(latEl.value);
      const lng = parseFloat(lngEl.value);
      if (!Number.isNaN(lat) && !Number.isNaN(lng)) setPoint(lat, lng, true);
    });
    const browserBtn = document.getElementById('online_food_use_browser_location');
    if (browserBtn) {
      browserBtn.addEventListener('click', function () {
        if (!navigator.geolocation) {
          if (statusEl) statusEl.textContent = 'Browser tidak mendukung geolocation.';
          return;
        }
        if (statusEl) statusEl.textContent = 'Mengambil lokasi browser...';
        navigator.geolocation.getCurrentPosition(function (pos) {
          setPoint(pos.coords.latitude, pos.coords.longitude, true);
        }, function () {
          if (statusEl) statusEl.textContent = 'Gagal mengambil lokasi browser. Geser pin secara manual.';
        }, { enableHighAccuracy: true, timeout: 12000 });
      });
    }
    setPoint(initialLat, initialLng, false);
    mapInit = true;
    setTimeout(function () { map.invalidateSize(); }, 120);
  }
  buttons.forEach(function (button) {
    button.addEventListener('click', function () {
      const target = button.getAttribute('data-online-food-tab');
      buttons.forEach(function (item) {
        item.classList.toggle('is-active', item === button);
      });
      panes.forEach(function (pane) {
        pane.classList.toggle('is-active', pane.getAttribute('data-online-food-pane') === target);
      });
      if (target === 'admin') {
        initOutletMap();
      }
    });
  });
  const onlineUrl = document.getElementById('online_order_url');
  if (onlineUrl) {
    onlineUrl.addEventListener('input', syncBaseUrlFromOnlineUrl);
    onlineUrl.addEventListener('change', syncBaseUrlFromOnlineUrl);
  }
  const openMode = document.getElementById('online_food_open_mode');
  if (openMode) openMode.addEventListener('change', syncOperationalUi);
  document.querySelectorAll('.online-food-schedule-day').forEach(function (input) {
    input.addEventListener('change', syncOperationalUi);
  });
  restoreScheduleDaysFromCsv();
  syncOperationalUi();
  const form = document.querySelector('form.online-food-shell');
  if (form) {
    form.addEventListener('submit', function () {
      syncOperationalUi();
      syncBaseUrlFromOnlineUrl();
    });
  }
});
</script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
