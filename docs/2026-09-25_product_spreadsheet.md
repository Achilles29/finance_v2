# Product Spreadsheet Export

## Operator Flow

- Open `/master/product`. Users need both View and Export permission for `master.product.index`, plus the existing HPP_CONTROL feature entitlement.
- **Perbarui Spreadsheet** exports the complete catalog, including inactive products. Current search filters and pagination do not limit it.
- **Buka Spreadsheet** opens the configured tab. **Unduh Excel** creates the same snapshot as a numeric XLSX export.
- **Pengaturan & Panduan** accepts a full Google Sheets URL, checks service-account access and the selected `gid`, then saves the destination. Refresh remains manual after product changes.
- Order matches Master Product: division sort/id, classification sort/id, category sort/id, product name/id.
- The Google tab is a system-owned snapshot. Put manual analysis in another tab. Subsequent refreshes replace the owned range; they do not append duplicate products or update the product database.

## Data

45 columns include taxonomy, product identity, operating division, UOM, description, active state, stock mode, sale prices, standard HPP, direct live cost, product variable-cost mode/rate/amount, total live HPP, HPP percentages, unit profit, margins, sales-channel flags, photo URL, capture timestamp, database IDs, raw cache metadata, and creation/update timestamps.

The existing `Master::decorateProductListRow` calculation is reused without changing its HPP/profit arithmetic. HPP source labels identify recipe calculation, cache/standard fallback, or unavailable/zero HPP. Component costs keep the existing master resolver's treatment; the product variable amount is added once by that same resolver.

- HPP percentage = total HPP / sale price * 100.
- Margin percentage = (sale price - total HPP) / sale price * 100.
- Percent values are numeric percentage points, e.g. 30 means 30%, not 0.30.
- Zero/missing sale-price denominators produce blank percentages.
- Profit is not net business income. Online profit is before platform fees, discounts, and taxes.
- Text is sent as explicit Google `stringValue` and XLSX inline strings, preventing formula interpretation and preserving codes with leading zeros.

## Runtime Setup (Not in Git)

Credentials and connection settings are private files, not application/database configuration changes:

```text
/var/lib/finance-secrets/google-sheets.json
/var/lib/finance-secrets/product-spreadsheet.json
/var/lib/finance-secrets/runtime/
```

Base connection structure (placeholders only):

```json
{
  "spreadsheet_id": "YOUR_SPREADSHEET_ID",
  "sheet_id": 123456,
  "credentials_file": "/var/lib/finance-secrets/google-sheets.json"
}
```

Optional server environment `FINANCE_PRODUCT_SHEET_CONFIG` overrides the base settings-file path. An optional `lock_file` setting overrides the default runtime lock path. Credentials and API endpoints remain server-controlled; the settings page accepts only a Google Sheets URL and a tab gid.

The spreadsheet and tab selected in the UI are stored separately at `/var/lib/finance-secrets/runtime/product-spreadsheet-target.json`, writable only by the application service account. This runtime value overrides the initial destination in the base connection file. The browser can only provide an HTTPS `docs.google.com/spreadsheets` URL; the server validates that the service account can access the workbook and that the requested tab exists before saving.

On this server PHP runs as `www`: the private parent directory is `root:www` mode 0750, both JSON files are `root:www` mode 0640, and runtime directory is `www:www` mode 0700. The uploaded key was renamed to `google-sheets.json`; no key contents were added to Git.

The site's ignored `.user.ini` now allows `/var/lib/finance-secrets/` in addition to its previous website and `/tmp/` paths. Other servers need the equivalent narrow PHP `open_basedir` exception and their own private credentials/settings. PHP caches `.user.ini` settings, so allow its cache TTL to expire after deployment. Do not disable `open_basedir` globally or put credentials under the webroot.

Google Sheets API must be enabled, and the exact spreadsheet shared with the service account as Editor. Public sharing and domain-wide delegation are not required.

## Safety and Verification

- Database extraction is performed inside a read-only transaction; no schema or stock changes.
- Sync requires POST, scoped Master CSRF, View/Export permissions, and HPP entitlement.
- A server lock covers extraction and sending. All tab data, formatting, and ownership metadata are updated in one Google batch; no clear-before-write sequence.
- First use refuses a nonempty unmarked tab. Subsequent refreshes use `finance_product_snapshot` developer metadata to restrict replacement to the managed range and clear stale exported rows.
- Remote errors do not print tokens/private keys. Network uncertainty is reported as uncertainty, not success. Repeating a refresh replaces the snapshot rather than appending.
- No new dependency or SQL migration. New libraries are included in the customer release allowlist.
- Regression checks: `tools/tests/product_spreadsheet_smoke.php` and `tools/tests/product_spreadsheet_browser_smoke.cjs`.
- Existing Master inline/form CSRF and Purchase opening authorization checks also pass. The broader feature-boundary suite currently stops on a pre-existing unmapped `procurement/division_po_sr_line_action`, unrelated to this export; the new export routes are individually tested for entitlement allow/deny behavior.
- Initial live export on 2026-09-25: 350 unique products, 253 active and 97 inactive, 45 columns, into the previously empty `PRODUK` tab (gid 270068602). Read-back verified HPP, profit, percentage arithmetic and product uniqueness. 67 rows had zero HPP (3 active); these retain the master's values and are explicitly labelled, not silently repaired.
- Authenticated HTTPS verification through the deployed PHP-FPM application also passed: Master page without PHP warnings, scoped-CSRF sync POST HTTP 200 at 13:30:43 WIB, and XLSX download HTTP 200 with the expected ZIP signature/content type. A second Google read-back still found exactly 350 unique products, proving the refresh replaces rather than duplicates the snapshot.

Implementation reference: [Google service-account OAuth](https://developers.google.com/identity/protocols/oauth2/service-account), [Sheets batchUpdate](https://developers.google.com/workspace/sheets/api/reference/rest/v4/spreadsheets/batchUpdate).
