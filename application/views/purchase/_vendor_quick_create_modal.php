<?php
$quickVendorModalId = (string)($quick_vendor_modal_id ?? 'quickVendorModal');
$quickVendorStoreUrl = (string)($quick_vendor_store_url ?? site_url('purchase/vendor/quick-store'));
?>

<div class="modal fade" id="<?php echo html_escape($quickVendorModalId); ?>" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content border-0 shadow-sm">
      <div class="modal-header">
        <div>
          <h5 class="modal-title mb-0" id="<?php echo html_escape($quickVendorModalId); ?>Title">Tambah Vendor Baru</h5>
          <small class="text-muted">Vendor baru bisa langsung dipilih tanpa pindah ke halaman master vendor.</small>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form id="<?php echo html_escape($quickVendorModalId); ?>Form" autocomplete="off">
        <div class="modal-body">
          <div id="<?php echo html_escape($quickVendorModalId); ?>Alert"></div>
          <div class="row g-3">
            <div class="col-md-7">
              <label class="form-label">Nama Vendor</label>
              <input type="text" class="form-control" name="vendor_name" required>
            </div>
            <div class="col-md-5">
              <label class="form-label">Kode Vendor</label>
              <input type="text" class="form-control" name="vendor_code" placeholder="Opsional, otomatis bila kosong">
            </div>
            <div class="col-md-6">
              <label class="form-label">Kontak</label>
              <input type="text" class="form-control" name="contact_name" placeholder="PIC vendor">
            </div>
            <div class="col-md-6">
              <label class="form-label">Telepon</label>
              <input type="text" class="form-control" name="phone" placeholder="Nomor telepon">
            </div>
            <div class="col-md-6">
              <label class="form-label">Email</label>
              <input type="email" class="form-control" name="email" placeholder="email@vendor.com">
            </div>
            <div class="col-md-6">
              <label class="form-label">Kota</label>
              <input type="text" class="form-control" name="city" placeholder="Kota vendor">
            </div>
            <div class="col-12">
              <label class="form-label">Alamat</label>
              <textarea class="form-control" name="address" rows="2" placeholder="Alamat opsional"></textarea>
            </div>
            <div class="col-12">
              <label class="form-label">Catatan</label>
              <textarea class="form-control" name="notes" rows="2" placeholder="Catatan internal opsional"></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-primary" id="<?php echo html_escape($quickVendorModalId); ?>Submit">Simpan Vendor</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
(function () {
  'use strict';

  if (window.FinanceQuickVendor) {
    return;
  }

  var modalId = <?php echo json_encode($quickVendorModalId); ?>;
  var storeUrl = <?php echo json_encode($quickVendorStoreUrl); ?>;
  var modalEl = document.getElementById(modalId);
  if (!modalEl) {
    return;
  }

  var modal = (window.bootstrap && window.bootstrap.Modal) ? window.bootstrap.Modal.getOrCreateInstance(modalEl) : null;
  var form = document.getElementById(modalId + 'Form');
  var titleEl = document.getElementById(modalId + 'Title');
  var alertEl = document.getElementById(modalId + 'Alert');
  var submitBtn = document.getElementById(modalId + 'Submit');
  var currentContext = null;

  function showModal() {
    if (modal) {
      modal.show();
      return;
    }
    modalEl.style.display = 'block';
    modalEl.classList.add('show');
    modalEl.removeAttribute('aria-hidden');
    modalEl.setAttribute('aria-modal', 'true');
    modalEl.setAttribute('role', 'dialog');
    document.body.classList.add('modal-open');
    if (!document.querySelector('[data-quick-vendor-backdrop="' + modalId + '"]')) {
      var backdrop = document.createElement('div');
      backdrop.className = 'modal-backdrop fade show';
      backdrop.setAttribute('data-quick-vendor-backdrop', modalId);
      document.body.appendChild(backdrop);
    }
  }

  function hideModal() {
    if (modal) {
      modal.hide();
      return;
    }
    modalEl.classList.remove('show');
    modalEl.style.display = 'none';
    modalEl.setAttribute('aria-hidden', 'true');
    modalEl.removeAttribute('aria-modal');
    modalEl.removeAttribute('role');
    document.body.classList.remove('modal-open');
    var backdrop = document.querySelector('[data-quick-vendor-backdrop="' + modalId + '"]');
    if (backdrop && backdrop.parentNode) {
      backdrop.parentNode.removeChild(backdrop);
    }
  }

  function esc(value) {
    var div = document.createElement('div');
    div.textContent = value == null ? '' : String(value);
    return div.innerHTML;
  }

  function showAlert(type, message) {
    if (!alertEl) {
      return;
    }
    alertEl.innerHTML = '<div class="alert alert-' + type + ' py-2 mb-3">' + esc(message) + '</div>';
  }

  function clearAlert() {
    if (alertEl) {
      alertEl.innerHTML = '';
    }
  }

  function parseJsonResponse(response) {
    return response.text().then(function (text) {
      var parsed = {};
      try {
        parsed = text ? JSON.parse(text) : {};
      } catch (error) {
        parsed = {};
      }
      if (!response.ok) {
        parsed.ok = false;
        parsed.message = parsed.message || ('Request gagal (' + response.status + ')');
      }
      return parsed;
    });
  }

  function vendorLabel(vendor) {
    var code = String(vendor && vendor.vendor_code ? vendor.vendor_code : '').trim();
    var name = String(vendor && vendor.vendor_name ? vendor.vendor_name : '').trim();
    if (code && name) {
      return code + ' - ' + name;
    }
    return name || code || ('Vendor #' + String(vendor && vendor.id ? vendor.id : ''));
  }

  function upsertSelectOption(selectEl, vendor) {
    if (!selectEl || !vendor || !vendor.id) {
      return;
    }

    var value = String(Number(vendor.id || 0));
    var label = vendorLabel(vendor);
    var option = null;
    for (var i = 0; i < selectEl.options.length; i++) {
      if (String(selectEl.options[i].value || '') === value) {
        option = selectEl.options[i];
        break;
      }
    }

    if (!option) {
      option = document.createElement('option');
      option.value = value;
      selectEl.appendChild(option);
    }

    option.textContent = label;
    selectEl.value = value;
  }

  function resetForm() {
    if (form) {
      form.reset();
    }
    clearAlert();
  }

  function open(options) {
    currentContext = options || {};
    resetForm();
    if (titleEl) {
      titleEl.textContent = currentContext.title || 'Tambah Vendor Baru';
    }
    if (currentContext.prefillName && form && form.vendor_name) {
      form.vendor_name.value = String(currentContext.prefillName || '');
    }
    showModal();
    window.setTimeout(function () {
      if (form && form.vendor_name) {
        form.vendor_name.focus();
      }
    }, 120);
  }

  if (form) {
    form.addEventListener('submit', function (event) {
      event.preventDefault();
      clearAlert();

      var payload = {
        vendor_name: form.vendor_name ? form.vendor_name.value : '',
        vendor_code: form.vendor_code ? form.vendor_code.value : '',
        contact_name: form.contact_name ? form.contact_name.value : '',
        phone: form.phone ? form.phone.value : '',
        email: form.email ? form.email.value : '',
        city: form.city ? form.city.value : '',
        address: form.address ? form.address.value : '',
        notes: form.notes ? form.notes.value : ''
      };

      if (!String(payload.vendor_name || '').trim()) {
        showAlert('warning', 'Nama vendor wajib diisi.');
        if (form.vendor_name) {
          form.vendor_name.focus();
        }
        return;
      }

      var originalHtml = submitBtn ? submitBtn.innerHTML : '';
      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Menyimpan...';
      }

      fetch(storeUrl, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify(payload)
      })
        .then(parseJsonResponse)
        .then(function (response) {
          if (!response || response.ok === false) {
            throw new Error(response && response.message ? response.message : 'Gagal menyimpan vendor.');
          }

          var vendor = response.data && response.data.vendor ? response.data.vendor : null;
          if (!vendor || !vendor.id) {
            throw new Error('Vendor berhasil disimpan, tetapi data vendor tidak lengkap.');
          }

          if (currentContext && typeof currentContext.onCreated === 'function') {
            currentContext.onCreated(vendor, response);
          }

          hideModal();
        })
        .catch(function (error) {
          showAlert('danger', error && error.message ? error.message : 'Gagal menyimpan vendor.');
        })
        .finally(function () {
          if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalHtml;
          }
        });
    });
  }

  modalEl.addEventListener('click', function (event) {
    if (event.target === modalEl) {
      hideModal();
    }
  });

  modalEl.querySelectorAll('[data-bs-dismiss="modal"]').forEach(function (button) {
    button.addEventListener('click', function () {
      hideModal();
    });
  });

  window.FinanceQuickVendor = {
    open: open,
    close: hideModal,
    upsertSelectOption: upsertSelectOption,
    vendorLabel: vendorLabel
  };
})();
</script>