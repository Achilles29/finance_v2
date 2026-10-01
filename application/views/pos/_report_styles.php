<style>
  .pos-report-shell {
    background:
      radial-gradient(circle at top left, rgba(255, 230, 209, .38), transparent 28%),
      linear-gradient(180deg, #fff9f4 0%, #fff 22%, #fffdfa 100%);
    min-height: calc(100vh - 120px);
    border-radius: 30px;
    padding: 1rem;
  }
  .pos-report-hero {
    border: 1px solid #f0dfd2;
    border-radius: 26px;
    background: linear-gradient(135deg, rgba(255,255,255,.98) 0%, rgba(255,246,238,.98) 100%);
    box-shadow: 0 18px 48px rgba(126, 73, 35, .08);
    padding: 1.15rem 1.2rem;
  }
  .pos-report-title {
    font-size: 1.65rem;
    font-weight: 800;
    color: #3b261c;
    letter-spacing: -.02em;
  }
  .pos-report-copy {
    color: #7f6658;
    max-width: 980px;
  }
  .pos-report-nav {
    display: flex;
    gap: .55rem;
    flex-wrap: wrap;
  }
  .pos-report-nav .btn {
    border-radius: 999px;
    padding-inline: .95rem;
  }
  .pos-report-nav .btn.active {
    background: #1f2937;
    color: #fff;
    border-color: #1f2937;
  }
  .pos-report-filter-box {
    border: 1px solid #efddd1;
    border-radius: 22px;
    background: rgba(255,255,255,.92);
    padding: .95rem;
    box-shadow: inset 0 1px 0 rgba(255,255,255,.7);
  }
  .pos-report-card {
    border: 1px solid #efddd1;
    border-radius: 20px;
    background: linear-gradient(180deg, #fffefd 0%, #fff6ef 100%);
    padding: .95rem 1rem;
    min-height: 108px;
  }
  .pos-report-card-label {
    color: #8d7366;
    text-transform: uppercase;
    font-size: .74rem;
    letter-spacing: .08em;
    font-weight: 800;
  }
  .pos-report-card-value {
    font-size: 1.35rem;
    font-weight: 800;
    color: #3b261c;
    margin-top: .4rem;
  }
  .pos-report-card-note {
    color: #8b7469;
    font-size: .78rem;
    margin-top: .35rem;
  }
  .pos-report-section {
    border: 1px solid #f0dfd2;
    border-radius: 24px;
    background: linear-gradient(180deg, #fffaf5 0%, #fff 100%);
    box-shadow: 0 16px 42px rgba(126, 73, 35, .08);
  }
  .pos-report-table-wrap {
    border: 1px solid #efddd1;
    border-radius: 20px;
    overflow: hidden;
    background: #fff;
  }
  .pos-report-table-scroll {
    max-height: min(64vh, 760px);
    overflow: auto;
  }
  .pos-report-table thead th {
    font-size: .79rem;
    text-transform: uppercase;
    letter-spacing: .03em;
    color: #7c6053;
    border-bottom-color: #ecd9cd;
    background: linear-gradient(180deg, #fff7f1 0%, #fff 100%);
    white-space: nowrap;
  }
  .pos-report-table-scroll .pos-report-table thead th {
    position: sticky;
    top: 0;
    z-index: 4;
    box-shadow: 0 1px 0 #ecd9cd, 0 8px 14px rgba(126, 73, 35, .06);
  }
  .pos-report-table tbody td {
    border-bottom-color: #f3e7df;
    padding-top: .85rem;
    padding-bottom: .85rem;
    vertical-align: top;
  }
  .pos-report-table tbody tr:hover {
    background: #fffaf6;
  }
  .pos-report-badge {
    display: inline-flex;
    align-items: center;
    padding: .25rem .65rem;
    border-radius: 999px;
    font-weight: 700;
    font-size: .76rem;
  }
  .pos-report-badge.success { background: #e9f8ef; color: #1e7a45; }
  .pos-report-badge.warning { background: #fff3dd; color: #8d5e00; }
  .pos-report-badge.danger { background: #fde8e8; color: #b42318; }
  .pos-report-badge.info { background: #e7f0ff; color: #1d4ed8; }
  .pos-report-badge.secondary { background: #eef2f6; color: #475467; }
  .pos-report-meta {
    color: #8a7063;
    font-size: .82rem;
  }
  .pos-report-kv {
    display: grid;
    grid-template-columns: auto 1fr;
    gap: .2rem .6rem;
    align-items: start;
  }
  .pos-report-kv strong {
    color: #4c3429;
    font-weight: 800;
  }
  .pos-report-empty {
    color: #8a7063;
    padding: 1rem 0;
  }
  .pos-report-inline-list {
    display: grid;
    gap: .25rem;
  }
  .pos-report-inline-list .muted {
    color: #8a7063;
    font-size: .8rem;
  }
  .pos-report-actions {
    display: flex;
    justify-content: flex-end;
    gap: .35rem;
  }
  .pos-report-chip-row {
    display: flex;
    flex-wrap: wrap;
    gap: .55rem;
  }
  .pos-report-chip {
    display: inline-flex;
    align-items: center;
    gap: .35rem;
    border: 1px solid #efddd1;
    background: rgba(255, 250, 245, .95);
    color: #6f5649;
    border-radius: 999px;
    padding: .42rem .8rem;
    font-size: .8rem;
    box-shadow: 0 8px 20px rgba(126, 73, 35, .05);
  }
  .pos-report-chip strong {
    color: #3f2a1f;
    font-weight: 800;
  }
  .pos-report-stack {
    display: grid;
    gap: .2rem;
  }
  .pos-report-tone-danger {
    background: #fff4f4;
  }
  .pos-report-tone-warning {
    background: #fffaf0;
  }
  .pos-report-subtable {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    font-size: .84rem;
  }
  .pos-report-subtable thead th {
    color: #7c6053;
    font-size: .72rem;
    text-transform: uppercase;
    letter-spacing: .04em;
    padding: .45rem .55rem;
    background: #fff6ef;
    border-bottom: 1px solid #ecd9cd;
  }
  .pos-report-subtable tbody td {
    padding: .45rem .55rem;
    border-bottom: 1px solid #f3e7df;
    vertical-align: top;
  }
  .pos-report-subtable tbody tr:last-child td {
    border-bottom: 0;
  }
  .pos-report-subtable .focus-row {
    background: #fff9ec;
  }
  .pos-report-subtable .variance-row {
    background: #fff5f5;
  }
</style>
