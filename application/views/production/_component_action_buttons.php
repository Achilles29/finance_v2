<?php
/**
 * _component_action_buttons.php
 * Quick-action bar: one Generate button (opname + stok awal sekaligus)
 * plus navigation links to view pages.
 *
 * Variables (all optional):
 *   $component_action_params : array of query params forwarded to view pages
 */
$fwdParams = is_array($component_action_params ?? null) ? $component_action_params : [];
unset($fwdParams['type'], $fwdParams['q']);

$month       = (string)($fwdParams['month'] ?? '');
if ($month === '' && !empty($fwdParams['date_from'])) {
  $month = date('Y-m', strtotime((string)$fwdParams['date_from']));
}
if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
  $month = date('Y-m');
}
$locationType = (string)($fwdParams['location_type'] ?? '');
$divisionId  = (string)($fwdParams['division_id'] ?? '');

$opnameUrl  = site_url('production/component-opname')   . (!empty($fwdParams) ? '?' . http_build_query($fwdParams) : '');
$openingUrl = site_url('production/component-openings') . (!empty($fwdParams) ? '?' . http_build_query($fwdParams) : '');
$generateUrl = site_url('production/component-openings/generate-monthly');
?>

<style>
  .component-action-btn-bar {
    display: flex;
    flex-wrap: wrap;
    gap: .4rem;
    align-items: center;
    margin-top: .4rem;
    padding-top: .4rem;
    border-top: 1px solid #e9e3dc;
  }
  .component-action-btn-bar .cab-label {
    min-width: 88px;
    font-size: .74rem;
    font-weight: 700;
    letter-spacing: .04em;
    text-transform: uppercase;
    color: #7a6d62;
  }
</style>

<div class="component-action-btn-bar">
  <span class="cab-label">Aksi Cepat</span>
  <button type="button" class="btn btn-sm btn-outline-danger fw-semibold js-component-generate-trigger" id="btnCabGenerate"
          data-month="<?php echo html_escape($month); ?>"
          data-location-type="<?php echo html_escape($locationType); ?>"
          data-division-id="<?php echo html_escape($divisionId); ?>"
          data-bs-toggle="modal"
          data-bs-target="#componentGenerateModal">
    <i class="ri ri-refresh-line me-1"></i>Generate Opname &amp; Stok Awal
  </button>
  <a href="<?php echo html_escape($opnameUrl); ?>" class="btn btn-sm btn-outline-secondary">
    <i class="ri ri-file-list-3-line me-1"></i>Lihat Opname
  </a>
  <a href="<?php echo html_escape($openingUrl); ?>" class="btn btn-sm btn-outline-secondary">
    <i class="ri ri-archive-drawer-line me-1"></i>Lihat Stok Awal
  </a>
</div>

<div id="cabGenerateAlert" class="mt-2"></div>

<div class="modal fade" id="componentGenerateModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Generate Opname Component</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label for="componentGenerateMonth" class="form-label">Bulan Sumber</label>
          <input type="month" class="form-control" id="componentGenerateMonth" value="<?php echo html_escape($month); ?>">
          <small class="text-muted">Pilih bulan sumber opname. Closing bulan ini akan menjadi stok awal bulan berikutnya.</small>
        </div>
        <div id="componentGenerateContext" class="small text-muted mb-3"></div>
        <div class="alert alert-light border small mb-0">
          <div><strong>Hasil generate:</strong></div>
          <div>1. Snapshot opname component untuk bulan sumber yang dipilih.</div>
          <div>2. Stok awal bulan berikutnya dibuat dari closing yang tidak sama dengan 0.</div>
          <div>3. Jika generate diulang, data generate sebelumnya akan ditimpa.</div>
          <div>4. Source of truth mengikuti stock bulanan final.</div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Tutup</button>
        <button type="button" class="btn btn-danger" id="btnComponentGenerateSubmit">
          <i class="ri ri-refresh-line me-1"></i>Generate Sekarang
        </button>
      </div>
    </div>
  </div>
</div>

<script>
(function () {
  const alertEl = document.getElementById('cabGenerateAlert');
  const modalEl = document.getElementById('componentGenerateModal');
  const monthEl = document.getElementById('componentGenerateMonth');
  const submitBtn = document.getElementById('btnComponentGenerateSubmit');
  const contextEl = document.getElementById('componentGenerateContext');
  const triggers = Array.from(document.querySelectorAll('.js-component-generate-trigger'));
  if (!modalEl || !monthEl || !submitBtn || !triggers.length) return;
  let activeTrigger = triggers[0] || null;

  function escapeHtml(str) {
    return String(str ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function nextMonth(month) {
    const parts = String(month || '').split('-');
    if (parts.length !== 2) return '';
    let year = Number(parts[0]);
    let mon = Number(parts[1]);
    if (!year || !mon || mon < 1 || mon > 12) return '';
    mon += 1;
    if (mon > 12) {
      mon = 1;
      year += 1;
    }
    return String(year).padStart(4, '0') + '-' + String(mon).padStart(2, '0');
  }

  function monthEnd(month) {
    const parts = String(month || '').split('-');
    if (parts.length !== 2) return '';
    const date = new Date(Number(parts[0]), Number(parts[1]), 0);
    const year = date.getFullYear();
    const mon = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return year + '-' + mon + '-' + day;
  }

  function inferMonth(trigger) {
    const direct = String(trigger?.dataset.month || '').trim();
    if (/^\d{4}-\d{2}$/.test(direct)) return direct;
    const named = document.querySelector('input[name="month"]');
    if (named && /^\d{4}-\d{2}$/.test(String(named.value || '').trim())) return String(named.value).trim();
    const monthly = document.getElementById('monthly-month');
    if (monthly && /^\d{4}-\d{2}$/.test(String(monthly.value || '').trim())) return String(monthly.value).trim();
    return <?php echo json_encode($month); ?>;
  }

  function inferLocationType(trigger) {
    const direct = String(trigger?.dataset.locationType || '').trim();
    if (direct !== '') return direct;
    const named = document.querySelector('select[name="location_type"]');
    if (named) return String(named.value || '').trim();
    const monthly = document.getElementById('monthly-location-type');
    if (monthly) return String(monthly.value || '').trim();
    return '';
  }

  function inferDivisionId(trigger) {
    const direct = String(trigger?.dataset.divisionId || '').trim();
    if (direct !== '') return direct;
    const named = document.querySelector('select[name="division_id"]');
    if (named) return String(named.value || '').trim();
    const monthly = document.getElementById('monthly-division-id');
    if (monthly) return String(monthly.value || '').trim();
    return '';
  }

  function syncTriggerContext(trigger) {
    if (!trigger) return;
    trigger.dataset.month = inferMonth(trigger);
    trigger.dataset.locationType = inferLocationType(trigger);
    trigger.dataset.divisionId = inferDivisionId(trigger);
  }

  function renderContext(trigger, month) {
    if (!contextEl) return;
    const loc = String(trigger?.dataset.locationType || '').trim();
    const div = String(trigger?.dataset.divisionId || '').trim();
    const chips = [];
    if (loc !== '') chips.push('Lokasi: <strong>' + escapeHtml(loc) + '</strong>');
    if (div !== '' && div !== '0') chips.push('Divisi ID: <strong>' + escapeHtml(div) + '</strong>');
    chips.push('Stok awal target: <strong>' + escapeHtml(nextMonth(month) || '-') + '</strong>');
    contextEl.innerHTML = chips.join(' <span class="text-muted">•</span> ');
  }

  function reloadToMonth(trigger, month) {
    const url = new URL(window.location.href);
    const locType = inferLocationType(trigger);
    const divisionId = inferDivisionId(trigger);
    if (url.searchParams.has('month') || document.querySelector('input[name="month"]') || document.getElementById('monthly-month')) {
      url.searchParams.set('month', month);
    }
    if (url.searchParams.has('date_from') || document.querySelector('input[name="date_from"]')) {
      url.searchParams.set('date_from', month + '-01');
    }
    if (url.searchParams.has('date_to') || document.querySelector('input[name="date_to"]')) {
      url.searchParams.set('date_to', monthEnd(month));
    }
    if (url.searchParams.has('location_type') || document.querySelector('select[name="location_type"]') || document.getElementById('monthly-location-type')) {
      if (locType !== '') {
        url.searchParams.set('location_type', locType);
      } else {
        url.searchParams.delete('location_type');
      }
    }
    if (url.searchParams.has('division_id') || document.querySelector('select[name="division_id"]') || document.getElementById('monthly-division-id')) {
      if (divisionId !== '' && divisionId !== '0') {
        url.searchParams.set('division_id', divisionId);
      } else {
        url.searchParams.delete('division_id');
      }
    }
    window.location.href = url.toString();
  }

  function showAlert(type, msg) {
    if (!alertEl) return;
    alertEl.innerHTML = '<div class="alert alert-' + type + ' alert-dismissible mb-0 py-2"><button type="button" class="btn-close" data-bs-dismiss="alert"></button>' + msg + '</div>';
  }

  async function parseJsonResponse(response) {
    const raw = await response.text();
    try {
      return JSON.parse(raw);
    } catch (err) {
      throw new Error(raw && raw.trim() !== '' ? raw.trim().slice(0, 240) : 'Respons server bukan JSON valid.');
    }
  }

  function buildNegativeSamples(samples) {
    if (!Array.isArray(samples) || !samples.length) return '';
    return '<ul class="mb-0 mt-2 ps-3 small">'
      + samples.slice(0, 8).map(sample => {
        const label = [sample.code || '-', sample.name || '-', [sample.location_type || '-', sample.division_name || '-'].join('/')].join(' • ');
        return '<li>' + escapeHtml(label) + '</li>';
      }).join('')
      + '</ul>';
  }

  triggers.forEach(trigger => {
    trigger.addEventListener('click', function () {
      syncTriggerContext(trigger);
      activeTrigger = trigger;
      const month = String(trigger.dataset.month || '').trim();
      if (month !== '') {
        monthEl.value = month;
      }
      renderContext(trigger, monthEl.value || month);
    });
  });

  monthEl.addEventListener('input', function () {
    renderContext(activeTrigger, monthEl.value || '');
  });

  modalEl.addEventListener('show.bs.modal', function (event) {
    const trigger = event.relatedTarget && event.relatedTarget.closest
      ? event.relatedTarget.closest('.js-component-generate-trigger')
      : activeTrigger;
    if (trigger) {
      syncTriggerContext(trigger);
      activeTrigger = trigger;
    }
    const month = inferMonth(activeTrigger);
    if (month) {
      monthEl.value = month;
    }
    renderContext(activeTrigger, monthEl.value || month);
  });

  submitBtn.addEventListener('click', async () => {
    const trigger = activeTrigger || triggers[0];
    const month = String(monthEl.value || '').trim();
    const locType = inferLocationType(trigger);
    const divisionId = inferDivisionId(trigger);
    if (!month) {
      showAlert('warning', 'Pilih bulan sumber terlebih dahulu.');
      return;
    }

    const origHtml = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Generating...';

    try {
      const payload = { month: month, location_type: locType, division_id: divisionId };
      const res = await fetch(<?php echo json_encode($generateUrl); ?>, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify(payload)
      });
      const data = await parseJsonResponse(res);
      if (!res.ok || !data.ok) {
        const sampleHtml = buildNegativeSamples(data?.data?.negative_samples || []);
        showAlert('danger', 'Gagal: ' + escapeHtml(data.message || res.statusText) + sampleHtml);
        submitBtn.disabled = false;
        submitBtn.innerHTML = origHtml;
        return;
      }

      if (trigger) {
        trigger.dataset.month = month;
      }
      showAlert(
        'success',
        'Generate selesai untuk bulan <strong>' + escapeHtml(month) + '</strong>. '
        + 'Opname: <strong>' + escapeHtml(String(data?.data?.generated_rows ?? data?.data?.opname_rows ?? 0)) + '</strong> baris, '
        + 'stok awal: <strong>' + escapeHtml(String(data?.data?.carried_rows ?? data?.data?.opening_rows ?? 0)) + '</strong> baris.'
      );
      const modal = bootstrap.Modal.getInstance(modalEl);
      if (modal) modal.hide();
      window.setTimeout(function () {
        reloadToMonth(trigger, month);
      }, 400);
    } catch (err) {
      showAlert('danger', 'Error: ' + escapeHtml(err.message));
    }

    submitBtn.disabled = false;
    submitBtn.innerHTML = origHtml;
  });
})();
</script>
