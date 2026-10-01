/**
 * Finance App — app.js
 * Sidebar toggle dihandle oleh Materio main.js
 * File ini: AJAX favorit, auto-dismiss alerts, DataTables init
 */
'use strict';

$(function () {
    function loadStylesheetOnce(href) {
        return new Promise(function (resolve, reject) {
            var existing = document.querySelector('link[data-dynamic-href="' + href + '"]');
            if (existing) {
                resolve();
                return;
            }

            var link = document.createElement('link');
            link.rel = 'stylesheet';
            link.href = href;
            link.setAttribute('data-dynamic-href', href);
            link.onload = function () { resolve(); };
            link.onerror = function () { reject(new Error('Failed to load stylesheet: ' + href)); };
            document.head.appendChild(link);
        });
    }

    function loadScriptOnce(src) {
        return new Promise(function (resolve, reject) {
            var existing = document.querySelector('script[data-dynamic-src="' + src + '"]');
            if (existing) {
                if (existing.getAttribute('data-loaded') === '1') {
                    resolve();
                    return;
                }
                existing.addEventListener('load', function () { resolve(); }, { once: true });
                existing.addEventListener('error', function () { reject(new Error('Failed to load script: ' + src)); }, { once: true });
                return;
            }

            var script = document.createElement('script');
            script.src = src;
            script.async = false;
            script.setAttribute('data-dynamic-src', src);
            script.onload = function () {
                script.setAttribute('data-loaded', '1');
                resolve();
            };
            script.onerror = function () { reject(new Error('Failed to load script: ' + src)); };
            document.body.appendChild(script);
        });
    }

    function initTooltips() {
        if (typeof bootstrap === 'undefined' || !bootstrap.Tooltip) return;
        document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
            bootstrap.Tooltip.getOrCreateInstance(el);
        });
    }

    function applyPageTitleIcon() {
        var wrapper = document.querySelector('.layout-wrapper[data-active-menu]');
        var activeMenu = wrapper ? (wrapper.getAttribute('data-active-menu') || '') : '';
        var heading = document.querySelector('.container-xxl h4, .container-xxl h5');
        if (!heading) return;
        if (heading.querySelector('.page-title-icon')) return;

        var iconClass = 'ri-file-list-3-line';
        if (/dashboard/.test(activeMenu)) iconClass = 'ri-dashboard-line';
        else if (/master/.test(activeMenu)) iconClass = 'ri-database-2-line';
        else if (/role/.test(activeMenu)) iconClass = 'ri-shield-keyhole-line';
        else if (/user/.test(activeMenu)) iconClass = 'ri-user-settings-line';
        else if (/relation/.test(activeMenu)) iconClass = 'ri-links-line';

        var icon = document.createElement('i');
        icon.className = 'ri ' + iconClass + ' page-title-icon';
        heading.insertBefore(icon, heading.firstChild);
    }

    function formatTwoDecimals() {
        document.querySelectorAll('[data-decimal="1"]').forEach(function (el) {
            var raw = (el.getAttribute('data-value') || el.textContent || '').trim();
            if (raw === '') return;
            var normalized = raw.replace(/,/g, '');
            var num = Number(normalized);
            if (!Number.isFinite(num)) return;
            el.textContent = num.toLocaleString('id-ID', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
            el.classList.add('number-cell');
        });
    }

    function harmonizeLegacyTables() {
        document.querySelectorAll('table').forEach(function (table) {
            var headerCells = Array.prototype.slice.call(table.querySelectorAll('thead th'));
            if (!headerCells.length) return;

            var actionIndex = -1;
            headerCells.forEach(function (th, idx) {
                var txt = (th.textContent || '').trim().toLowerCase();
                if (txt === 'aksi' || txt === 'action') {
                    actionIndex = idx;
                    th.classList.add('action-cell');
                }
            });

            if (actionIndex < 0) return;

            table.querySelectorAll('tbody tr').forEach(function (row) {
                var cells = row.querySelectorAll('td');
                if (!cells.length || !cells[actionIndex]) return;

                var actionCell = cells[actionIndex];
                actionCell.classList.add('action-cell');

                actionCell.querySelectorAll('a.btn, button.btn').forEach(function (btn) {
                    var txt = (btn.textContent || '').replace(/\s+/g, ' ').trim();
                    var hasOnlyIcon = txt === '';
                    if (hasOnlyIcon) {
                        btn.classList.add('action-icon-btn');
                        btn.classList.remove('action-text-btn');
                    } else {
                        btn.classList.remove('action-icon-btn');
                        btn.classList.add('action-text-btn');
                    }
                    if (!btn.classList.contains('btn-sm')) {
                        btn.classList.add('btn-sm');
                    }
                });
            });
        });
    }

    function applyAutoTableFit() {
        var nowrapPatterns = [
            /\bref\b/,
            /referensi/,
            /\bno\b/,
            /nomor/,
            /kode/,
            /\bnip\b/,
            /tanggal/,
            /\btgl\b/,
            /status/,
            /aksi/,
            /shift/,
            /divisi/,
            /\bjam\b/
        ];
        var notesPatterns = [/catatan/, /notes?/, /alasan/, /keterangan/, /deskripsi/];

        document.querySelectorAll('table').forEach(function (table) {
            var headerCells = Array.prototype.slice.call(table.querySelectorAll('thead th'));
            if (!headerCells.length) return;

            table.classList.add('table-autofit');

            var colRules = [];
            headerCells.forEach(function (th, idx) {
                var label = (th.textContent || '').toLowerCase().trim();
                var isNotes = notesPatterns.some(function (pattern) { return pattern.test(label); });
                var isNoWrap = nowrapPatterns.some(function (pattern) { return pattern.test(label); });

                if (isNotes) {
                    th.classList.add('col-notes');
                    colRules.push({ idx: idx, className: 'col-notes' });
                    return;
                }
                if (isNoWrap) {
                    th.classList.add('cell-nowrap');
                    colRules.push({ idx: idx, className: 'cell-nowrap' });
                }
            });

            if (!colRules.length) return;
            table.querySelectorAll('tbody tr').forEach(function (row) {
                var cells = row.querySelectorAll('td');
                if (!cells.length) return;
                colRules.forEach(function (rule) {
                    if (cells[rule.idx]) {
                        cells[rule.idx].classList.add(rule.className);
                    }
                });
            });
        });
    }

    function reinforceSidebarActivePath() {
        var wrapper = document.querySelector('.layout-wrapper[data-current-url]');
        var currentUrl = wrapper ? (wrapper.getAttribute('data-current-url') || '').replace(/^\/+|\/+$/g, '') : '';

        if (currentUrl) {
            function normalizePath(urlText) {
                if (!urlText) return '';
                try {
                    var u = new URL(urlText, window.location.origin);
                    var p = (u.pathname || '').replace(/^\/+|\/+$/g, '');
                    if (p && currentUrl && !currentUrl.startsWith(p) && p.indexOf('/') > -1) {
                        var parts = p.split('/');
                        if (parts.length > 1) {
                            p = parts.slice(1).join('/');
                        }
                    }
                    return p;
                } catch (e) {
                    return String(urlText).replace(/^\/+|\/+$/g, '');
                }
            }

            document.querySelectorAll('.layout-menu .menu-item > .menu-link[href]').forEach(function (link) {
                var href = normalizePath(link.getAttribute('href') || '');
                if (!href) return;

                if (currentUrl === href || currentUrl.indexOf(href + '/') === 0) {
                    var li = link.closest('.menu-item');
                    if (li) {
                        li.classList.add('active');
                    }
                }
            });
        }

        var activeLeaf = document.querySelector('.layout-menu .menu-item.active:last-of-type') || document.querySelector('.layout-menu .menu-item.active');
        if (!activeLeaf) return;

        var parent = activeLeaf.parentElement;
        while (parent) {
            if (parent.classList && parent.classList.contains('menu-sub')) {
                var owner = parent.closest('.menu-item');
                if (owner) {
                    owner.classList.add('open', 'active', 'current-path');
                    parent = owner.parentElement;
                    continue;
                }
            }
            parent = parent.parentElement;
        }

        var menuScroll = document.querySelector('.layout-menu .menu-inner');
        var activeLink = activeLeaf.querySelector('.menu-link');
        if (menuScroll && activeLink) {
            var top = activeLink.offsetTop - menuScroll.clientHeight * 0.35;
            menuScroll.scrollTop = Math.max(0, top);
        }
    }

    function initDataTables() {
        if (typeof $.fn.DataTable === 'undefined' || !$('table.datatable').length) {
            return;
        }

        $('table.datatable').each(function () {
            if ($.fn.dataTable.isDataTable(this)) {
                return;
            }

            $(this).DataTable({
                language: {
                    search: 'Cari:',
                    lengthMenu: 'Tampilkan _MENU_ data',
                    info: 'Menampilkan _START_ sampai _END_ dari _TOTAL_ data',
                    infoEmpty: 'Menampilkan 0 sampai 0 dari 0 data',
                    infoFiltered: '(disaring dari _MAX_ total data)',
                    loadingRecords: 'Memuat...',
                    processing: 'Memproses...',
                    zeroRecords: 'Tidak ada data yang cocok',
                    emptyTable: 'Belum ada data',
                    paginate: {
                        first: 'Pertama',
                        last: 'Terakhir',
                        next: 'Berikutnya',
                        previous: 'Sebelumnya'
                    }
                },
                pageLength: 25,
                responsive: true,
                dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>t<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
            });
        });
    }

    function bootDataTablesIfNeeded() {
        if (!$('table.datatable').length) {
            return;
        }

        if (typeof $.fn.DataTable !== 'undefined') {
            initDataTables();
            return;
        }

        loadStylesheetOnce(BASE_URL + 'assets/vendor/datatables/dataTables.bootstrap4.min.css')
            .then(function () {
                return loadScriptOnce(BASE_URL + 'assets/vendor/datatables/jquery.dataTables.min.js');
            })
            .then(function () {
                return loadScriptOnce(BASE_URL + 'assets/vendor/datatables/dataTables.bootstrap4.min.js');
            })
            .then(function () {
                initDataTables();
            })
            .catch(function (error) {
                if (window.console && typeof window.console.error === 'function') {
                    console.error(error);
                }
            });
    }

    function ensureUiModal() {
        var existing = document.getElementById('financeUiModal');
        if (existing) {
            return existing;
        }
        var wrapper = document.createElement('div');
        wrapper.innerHTML = ''
            + '<div class="modal fade finance-ui-modal" id="financeUiModal" tabindex="-1" aria-hidden="true">'
            + '  <div class="modal-dialog modal-dialog-centered">'
            + '    <div class="modal-content">'
            + '      <div class="modal-header">'
            + '        <h5 class="modal-title" id="financeUiModalTitle">Konfirmasi</h5>'
            + '        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>'
            + '      </div>'
            + '      <div class="modal-body">'
            + '        <p class="mb-0 finance-ui-modal-message" id="financeUiModalMessage"></p>'
            + '        <div class="mt-3 d-none" id="financeUiModalPromptWrap">'
            + '          <input type="text" class="form-control" id="financeUiModalPromptInput" autocomplete="off">'
            + '        </div>'
            + '      </div>'
            + '      <div class="modal-footer">'
            + '        <button type="button" class="btn btn-outline-secondary" id="financeUiModalCancelBtn">Batal</button>'
            + '        <button type="button" class="btn btn-primary" id="financeUiModalOkBtn">Lanjut</button>'
            + '      </div>'
            + '    </div>'
            + '  </div>'
            + '</div>';
        document.body.appendChild(wrapper.firstChild);
        return document.getElementById('financeUiModal');
    }

    function openUiModal(options) {
        return new Promise(function (resolve) {
            var modalEl = ensureUiModal();
            var modal = bootstrap.Modal.getOrCreateInstance(modalEl, { backdrop: 'static' });
            var titleEl = modalEl.querySelector('#financeUiModalTitle');
            var msgEl = modalEl.querySelector('#financeUiModalMessage');
            var promptWrapEl = modalEl.querySelector('#financeUiModalPromptWrap');
            var promptInputEl = modalEl.querySelector('#financeUiModalPromptInput');
            var cancelBtn = modalEl.querySelector('#financeUiModalCancelBtn');
            var okBtn = modalEl.querySelector('#financeUiModalOkBtn');

            var type = String(options.type || 'confirm');
            titleEl.textContent = options.title || (type === 'alert' ? 'Informasi' : 'Konfirmasi');
            msgEl.textContent = options.message || '';
            okBtn.textContent = options.okText || (type === 'alert' ? 'Tutup' : 'Lanjut');
            cancelBtn.textContent = options.cancelText || 'Batal';

            var promptMode = type === 'prompt';
            promptWrapEl.classList.toggle('d-none', !promptMode);
            promptInputEl.value = options.defaultValue || '';

            if (type === 'alert') {
                cancelBtn.classList.add('d-none');
            } else {
                cancelBtn.classList.remove('d-none');
            }

            var settled = false;
            var cleanup = function () {
                okBtn.removeEventListener('click', onOk);
                cancelBtn.removeEventListener('click', onCancel);
                modalEl.removeEventListener('hidden.bs.modal', onHide);
            };
            var finish = function (value) {
                if (settled) return;
                settled = true;
                cleanup();
                resolve(value);
            };
            var onOk = function () {
                if (promptMode) {
                    finish(promptInputEl.value);
                } else if (type === 'alert') {
                    finish(undefined);
                } else {
                    finish(true);
                }
                modal.hide();
            };
            var onCancel = function () {
                finish(type === 'prompt' ? null : false);
                modal.hide();
            };
            var onHide = function () {
                if (!settled) {
                    finish(type === 'prompt' ? null : false);
                }
            };

            okBtn.addEventListener('click', onOk);
            cancelBtn.addEventListener('click', onCancel);
            modalEl.addEventListener('hidden.bs.modal', onHide);

            modal.show();
            if (promptMode) {
                setTimeout(function () {
                    promptInputEl.focus();
                    promptInputEl.select();
                }, 120);
            }
        });
    }

    function initUiDialogs() {
        window.FinanceUI = window.FinanceUI || {};
        if (!window.__financeNativeAlert) {
            window.__financeNativeAlert = window.alert.bind(window);
        }
        window.FinanceUI.alert = function (message, opts) {
            opts = opts || {};
            return openUiModal({
                type: 'alert',
                title: opts.title || 'Informasi',
                message: String(message || ''),
                okText: opts.okText || 'Tutup'
            });
        };
        window.FinanceUI.confirm = function (message, opts) {
            opts = opts || {};
            return openUiModal({
                type: 'confirm',
                title: opts.title || 'Konfirmasi',
                message: String(message || ''),
                okText: opts.okText || 'Lanjut',
                cancelText: opts.cancelText || 'Batal'
            });
        };
        window.FinanceUI.prompt = function (message, defaultValue, opts) {
            opts = opts || {};
            return openUiModal({
                type: 'prompt',
                title: opts.title || 'Input',
                message: String(message || ''),
                defaultValue: defaultValue || '',
                okText: opts.okText || 'Simpan',
                cancelText: opts.cancelText || 'Batal'
            });
        };
        window.alert = function (message) {
            window.FinanceUI.alert(String(message || ''));
        };
    }

    function ensureGlobalNotifyUi() {
        var existing = document.getElementById('financeGlobalNotifyRoot');
        if (existing) {
            return existing;
        }

        var style = document.createElement('style');
        style.setAttribute('data-finance-global-notify-style', '1');
        style.textContent = ''
            + '.finance-global-notify-stack{position:fixed;top:84px;right:18px;z-index:2000;display:grid;gap:.6rem;width:min(360px,calc(100vw - 24px));pointer-events:none;}'
            + '.finance-global-notify-toast{pointer-events:auto;display:flex;align-items:flex-start;gap:.7rem;padding:.9rem 1rem;border-radius:18px;box-shadow:0 18px 38px rgba(17,27,46,.18);background:linear-gradient(135deg,#17263f,#28456e);color:#fff;opacity:0;transform:translateY(-6px);transition:opacity .18s ease, transform .18s ease;}'
            + '.finance-global-notify-toast.is-visible{opacity:1;transform:translateY(0);}'
            + '.finance-global-notify-toast-title{font-size:.76rem;font-weight:800;letter-spacing:.08em;text-transform:uppercase;opacity:.8;margin-bottom:.15rem;}'
            + '.finance-global-notify-toast-body{font-size:.88rem;font-weight:600;line-height:1.4;}'
            + '.finance-global-notify-toast-icon{flex:0 0 auto;width:2rem;height:2rem;border-radius:999px;background:rgba(255,255,255,.14);display:inline-flex;align-items:center;justify-content:center;font-size:1rem;}'
            + '.finance-global-notify-flare{position:fixed;inset:0;z-index:1999;pointer-events:none;border:0 solid rgba(220,38,38,.38);box-shadow:inset 0 0 0 0 rgba(220,38,38,.18);animation:financeNotifyFlare .9s ease-out 1;}'
            + '@keyframes financeNotifyFlare{0%{border-width:0;box-shadow:inset 0 0 0 0 rgba(220,38,38,.18);}32%{border-width:10px;box-shadow:inset 0 0 0 999px rgba(220,38,38,.035);}100%{border-width:0;box-shadow:inset 0 0 0 0 rgba(220,38,38,0);}}';
        document.head.appendChild(style);

        var root = document.createElement('div');
        root.id = 'financeGlobalNotifyRoot';
        root.className = 'finance-global-notify-stack';
        root.setAttribute('aria-live', 'polite');
        root.setAttribute('aria-atomic', 'true');
        document.body.appendChild(root);
        return root;
    }

    function showGlobalNotifyToast(message, title) {
        var root = ensureGlobalNotifyUi();
        if (!root) return;

        var toast = document.createElement('div');
        toast.className = 'finance-global-notify-toast';
        toast.innerHTML = ''
            + '<div class="finance-global-notify-toast-icon"><i class="ri-volume-up-line"></i></div>'
            + '<div>'
            + '  <div class="finance-global-notify-toast-title"></div>'
            + '  <div class="finance-global-notify-toast-body"></div>'
            + '</div>';
        var titleEl = toast.querySelector('.finance-global-notify-toast-title');
        var bodyEl = toast.querySelector('.finance-global-notify-toast-body');
        if (titleEl) {
            titleEl.textContent = String(title || 'Notifikasi');
        }
        if (bodyEl) {
            bodyEl.textContent = String(message || '');
        }
        root.appendChild(toast);
        var flare = document.createElement('div');
        flare.className = 'finance-global-notify-flare';
        document.body.appendChild(flare);
        window.setTimeout(function () {
            if (flare.parentNode) {
                flare.parentNode.removeChild(flare);
            }
        }, 950);

        window.requestAnimationFrame(function () {
            toast.classList.add('is-visible');
        });

        window.setTimeout(function () {
            toast.classList.remove('is-visible');
            window.setTimeout(function () {
                if (toast.parentNode) {
                    toast.parentNode.removeChild(toast);
                }
            }, 220);
        }, 3000);
    }

    function initGlobalSelfOrderNotifier() {
        var rootCfg = window.FINANCE_GLOBAL_NOTIFIER_CONFIG || {};
        var configs = Array.isArray(rootCfg.notifiers) ? rootCfg.notifiers : [rootCfg];
        configs.forEach(function (rawCfg) {
            var cfg = Object.assign({}, rawCfg || {});
            if (!cfg.sound_url && rootCfg.sound_url) {
                cfg.sound_url = rootCfg.sound_url;
            }
            startGlobalOrderNotifier(cfg);
        });
    }

    function startGlobalOrderNotifier(cfg) {
        if (!cfg || !cfg.enabled || !cfg.endpoint) {
            return;
        }
        var currentPath = String(cfg.current_path || '').replace(/^\/+|\/+$/g, '');
        var skipPaths = Array.isArray(cfg.skip_paths) ? cfg.skip_paths.map(function (path) {
            return String(path || '').replace(/^\/+|\/+$/g, '');
        }) : [];
        if (skipPaths.indexOf(currentPath) >= 0) {
            return;
        }

        var pollMs = Math.max(5000, Number(cfg.poll_ms || 12000));
        var baselineReady = false;
        var pollBusy = false;
        var seenOrderIds = {};
        var verificationReady = {};
        var audioReady = false;
        var audio = null;

        function unlockAudio() {
            audioReady = true;
            if (!cfg.sound_url) {
                return;
            }
            try {
                if (!audio) {
                    audio = new Audio(cfg.sound_url);
                    audio.preload = 'auto';
                }
                var playPromise = audio.play();
                if (playPromise && typeof playPromise.then === 'function') {
                    playPromise.then(function () {
                        audio.pause();
                        audio.currentTime = 0;
                    }).catch(function () {});
                } else {
                    audio.pause();
                    audio.currentTime = 0;
                }
            } catch (error) {}
        }

        function playAudio() {
            if (!audioReady || !cfg.sound_url) {
                return;
            }
            try {
                if (!audio) {
                    audio = new Audio(cfg.sound_url);
                    audio.preload = 'auto';
                }
                audio.pause();
                audio.currentTime = 0;
                var playPromise = audio.play();
                if (playPromise && typeof playPromise.catch === 'function') {
                    playPromise.catch(function () {});
                }
            } catch (error) {}
        }

        async function fetchRows() {
            var qs = new URLSearchParams();
            qs.set('q', '');
            qs.set('outlet_id', '0');
            qs.set('payment_tab', 'ALL');
            qs.set('status_tab', 'ALL');
            qs.set('date_from', '');
            qs.set('date_to', '');
            qs.set('page', '1');
            qs.set('limit', '20');

            var response = await fetch(String(cfg.endpoint) + '?' + qs.toString(), {
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }
            var json = await response.json();
            if (!json || json.ok === false) {
                throw new Error(json && json.message ? json.message : 'Gagal memuat notifikasi order.');
            }
            return Array.isArray(json.rows) ? json.rows : [];
        }

        async function poll() {
            if (pollBusy) {
                return;
            }
            pollBusy = true;
            try {
                var rows = await fetchRows();
                var newRows = [];
                rows.forEach(function (row) {
                    var orderId = Number(row && row.id ? row.id : 0);
                    if (orderId <= 0) {
                        return;
                    }
                    var ready = Number(row.can_verify || 0) === 1;
                    if (!seenOrderIds[orderId] || (ready && !verificationReady[orderId])) {
                        if (baselineReady || ready) {
                            newRows.push(row);
                        }
                        seenOrderIds[orderId] = true;
                    }
                    verificationReady[orderId] = ready;
                });

                if (!baselineReady) {
                    baselineReady = true;
                }

                if (newRows.length) {
                    var newest = newRows[0] || {};
                    var orderNo = String(newest.order_no || 'ORDER');
                    var tableNo = String(newest.table_no || '').trim();
                    var message = (Number(newest.can_verify || 0) === 1 ? 'Order perlu verifikasi: ' : 'Order baru masuk: ') + orderNo + (tableNo ? ' | ' + tableNo : '');
                    playAudio();
                    showGlobalNotifyToast(message, cfg.title || 'Order');
                }
            } catch (error) {
                // Fail silently so global polling never disturbs the current page.
            } finally {
                pollBusy = false;
            }
        }

        document.addEventListener('pointerdown', unlockAudio, { once: true, passive: true });
        document.addEventListener('keydown', unlockAudio, { once: true });
        poll();
        window.setInterval(poll, pollMs);
    }

    function extractConfirmMessage(handlerValue) {
        if (!handlerValue) return '';
        var regex = /^\s*return\s+confirm\((['"])([\s\S]*?)\1\)\s*;?\s*$/i;
        var match = String(handlerValue).match(regex);
        return match ? match[2] : '';
    }

    function normalizeInlineConfirmToDataAttr() {
        document.querySelectorAll('[onsubmit]').forEach(function (el) {
            var handler = el.getAttribute('onsubmit') || '';
            var message = extractConfirmMessage(handler);
            if (!message) return;
            el.setAttribute('data-confirm', message);
            el.removeAttribute('onsubmit');
        });
        document.querySelectorAll('[onclick]').forEach(function (el) {
            var handler = el.getAttribute('onclick') || '';
            var message = extractConfirmMessage(handler);
            if (!message) return;
            el.setAttribute('data-confirm', message);
            el.setAttribute('data-confirm-click', '1');
            el.removeAttribute('onclick');
        });
    }

    function isIconOnlyButton(button) {
        if (!button) return false;
        if (button.classList.contains('action-icon-btn') || button.classList.contains('component-action-btn')) {
            return true;
        }

        var hasIcon = !!button.querySelector('i, [class^="ri-"], [class*=" ri-"]');
        var text = String(button.textContent || '').replace(/\s+/g, ' ').trim();
        return hasIcon && text === '';
    }

    function setButtonLoading(button, label) {
        if (!button || button.dataset.loadingActive === '1') return;

        var loadingLabel = String(label || button.getAttribute('data-loading-label') || button.getAttribute('aria-label') || button.getAttribute('title') || 'Memproses...');
        var isInput = button.tagName === 'INPUT';
        var iconOnly = !isInput && isIconOnlyButton(button);

        button.dataset.loadingActive = '1';
        button.dataset.originalTitle = button.getAttribute('title') || '';
        button.dataset.originalAriaLabel = button.getAttribute('aria-label') || '';
        button.disabled = true;
        button.classList.add('is-loading');
        button.setAttribute('aria-busy', 'true');

        if (isInput) {
            button.dataset.originalValue = button.value || '';
            button.value = loadingLabel;
            return;
        }

        button.dataset.originalHtml = button.innerHTML;
        button.classList.toggle('is-loading-icon', iconOnly);
        button.replaceChildren();

        var spinner = document.createElement('span');
        spinner.className = 'spinner-border spinner-border-sm';
        spinner.setAttribute('role', 'status');
        spinner.setAttribute('aria-hidden', 'true');
        if (!iconOnly) {
            spinner.classList.add('me-1');
        }
        button.appendChild(spinner);

        if (!iconOnly) {
            button.appendChild(document.createTextNode(loadingLabel));
        }

        button.setAttribute('title', loadingLabel);
        button.setAttribute('aria-label', loadingLabel);
    }

    function clearButtonLoading(button) {
        if (!button || button.dataset.loadingActive !== '1') return;
        button.disabled = false;
        button.classList.remove('is-loading');
        button.classList.remove('is-loading-icon');
        button.removeAttribute('aria-busy');
        if (button.tagName === 'INPUT' && Object.prototype.hasOwnProperty.call(button.dataset, 'originalValue')) {
            button.value = button.dataset.originalValue;
            delete button.dataset.originalValue;
        } else if (Object.prototype.hasOwnProperty.call(button.dataset, 'originalHtml')) {
            button.innerHTML = button.dataset.originalHtml;
            delete button.dataset.originalHtml;
        }
        if (Object.prototype.hasOwnProperty.call(button.dataset, 'originalTitle')) {
            if (button.dataset.originalTitle !== '') {
                button.setAttribute('title', button.dataset.originalTitle);
            } else {
                button.removeAttribute('title');
            }
            delete button.dataset.originalTitle;
        }
        if (Object.prototype.hasOwnProperty.call(button.dataset, 'originalAriaLabel')) {
            if (button.dataset.originalAriaLabel !== '') {
                button.setAttribute('aria-label', button.dataset.originalAriaLabel);
            } else {
                button.removeAttribute('aria-label');
            }
            delete button.dataset.originalAriaLabel;
        }
        delete button.dataset.loadingActive;
    }

    function bindUiConfirmAndLoading() {
        document.addEventListener('click', function (event) {
            var target = event.target.closest('[data-confirm][data-confirm-click]');
            if (!target) return;
            if (target.dataset.confirmed === '1') return;
            event.preventDefault();
            var message = target.getAttribute('data-confirm') || 'Lanjutkan aksi ini?';
            window.FinanceUI.confirm(message).then(function (ok) {
                if (!ok) return;
                target.dataset.confirmed = '1';
                setButtonLoading(target, target.getAttribute('data-loading-label') || 'Memproses...');
                if (target.tagName === 'A') {
                    window.location.href = target.getAttribute('href');
                } else if (target.tagName === 'BUTTON') {
                    target.click();
                }
            });
        });

        document.addEventListener('submit', function (event) {
            var form = event.target;
            if (!(form instanceof HTMLFormElement)) return;
            if (event.defaultPrevented) {
                return;
            }

            var confirmMessage = form.getAttribute('data-confirm');
            if (confirmMessage && form.dataset.confirmed !== '1') {
                event.preventDefault();
                window.FinanceUI.confirm(confirmMessage).then(function (ok) {
                    if (!ok) return;
                    form.dataset.confirmed = '1';
                    if (typeof form.requestSubmit === 'function') {
                        form.requestSubmit(event.submitter || undefined);
                    } else {
                        form.submit();
                    }
                });
                return;
            }

            var method = (form.getAttribute('method') || 'get').toLowerCase();
            if (method !== 'post') {
                return;
            }
            if (form.dataset.noLoading === '1') {
                return;
            }

            var submitter = event.submitter || form.querySelector('button[type="submit"],input[type="submit"]');
            var label = submitter ? (submitter.getAttribute('data-loading-label') || 'Memproses...') : 'Memproses...';
            if (submitter) {
                setButtonLoading(submitter, label);
            }
            form.querySelectorAll('button[type="submit"],input[type="submit"]').forEach(function (btn) {
                if (submitter && btn === submitter) return;
                btn.disabled = true;
            });
        });
    }

    window.FinanceUI = window.FinanceUI || {};
    window.FinanceUI.setButtonLoading = setButtonLoading;
    window.FinanceUI.clearButtonLoading = clearButtonLoading;

    // ---------------------------------------------------------------
    // Toggle favorit sidebar via AJAX (persist per user)
    // ---------------------------------------------------------------
    function sidebarFavoriteRequest(endpoint, data) {
        return $.ajax({
            url: BASE_URL + endpoint,
            method: 'POST',
            dataType: 'json',
            data: data,
            headers: {
                'X-Sidebar-Favorite-CSRF': String(window.FINANCE_SIDEBAR_FAVORITE_CSRF || '')
            }
        });
    }

    function sidebarFavoriteError(xhr) {
        var message = xhr && xhr.responseJSON && xhr.responseJSON.message
            ? xhr.responseJSON.message
            : 'Gagal memperbarui favorit sidebar.';
        window.alert(message);
    }

    function setSidebarFavoriteButtons(menuId, pinned) {
        document.querySelectorAll('.sidebar-pin-toggle[data-menu-id="' + menuId + '"]').forEach(function (button) {
            var label = pinned ? 'Hapus dari favorit' : 'Tambah ke favorit';
            button.setAttribute('data-pinned', pinned ? '1' : '0');
            button.classList.toggle('is-pinned', pinned);
            button.setAttribute('title', label);
            button.setAttribute('aria-label', label);
            button.disabled = false;
            var icon = button.querySelector('i');
            if (icon) {
                icon.classList.toggle('ri-star-fill', pinned);
                icon.classList.toggle('ri-star-line', !pinned);
            }
        });
    }

    function updateSidebarFavoriteChrome() {
        var hasFavorites = !!document.querySelector('li[data-fav-id]');
        var header = document.querySelector('[data-sidebar-favorites-header]');
        var divider = document.querySelector('[data-sidebar-favorites-divider]');
        if (header) header.hidden = !hasFavorites;
        if (divider) divider.hidden = !hasFavorites;
    }

    function appendSidebarFavorite(menuId, clickedButton) {
        if (document.querySelector('li[data-fav-id="' + menuId + '"]')) return;
        var sourceLink = clickedButton ? clickedButton.closest('a.menu-link') : null;
        var divider = document.querySelector('[data-sidebar-favorites-divider]');
        if (!sourceLink || !divider || !divider.parentNode) return;

        var href = sourceLink.getAttribute('data-sidebar-favorite-url') || sourceLink.getAttribute('href') || '';
        if (!href || href === '#' || href.toLowerCase().indexOf('javascript:') === 0) return;

        var row = document.createElement('li');
        row.className = 'menu-item';
        row.setAttribute('data-fav-id', String(menuId));
        var link = document.createElement('a');
        link.className = 'menu-link d-flex align-items-center';
        link.setAttribute('href', href);
        var icon = document.createElement('i');
        var sourceIcon = sourceLink.querySelector('.menu-icon');
        icon.className = sourceIcon ? sourceIcon.className : 'menu-icon tf-icons ri ri-star-line';
        var label = document.createElement('div');
        label.className = 'flex-grow-1';
        var sourceLabel = sourceLink.querySelector('.flex-grow-1');
        label.textContent = sourceLabel ? sourceLabel.textContent.trim() : 'Favorit';
        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'btn p-0 border-0 bg-transparent sidebar-pin-toggle is-pinned';
        button.setAttribute('data-menu-id', String(menuId));
        button.setAttribute('data-pinned', '1');
        button.setAttribute('title', 'Hapus dari favorit');
        button.setAttribute('aria-label', 'Hapus dari favorit');
        var star = document.createElement('i');
        star.className = 'ri ri-star-fill';
        button.appendChild(star);
        link.appendChild(icon);
        link.appendChild(label);
        link.appendChild(button);
        row.appendChild(link);
        divider.parentNode.insertBefore(row, divider);
    }

    function removeSidebarFavorite(menuId) {
        document.querySelectorAll('li[data-fav-id="' + menuId + '"]').forEach(function (row) {
            row.remove();
        });
    }

    window.FinanceSidebarFavorites = {
        reorder: function (menuIds) {
            return sidebarFavoriteRequest('sidebar/reorder', { ids: menuIds || [] })
                .fail(sidebarFavoriteError);
        }
    };

    $(document).on('click', '.sidebar-pin-toggle', function (e) {
        e.preventDefault();
        e.stopPropagation();
        var $btn = $(this);
        var menuId = Number($btn.data('menu-id') || 0);
        if (!menuId) return;

        var pinned = String($btn.attr('data-pinned') || '0') === '1';
        var endpoint = pinned ? 'sidebar/unpin' : 'sidebar/pin';

        $btn.prop('disabled', true);
        sidebarFavoriteRequest(endpoint, { menu_id: menuId })
            .done(function (payload) {
                if (!payload || payload.ok !== true) {
                    window.alert((payload && payload.message) || 'Gagal memperbarui favorit sidebar.');
                    $btn.prop('disabled', false);
                    return;
                }
                if (pinned) {
                    removeSidebarFavorite(menuId);
                    setSidebarFavoriteButtons(menuId, false);
                } else {
                    appendSidebarFavorite(menuId, $btn[0]);
                    setSidebarFavoriteButtons(menuId, true);
                }
                updateSidebarFavoriteChrome();
            })
            .fail(function (xhr) {
                $btn.prop('disabled', false);
                sidebarFavoriteError(xhr);
            });
    });

    // ---------------------------------------------------------------
    // Auto-dismiss flash alerts setelah 4 detik
    // ---------------------------------------------------------------
    setTimeout(function () {
        $('.alert-dismissible').each(function () {
            var alertEl = this;
            if (typeof bootstrap !== 'undefined' && bootstrap.Alert) {
                var bsAlert = bootstrap.Alert.getOrCreateInstance(alertEl);
                bsAlert.close();
            } else {
                $(alertEl).fadeOut(400, function () { $(this).remove(); });
            }
        });
    }, 4000);

    applyPageTitleIcon();
    formatTwoDecimals();
    harmonizeLegacyTables();
    applyAutoTableFit();
    initUiDialogs();
    document.querySelectorAll('[data-product-spreadsheet]').forEach(function (panel) {
        var button = panel.querySelector('[data-product-sheet-sync]');
        var status = panel.querySelector('[data-product-sheet-status]');
        if (!button || !status) return;
        button.addEventListener('click', async function () {
            if (button.disabled) return;
            button.disabled = true;
            var originalLabel = button.textContent;
            try {
                if (!window.FinanceUI || !window.FinanceUI.confirm) throw new Error('Dialog konfirmasi belum siap. Muat ulang halaman.');
                var confirmed = await window.FinanceUI.confirm('Perbarui seluruh produk di tab data sistem? Data pada tab tersebut diganti dengan snapshot terbaru. Tab lain dan database produk tidak diubah.', {
                    title: 'Perbarui Spreadsheet Produk', okText: 'Perbarui', cancelText: 'Batal'
                });
                if (!confirmed) return;
                button.textContent = 'Mengirim produk...';
                status.textContent = 'Menghitung HPP live dan mengirim semua produk. Tunggu hingga selesai.';
                status.className = 'px-3 pb-3 small text-muted';
                var response = await fetch(panel.dataset.syncUrl, {
                    method: 'POST', credentials: 'same-origin',
                    headers: {'Accept': 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-Master-Mutation-Csrf': panel.dataset.csrf},
                    body: '{}'
                });
                var result;
                try { result = await response.json(); }
                catch (error) { throw new Error('Respons server tidak valid. Periksa spreadsheet sebelum mencoba kembali.'); }
                if (!response.ok || !result.ok) throw new Error(result.message || 'Pengiriman belum berhasil.');
                status.className = 'px-3 pb-3 small text-success';
                status.textContent = result.message + ' Snapshot: ' + result.data.captured_at + ' WIB. Aktif: ' + result.data.active_count + ', nonaktif: ' + result.data.inactive_count + '.';
            } catch (error) {
                status.className = 'px-3 pb-3 small text-danger';
                status.textContent = error instanceof TypeError ? 'Koneksi terputus; hasil pengiriman belum pasti. Periksa spreadsheet sebelum mengulang.' : error.message;
            } finally {
                button.disabled = false;
                button.textContent = originalLabel;
            }
        });
    });
    document.querySelectorAll('[data-inventory-matrix-sheet]').forEach(function (panel) {
        var button = panel.querySelector('[data-inventory-matrix-sheet-sync]');
        var status = panel.querySelector('[data-inventory-matrix-sheet-status]');
        if (!button || !status) return;
        button.addEventListener('click', async function () {
            var monthEl = document.querySelector(panel.dataset.monthSelector || '');
            var month = monthEl ? monthEl.value : '';
            if (!/^\d{4}-(0[1-9]|1[0-2])$/.test(month)) { status.textContent = 'Pilih bulan yang valid pada filter.'; status.className = 'small text-danger'; return; }
            try {
                if (!window.FinanceUI || !window.FinanceUI.confirm) throw new Error('Dialog konfirmasi belum siap. Muat ulang halaman.');
                var ok = await window.FinanceUI.confirm('Snapshot bulan ' + month + ' akan memperbarui tab utama dan tab arsip bulanan untuk gudang serta semua divisi. Tab data manual tidak akan ditimpa.', {title:'Perbarui Daily Matrix Spreadsheet', okText:'Perbarui', cancelText:'Batal'});
                if (!ok) return;
                button.disabled = true; button.textContent = 'Menyusun snapshot...'; status.textContent = 'Mengambil matriks stok dan menulis ke spreadsheet. Jangan tutup halaman.'; status.className = 'small text-muted';
                var body = new URLSearchParams({month:month, inventory_matrix_csrf:panel.dataset.csrf});
                var response = await fetch(panel.dataset.syncUrl, {method:'POST', credentials:'same-origin', headers:{'Accept':'application/json','Content-Type':'application/x-www-form-urlencoded;charset=UTF-8','X-Requested-With':'XMLHttpRequest'}, body:body.toString()});
                var responseText = await response.text();
                var result;
                try { result = JSON.parse(responseText); } catch (e) {
                    var detail = responseText.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 260);
                    throw new Error('Respons server tidak valid (HTTP ' + response.status + '). ' + (detail || 'Server mengirim respons kosong/non-JSON.') + ' Periksa spreadsheet sebelum mengulang.');
                }
                if (!response.ok || !result.ok) throw new Error(result.message || 'Sinkronisasi belum berhasil.');
                status.textContent = 'Berhasil: ' + result.data.spreadsheet_title + ' / ' + month + '. ';
                (result.data.tabs || []).forEach(function (x, index) {
                    if (index) status.appendChild(document.createTextNode(' · '));
                    status.appendChild(document.createTextNode(x.tab + ': ' + x.rows + ' baris '));
                    if (x.url) {
                        var link = document.createElement('a');
                        link.href = x.url; link.target = '_blank'; link.rel = 'noopener noreferrer'; link.textContent = 'Buka tab';
                        status.appendChild(link);
                    }
                });
                status.appendChild(document.createTextNode(' Tautan bulan berjalan diarahkan ke tanggal hari ini.'));
                status.className = 'small text-success';
            } catch (error) { status.textContent = error instanceof TypeError ? 'Koneksi terputus; periksa spreadsheet sebelum mencoba kembali.' : error.message; status.className = 'small text-danger'; }
            finally { button.disabled = false; button.textContent = 'Perbarui Spreadsheet'; }
        });
    });
    var settingsPanel = document.querySelector('[data-inventory-matrix-settings]');
    if (settingsPanel) {
        var settingStatus = settingsPanel.querySelector('[data-ims-status]');
        var requestSettings = async function (url, params) {
            params.append('inventory_matrix_csrf', settingsPanel.dataset.csrf);
            var response = await fetch(url, {method:'POST', credentials:'same-origin', headers:{'Accept':'application/json','Content-Type':'application/x-www-form-urlencoded;charset=UTF-8','X-Requested-With':'XMLHttpRequest'}, body:params.toString()});
            var result = await response.json();
            if (!response.ok || !result.ok) throw new Error(result.message || 'Permintaan pengaturan gagal.');
            return result.data;
        };
        settingsPanel.querySelector('[data-ims-discover]').addEventListener('click', async function () {
            try {
                var data = await requestSettings(settingsPanel.dataset.discoverUrl, new URLSearchParams({spreadsheet_url:settingsPanel.querySelector('#imsUrl').value}));
                var selects = settingsPanel.querySelectorAll('[data-ims-sheet]');
                selects.forEach(function (select) {
                    var saved = select.dataset.saved;
                    select.innerHTML = select.id === 'imsWarehouse' ? '<option value="">Pilih tab...</option>' : '<option value="">Tidak diekspor jika divisi tidak memiliki stok</option>';
                    data.tabs.forEach(function (tab) { var option=document.createElement('option'); option.value=String(tab.id); option.textContent=tab.title; if (String(tab.id)===saved) option.selected=true; select.appendChild(option); });
                });
                settingStatus.textContent = 'Workbook: ' + data.title + ' · ' + data.tabs.length + ' tab ditemukan.'; settingStatus.className='small text-success';
            } catch (error) { settingStatus.textContent=error.message; settingStatus.className='small text-danger'; }
        });
        settingsPanel.querySelector('[data-ims-save]').addEventListener('click', async function () {
            try {
                var params = new URLSearchParams({spreadsheet_url:settingsPanel.querySelector('#imsUrl').value});
                settingsPanel.querySelectorAll('[data-ims-sheet]').forEach(function (select) { params.append(select.dataset.settingName, select.value); });
                var data = await requestSettings(settingsPanel.dataset.saveUrl, params);
                settingStatus.textContent = data.message + ' Workbook: ' + data.spreadsheet_title; settingStatus.className='small text-success';
            } catch (error) { settingStatus.textContent=error.message; settingStatus.className='small text-danger'; }
        });
        if (settingsPanel.querySelector('#imsUrl').value) settingsPanel.querySelector('[data-ims-discover]').click();
    }
    initGlobalSelfOrderNotifier();
    normalizeInlineConfirmToDataAttr();
    bindUiConfirmAndLoading();
    reinforceSidebarActivePath();
    initTooltips();
    bootDataTablesIfNeeded();
});
