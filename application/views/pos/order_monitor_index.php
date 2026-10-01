<?php
$filters = (array)($filters ?? []);
$payload = (array)($payload ?? []);
$stats = (array)($payload['stats'] ?? []);
$activeOrders = (array)($payload['active_orders'] ?? $payload['orders'] ?? []);
$completedOrders = (array)($payload['completed_orders'] ?? []);
$stationOptions = (array)($station_options ?? []);
$outletOptions = (array)($outlet_options ?? []);
$pollMs = max(5000, (int)($poll_ms ?? 12000));
$orderMonitorCsrfToken = (string)($order_monitor_csrf_token ?? '');
?>
<div class="container-fluid py-4 pos-monitor-page">
  <style>
    .pos-monitor-shell{background:radial-gradient(circle at top left,#fff8ef,#f6faf7 42%,#eef3f8 100%);border:1px solid #e5ebf2;border-radius:28px;padding:1.5rem;box-shadow:0 22px 50px rgba(19,32,56,.08)}
    .pos-monitor-header{display:flex;justify-content:space-between;gap:1rem;align-items:flex-start;flex-wrap:wrap;margin-bottom:1.5rem}
    .pos-monitor-title{font-size:1.9rem;font-weight:800;letter-spacing:-.03em;color:#17263f;margin:0}
    .pos-monitor-subtitle{color:#5f6f86;max-width:760px}
    .pos-monitor-live{display:inline-flex;align-items:center;gap:.55rem;background:#fff;border:1px solid #dde7f2;border-radius:999px;padding:.55rem .9rem;color:#31564a;font-weight:700}
    .pos-monitor-live-dot{width:.7rem;height:.7rem;border-radius:999px;background:#20b26b;box-shadow:0 0 0 0 rgba(32,178,107,.55);animation:posMonitorPulse 1.8s infinite}
    .pos-monitor-filter{background:rgba(255,255,255,.8);backdrop-filter:blur(10px);border:1px solid #e3eaf3;border-radius:22px;padding:1rem;margin-bottom:1.25rem}
    .pos-monitor-tabbar{display:flex;gap:.65rem;flex-wrap:wrap;margin-bottom:1rem}
    .pos-monitor-tab{display:inline-flex;align-items:center;gap:.5rem;padding:.7rem 1rem;border-radius:999px;border:1px solid #dce5ef;background:#fff;color:#53657f;font-weight:700;cursor:pointer;transition:all .14s ease}
    .pos-monitor-tab.is-active{background:#16263c;border-color:#16263c;color:#fff;box-shadow:0 16px 26px rgba(22,38,60,.16)}
    .pos-monitor-tab-count{display:inline-flex;align-items:center;justify-content:center;min-width:1.5rem;height:1.5rem;padding:0 .35rem;border-radius:999px;background:rgba(255,255,255,.14);font-size:.76rem}
    .pos-monitor-tab:not(.is-active) .pos-monitor-tab-count{background:#edf3f8;color:#4f6480}
    .pos-monitor-helper{display:flex;justify-content:space-between;gap:1rem;align-items:flex-start;flex-wrap:wrap;background:rgba(255,255,255,.66);border:1px solid #e3eaf3;border-radius:20px;padding:.9rem 1rem;margin-bottom:1rem}
    .pos-monitor-helper-title{font-size:.84rem;font-weight:800;color:#19324e;text-transform:uppercase;letter-spacing:.06em}
    .pos-monitor-helper-note{font-size:.84rem;color:#627489;margin-top:.25rem}
    .pos-monitor-legend{display:flex;gap:.45rem;flex-wrap:wrap;justify-content:flex-end}
    .pos-monitor-kpi{background:#fff;border:1px solid #dce5ef;border-radius:22px;padding:1rem 1.1rem;box-shadow:0 12px 28px rgba(17,27,46,.05);height:100%}
    .pos-monitor-kpi-label{font-size:.76rem;font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:#7c8ba1}
    .pos-monitor-kpi-value{font-size:1.85rem;line-height:1.1;font-weight:800;color:#16263c;margin-top:.45rem}
    .pos-monitor-kpi-note{font-size:.82rem;color:#5d6a7f;margin-top:.3rem}
    .pos-monitor-board{display:grid;grid-template-columns:repeat(auto-fill,minmax(330px,1fr));gap:1rem}
    .pos-monitor-ticket{background:linear-gradient(180deg,#ffffff,#f9fbfd);border:1px solid #dbe4ee;border-radius:24px;overflow:hidden;box-shadow:0 18px 38px rgba(17,27,46,.07)}
    .pos-monitor-ticket-head{padding:1rem 1.1rem;border-bottom:1px solid #e7edf4;background:linear-gradient(135deg,#f8fbff,#fff7ed)}
    .pos-monitor-ticket-no{font-size:1.12rem;font-weight:800;color:#182740}
    .pos-monitor-ticket-top{display:flex;justify-content:space-between;align-items:flex-start;gap:.75rem}
    .pos-monitor-ticket-customer{font-size:1rem;font-weight:700;color:#243852;margin-top:.25rem}
    .pos-monitor-ticket-line{font-size:.8rem;color:#65768c;margin-top:.2rem}
    .pos-monitor-ticket-meta{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.75rem;margin-top:.85rem}
    .pos-monitor-ticket-meta-label{font-size:.72rem;font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:#7c8ba1}
    .pos-monitor-ticket-meta-value{font-size:.92rem;color:#22324a;font-weight:700}
    .pos-monitor-ticket-body{padding:1rem 1.1rem 1.1rem}
    .pos-monitor-station{border:1px solid #e7edf4;border-radius:18px;padding:.9rem;background:#fcfdff}
    .pos-monitor-station + .pos-monitor-station{margin-top:.85rem}
    .pos-monitor-station-head{display:flex;justify-content:space-between;gap:.75rem;align-items:flex-start;margin-bottom:.8rem}
    .pos-monitor-station-title{font-size:.82rem;font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:#4f5f78}
    .pos-monitor-station-note{font-size:.78rem;color:#73839a;margin-top:.15rem}
    .pos-monitor-actions{display:flex;gap:.45rem;flex-wrap:wrap}
    .pos-monitor-row{border:1px solid #e8eef5;border-radius:16px;background:#fff;padding:.8rem .85rem}
    .pos-monitor-row + .pos-monitor-row{margin-top:.65rem}
    .pos-monitor-row-top{display:flex;justify-content:space-between;gap:.75rem;align-items:flex-start}
    .pos-monitor-row-name{font-size:1rem;font-weight:800;color:#152540}
    .pos-monitor-row-sub{font-size:.82rem;color:#647489;margin-top:.22rem}
    .pos-monitor-extra{display:inline-flex;align-items:center;gap:.3rem;margin:.35rem .35rem 0 0;padding:.2rem .55rem;border:1px solid #e3ebf3;border-radius:999px;background:#f6f9fc;color:#46617d;font-size:.76rem}
    .pos-monitor-badges{display:flex;gap:.35rem;flex-wrap:wrap;justify-content:flex-end}
    .pos-monitor-pill{display:inline-flex;align-items:center;padding:.24rem .56rem;border-radius:999px;font-size:.74rem;font-weight:800;letter-spacing:.04em}
    .pos-monitor-pill-new{background:#eef2f7;color:#46566e}
    .pos-monitor-pill-acked{background:#e7f1ff;color:#1758a8}
    .pos-monitor-pill-ready{background:#ecfbf4;color:#1c7a4f}
    .pos-monitor-pill-time-secondary{background:#eef2f7;color:#506079}
    .pos-monitor-pill-time-info{background:#e8f2ff;color:#1758a8}
    .pos-monitor-pill-time-warning{background:#fff4df;color:#a15f00}
    .pos-monitor-pill-time-danger{background:#fee9ea;color:#b3263e}
    .pos-monitor-pill-complete{background:#edf8f1;color:#1d6e4d}
    .pos-monitor-row-actions{display:flex;gap:.45rem;flex-wrap:wrap;margin-top:.75rem}
    .pos-monitor-complete-card{background:linear-gradient(180deg,#fff,#f8fbfd);border:1px solid #dde7ef;border-radius:22px;padding:1rem 1.05rem;box-shadow:0 14px 30px rgba(17,27,46,.06)}
    .pos-monitor-complete-card + .pos-monitor-complete-card{margin-top:.85rem}
    .pos-monitor-complete-items{margin-top:.8rem;padding-top:.8rem;border-top:1px dashed #dce6ef}
    .pos-monitor-complete-item{display:flex;justify-content:space-between;gap:.75rem;font-size:.86rem;color:#31455f;padding:.22rem 0}
    .pos-monitor-complete-item-name{font-weight:600;color:#1f3048}
    .pos-monitor-empty{padding:2rem 1rem;border:1px dashed #ccd9e7;border-radius:24px;background:rgba(255,255,255,.76);text-align:center;color:#607086}
    .pos-monitor-toast{position:fixed;right:18px;bottom:18px;z-index:1080;min-width:280px;display:none;box-shadow:0 18px 36px rgba(17,27,46,.22)}
    .pos-monitor-toast.is-visible{display:block}
    @keyframes posMonitorPulse{0%{box-shadow:0 0 0 0 rgba(32,178,107,.55)}70%{box-shadow:0 0 0 11px rgba(32,178,107,0)}100%{box-shadow:0 0 0 0 rgba(32,178,107,0)}}
    @media (max-width:767.98px){.pos-monitor-shell{padding:1rem}.pos-monitor-ticket-meta{grid-template-columns:1fr}.pos-monitor-board{grid-template-columns:1fr}}
  </style>

  <div class="pos-monitor-shell">
    <div class="pos-monitor-header">
      <div>
        <h1 class="pos-monitor-title"><?= html_escape($page_title ?? 'Monitor Dapur, Bar & Checker') ?></h1>
        <div class="pos-monitor-subtitle mt-2">Board operasional untuk memantau order aktif per stasiun, menerima task masuk, menandai siap, lalu menyelesaikan checker tanpa mengganggu alur pembayaran POS.</div>
      </div>
      <div class="pos-monitor-live">
        <span class="pos-monitor-live-dot"></span>
        <span>Auto refresh <?= (int)round($pollMs / 1000) ?> detik</span>
      </div>
    </div>

    <form id="monitorFilterForm" class="pos-monitor-filter row g-3 align-items-end">
      <div class="col-12 col-md-3 col-xl-2">
        <label class="form-label fw-semibold">Tanggal Awal</label>
        <input type="date" class="form-control" name="date_from" value="<?= html_escape((string)($filters['date_from'] ?? date('Y-m-d'))) ?>">
      </div>
      <div class="col-12 col-md-3 col-xl-2">
        <label class="form-label fw-semibold">Tanggal Akhir</label>
        <input type="date" class="form-control" name="date_to" value="<?= html_escape((string)($filters['date_to'] ?? date('Y-m-d'))) ?>">
      </div>
      <div class="col-12 col-md-3 col-xl-2">
        <label class="form-label fw-semibold">Stasiun</label>
        <select class="form-select" name="station">
          <?php foreach ($stationOptions as $value => $label): ?>
            <option value="<?= html_escape($value) ?>" <?= (($filters['station'] ?? 'ALL') === $value) ? 'selected' : '' ?>><?= html_escape($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-12 col-md-3 col-xl-3">
        <label class="form-label fw-semibold">Outlet</label>
        <select class="form-select" name="outlet_id">
          <option value="0">Semua Outlet</option>
          <?php foreach ($outletOptions as $row): ?>
            <option value="<?= (int)($row['id'] ?? 0) ?>" <?= (int)($filters['outlet_id'] ?? 0) === (int)($row['id'] ?? 0) ? 'selected' : '' ?>><?= html_escape((string)($row['outlet_name'] ?? '-')) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-6 col-xl-1 d-grid">
        <button type="submit" class="btn btn-primary">Muat</button>
      </div>
      <div class="col-6 col-xl-2 d-grid">
        <a href="<?= base_url('pos/order-monitor') ?>" class="btn btn-outline-secondary">Reset Hari Ini</a>
      </div>
    </form>

    <div class="row g-3 mb-3">
      <div class="col-6 col-xl-3"><div class="pos-monitor-kpi"><div class="pos-monitor-kpi-label">Ticket Aktif</div><div id="kpi-total-orders" class="pos-monitor-kpi-value"><?= number_format((int)($stats['total_orders'] ?? 0), 0, ',', '.') ?></div><div class="pos-monitor-kpi-note">Jumlah nota yang masih antre</div></div></div>
      <div class="col-6 col-xl-3"><div class="pos-monitor-kpi"><div class="pos-monitor-kpi-label">Task Baru</div><div id="kpi-new" class="pos-monitor-kpi-value"><?= number_format((int)($stats['new'] ?? 0), 0, ',', '.') ?></div><div class="pos-monitor-kpi-note">Belum diterima stasiun</div></div></div>
      <div class="col-6 col-xl-3"><div class="pos-monitor-kpi"><div class="pos-monitor-kpi-label">Sedang Diproses</div><div id="kpi-acked" class="pos-monitor-kpi-value"><?= number_format((int)($stats['acked'] ?? 0), 0, ',', '.') ?></div><div class="pos-monitor-kpi-note">Sudah diterima dapur/bar</div></div></div>
      <div class="col-6 col-xl-3"><div class="pos-monitor-kpi"><div class="pos-monitor-kpi-label">Siap Checker</div><div id="kpi-ready" class="pos-monitor-kpi-value"><?= number_format((int)($stats['ready'] ?? 0), 0, ',', '.') ?></div><div class="pos-monitor-kpi-note">Tinggal serah ke checker</div></div></div>
    </div>

    <div class="pos-monitor-tabbar" id="monitorTabBar">
      <button type="button" class="pos-monitor-tab is-active" data-monitor-tab="active">
        <span>Order Aktif</span>
        <span id="tab-count-active" class="pos-monitor-tab-count"><?= number_format(count($activeOrders), 0, ',', '.') ?></span>
      </button>
      <button type="button" class="pos-monitor-tab" data-monitor-tab="completed">
        <span>Sudah Selesai</span>
        <span id="tab-count-completed" class="pos-monitor-tab-count"><?= number_format(count($completedOrders), 0, ',', '.') ?></span>
      </button>
    </div>

    <div class="pos-monitor-helper">
      <div>
        <div class="pos-monitor-helper-title" id="monitorHelperTitle">Order Aktif</div>
        <div class="pos-monitor-helper-note" id="monitorHelperNote">Fokus utama untuk menerima task baru, memproses, lalu menyerahkan order ke checker.</div>
      </div>
      <div class="pos-monitor-legend">
        <span class="pos-monitor-pill pos-monitor-pill-new">Baru</span>
        <span class="pos-monitor-pill pos-monitor-pill-acked">Diproses</span>
        <span class="pos-monitor-pill pos-monitor-pill-ready">Siap</span>
        <span class="pos-monitor-pill pos-monitor-pill-complete">Selesai</span>
      </div>
    </div>

    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
      <div class="small text-muted">Update terakhir: <span id="monitor-generated-at"><?= html_escape((string)($payload['generated_at'] ?? '-')) ?></span></div>
      <div class="small text-muted">Filter aktif akan dipertahankan saat polling.</div>
    </div>

    <div id="monitorBoard" class="pos-monitor-board"></div>
  </div>
</div>

<div class="toast align-items-center text-bg-dark border-0 pos-monitor-toast" id="monitorToast" role="alert" aria-live="assertive" aria-atomic="true">
  <div class="d-flex">
    <div id="monitorToastBody" class="toast-body">Board monitor diperbarui.</div>
    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
  </div>
</div>

<script>
(function(){
  const initialPayload = <?= json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?> || {};
  if (!Array.isArray(initialPayload.active_orders) && Array.isArray(initialPayload.orders)) {
    initialPayload.active_orders = initialPayload.orders;
  }
  const boardEl = document.getElementById('monitorBoard');
  const formEl = document.getElementById('monitorFilterForm');
  const generatedAtEl = document.getElementById('monitor-generated-at');
  const tabBarEl = document.getElementById('monitorTabBar');
  const helperTitleEl = document.getElementById('monitorHelperTitle');
  const helperNoteEl = document.getElementById('monitorHelperNote');
  const activeTabCountEl = document.getElementById('tab-count-active');
  const completedTabCountEl = document.getElementById('tab-count-completed');
  const toastEl = document.getElementById('monitorToast');
  const toastBodyEl = document.getElementById('monitorToastBody');
  const bsToast = toastEl && window.bootstrap ? new bootstrap.Toast(toastEl, {delay: 2200}) : null;
  const notificationAudioUrl = <?= json_encode(base_url('assets/sounds/notifikasi.mp3')) ?>;
  const orderMonitorCsrfToken = <?= json_encode($orderMonitorCsrfToken, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  const endpoint = {
    data: <?= json_encode(base_url('pos/order-monitor/data')) ?>,
    ackTask: <?= json_encode(base_url('pos/order-monitor/ack-task')) ?>,
    readyTask: <?= json_encode(base_url('pos/order-monitor/ready-task')) ?>,
    checkerTask: <?= json_encode(base_url('pos/order-monitor/checker-task')) ?>,
    ackOrder: <?= json_encode(base_url('pos/order-monitor/ack-order-station')) ?>,
    readyOrder: <?= json_encode(base_url('pos/order-monitor/ready-order-station')) ?>,
    checkerOrder: <?= json_encode(base_url('pos/order-monitor/checker-order')) ?>,
    page: <?= json_encode(base_url('pos/order-monitor')) ?>
  };
  const pollMs = <?= (int)$pollMs ?>;
  let currentPayload = initialPayload;
  let pollHandle = null;
  let isLoading = false;
  let currentTab = 'active';
  let lastSeenTaskId = Number((initialPayload.stats && initialPayload.stats.latest_task_id) || 0);
  let toastHideHandle = null;
  let knownActiveOrderKeys = getActiveOrderKeys(initialPayload);
  let notificationAudio = null;

  function esc(value){
    return String(value == null ? '' : value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  async function getJson(url){
    const response = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
    const text = await response.text();
    let json = null;
    try { json = JSON.parse(text); } catch (error) {
      const snippet = String(text || '').replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 220);
      throw new Error(snippet ? 'Response backend tidak valid: ' + snippet : 'Response backend tidak valid dan bukan JSON.');
    }
    if (!response.ok || !json.ok) {
      throw new Error(json.message || 'Gagal memuat board monitor.');
    }
    return json;
  }

  async function postJson(url, payload){
    const response = await fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify(payload || {})
    });
    const text = await response.text();
    let json = null;
    try { json = JSON.parse(text); } catch (error) {
      const snippet = String(text || '').replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 220);
      throw new Error(snippet ? 'Response save backend tidak valid: ' + snippet : 'Response save backend tidak valid dan bukan JSON.');
    }
    if (!response.ok || !json.ok) {
      throw new Error(json.message || 'Gagal memproses aksi monitor.');
    }
    return json;
  }

  async function postOrderMonitorTaskJson(url, payload){
    const response = await fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-Pos-Order-Monitor-CSRF': orderMonitorCsrfToken
      },
      body: JSON.stringify(payload || {})
    });
    const text = await response.text();
    let json = null;
    try { json = JSON.parse(text); } catch (error) {
      const snippet = String(text || '').replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 220);
      throw new Error(snippet ? 'Response save backend tidak valid: ' + snippet : 'Response save backend tidak valid dan bukan JSON.');
    }
    if (!response.ok || !json.ok) {
      throw new Error(json.message || 'Gagal memproses aksi monitor.');
    }
    return json;
  }

  function formQuery(){
    return new URLSearchParams(new FormData(formEl)).toString();
  }

  function progressClass(code){
    const normalized = String(code || 'NEW').toUpperCase();
    if (normalized === 'READY') return 'pos-monitor-pill-ready';
    if (normalized === 'ACKED') return 'pos-monitor-pill-acked';
    return 'pos-monitor-pill-new';
  }

  function progressLabel(code){
    const normalized = String(code || 'NEW').toUpperCase();
    if (normalized === 'READY') return 'Siap';
    if (normalized === 'ACKED') return 'Diproses';
    return 'Baru';
  }

  function timeBadgeClass(code){
    const normalized = String(code || 'secondary').toLowerCase();
    if (normalized === 'info') return 'pos-monitor-pill-time-info';
    if (normalized === 'warning') return 'pos-monitor-pill-time-warning';
    if (normalized === 'danger') return 'pos-monitor-pill-time-danger';
    return 'pos-monitor-pill-time-secondary';
  }

  function stationTitle(stationRole){
    const titleMap = { BAR: 'Bar', KITCHEN: 'Kitchen', CHECKER: 'Checker' };
    return titleMap[stationRole] || stationRole;
  }

  function formatDateTime(value){
    if (!value) return '-';
    const date = new Date(String(value).replace(' ', 'T'));
    if (Number.isNaN(date.getTime())) return String(value);
    return new Intl.DateTimeFormat('id-ID', {
      day: '2-digit',
      month: 'short',
      hour: '2-digit',
      minute: '2-digit'
    }).format(date);
  }

  function activeOrderKey(order){
    const orderId = Number(order && order.order_id ? order.order_id : 0);
    if (orderId > 0) return 'id:' + orderId;
    return 'no:' + String(order && order.order_no ? order.order_no : '') + '|at:' + String(order && order.ordered_at ? order.ordered_at : '');
  }

  function getActiveOrders(payload){
    if (payload && Array.isArray(payload.active_orders)) {
      return payload.active_orders;
    }
    if (payload && Array.isArray(payload.orders)) {
      return payload.orders;
    }
    return [];
  }

  function getActiveOrderKeys(payload){
    return getActiveOrders(payload).map(activeOrderKey);
  }

  function playNewOrderTone(){
    if (!notificationAudioUrl) return;
    try {
      if (!notificationAudio) {
        notificationAudio = new Audio(notificationAudioUrl);
        notificationAudio.preload = 'auto';
      }
      notificationAudio.pause();
      notificationAudio.currentTime = 0;
      const playPromise = notificationAudio.play();
      if (playPromise && typeof playPromise.catch === 'function') {
        playPromise.catch(function(){
          // Browser can block autoplay until there is user interaction; toast still shows.
        });
      }
    } catch (error) {
      // If audio asset fails to load, visual toast still notifies the operator.
    }
  }

  function rowActionButtons(row, stationRole){
    const buttons = [];
    const taskId = Number(row.id || 0);
    const progress = String(row.progress_code || 'NEW').toUpperCase();
    if (stationRole === 'CHECKER') {
      buttons.push('<button type="button" class="btn btn-sm btn-success" data-monitor-action="checker-task" data-task-id="' + taskId + '">Checker Selesai</button>');
      return buttons.join('');
    }
    if (progress === 'NEW') {
      buttons.push('<button type="button" class="btn btn-sm btn-outline-primary" data-monitor-action="ack-task" data-task-id="' + taskId + '">Terima</button>');
    }
    if (progress !== 'READY') {
      buttons.push('<button type="button" class="btn btn-sm btn-primary" data-monitor-action="ready-task" data-task-id="' + taskId + '">Tandai Siap</button>');
    }
    return buttons.join('');
  }

  function stationBulkButtons(order, stationRole, rows){
    if (!Array.isArray(rows) || !rows.length) {
      return '';
    }
    const orderId = Number(order.order_id || 0);
    if (stationRole === 'CHECKER') {
      return '<button type="button" class="btn btn-sm btn-success" data-monitor-action="checker-order" data-order-id="' + orderId + '">Selesaikan Semua</button>';
    }
    return [
      '<button type="button" class="btn btn-sm btn-outline-primary" data-monitor-action="ack-order" data-order-id="' + orderId + '" data-station-role="' + stationRole + '">Terima Semua</button>',
      '<button type="button" class="btn btn-sm btn-primary" data-monitor-action="ready-order" data-order-id="' + orderId + '" data-station-role="' + stationRole + '">Semua Siap</button>'
    ].join('');
  }

  function renderRow(row, stationRole){
    const extras = Array.isArray(row.extras) ? row.extras : [];
    const extrasHtml = extras.length ? '<div class="mt-2">' + extras.map((extra) => '<span class="pos-monitor-extra">+' + esc(extra.name || '-') + ' x' + Number(extra.qty || 0) + '</span>').join('') + '</div>' : '';
    const notesHtml = row.notes ? '<div class="pos-monitor-row-sub">Catatan: ' + esc(row.notes) + '</div>' : '';
    const bundleHtml = row.bundle_display ? '<div class="pos-monitor-row-sub">Bundle: ' + esc(row.bundle_display) + '</div>' : '';
    const qtyLabel = Number(row.qty || 0).toLocaleString('id-ID', { maximumFractionDigits: 2 });
    return '<div class="pos-monitor-row">'
      + '<div class="pos-monitor-row-top">'
      +   '<div>'
      +     '<div class="pos-monitor-row-name">' + esc(row.display_name || '-') + '</div>'
      +     '<div class="pos-monitor-row-sub">Qty ' + qtyLabel + ' • Divisi ' + esc(row.product_division_name || '-') + '</div>'
      +     bundleHtml
      +     notesHtml
      +   '</div>'
      +   '<div class="pos-monitor-badges">'
      +     '<span class="pos-monitor-pill ' + progressClass(row.progress_code) + '">' + esc(progressLabel(row.progress_code)) + '</span>'
      +     '<span class="pos-monitor-pill ' + timeBadgeClass(row.time_badge && row.time_badge.class) + '">' + esc(row.time_badge && row.time_badge.label ? row.time_badge.label : '0 mnt') + '</span>'
      +   '</div>'
      + '</div>'
      + extrasHtml
      + '<div class="pos-monitor-row-actions">' + rowActionButtons(row, stationRole) + '</div>'
      + '</div>';
  }

  function stationCard(order, stationRole, rows){
    if (!Array.isArray(rows) || !rows.length) {
      return '';
    }
    return '<div class="pos-monitor-station">'
      + '<div class="pos-monitor-station-head">'
      +   '<div>'
      +     '<div class="pos-monitor-station-title">' + esc(stationTitle(stationRole)) + ' • ' + rows.length + ' task</div>'
      +     '<div class="pos-monitor-station-note">' + esc(stationRole === 'CHECKER' ? 'Periksa item yang sudah siap lalu tandai selesai.' : 'Aksi cepat per stasiun agar operator tidak perlu membuka tiap item satu per satu.') + '</div>'
      +   '</div>'
      +   '<div class="pos-monitor-actions">' + stationBulkButtons(order, stationRole, rows) + '</div>'
      + '</div>'
      + rows.map((row) => renderRow(row, stationRole)).join('')
      + '</div>';
  }

  function renderTicket(order){
    const stationValue = String((new FormData(formEl).get('station') || 'ALL')).toUpperCase();
    const blocks = [];
    if (stationValue === 'ALL') {
      ['BAR', 'KITCHEN', 'CHECKER'].forEach((stationRole) => {
        blocks.push(stationCard(order, stationRole, (order.stations && order.stations[stationRole]) || []));
      });
    } else {
      const key = stationValue === 'CHECKER' ? 'CHECKER' : stationValue;
      blocks.push(stationCard(order, key, (order.stations && order.stations[key]) || []));
    }
    return '<article class="pos-monitor-ticket">'
      + '<div class="pos-monitor-ticket-head">'
      +   '<div class="pos-monitor-ticket-top">'
      +     '<div>'
      +       '<div class="pos-monitor-ticket-no">' + esc(order.order_no || '-') + '</div>'
      +       '<div class="pos-monitor-ticket-customer">' + esc(order.customer_display || 'Walk In') + '</div>'
      +       '<div class="pos-monitor-ticket-line">' + esc(order.outlet_name || '-') + ' • ' + esc(order.service_type || '-') + (order.table_display ? ' • Meja ' + esc(order.table_display) : '') + '</div>'
      +     '</div>'
      +     '<span class="pos-monitor-pill ' + timeBadgeClass(order.time_badge && order.time_badge.class) + '">' + esc(order.time_badge && order.time_badge.label ? order.time_badge.label : '0 mnt') + '</span>'
      +   '</div>'
      +   '<div class="pos-monitor-ticket-meta">'
      +     '<div><div class="pos-monitor-ticket-meta-label">Status Dapur</div><div class="pos-monitor-ticket-meta-value">' + esc(order.kitchen_status || 'PENDING') + '</div></div>'
      +     '<div><div class="pos-monitor-ticket-meta-label">Scope</div><div class="pos-monitor-ticket-meta-value">' + esc(order.order_scope || '-') + '</div></div>'
      +     '<div><div class="pos-monitor-ticket-meta-label">Jam Masuk</div><div class="pos-monitor-ticket-meta-value">' + esc(formatDateTime(order.ordered_at)) + '</div></div>'
      +     '<div><div class="pos-monitor-ticket-meta-label">Kitchen Status</div><div class="pos-monitor-ticket-meta-value">' + esc(order.kitchen_status || 'PENDING') + '</div></div>'
      +   '</div>'
      + '</div>'
      + '<div class="pos-monitor-ticket-body">' + blocks.join('') + '</div>'
      + '</article>';
  }

  function renderCompletedTicket(order){
    const items = Array.isArray(order.items) ? order.items : [];
    const itemRows = items.slice(0, 6).map(function(row){
      const qtyLabel = Number(row.qty || 0).toLocaleString('id-ID', { maximumFractionDigits: 2 });
      return '<div class="pos-monitor-complete-item">'
        + '<div>'
        +   '<div class="pos-monitor-complete-item-name">' + esc(row.display_name || '-') + '</div>'
        +   '<div class="small text-muted">' + esc(stationTitle(row.station_role || '')) + (row.bundle_display ? ' • ' + esc(row.bundle_display) : '') + '</div>'
        + '</div>'
        + '<div class="text-end text-muted">x' + qtyLabel + '</div>'
        + '</div>';
    }).join('');
    const moreCount = Math.max(0, items.length - 6);
    return '<article class="pos-monitor-complete-card">'
      + '<div class="d-flex justify-content-between align-items-start gap-2">'
      +   '<div>'
      +     '<div class="pos-monitor-ticket-no">' + esc(order.order_no || '-') + '</div>'
      +     '<div class="pos-monitor-ticket-customer">' + esc(order.customer_display || 'Walk In') + '</div>'
      +     '<div class="pos-monitor-ticket-line">' + esc(order.outlet_name || '-') + ' • ' + esc(order.service_type || '-') + (order.table_display ? ' • Meja ' + esc(order.table_display) : '') + '</div>'
      +   '</div>'
      +   '<span class="pos-monitor-pill pos-monitor-pill-complete">Selesai</span>'
      + '</div>'
      + '<div class="pos-monitor-ticket-meta mt-3">'
      +   '<div><div class="pos-monitor-ticket-meta-label">Selesai</div><div class="pos-monitor-ticket-meta-value">' + esc(formatDateTime(order.served_at || order.ordered_at)) + '</div></div>'
      +   '<div><div class="pos-monitor-ticket-meta-label">Durasi</div><div class="pos-monitor-ticket-meta-value">' + esc(order.completion_badge && order.completion_badge.label ? order.completion_badge.label : '0 mnt') + '</div></div>'
      +   '<div><div class="pos-monitor-ticket-meta-label">Total Baris</div><div class="pos-monitor-ticket-meta-value">' + esc(order.line_count || 0) + '</div></div>'
      +   '<div><div class="pos-monitor-ticket-meta-label">Total Qty</div><div class="pos-monitor-ticket-meta-value">' + esc(Number(order.qty_total || 0).toLocaleString('id-ID', { maximumFractionDigits: 2 })) + '</div></div>'
      + '</div>'
      + '<div class="pos-monitor-complete-items">'
      +   '<div class="small fw-bold text-muted mb-2">Ringkasan Item</div>'
      +   itemRows
      +   (moreCount > 0 ? '<div class="small text-muted mt-2">+' + moreCount + ' item lain</div>' : '')
      + '</div>'
      + '</article>';
  }

  function renderEmptyState(type){
    if (type === 'completed') {
      return '<div class="pos-monitor-empty"><div class="fw-bold mb-2">Belum ada order selesai</div><div>Order yang sudah selesai checker dan served akan muncul di tab ini untuk review cepat.</div></div>';
    }
    return '<div class="pos-monitor-empty"><div class="fw-bold mb-2">Belum ada task aktif</div><div>Begitu order POS dikonfirmasi, task dapur/bar akan muncul di sini untuk diproses.</div></div>';
  }

  function updateTabUi(){
    if (!tabBarEl) return;
    tabBarEl.querySelectorAll('[data-monitor-tab]').forEach(function(button){
      button.classList.toggle('is-active', button.getAttribute('data-monitor-tab') === currentTab);
    });
    if (helperTitleEl) {
      helperTitleEl.textContent = currentTab === 'completed' ? 'Order Sudah Selesai' : 'Order Aktif';
    }
    if (helperNoteEl) {
      helperNoteEl.textContent = currentTab === 'completed'
        ? 'Gunakan tab ini untuk audit cepat order yang sudah tuntas, tanpa bercampur dengan antrean aktif.'
        : 'Fokus utama untuk menerima task baru, memproses, lalu menyerahkan order ke checker.';
    }
  }

  function maybeNotifyNewOrder(previousPayload, nextPayload){
    const previousKeys = new Set((previousPayload ? getActiveOrderKeys(previousPayload) : knownActiveOrderKeys) || []);
    const nextOrders = getActiveOrders(nextPayload);
    const newOrder = nextOrders.find(function(order){
      return !previousKeys.has(activeOrderKey(order));
    }) || null;
    const prevTaskId = Number((previousPayload && previousPayload.stats && previousPayload.stats.latest_task_id) || lastSeenTaskId || 0);
    const nextTaskId = Number((nextPayload && nextPayload.stats && nextPayload.stats.latest_task_id) || 0);
    knownActiveOrderKeys = nextOrders.map(activeOrderKey);
    lastSeenTaskId = Math.max(lastSeenTaskId, nextTaskId);
    if (!newOrder && (nextTaskId <= 0 || nextTaskId <= prevTaskId)) {
      return;
    }

    const info = (nextPayload && nextPayload.notification) || {};
    const message = 'Order baru masuk: ' + (newOrder && newOrder.order_no ? newOrder.order_no : (info.order_no || 'Tanpa nomor'))
      + ' • ' + (newOrder && newOrder.customer_display ? newOrder.customer_display : (info.customer_display || 'Walk In'))
      + ' • ' + (newOrder && newOrder.outlet_name ? newOrder.outlet_name : (info.outlet_name || '-'));
    playNewOrderTone();
    showToast(message);
  }

  function renderBoard(payload){
    const stats = payload && payload.stats ? payload.stats : {};
    const activeOrders = payload && Array.isArray(payload.active_orders) ? payload.active_orders : (payload && Array.isArray(payload.orders) ? payload.orders : []);
    const completedOrders = payload && Array.isArray(payload.completed_orders) ? payload.completed_orders : [];
    document.getElementById('kpi-total-orders').textContent = String(Number(stats.total_orders || 0));
    document.getElementById('kpi-new').textContent = String(Number(stats.new || 0));
    document.getElementById('kpi-acked').textContent = String(Number(stats.acked || 0));
    document.getElementById('kpi-ready').textContent = String(Number(stats.ready || 0));
    if (activeTabCountEl) activeTabCountEl.textContent = String(Number(stats.total_orders || activeOrders.length || 0));
    if (completedTabCountEl) completedTabCountEl.textContent = String(Number(stats.completed_orders || completedOrders.length || 0));
    if (generatedAtEl) {
      generatedAtEl.textContent = String(payload && payload.generated_at ? payload.generated_at : '-');
    }
    updateTabUi();
    if (currentTab === 'completed') {
      boardEl.innerHTML = completedOrders.length ? completedOrders.map(renderCompletedTicket).join('') : renderEmptyState('completed');
      return;
    }
    boardEl.innerHTML = activeOrders.length ? activeOrders.map(renderTicket).join('') : renderEmptyState('active');
  }

  function showToast(message){
    if (!toastBodyEl) return;
    toastBodyEl.textContent = message;
    if (bsToast) {
      bsToast.show();
      return;
    }
    if (!toastEl) return;
    toastEl.classList.add('is-visible', 'show');
    if (toastHideHandle) {
      window.clearTimeout(toastHideHandle);
    }
    toastHideHandle = window.setTimeout(function(){
      toastEl.classList.remove('is-visible', 'show');
      toastHideHandle = null;
    }, 2400);
  }

  async function loadBoard(showToastMessage, shouldNotifyNewOrder){
    if (isLoading) return;
    isLoading = true;
    try {
      const json = await getJson(endpoint.data + '?' + formQuery());
      const previousPayload = currentPayload;
      currentPayload = json.payload || {};
      if (!Array.isArray(currentPayload.active_orders) && Array.isArray(currentPayload.orders)) {
        currentPayload.active_orders = currentPayload.orders;
      }
      renderBoard(currentPayload);
      if (shouldNotifyNewOrder) {
        maybeNotifyNewOrder(previousPayload, currentPayload);
      } else {
        knownActiveOrderKeys = getActiveOrderKeys(currentPayload);
        lastSeenTaskId = Math.max(lastSeenTaskId, Number((currentPayload.stats && currentPayload.stats.latest_task_id) || 0));
      }
      if (showToastMessage) {
        showToast(showToastMessage);
      }
    } catch (error) {
      showToast(error.message || 'Board monitor gagal dimuat.');
    } finally {
      isLoading = false;
    }
  }

  formEl.addEventListener('submit', function(event){
    event.preventDefault();
    history.replaceState(null, '', endpoint.page + '?' + formQuery());
    loadBoard('Filter monitor diperbarui.', false);
  });

  if (tabBarEl) {
    tabBarEl.addEventListener('click', function(event){
      const button = event.target.closest('[data-monitor-tab]');
      if (!button) return;
      currentTab = String(button.getAttribute('data-monitor-tab') || 'active');
      renderBoard(currentPayload);
    });
  }

  boardEl.addEventListener('click', async function(event){
    const button = event.target.closest('[data-monitor-action]');
    if (!button) return;
    const action = String(button.getAttribute('data-monitor-action') || '');
    let url = '';
    let payload = {};
    let requestJson = postJson;
    if (action === 'ack-task') {
      url = endpoint.ackTask;
      payload = { task_id: Number(button.getAttribute('data-task-id') || 0) };
      requestJson = postOrderMonitorTaskJson;
    } else if (action === 'ready-task') {
      url = endpoint.readyTask;
      payload = { task_id: Number(button.getAttribute('data-task-id') || 0) };
      requestJson = postOrderMonitorTaskJson;
    } else if (action === 'checker-task') {
      url = endpoint.checkerTask;
      payload = { task_id: Number(button.getAttribute('data-task-id') || 0) };
      requestJson = postOrderMonitorTaskJson;
    } else if (action === 'ack-order') {
      url = endpoint.ackOrder;
      payload = { order_id: Number(button.getAttribute('data-order-id') || 0), station_role: String(button.getAttribute('data-station-role') || '') };
      requestJson = postOrderMonitorTaskJson;
    } else if (action === 'ready-order') {
      url = endpoint.readyOrder;
      payload = { order_id: Number(button.getAttribute('data-order-id') || 0), station_role: String(button.getAttribute('data-station-role') || '') };
      requestJson = postOrderMonitorTaskJson;
    } else if (action === 'checker-order') {
      url = endpoint.checkerOrder;
      payload = { order_id: Number(button.getAttribute('data-order-id') || 0) };
      requestJson = postOrderMonitorTaskJson;
    }
    if (!url) return;
    const originalHtml = button.innerHTML;
    button.disabled = true;
    button.innerHTML = 'Memproses...';
    try {
      await requestJson(url, payload);
      await loadBoard('Status task monitor diperbarui.', false);
    } catch (error) {
      showToast(error.message || 'Aksi monitor gagal diproses.');
    } finally {
      button.disabled = false;
      button.innerHTML = originalHtml;
    }
  });

  renderBoard(currentPayload);
  pollHandle = window.setInterval(function(){
    loadBoard('', true);
  }, pollMs);

  window.addEventListener('beforeunload', function(){
    if (pollHandle) {
      window.clearInterval(pollHandle);
    }
  });
})();
</script>
