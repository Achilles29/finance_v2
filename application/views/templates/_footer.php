<footer class="content-footer footer bg-footer-theme">
    <div class="container-xxl">
        <div class="footer-container d-flex align-items-center justify-content-between py-4 flex-md-row flex-column">
            <div class="mb-2 mb-md-0">
                &#169;
                <script>
                    document.write(new Date().getFullYear());
                </script>
                , made with ❤️ by
                <a href="https://dpmptsp.rembangkab.go.id" target="_blank" class="footer-link fw-medium">DPMPTSP</a>
            </div>
            <!-- <div class="d-none d-lg-inline-block">
                <a href="https://themeselection.com/item/category/admin-templates/" target="_blank"
                    class="footer-link me-4">Admin Templates</a>

                <a href="https://themeselection.com/license/" class="footer-link me-4" target="_blank">License</a>

                <a href="https://themeselection.com/item/category/bootstrap-templates/" target="_blank"
                    class="footer-link me-4">Bootstrap Templates</a>
                <a href="https://demos.themeselection.com/materio-bootstrap-html-admin-template/documentation/"
                    target="_blank" class="footer-link me-4">Documentation</a>

                <a href="https://github.com/themeselection/materio-bootstrap-html-admin-template-free/issues"
                    target="_blank" class="footer-link">Support</a>
            </div> -->
        </div>
    </div>
</footer>
<!-- / Footer -->

<div class="content-backdrop fade"></div>
</div>

</div>
<!-- / Layout page -->
</div>

<!-- Overlay -->
<div class="layout-overlay layout-menu-toggle"></div>
</div>
<!-- / Layout wrapper -->


<!-- Core JS -->

<!-- <script src="../assets/vendor/libs/jquery/jquery.js"></script> -->

<script src="<?= base_url('assets/libs/jquery/jquery.js'); ?>"></script>


<script src="<?= base_url('assets/vendor/libs/popper/popper.js'); ?>"></script>


<script src="<?= base_url('assets/vendor/js/bootstrap.js'); ?>"></script>


<!-- <script src="../assets/vendor/libs/node-waves/node-waves.js"></script> -->
<script src="<?= base_url('assets/vendor/libs/node-waves/node-waves.js'); ?>"></script>

<!-- <script src="../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script> -->
<script src="<?= base_url('assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js'); ?>"></script>

<!-- <script src="../assets/vendor/js/menu.js"></script> -->
<script src="<?= base_url('assets/vendor/js/menu.js'); ?>"></script>

<!-- endbuild -->

<!-- Vendors JS -->
<!-- <script src="../assets/vendor/libs/apex-charts/apexcharts.js"></script> -->
<script src="<?= base_url('assets/vendor/libs/apex-charts/apexcharts.js'); ?>"></script>

<!-- Main JS -->

<!-- <script src="../assets/js/main.js"></script> -->
<script src="<?= base_url('assets/js/main.js'); ?>"></script>

<!-- Page JS -->
<!-- <script src="../assets/js/dashboards-analytics.js"></script> -->
<script src="<?= base_url('assets/js/dashboards-analytics.js'); ?>"></script>

<style>
  .table-responsive {
    border: 1px solid rgba(170, 95, 78, 0.22);
    border-radius: 14px;
    overflow-x: auto;
    background: rgba(255, 255, 255, 0.92);
  }
  table.table {
    margin-bottom: 0;
    border: 1px solid rgba(170, 95, 78, 0.2);
  }
  table.table thead th {
    background: linear-gradient(90deg, #8d0f1d 0%, #b21829 100%);
    color: #ffffff;
    font-weight: 700;
    border: 1px solid rgba(255, 255, 255, 0.18);
    vertical-align: middle;
  }
  table.table tbody td {
    border: 1px solid rgba(170, 95, 78, 0.16);
    vertical-align: middle;
    color: #3f3134;
  }
  table.table tbody tr:nth-child(odd) {
    background: rgba(243, 232, 225, 0.46);
  }
  table.table tbody tr:nth-child(even) {
    background: rgba(255, 255, 255, 0.94);
  }
  table.table tbody tr:hover td {
    background: rgba(250, 227, 220, 0.72);
  }
  .sortable-header {
    position: relative;
    cursor: pointer;
    user-select: none;
    padding-right: 18px;
  }
  .sort-indicator {
    position: absolute;
    right: 6px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 10px;
    color: #9ca3af;
    line-height: 1;
  }
  .sortable-header[data-sort-dir="asc"] .sort-indicator,
  .sortable-header[data-sort-dir="desc"] .sort-indicator {
    color: #8f1e20;
    font-weight: 700;
  }
</style>

<script>
  (function () {
    function cleanText(str) {
      return (str || '')
        .replace(/\s+/g, ' ')
        .trim();
    }

    function parseComparable(value) {
      const v = cleanText(value).toLowerCase();
      if (!v) return { type: 'empty', value: '' };

      // Number: 1, 1.5, 1,000.25
      const numeric = v.replace(/,/g, '');
      if (/^-?\d+(\.\d+)?$/.test(numeric)) {
        return { type: 'number', value: parseFloat(numeric) };
      }

      // Date: YYYY-MM-DD or DD/MM/YYYY
      if (/^\d{4}-\d{1,2}-\d{1,2}$/.test(v)) {
        return { type: 'date', value: new Date(v).getTime() || 0 };
      }
      if (/^\d{1,2}\/\d{1,2}\/\d{4}$/.test(v)) {
        const p = v.split('/');
        const d = new Date(p[2] + '-' + p[1] + '-' + p[0]).getTime();
        return { type: 'date', value: d || 0 };
      }

      return { type: 'text', value: v };
    }

    function getCellText(row, index) {
      const cell = row.cells[index];
      if (!cell) return '';
      const attr = cell.getAttribute('data-sort-value');
      return attr !== null ? attr : cell.innerText;
    }

    function sortTable(table, colIndex, direction) {
      const tbody = table.tBodies[0];
      if (!tbody) return;

      const rows = Array.from(tbody.rows);
      rows.sort(function (a, b) {
        const av = parseComparable(getCellText(a, colIndex));
        const bv = parseComparable(getCellText(b, colIndex));

        // Empty values always last
        if (av.type === 'empty' && bv.type !== 'empty') return 1;
        if (bv.type === 'empty' && av.type !== 'empty') return -1;

        let cmp = 0;
        if (av.type === bv.type && (av.type === 'number' || av.type === 'date')) {
          cmp = av.value - bv.value;
        } else {
          cmp = String(av.value).localeCompare(String(bv.value), 'id', { numeric: true, sensitivity: 'base' });
        }

        return direction === 'asc' ? cmp : -cmp;
      });

      rows.forEach(function (row) { tbody.appendChild(row); });
    }

    function setSortIndicator(th, dir) {
      let icon = th.querySelector('.sort-indicator');
      if (!icon) {
        icon = document.createElement('span');
        icon.className = 'sort-indicator';
        th.appendChild(icon);
      }
      if (dir === 'asc') icon.textContent = '▲';
      else if (dir === 'desc') icon.textContent = '▼';
      else icon.textContent = '↕';
    }

    function applySortAllTables() {
      const tables = document.querySelectorAll('table');
      tables.forEach(function (table) {
        if (table.classList.contains('table')) {
          table.classList.add('table-striped', 'table-bordered');
        }

        const head = table.tHead;
        const body = table.tBodies[0];
        if (!head || !head.rows.length || !body) return;

        const headers = Array.from(head.rows[0].cells);
        headers.forEach(function (th, index) {
          if (th.dataset.noSort === '1' || th.classList.contains('no-sort')) return;
          th.classList.add('sortable-header');
          th.title = 'Klik untuk sortir';
          setSortIndicator(th, '');

          th.addEventListener('click', function () {
            const current = th.dataset.sortDir || '';
            const next = current === 'asc' ? 'desc' : 'asc';

            headers.forEach(function (h) {
              if (!h.classList.contains('sortable-header')) return;
              h.dataset.sortDir = '';
              setSortIndicator(h, '');
            });

            th.dataset.sortDir = next;
            setSortIndicator(th, next);

            sortTable(table, index, next);
          });
        });
      });
    }

    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', applySortAllTables);
    } else {
      applySortAllTables();
    }
  })();
</script>

<!-- Place this tag before closing body tag for github widget button. -->
<script async="async" defer="defer" src="https://buttons.github.io/buttons.js"></script>
</body>

</html>
