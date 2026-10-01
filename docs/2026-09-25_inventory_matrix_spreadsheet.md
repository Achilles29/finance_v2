# Daily Inventory Matrix Spreadsheet

## Scope

The export reuses the existing stock matrix models and writes monthly snapshots to a configured Google Sheets workbook. It does not create database tables, perform SQL migrations, or change inventory balances.

- Warehouse stock is sourced from `Purchase_model::list_warehouse_daily_matrix` and mapped to the configured central warehouse tab (typically named `STOREROOM`).
- Division raw materials come from `Purchase_model::list_material_daily_matrix`.
- Division components come from `Production_model::component_daily_matrix`; regular and event locations are combined and BASE/PREPARE remain distinguishable.
- Each row represents a stock profile/component. Category appears before code/name, and rows are sorted by domain, type, category, then item name. The material/component name shares one column, **Nama Material / Component**. Each calendar day has Awal, In, Out, Adj, and Akhir quantities.
- Data rows use subtle alternating stripes. Past dates follow the row stripes; today's five cells use a stronger amber highlight, based on Jakarta time. A strong left boundary marks the start of each date at its **Awal** column. Rows whose **Akhir** on the month's final date is zero or negative are highlighted red for review.
- Main tabs are the latest explicitly synchronized month. A tab named `<configured tab>_YYYY-MM` stores each monthly snapshot.

## Setup

1. Ensure the existing Google service account credential is readable by PHP from `/var/lib/finance-secrets/google-sheets.json` and that the service account has Editor access to the workbook.
2. Create/select the desired workbook and open `/inventory/daily-matrix/spreadsheet/settings`.
3. Paste the workbook URL, discover tabs, assign a unique tab for warehouse and every active division, and save.
4. Open any of the daily matrix pages, select a month, and choose **Perbarui Spreadsheet**. The first 18 columns (A:R) stay frozen; the date columns start at S.

The workbook ID and mappings are stored outside the repository in `/var/lib/finance-secrets/runtime/inventory-matrix-spreadsheet.json` with private file permissions. `runtime` must be writable by the PHP worker. The application does not persist the config in SQL.

## Safety

- The app requires global division scope and view permissions for warehouse matrix, material matrix, and component daily matrix.
- A first-use tab must be empty. A tab containing data without this exporter’s developer-metadata marker is never overwritten.
- Only configured target tabs and their month archive tabs are changed. Other workbook tabs remain untouched.
- A batch is rejected if matrix rows are truncated or exceed the configured safety limit.
- This is a manual snapshot action, not an automatic live connection. Run it again after source stock changes.
- After a successful sync, the result provides a link to each primary tab. For the current month the link focuses the date column for today. Google Sheets API cannot force the active cell when a user opens or refreshes the workbook directly; automatic focus on every direct open would require an authorized Apps Script `onOpen` trigger.
- To enable focus when opening/reloading the workbook, the workbook owner must install `docs/inventory_matrix_spreadsheet_on_open.gs` once in **Extensions → Apps Script** and save it. The trigger selects today's date header on whichever monthly tab is active, scrolling the non-frozen pane while A:R stays fixed. This is separate from the Finance app and needs no database change.
- Spreadsheet writes are split into bounded batches. If a request still fails, the UI shows the HTTP status and a sanitized response excerpt; retrying the same month is safe.

## Deployment

No SQL file or database change is needed. Deploy the listed PHP/controller/view/config/JS files, ensure `/var/lib/finance-secrets/runtime` ownership and mode permit the web worker to write (directory `0700`, file `0600`), and assign the three page permissions to the intended global role. No service-account secret belongs in Git.
