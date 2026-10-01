<?php $pickerSearchUrl = site_url('production/component-picker-search'); ?>
<style>
  .ajax-picker-selected-badge {
    display: flex;
    align-items: center;
    gap: .4rem;
    margin-top: .35rem;
    padding: .35rem .55rem;
    border: 1px solid #d8c7a6;
    border-radius: .7rem;
    background: #fff8e7;
    color: #5b4520;
    font-size: .76rem;
    line-height: 1.2;
  }
  .ajax-picker-selected-badge .badge {
    background: #8a5a12;
    color: #fff;
    font-size: .65rem;
    letter-spacing: .02em;
  }
  .ajax-picker-selected-badge .ajax-picker-selected-text {
    font-weight: 600;
  }
  .ajax-picker-selected-badge .ajax-picker-selected-subtext {
    color: #7a6849;
  }
  .ajax-picker-option-active {
    background: #f4e3bf;
    border-color: #ead3a0;
  }
</style>
<script>
window.ProductionAjaxPicker = window.ProductionAjaxPicker || (function () {
  const searchUrl = '<?php echo $pickerSearchUrl; ?>';
  let overlay = null;
  let activeRequestId = 0;
  let activeRows = [];
  let activeInput = null;
  let activeConfig = null;
  let activeIndex = -1;

  function escapeHtml(value) {
    return String(value ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function ensureOverlay() {
    if (overlay) {
      return overlay;
    }
    overlay = document.createElement('div');
    overlay.className = 'list-group d-none border rounded-2 bg-white shadow-sm';
    overlay.style.position = 'fixed';
    overlay.style.zIndex = '4000';
    overlay.style.maxHeight = '260px';
    overlay.style.overflow = 'auto';
    overlay.style.minWidth = '240px';
    document.body.appendChild(overlay);

    document.addEventListener('click', function (event) {
      if (!overlay) {
        return;
      }
      const pickerInput = event.target.closest('[data-ajax-picker-bound="1"]');
      if (pickerInput || overlay.contains(event.target)) {
        return;
      }
      hide();
    });
    window.addEventListener('resize', hide);
    window.addEventListener('scroll', hide, true);
    return overlay;
  }

  function hide() {
    if (!overlay) {
      return;
    }
    overlay.classList.add('d-none');
    overlay.innerHTML = '';
    overlay.removeAttribute('data-owner-id');
    activeRows = [];
    activeInput = null;
    activeConfig = null;
    activeIndex = -1;
  }

  function ensureBadge(input) {
    if (!input || !input.parentNode) {
      return null;
    }
    let badge = input.parentNode.querySelector('.ajax-picker-selected-badge[data-owner-id="' + (input.dataset.ajaxPickerId || '') + '"]');
    if (badge) {
      return badge;
    }
    badge = document.createElement('div');
    badge.className = 'ajax-picker-selected-badge d-none';
    badge.setAttribute('data-owner-id', input.dataset.ajaxPickerId || '');
    input.insertAdjacentElement('afterend', badge);
    return badge;
  }

  function renderSelectedBadge(input, config, label, subLabel) {
    const badge = ensureBadge(input);
    if (!badge) {
      return;
    }
    const safeLabel = String(label || '').trim();
    const safeSubLabel = String(subLabel || '').trim();
    if (safeLabel === '') {
      badge.classList.add('d-none');
      badge.innerHTML = '';
      delete input.dataset.selectedLabel;
      delete input.dataset.selectedSubLabel;
      return;
    }
    input.dataset.selectedLabel = safeLabel;
    input.dataset.selectedSubLabel = safeSubLabel;
    badge.innerHTML = '<span class="badge">Terpilih</span>'
      + '<span class="ajax-picker-selected-text">' + escapeHtml(safeLabel) + '</span>'
      + (safeSubLabel ? '<span class="ajax-picker-selected-subtext">' + escapeHtml(safeSubLabel) + '</span>' : '');
    badge.classList.remove('d-none');
  }

  function optionLabel(row, config) {
    return typeof config.renderLabel === 'function'
      ? config.renderLabel(row)
      : [row.code || '', row.name || ''].filter(Boolean).join(' - ');
  }

  function optionSubLabel(row, config) {
    return typeof config.renderSubLabel === 'function'
      ? config.renderSubLabel(row)
      : [row.entity_type || '', row.uom_code || ''].filter(Boolean).join(' | ');
  }

  function syncActiveOption() {
    if (!overlay) {
      return;
    }
    Array.from(overlay.querySelectorAll('[data-result-index]')).forEach(function (button) {
      const isActive = Number(button.dataset.resultIndex || -1) === activeIndex;
      button.classList.toggle('ajax-picker-option-active', isActive);
      if (isActive) {
        button.scrollIntoView({block: 'nearest'});
      }
    });
  }

  function selectRow(index) {
    if (!activeInput || !activeConfig || index < 0 || index >= activeRows.length) {
      return;
    }
    const row = activeRows[index];
    const label = optionLabel(row, activeConfig);
    const subLabel = optionSubLabel(row, activeConfig);
    activeInput.value = label;
    if (typeof activeConfig.onSelect === 'function') {
      activeConfig.onSelect(row, activeInput);
    }
    renderSelectedBadge(activeInput, activeConfig, label, subLabel);
    hide();
  }

  function placeOverlay(input) {
    const el = ensureOverlay();
    const rect = input.getBoundingClientRect();
    el.style.left = rect.left + 'px';
    el.style.top = (rect.bottom + 2) + 'px';
    el.style.width = Math.max(rect.width, 260) + 'px';
    el.setAttribute('data-owner-id', input.dataset.ajaxPickerId || '');
  }

  async function requestRows(params) {
    const response = await fetch(searchUrl + '?' + new URLSearchParams(params).toString(), {
      headers: {'X-Requested-With': 'XMLHttpRequest'}
    });
    const text = await response.text();
    let json;
    try {
      json = JSON.parse(text);
    } catch (error) {
      return [];
    }
    if (!response.ok || !json.ok) {
      return [];
    }
    return Array.isArray(json.rows) ? json.rows : [];
  }

  function renderResults(input, rows, config) {
    const el = ensureOverlay();
    activeRows = rows.slice();
    activeInput = input;
    activeConfig = config;
    activeIndex = rows.length ? 0 : -1;
    if (!rows.length) {
      el.innerHTML = '<div class="list-group-item text-muted small">Tidak ada hasil.</div>';
      placeOverlay(input);
      el.classList.remove('d-none');
      return;
    }

    el.innerHTML = rows.map(function (row, index) {
      const label = optionLabel(row, config);
      const subLabel = optionSubLabel(row, config);
      return '<button type="button" class="list-group-item list-group-item-action text-start" data-result-index="' + index + '">'
        + '<div class="fw-semibold">' + escapeHtml(label) + '</div>'
        + (subLabel ? '<div class="small text-muted">' + escapeHtml(subLabel) + '</div>' : '')
        + '</button>';
    }).join('');

    Array.from(el.querySelectorAll('[data-result-index]')).forEach(function (button) {
      button.addEventListener('click', function () {
        selectRow(Number(button.dataset.resultIndex || -1));
      });
    });

    placeOverlay(input);
    el.classList.remove('d-none');
    syncActiveOption();
  }

  function bind(input, config) {
    if (!input || input.dataset.ajaxPickerBound === '1') {
      return;
    }
    ensureOverlay();
    input.dataset.ajaxPickerBound = '1';
    input.dataset.ajaxPickerId = input.dataset.ajaxPickerId || ('picker-' + Math.random().toString(36).slice(2));

    const minLength = Number(config.minLength || 2);

    if (String(input.dataset.selectedLabel || '').trim() !== '') {
      renderSelectedBadge(input, config, input.dataset.selectedLabel, input.dataset.selectedSubLabel || '');
    }

    input.addEventListener('input', async function () {
      renderSelectedBadge(input, config, '', '');
      if (typeof config.onType === 'function') {
        config.onType(input.value, input);
      }
      const keyword = String(input.value || '').trim();
      if (keyword.length < minLength) {
        hide();
        return;
      }

      const requestId = ++activeRequestId;
      const extraParams = typeof config.params === 'function' ? (config.params(input) || {}) : (config.params || {});
      const rows = await requestRows(Object.assign({
        entity: config.entity || 'COMPONENT',
        q: keyword,
        limit: String(config.limit || 20)
      }, extraParams));
      if (requestId !== activeRequestId) {
        return;
      }
      renderResults(input, rows, config);
    });

    input.addEventListener('focus', function () {
      const selectedLabel = String(input.dataset.selectedLabel || '').trim();
      if (selectedLabel !== '') {
        renderSelectedBadge(input, config, selectedLabel, input.dataset.selectedSubLabel || '');
      }
      const keyword = String(input.value || '').trim();
      if (keyword.length >= minLength && (selectedLabel === '' || keyword !== selectedLabel)) {
        input.dispatchEvent(new Event('input', {bubbles: true}));
      }
    });

    input.addEventListener('keydown', function (event) {
      const ownsVisibleOverlay = overlay && !overlay.classList.contains('d-none') && overlay.getAttribute('data-owner-id') === (input.dataset.ajaxPickerId || '');
      if (ownsVisibleOverlay && event.key === 'ArrowDown' && activeRows.length) {
        event.preventDefault();
        activeIndex = activeIndex >= activeRows.length - 1 ? 0 : activeIndex + 1;
        syncActiveOption();
        return;
      }
      if (ownsVisibleOverlay && event.key === 'ArrowUp' && activeRows.length) {
        event.preventDefault();
        activeIndex = activeIndex <= 0 ? activeRows.length - 1 : activeIndex - 1;
        syncActiveOption();
        return;
      }
      if (ownsVisibleOverlay && event.key === 'Enter' && activeRows.length) {
        event.preventDefault();
        selectRow(activeIndex >= 0 ? activeIndex : 0);
        return;
      }
      if (event.key === 'Escape') {
        hide();
      }
    });
  }

  return {
    bind: bind,
    close: hide
  };
})();
</script>