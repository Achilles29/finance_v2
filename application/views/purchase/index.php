<?php
$purchaseMutationCsrfToken = (string)($purchase_mutation_csrf_token ?? '');
$cardSummary = (array)($card_summary ?? []);
$filteredSummary = (array)($filtered_summary ?? []);
$lineSummary = (array)($line_summary ?? []);
$monthAttentionSummary = (array)($month_attention_summary ?? []);
$monthAttentionUnpaidDetails = (array)($month_attention_unpaid_details ?? []);
$allAttentionUnpaidDetails = (array)($all_attention_unpaid_details ?? []);
$paymentMethodBreakdown = (array)($payment_method_breakdown ?? []);
$typeBreakdown = (array)($type_breakdown ?? []);
$usagePurposeAttention = (array)($usage_purpose_attention ?? []);
$paidRows = (array)($paid_rows ?? []);
$paidSummary = (array)($paid_summary ?? []);
$poPaidPageCount = count($paidRows);
$poPaidPageValue = 0.0;
foreach ($paidRows as $pr) {
    $poPaidPageValue += (float)($pr['grand_total'] ?? 0);
}
$poRows = (array)($rows ?? []);
$poLineRows = (array)($line_rows ?? []);
$poPageCount = count($poRows);
$poPageValue = 0.0;
foreach ($poRows as $pageRow) {
    $poPageValue += (float)($pageRow['grand_total'] ?? 0);
}
$poLinePageCount = count($poLineRows);
$poLinePageQty = 0.0;
$poLinePageValue = 0.0;
foreach ($poLineRows as $lineRow) {
    $poLinePageQty += (float)($lineRow['qty_buy'] ?? 0);
    $poLinePageValue += (float)($lineRow['line_subtotal'] ?? 0);
}
$activeTab = in_array((string)($tab ?? 'nota'), ['nota', 'rincian', 'paid'], true) ? (string)$tab : 'nota';
$dateStart = (string)($date_start ?? '');
$dateEnd = (string)($date_end ?? '');
$statusOptions = (array)($status_options ?? ['ALL']);
$activeStatus = strtoupper(trim((string)($status ?? 'ALL')));
if ($activeStatus === '') {
    $activeStatus = 'ALL';
}
$statusLabel = static function (string $value): string {
    $value = strtoupper(trim($value));
    if ($value === '' || $value === 'ALL') {
        return 'Semua';
    }
    return ucwords(strtolower(str_replace('_', ' ', $value)));
};
$buildStatusUrl = static function (string $statusValue) use ($q, $dateStart, $dateEnd, $activeTab): string {
    return site_url('purchase-orders') . '?' . http_build_query([
        'tab' => $activeTab,
        'q' => (string)$q,
        'status' => $statusValue,
        'date_start' => $dateStart,
        'date_end' => $dateEnd,
    ]);
};
$summaryStatusText = $statusLabel($activeStatus);
$summaryRangeText = trim(($dateStart !== '' ? $dateStart : '-') . ' s/d ' . ($dateEnd !== '' ? $dateEnd : '-'));

$statusBadgeClass = function (string $status): string {
    $status = strtoupper(trim($status));
    $map = [
        'DRAFT' => 'bg-secondary',
        'APPROVED' => 'bg-info',
        'ORDERED' => 'bg-primary',
        'REJECTED' => 'bg-danger',
        'PARTIAL_RECEIVED' => 'bg-warning text-dark',
        'RECEIVED' => 'bg-success',
        'PAID' => 'bg-info text-dark',
        'CLOSED' => 'bg-dark',
        'VOID' => 'bg-secondary',
    ];
    return $map[$status] ?? 'bg-secondary';
};
$usagePurposeLabel = static function (string $purpose): string {
    return strtoupper(trim($purpose)) === 'OPERASIONAL' ? 'Operasional' : 'Persediaan Produksi';
};
$usagePurposeIssueLabel = static function (string $issueType): string {
    return strtoupper(trim($issueType)) === 'MATERIAL_AS_OPERATIONAL'
        ? 'Material sebagai operasional'
        : 'Tujuan berbeda dari default';
};

$canEditPo = !empty($current_user['is_superadmin']) || !empty($user_perms['purchase.order.index']['can_edit']);
?>

<style>
    .po-table-wrap {
        max-width: 100%;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
    .po-table {
        table-layout: fixed;
        width: 100%;
        min-width: 960px;
    }
    .po-table > :not(caption) > * > * {
        padding: 0.5rem 0.55rem;
    }
    .po-table th,
    .po-table td {
        white-space: normal;
        vertical-align: middle;
        word-break: break-word;
    }
    .po-col-po {
        width: 16%;
    }
    .po-col-vendor {
        width: 13%;
    }
    .po-col-type {
        width: 11%;
    }
    .po-col-purchase {
        width: 16%;
    }
    .po-col-value {
        width: 10%;
    }
    .po-col-status {
        width: 11%;
    }
    .po-col-action {
        width: 110px;
    }
    .po-status-next {
        min-width: 0;
        width: 5.75rem;
        max-width: 100%;
        font-size: 0.72rem;
        padding-top: 0.22rem;
        padding-bottom: 0.22rem;
        padding-left: 0.35rem;
        padding-right: 1.3rem;
        margin: 0 auto;
    }
    .po-tab-link {
        font-weight: 700;
    }
    .po-status-tab-link {
        font-weight: 600;
        white-space: nowrap;
    }
    .po-view-tab-link {
        font-weight: 700;
        border-color: #cec2b8 !important;
        background: #efe8e2 !important;
        color: #544740 !important;
    }
    .po-view-tab-link.active {
        background: #2f2a4f !important;
        border-color: #2f2a4f !important;
        color: #fff !important;
        box-shadow: 0 6px 14px rgba(47, 42, 79, 0.2);
    }
    .po-status-pill {
        font-size: 0.62rem;
        letter-spacing: 0.04em;
        padding: 0.28rem 0.42rem;
    }
    .po-cell-title {
        font-weight: 700;
        color: #243445;
        line-height: 1.25;
        font-size: 0.79rem;
    }
    .po-cell-subtext {
        display: block;
        margin-top: 0.08rem;
        color: #6c757d;
        font-size: 0.68rem;
        line-height: 1.18;
    }
    .po-money-main {
        font-weight: 700;
        text-align: right;
        line-height: 1.2;
        font-size: 0.79rem;
    }
    .po-table thead th {
        font-size: 0.74rem;
        vertical-align: top;
        white-space: nowrap;
    }
    .po-table tbody td {
        font-size: 0.77rem;
    }
    .po-status-cell {
        display: flex;
        gap: 0.3rem;
        align-items: center;
        justify-content: center;
        flex-wrap: wrap;
    }
    .po-status-note {
        font-size: 0.66rem;
        line-height: 1;
        text-align: left;
        margin: 0;
        white-space: nowrap;
    }
    .po-action-cell {
        text-align: center;
    }
    .po-summary-card {
        border: 0;
        box-shadow: 0 6px 22px rgba(67, 89, 113, 0.08);
    }
    .po-summary-card .card-body {
        padding: 0.9rem 1rem;
    }
    .po-summary-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 0.75rem;
        margin-bottom: 0.45rem;
    }
    .po-summary-kicker {
        display: block;
        color: #7b8190;
        font-size: 0.72rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin-bottom: 0.2rem;
    }
    .po-summary-count {
        font-size: 1.05rem;
        font-weight: 800;
        color: #243445;
        line-height: 1.1;
    }
    .po-summary-icon {
        width: 2rem;
        height: 2rem;
        border-radius: 0.7rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: rgba(168, 16, 39, 0.08);
        color: #a81027;
        flex: 0 0 2rem;
    }
    .po-summary-value {
        display: block;
        font-weight: 800;
        color: #243445;
        font-size: 0.92rem;
        line-height: 1.15;
        margin-bottom: 0.18rem;
    }
    .po-summary-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 0.35rem;
    }
    .po-summary-chip {
        display: inline-flex;
        align-items: center;
        padding: 0.18rem 0.5rem;
        border-radius: 999px;
        background: rgba(67, 89, 113, 0.08);
        color: #566070;
        font-size: 0.68rem;
        line-height: 1.15;
    }
    .po-table-footer {
        border-top: 1px solid rgba(67, 89, 113, 0.12);
        background: rgba(67, 89, 113, 0.04);
        padding: 0.65rem 0.8rem;
        font-size: 0.78rem;
    }
    .po-footer-block {
        padding: 0.45rem 0.55rem;
        border-radius: 0.7rem;
        background: rgba(255, 255, 255, 0.76);
        height: 100%;
    }
    .po-table-footer strong {
        display: block;
        font-size: 0.78rem;
        color: #243445;
    }
    .po-table-footer span {
        color: #6c757d;
        font-size: 0.75rem;
    }
    .po-footer-value {
        display: block;
        font-size: 0.84rem;
        font-weight: 800;
        color: #243445;
        line-height: 1.2;
        margin-top: 0.12rem;
    }
    .po-rincian-table {
        min-width: 1100px;
    }
    .po-rincian-table th,
    .po-rincian-table td {
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .po-rincian-table td {
        text-align: left;
    }
    .po-rincian-table td.rincian-text-left {
        text-align: left !important;
        white-space: normal;
    }
    .po-rincian-table td.rincian-center {
        text-align: center !important;
    }
    .po-rincian-table td.rincian-number {
        text-align: right !important;
    }
    .po-rincian-main {
        font-weight: 400;
        color: #233243;
        line-height: 1.2;
    }
    .po-rincian-inline {
        font-size: 0.73rem;
        color: #6b7280;
        margin-left: .35rem;
    }
    .po-status-pill--xs {
        font-size: 0.56rem;
        padding: 0.22rem 0.38rem;
        letter-spacing: 0.03em;
    }
    .po-rincian-table .po-col-type {
        width: 18%;
    }
    .po-rincian-table .po-col-purchase {
        width: 18%;
    }
    @media (max-width: 767.98px) {
        .po-summary-card .card-body {
            padding: 0.8rem 0.85rem;
        }
        .po-summary-count {
            font-size: 0.96rem;
        }
        .po-summary-value {
            font-size: 0.86rem;
        }
        .po-table-footer {
            padding: 0.55rem 0.65rem;
        }
    }
    .po-hero {
        position: relative;
        overflow: hidden;
        border: 1px solid #eadcd2;
        border-radius: 24px;
        background: linear-gradient(135deg, #fffdfb 0%, #fff6f2 100%);
        box-shadow: 0 12px 30px rgba(67, 89, 113, 0.08);
    }
    .po-hero::before,
    .po-hero::after {
        content: '';
        position: absolute;
        border-radius: 999px;
        pointer-events: none;
    }
    .po-hero::before {
        width: 280px;
        height: 280px;
        top: -140px;
        right: -80px;
        background: radial-gradient(circle, rgba(159, 33, 65, .08) 0%, rgba(159, 33, 65, 0) 72%);
    }
    .po-hero::after {
        width: 240px;
        height: 240px;
        bottom: -140px;
        left: -70px;
        background: radial-gradient(circle, rgba(196, 95, 61, .08) 0%, rgba(196, 95, 61, 0) 72%);
    }
    .po-hero-body {
        position: relative;
        z-index: 1;
        padding: 1.2rem 1.3rem;
    }
    .po-hero-kicker {
        display: inline-flex;
        align-items: center;
        gap: .4rem;
        padding: .28rem .62rem;
        border-radius: 999px;
        background: rgba(255,255,255,.14);
        color: #8a1538;
        font-size: .72rem;
        font-weight: 700;
        letter-spacing: .08em;
        text-transform: uppercase;
        border: 1px solid rgba(159, 33, 65, .14);
    }
    .po-hero-title {
        margin: .8rem 0 .35rem;
        color: #2c2225;
        font-size: 1.9rem;
        font-weight: 800;
        line-height: 1.05;
    }
    .po-hero-subtitle {
        max-width: 720px;
        color: #655650;
        line-height: 1.5;
    }
    .po-hero-meta {
        display: flex;
        flex-wrap: wrap;
        gap: .45rem;
        margin-top: .95rem;
    }
    .po-hero-chip {
        display: inline-flex;
        align-items: center;
        padding: .34rem .68rem;
        border-radius: 999px;
        background: #fff;
        border: 1px solid #eadcd2;
        color: #564942;
        font-size: .75rem;
        font-weight: 600;
    }
    .po-hero-actions {
        display: flex;
        align-items: center;
        gap: .6rem;
    }
    .po-hero-btn {
        border: 0;
        border-radius: 16px;
        padding: .8rem 1rem;
        font-weight: 800;
        color: #fff;
        background: #9f2141;
        box-shadow: 0 12px 24px rgba(159, 33, 65, .2);
    }
    .po-board-card {
        border: 0;
        border-radius: 24px;
        overflow: hidden;
        box-shadow: 0 14px 30px rgba(67, 89, 113, .09);
    }
    .po-summary-card {
        --po-accent: #8a1538;
        --po-soft: rgba(138, 21, 56, .14);
        --po-surface: #fff5f4;
        position: relative;
        overflow: hidden;
        border-radius: 24px;
        background: linear-gradient(145deg, var(--po-surface) 0%, #fff 68%);
        box-shadow: 0 16px 32px rgba(67, 89, 113, .1);
        border: 1px solid rgba(219, 208, 199, .65);
    }
    .po-summary-card::before {
        content: '';
        position: absolute;
        inset: auto -26px -34px auto;
        width: 132px;
        height: 132px;
        border-radius: 50%;
        background: radial-gradient(circle, var(--po-soft) 0%, rgba(255,255,255,0) 72%);
        pointer-events: none;
    }
    .po-summary-card .card-body {
        padding: 1rem 1.05rem;
        position: relative;
        z-index: 1;
    }
    .po-summary-card--primary {
        --po-accent: #8a1538;
        --po-soft: rgba(138, 21, 56, .15);
        --po-surface: #fff3f5;
    }
    .po-summary-card--paid {
        --po-accent: #0f766e;
        --po-soft: rgba(15, 118, 110, .16);
        --po-surface: #eefaf7;
    }
    .po-summary-card--open {
        --po-accent: #b45309;
        --po-soft: rgba(180, 83, 9, .16);
        --po-surface: #fff6ee;
    }
    .po-summary-card--lines {
        --po-accent: #1d4ed8;
        --po-soft: rgba(29, 78, 216, .15);
        --po-surface: #eff4ff;
    }
    .po-summary-card--payment {
        --po-accent: #7c3aed;
        --po-soft: rgba(124, 58, 237, .14);
        --po-surface: #f5f0ff;
    }
    .po-summary-card--type {
        --po-accent: #0e7490;
        --po-soft: rgba(14, 116, 144, .14);
        --po-surface: #ecfafe;
    }
    .po-breakdown-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: .45rem;
    }
    .po-breakdown-table td {
        padding: .18rem .1rem;
        font-size: .73rem;
        vertical-align: middle;
        border: 0;
    }
    .po-breakdown-table td:last-child {
        text-align: right;
        font-weight: 700;
        white-space: nowrap;
    }
    .po-breakdown-name {
        color: #2a3544;
        font-weight: 600;
        max-width: 0;
        width: 99%;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .po-breakdown-count {
        color: #6b7280;
        white-space: nowrap;
        padding-left: .4rem;
        padding-right: .4rem;
    }
    .po-breakdown-bar-wrap {
        height: 4px;
        background: var(--po-soft);
        border-radius: 99px;
        margin-top: .1rem;
        overflow: hidden;
    }
    .po-breakdown-bar {
        height: 100%;
        background: var(--po-accent);
        border-radius: 99px;
        transition: width .3s;
    }
    .po-summary-kicker {
        color: var(--po-accent);
        font-weight: 800;
        letter-spacing: .08em;
    }
    .po-summary-count,
    .po-summary-value {
        color: #1f2a39;
    }
    .po-summary-icon {
        background: var(--po-soft);
        color: var(--po-accent);
        width: 2.5rem;
        height: 2.5rem;
        border-radius: 18px;
        box-shadow: inset 0 1px 0 rgba(255,255,255,.72);
    }
    .po-summary-meta {
        gap: .42rem;
    }
    .po-summary-chip {
        background: rgba(255,255,255,.94);
        border: 1px solid rgba(31, 42, 57, .12);
        color: #46515f;
        font-weight: 600;
    }
    .po-tab-strip {
        gap: .55rem;
        border: 0;
    }
    .po-tab-strip .nav-item {
        margin: 0;
    }
    .po-tab-strip .nav-link {
        border: 0;
        border-radius: 15px;
        padding: .55rem .85rem;
        background: #f6efe9;
        color: #54473f;
        font-weight: 700;
        box-shadow: inset 0 1px 0 rgba(255,255,255,.72);
        border: 1px solid #eadcd2;
    }
    .po-tab-strip .nav-link:hover {
        color: #3e332d;
        background: #f1e2d7;
    }
    .po-tab-strip .nav-link.active {
        background: #9f2141;
        color: #fff9f6;
        box-shadow: 0 12px 22px rgba(159, 33, 65, .16);
        border-color: #9f2141;
    }
    .po-board-card .card-body {
        padding: 1rem 1rem 0;
    }
    .po-toolbar input,
    .po-toolbar select {
        border-radius: 12px;
        border-color: rgba(103, 88, 74, .18);
        background: #fffdfb;
    }
    .po-toolbar .btn {
        border-radius: 12px;
        font-weight: 700;
    }
    .po-month-warning {
        border-radius: 16px;
        border-width: 1px;
        box-shadow: 0 10px 24px rgba(185, 28, 28, 0.08);
    }
    .po-purpose-flare {
        border: 1px solid #efc98f;
        border-radius: 18px;
        background: linear-gradient(135deg, #fffaf0 0%, #fff4df 100%);
        box-shadow: 0 10px 24px rgba(176, 111, 26, 0.08);
        color: #5b4123;
    }
    .po-purpose-flare-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: .85rem;
    }
    .po-purpose-flare-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 2.35rem;
        height: 2.35rem;
        border-radius: .85rem;
        background: #9c5b10;
        color: #fff;
        flex: 0 0 auto;
    }
    .po-purpose-flare-title { font-weight: 800; color: #72420b; }
    .po-purpose-flare-meta { color: #806043; font-size: .76rem; line-height: 1.35; }
    .po-purpose-flare-chips { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: .35rem; }
    .po-purpose-flare-chip { display: inline-flex; align-items: center; border: 1px solid #e5bd81; border-radius: 999px; padding: .2rem .52rem; background: rgba(255,255,255,.72); color: #775027; font-size: .69rem; font-weight: 700; }
    .po-purpose-flare-list { display: grid; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); gap: .45rem; margin-top: .75rem; }
    .po-purpose-flare-item { display: block; padding: .52rem .62rem; border: 1px solid rgba(173, 111, 27, .22); border-radius: 12px; background: rgba(255,255,255,.72); color: inherit; text-decoration: none; }
    .po-purpose-flare-item:hover { border-color: #b86d17; color: inherit; background: #fff; }
    .po-purpose-flare-doc { display: flex; align-items: center; justify-content: space-between; gap: .45rem; color: #75430d; font-size: .72rem; font-weight: 800; }
    .po-purpose-flare-line { display: block; margin-top: .15rem; color: #2e2b28; font-size: .78rem; font-weight: 700; line-height: 1.25; }
    .po-purpose-flare-item small { display: block; margin-top: .2rem; color: #806043; font-size: .68rem; line-height: 1.25; }
    @media (max-width: 767.98px) {
        .po-purpose-flare-head { align-items: flex-start; }
        .po-purpose-flare-chips { justify-content: flex-start; }
    }
    .po-month-unpaid-list {
        display: grid;
        gap: .45rem;
        margin-top: .75rem;
        max-height: 13.5rem;
        overflow: auto;
        padding-right: .35rem;
    }
    .po-month-unpaid-item {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: .75rem;
        align-items: center;
        padding: .55rem .7rem;
        border-radius: 12px;
        background: rgba(255, 255, 255, .65);
        border: 1px solid rgba(185, 28, 28, .12);
    }
    .po-month-unpaid-title {
        font-weight: 800;
        color: #7f1d1d;
        line-height: 1.2;
    }
    .po-month-unpaid-meta {
        color: #7b625e;
        font-size: .72rem;
        line-height: 1.25;
    }
    .po-month-unpaid-value {
        font-weight: 800;
        color: #991b1b;
        white-space: nowrap;
    }
    .po-board-card .action-icon-btn,
    .po-rincian-table .action-icon-btn {
        width: 30px !important;
        height: 30px !important;
        min-width: 30px !important;
        border-radius: 8px !important;
        padding: 0 !important;
    }
    .po-board-card .action-icon-btn i,
    .po-rincian-table .action-icon-btn i {
        font-size: .92rem !important;
    }
    .po-hero-body {
        padding: 0.95rem 1.15rem;
    }
    .po-hero-title {
        font-size: 1.55rem;
        line-height: 1.1;
        margin-bottom: 0;
    }
    .po-hero-actions {
        align-self: center;
    }
    .po-hero-btn {
        padding: 0.58rem 0.9rem;
        border-radius: 14px;
        box-shadow: 0 8px 18px rgba(159, 33, 65, 0.15);
    }
    @media (max-width: 767.98px) {
        .po-hero-title {
            font-size: 1.45rem;
        }
        .po-hero-body {
            padding: 0.9rem 1rem;
        }
        .po-hero-actions {
            width: 100%;
        }
        .po-hero-btn {
            width: 100%;
            justify-content: center;
        }
        /* Reuse the same rows/controls: no duplicated status-update handlers. */
        .po-table-wrap {
            overflow: visible;
        }
        .po-table,
        .po-table tbody {
            display: block;
            width: 100%;
            min-width: 0;
        }
        .po-table colgroup {
            display: none;
        }
        .po-table thead {
            position: absolute;
            width: 1px;
            height: 1px;
            overflow: hidden;
            clip-path: inset(50%);
        }
        .po-table tbody tr {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            margin-bottom: .85rem;
            border: 1px solid #eadcd2;
            border-radius: 16px;
            background: #fffdfb;
            padding: .4rem;
        }
        .po-table tbody td,
        .po-table.po-rincian-table tbody td {
            display: block;
            width: auto;
            min-width: 0;
            padding: .55rem;
            border: 0;
            text-align: left !important;
            overflow-wrap: anywhere;
            white-space: normal;
            overflow: visible;
            text-overflow: clip;
            box-shadow: none;
            background: transparent;
            font-size: .875rem;
        }
        .po-table tbody td::before {
            content: attr(data-label);
            display: block;
            color: #7b625e;
            font-size: .72rem;
            font-weight: 600;
            margin-bottom: .3rem;
        }
        .po-table td.po-mobile-wide,
        .po-table td[colspan],
        .po-table td.action-cell,
        .po-table td.po-action-cell {
            grid-column: 1 / -1;
        }
        .po-table td[colspan]::before {
            display: none;
        }
        .po-table .po-cell-title,
        .po-table .po-rincian-main,
        .po-table .po-money-main {
            font-size: .875rem;
            text-align: left;
        }
        .po-table .po-cell-subtext {
            font-size: .78rem;
            line-height: 1.4;
        }
        .po-table .po-status-cell {
            align-items: flex-start;
            justify-content: flex-start;
        }
        .po-table .po-status-next {
            width: 100%;
            min-height: 44px !important;
            font-size: .8rem;
        }
        .po-table .po-status-pill {
            white-space: normal;
            overflow-wrap: anywhere;
            line-height: 1.35;
            font-size: .7rem;
        }
        .po-board-card .po-table .action-icon-btn {
            width: 44px !important;
            height: 44px !important;
            min-width: 44px !important;
        }
        .po-purpose-flare-list {
            grid-template-columns: minmax(0, 1fr);
        }
        .po-month-unpaid-item {
            grid-template-columns: minmax(0, 1fr);
        }
    }
    @media (max-width: 399.98px) {
        .po-table tbody tr {
            grid-template-columns: minmax(0, 1fr);
        }
    }
</style>

<div class="card po-hero mb-3">
    <div class="po-hero-body d-flex justify-content-between align-items-start flex-wrap gap-3">
        <div>
            <h4 class="po-hero-title d-flex align-items-center gap-2"><i class="ri ri-shopping-cart-line text-primary"></i><span>Purchase Order</span></h4>
            <div class="po-hero-meta">
                <span class="po-hero-chip">Status: <?php echo html_escape($summaryStatusText); ?></span>
                <span class="po-hero-chip"><?php echo html_escape($summaryRangeText); ?></span>
                <span class="po-hero-chip">View: <?php echo $activeTab === 'rincian' ? 'Per Rincian' : ($activeTab === 'paid' ? 'Tgl Paid' : 'Per Nota'); ?></span>
            </div>
        </div>
        <div class="po-hero-actions">
            <a class="btn d-inline-flex align-items-center gap-2 po-hero-btn" href="<?php echo site_url('purchase-orders/create'); ?>"><i class="ri ri-add-line"></i>Create Order</a>
        </div>
    </div>
</div>

<?php
$monthUnpaidCount = (int)($monthAttentionSummary['unpaid_count'] ?? 0);
$allUnpaidCount = (int)($allAttentionUnpaidDetails['total_count'] ?? 0);
?>
<?php if ($monthUnpaidCount > 0 || $allUnpaidCount > 0): ?>
<div class="alert alert-danger po-month-warning mb-3" role="alert">
    <div class="fw-semibold mb-1">Perhatian Bulan Ini</div>
    <?php if ($monthUnpaidCount > 0): ?>
        <div>
            Masih ada <?php echo number_format($monthUnpaidCount); ?> purchase order bulan ini yang statusnya belum PAID.
            Total nilai belum PAID bulan ini: <strong>Rp <?php echo number_format((float)($monthAttentionSummary['unpaid_value'] ?? 0), 2, ',', '.'); ?></strong>.
        </div>
    <?php else: ?>
        <div>
            Tidak ada purchase order bulan ini yang belum PAID, tetapi masih ada tunggakan purchase order dari periode sebelumnya.
        </div>
    <?php endif; ?>
    <?php if ($allUnpaidCount > 0): ?>
        <div class="mt-1">
            Total semua periode: <strong><?php echo number_format($allUnpaidCount); ?> nota</strong>
            / <strong>Rp <?php echo number_format((float)($allAttentionUnpaidDetails['total_value'] ?? 0), 2, ',', '.'); ?></strong>.
        </div>
    <?php endif; ?>
    <?php if (!empty($monthAttentionUnpaidDetails['status_totals'])): ?>
        <div class="small mt-2">
            <?php
            $statusParts = [];
            foreach ((array)$monthAttentionUnpaidDetails['status_totals'] as $stRow) {
                $statusParts[] = html_escape((string)($stRow['status'] ?? '-')) . ': ' . number_format((int)($stRow['total_count'] ?? 0)) . ' nota / Rp ' . number_format((float)($stRow['total_value'] ?? 0), 2, ',', '.');
            }
            echo implode(' · ', $statusParts);
            ?>
        </div>
    <?php endif; ?>
    <?php if (!empty($monthAttentionUnpaidDetails['rows'])): ?>
        <div class="small fw-semibold mt-2">Rincian bulan ini</div>
        <div class="po-month-unpaid-list">
            <?php foreach ((array)$monthAttentionUnpaidDetails['rows'] as $unpaidRow): ?>
                <a class="po-month-unpaid-item text-decoration-none" href="<?php echo site_url('purchase-orders/edit/' . (int)($unpaidRow['id'] ?? 0)); ?>">
                    <span>
                        <span class="po-month-unpaid-title"><?php echo html_escape((string)($unpaidRow['po_no'] ?? '-')); ?></span>
                        <span class="po-month-unpaid-meta d-block">
                            <?php echo html_escape((string)($unpaidRow['request_date'] ?? '-')); ?>
                            · <?php echo html_escape((string)($unpaidRow['status'] ?? '-')); ?>
                            · <?php echo html_escape((string)($unpaidRow['purchase_type_name'] ?? '-')); ?>
                            <?php if (!empty($unpaidRow['vendor_name'])): ?> · <?php echo html_escape((string)$unpaidRow['vendor_name']); ?><?php endif; ?>
                        </span>
                    </span>
                    <span class="po-month-unpaid-value">Rp <?php echo number_format((float)($unpaidRow['grand_total'] ?? 0), 2, ',', '.'); ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <?php if (!empty($allAttentionUnpaidDetails['rows'])): ?>
        <div class="small fw-semibold mt-2">Rincian semua periode belum PAID</div>
        <div class="po-month-unpaid-list">
            <?php foreach ((array)$allAttentionUnpaidDetails['rows'] as $unpaidRow): ?>
                <a class="po-month-unpaid-item text-decoration-none" href="<?php echo site_url('purchase-orders/edit/' . (int)($unpaidRow['id'] ?? 0)); ?>">
                    <span>
                        <span class="po-month-unpaid-title"><?php echo html_escape((string)($unpaidRow['po_no'] ?? '-')); ?></span>
                        <span class="po-month-unpaid-meta d-block">
                            <?php echo html_escape((string)($unpaidRow['request_date'] ?? '-')); ?>
                            · <?php echo html_escape((string)($unpaidRow['status'] ?? '-')); ?>
                            · <?php echo html_escape((string)($unpaidRow['purchase_type_name'] ?? '-')); ?>
                            <?php if (!empty($unpaidRow['vendor_name'])): ?> · <?php echo html_escape((string)$unpaidRow['vendor_name']); ?><?php endif; ?>
                        </span>
                    </span>
                    <span class="po-month-unpaid-value">Rp <?php echo number_format((float)($unpaidRow['grand_total'] ?? 0), 2, ',', '.'); ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php if ((int)($usagePurposeAttention['total_lines'] ?? 0) > 0): ?>
<div class="po-purpose-flare mb-3 p-3" role="alert">
    <div class="po-purpose-flare-head">
        <div class="d-flex gap-2">
            <span class="po-purpose-flare-icon"><i class="ri ri-alarm-warning-line"></i></span>
            <div>
                <div class="po-purpose-flare-title">Peruntukan belanja perlu ditinjau</div>
                <div class="po-purpose-flare-meta">
                    <?php echo number_format((int)($usagePurposeAttention['total_lines'] ?? 0)); ?> line pada
                    <?php echo number_format((int)($usagePurposeAttention['document_count'] ?? 0)); ?> PO di rentang tanggal ini
                    tidak selaras dengan default jenis belanja dan material, atau merupakan material stok yang ditandai operasional. Hanya PO aktif sampai tahap PAID yang dihitung; REJECTED dan VOID dikecualikan. Data tidak diubah otomatis.
                </div>
            </div>
        </div>
        <div class="po-purpose-flare-chips">
            <?php if ((int)($usagePurposeAttention['material_operational_count'] ?? 0) > 0): ?>
                <span class="po-purpose-flare-chip"><?php echo number_format((int)$usagePurposeAttention['material_operational_count']); ?> material operasional</span>
            <?php endif; ?>
            <?php if ((int)($usagePurposeAttention['purpose_mismatch_count'] ?? 0) > 0): ?>
                <span class="po-purpose-flare-chip"><?php echo number_format((int)$usagePurposeAttention['purpose_mismatch_count']); ?> tujuan ≠ default</span>
            <?php endif; ?>
            <span class="po-purpose-flare-chip">Rp <?php echo number_format((float)($usagePurposeAttention['total_value'] ?? 0), 2, ',', '.'); ?></span>
        </div>
    </div>
    <?php if (!empty($usagePurposeAttention['rows'])): ?>
    <div class="po-purpose-flare-list">
        <?php foreach ((array)$usagePurposeAttention['rows'] as $attentionRow): ?>
            <a class="po-purpose-flare-item" href="<?php echo site_url('purchase-orders/detail/' . (int)($attentionRow['purchase_order_id'] ?? 0)); ?>">
                <span class="po-purpose-flare-doc">
                    <span><?php echo html_escape((string)($attentionRow['po_no'] ?? '-')); ?> · <?php echo html_escape((string)($attentionRow['request_date'] ?? '-')); ?></span>
                    <span><?php echo html_escape($usagePurposeIssueLabel((string)($attentionRow['issue_type'] ?? ''))); ?></span>
                </span>
                <span class="po-purpose-flare-line"><?php echo html_escape((string)($attentionRow['line_name'] ?? '-')); ?></span>
                <small>Transaksi: <?php echo html_escape($usagePurposeLabel((string)($attentionRow['actual_usage_purpose'] ?? ''))); ?> · Default: <?php echo html_escape($usagePurposeLabel((string)($attentionRow['default_usage_purpose'] ?? ''))); ?></small>
            </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php $this->load->view('purchase/_po_sr_tabs', ['po_sr_active' => 'purchase-order']); ?>

<div class="row g-3 mb-3">
    <div class="col-md-3">
        <div class="card h-100 po-summary-card po-summary-card--primary">
            <div class="card-body">
                <div class="po-summary-head">
                    <div>
                        <span class="po-summary-kicker">Total PO</span>
                        <div class="po-summary-count"><?php echo number_format((int)($cardSummary['total_count'] ?? 0)); ?> nota</div>
                    </div>
                    <span class="po-summary-icon"><i class="ri ri-file-list-3-line"></i></span>
                </div>
                <span class="po-summary-value">Rp <?php echo number_format((float)($cardSummary['total_value'] ?? 0), 2, ',', '.'); ?></span>
                <div class="po-summary-meta">
                    <span class="po-summary-chip">Status: <?php echo html_escape($summaryStatusText); ?></span>
                    <span class="po-summary-chip"><?php echo html_escape($summaryRangeText); ?></span>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card h-100 po-summary-card po-summary-card--paid">
            <div class="card-body">
                <div class="po-summary-head">
                    <div>
                        <span class="po-summary-kicker">PO Paid</span>
                        <div class="po-summary-count"><?php echo number_format((int)($cardSummary['paid_count'] ?? 0)); ?> nota</div>
                    </div>
                    <span class="po-summary-icon"><i class="ri ri-money-dollar-circle-line"></i></span>
                </div>
                <span class="po-summary-value">Rp <?php echo number_format((float)($cardSummary['paid_value'] ?? 0), 2, ',', '.'); ?></span>
                <div class="po-summary-meta">
                    <span class="po-summary-chip">Sudah dibayar</span>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card h-100 po-summary-card po-summary-card--open">
            <div class="card-body">
                <div class="po-summary-head">
                    <div>
                        <span class="po-summary-kicker">Belum Paid</span>
                        <div class="po-summary-count"><?php echo number_format((int)($cardSummary['unpaid_count'] ?? 0)); ?> nota</div>
                    </div>
                    <span class="po-summary-icon"><i class="ri ri-timer-line"></i></span>
                </div>
                <span class="po-summary-value">Rp <?php echo number_format((float)($cardSummary['unpaid_value'] ?? 0), 2, ',', '.'); ?></span>
                <div class="po-summary-meta">
                    <span class="po-summary-chip">Belum status Paid</span>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card h-100 po-summary-card po-summary-card--lines">
            <div class="card-body">
                <div class="po-summary-head">
                    <div>
                        <span class="po-summary-kicker">Rincian Filter</span>
                        <div class="po-summary-count"><?php echo number_format((int)($lineSummary['total_lines'] ?? 0)); ?> baris</div>
                    </div>
                    <span class="po-summary-icon"><i class="ri ri-list-check-2"></i></span>
                </div>
                <span class="po-summary-value">Rp <?php echo number_format((float)($lineSummary['total_value'] ?? 0), 2, ',', '.'); ?></span>
                <div class="po-summary-meta">
                    <span class="po-summary-chip">Qty <?php echo number_format((float)($lineSummary['total_qty_buy'] ?? 0), 2, ',', '.'); ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$pmTotal = array_sum(array_column($paymentMethodBreakdown, 'total_value'));
$ptTotal = array_sum(array_column($typeBreakdown, 'total_value'));
?>
<div class="row g-3 mb-3">
    <div class="col-md-6">
        <div class="card h-100 po-summary-card po-summary-card--payment">
            <div class="card-body">
                <div class="po-summary-head">
                    <div>
                        <span class="po-summary-kicker">Metode Pembayaran</span>
                        <div class="po-summary-count"><?php echo count($paymentMethodBreakdown); ?> rekening</div>
                    </div>
                    <span class="po-summary-icon"><i class="ri ri-bank-card-line"></i></span>
                </div>
                <?php if (empty($paymentMethodBreakdown)): ?>
                    <p class="text-muted mb-0" style="font-size:.76rem;">Belum ada data.</p>
                <?php else: ?>
                <table class="po-breakdown-table">
                    <?php foreach ($paymentMethodBreakdown as $pm): ?>
                    <?php $pct = $pmTotal > 0 ? round((float)$pm['total_value'] / $pmTotal * 100) : 0; ?>
                    <tr>
                        <td class="po-breakdown-name" title="<?php echo html_escape((string)$pm['method_name']); ?>"><?php echo html_escape((string)$pm['method_name']); ?></td>
                        <td class="po-breakdown-count"><?php echo (int)$pm['po_count']; ?> nota</td>
                        <td>Rp <?php echo number_format((float)$pm['total_value'], 0, ',', '.'); ?></td>
                    </tr>
                    <tr>
                        <td colspan="3" style="padding-top:0;padding-bottom:.3rem;">
                            <div class="po-breakdown-bar-wrap"><div class="po-breakdown-bar" style="width:<?php echo $pct; ?>%"></div></div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card h-100 po-summary-card po-summary-card--type">
            <div class="card-body">
                <div class="po-summary-head">
                    <div>
                        <span class="po-summary-kicker">Tipe Purchase</span>
                        <div class="po-summary-count"><?php echo count($typeBreakdown); ?> tipe</div>
                    </div>
                    <span class="po-summary-icon"><i class="ri ri-price-tag-3-line"></i></span>
                </div>
                <?php if (empty($typeBreakdown)): ?>
                    <p class="text-muted mb-0" style="font-size:.76rem;">Belum ada data.</p>
                <?php else: ?>
                <table class="po-breakdown-table">
                    <?php foreach ($typeBreakdown as $pt): ?>
                    <?php $pct = $ptTotal > 0 ? round((float)$pt['total_value'] / $ptTotal * 100) : 0; ?>
                    <tr>
                        <td class="po-breakdown-name" title="<?php echo html_escape((string)$pt['type_name']); ?>"><?php echo html_escape((string)$pt['type_name']); ?></td>
                        <td class="po-breakdown-count"><?php echo (int)$pt['po_count']; ?> nota</td>
                        <td>Rp <?php echo number_format((float)$pt['total_value'], 0, ',', '.'); ?></td>
                    </tr>
                    <tr>
                        <td colspan="3" style="padding-top:0;padding-bottom:.3rem;">
                            <div class="po-breakdown-bar-wrap"><div class="po-breakdown-bar" style="width:<?php echo $pct; ?>%"></div></div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="card mt-3 po-board-card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3 po-toolbar">
            <h6 class="mb-0">Daftar Purchase Order</h6>
            <div class="d-flex gap-2">
                <input type="text" id="po-q" class="form-control form-control-sm" placeholder="Cari PO/vendor/tipe..." value="<?php echo html_escape((string)($q ?? '')); ?>">
                <select id="po-status" class="form-select form-select-sm">
                    <?php foreach (($status_options ?? ['ALL']) as $st): ?>
                        <option value="<?php echo html_escape((string)$st); ?>" <?php echo ((string)($status ?? 'ALL') === (string)$st) ? 'selected' : ''; ?>><?php echo html_escape((string)$st); ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="date" id="po-date-start" class="form-control form-control-sm" value="<?php echo html_escape($dateStart); ?>">
                <input type="date" id="po-date-end" class="form-control form-control-sm" value="<?php echo html_escape($dateEnd); ?>">
                <button type="button" id="btn-filter-po" class="btn btn-sm btn-outline-secondary">Filter</button>
                <button type="button" id="btn-clear-po" class="btn btn-sm btn-outline-danger">Clear</button>
            </div>
        </div>

        <?php if ($activeTab !== 'paid'): ?>
        <ul class="nav nav-pills po-tab-strip mb-3" role="tablist" aria-label="Tab Pembagi Jenis">
            <?php foreach ($statusOptions as $statusOption): ?>
                <?php $statusOption = strtoupper(trim((string)$statusOption)); ?>
                <li class="nav-item" role="presentation">
                    <a class="nav-link po-status-tab-link <?php echo $activeStatus === $statusOption ? 'active' : ''; ?>" href="<?php echo html_escape($buildStatusUrl($statusOption)); ?>"><?php echo html_escape($statusLabel($statusOption)); ?></a>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>

        <ul class="nav nav-pills po-tab-strip mb-3" role="tablist" aria-label="Tab Pembagi Halaman">
            <li class="nav-item" role="presentation">
                <a class="nav-link po-tab-link po-view-tab-link <?php echo $activeTab === 'nota' ? 'active' : ''; ?>" href="<?php echo site_url('purchase-orders') . '?tab=nota&q=' . urlencode((string)($q ?? '')) . '&status=' . urlencode((string)($status ?? 'ALL')) . '&date_start=' . urlencode($dateStart) . '&date_end=' . urlencode($dateEnd); ?>">Per Nota</a>
            </li>
            <li class="nav-item" role="presentation">
                <a class="nav-link po-tab-link po-view-tab-link <?php echo $activeTab === 'rincian' ? 'active' : ''; ?>" href="<?php echo site_url('purchase-orders') . '?tab=rincian&q=' . urlencode((string)($q ?? '')) . '&status=' . urlencode((string)($status ?? 'ALL')) . '&date_start=' . urlencode($dateStart) . '&date_end=' . urlencode($dateEnd); ?>">Per Rincian</a>
            </li>
            <li class="nav-item" role="presentation">
                <a class="nav-link po-tab-link po-view-tab-link <?php echo $activeTab === 'paid' ? 'active' : ''; ?>" href="<?php echo site_url('purchase-orders') . '?tab=paid&q=' . urlencode((string)($q ?? '')) . '&date_start=' . urlencode($dateStart) . '&date_end=' . urlencode($dateEnd); ?>">Per Tgl Paid</a>
            </li>
        </ul>

        <?php if ($activeTab === 'paid'): ?>
        <div class="alert alert-info py-2 px-3 mb-3" style="font-size:.78rem;border-radius:12px;">
            <i class="ri ri-information-line me-1"></i>
            Filter tanggal pada tab ini berlaku untuk <strong>tanggal PAID</strong> (bukan tanggal PO). Hanya menampilkan PO dengan status <strong>PAID</strong>.
        </div>
        <?php endif; ?>

        <div class="tab-content p-0 border-0">
            <div class="tab-pane fade <?php echo $activeTab === 'nota' ? 'show active' : ''; ?>" id="po-tab-nota" role="tabpanel">
                <div class="po-table-wrap">
                    <table class="table table-sm table-striped align-middle po-table">
                        <colgroup>
                            <col class="po-col-po">
                            <col class="po-col-vendor">
                            <col class="po-col-purchase">
                            <col class="po-col-value">
                            <col class="po-col-status">
                            <col class="po-col-action">
                        </colgroup>
                        <thead>
                            <tr>
                                <th class="po-col-po">PO</th>
                                <th class="po-col-vendor">Vendor</th>
                                <th class="po-col-purchase">Pembelian</th>
                                <th class="text-end po-col-value">Nilai</th>
                                <th class="po-col-status">Status</th>
                                <th class="po-col-action text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($rows ?? [])): ?>
                                <tr><td colspan="6" class="text-center text-muted py-3">Belum ada data PO.</td></tr>
                            <?php else: ?>
                                <?php foreach (($rows ?? []) as $r): ?>
                                    <?php $statusCurrent = strtoupper((string)($r['status'] ?? 'DRAFT')); ?>
                                    <?php $canEditData = in_array($statusCurrent, ['DRAFT', 'APPROVED'], true); ?>
                                    <?php $requiresEditReview = (int)($r['requires_edit_review'] ?? 0) === 1; ?>
                                    <tr>
                                        <td data-label="PO &amp; Tanggal" class="po-mobile-wide">
                                            <div class="po-cell-title"><?php echo html_escape((string)($r['po_no'] ?? '-')); ?></div>
                                            <span class="po-cell-subtext"><?php echo html_escape((string)($r['request_date'] ?? '-')); ?></span>
                                        </td>
                                        <td data-label="Vendor">
                                            <div class="po-cell-title"><?php echo html_escape((string)($r['vendor_name'] ?? '-')); ?></div>
                                            <span class="po-cell-subtext"><?php echo html_escape((string)($r['destination_type'] ?? '-')); ?></span>
                                        </td>
                                        <td data-label="Pembelian">
                                            <div><?php echo html_escape((string)($r['purchase_type_name'] ?? '-')); ?></div>
                                            <span class="po-cell-subtext"><?php echo html_escape(trim((string)($r['payment_account_name'] ?? '')) !== '' ? (string)($r['payment_account_name'] ?? '') : '-'); ?></span>
                                        </td>
                                        <td class="text-end" data-label="Nilai">
                                            <div class="po-money-main"><?php echo number_format((float)($r['grand_total'] ?? 0), 2, ',', '.'); ?></div>
                                        </td>
                                        <td data-label="Status">
                                            <div class="po-status-cell">
                                                <div><span class="badge <?php echo $statusBadgeClass($statusCurrent); ?> po-status-pill"><?php echo html_escape($statusCurrent); ?></span></div>
                                            <?php
                                                $transitions = [
                                                    'DRAFT' => ['APPROVED', 'ORDERED', 'PARTIAL_RECEIVED', 'RECEIVED', 'PAID', 'REJECTED', 'VOID'],
                                                    'APPROVED' => ['ORDERED', 'PARTIAL_RECEIVED', 'RECEIVED', 'PAID', 'REJECTED', 'VOID'],
                                                    'ORDERED' => ['PARTIAL_RECEIVED', 'RECEIVED', 'PAID', 'REJECTED', 'VOID'],
                                                    'PARTIAL_RECEIVED' => ['RECEIVED', 'PAID', 'VOID'],
                                                    'RECEIVED' => ['PAID', 'VOID'],
                                                    'PAID' => ['VOID'],
                                                    'CLOSED' => ['VOID'],
                                                    'REJECTED' => [],
                                                    'VOID' => [],
                                                ];
                                                $nextOptions = [];
                                                foreach (($transitions[$statusCurrent] ?? []) as $nextStatus) {
                                                    if (!in_array($nextStatus, $nextOptions, true)) {
                                                        $nextOptions[] = $nextStatus;
                                                    }
                                                }
                                                $statusUpdateDisabled = !$canEditPo || empty($nextOptions) || $requiresEditReview;
                                            ?>
                                                <select class="form-select form-select-sm po-status-next" data-id="<?php echo (int)($r['id'] ?? 0); ?>" <?php echo $statusUpdateDisabled ? 'disabled' : ''; ?>>
                                                    <option value="">Status...</option>
                                                    <?php foreach ($nextOptions as $st): ?>
                                                        <option value="<?php echo html_escape($st); ?>"><?php echo html_escape($st); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <?php if ($requiresEditReview): ?>
                                                    <span class="text-warning po-status-note">Review via edit.</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td class="action-cell" data-label="Aksi">
                                            <div class="d-flex gap-1 flex-nowrap justify-content-end">
                                                <a href="<?php echo site_url('purchase-orders/detail/' . (int)($r['id'] ?? 0)); ?>" class="btn btn-sm btn-outline-info action-icon-btn" data-bs-toggle="tooltip" title="Detail" aria-label="Detail">
                                                    <i class="ri ri-eye-line"></i>
                                                </a>
                                                <?php if ($canEditPo && $canEditData): ?>
                                                    <a href="<?php echo site_url('purchase-orders/edit/' . (int)($r['id'] ?? 0)); ?>" class="btn btn-sm btn-outline-primary action-icon-btn" data-bs-toggle="tooltip" title="Edit" aria-label="Edit">
                                                        <i class="ri ri-edit-line"></i>
                                                    </a>
                                                <?php endif; ?>
                                                <?php if (!$statusUpdateDisabled): ?>
                                                    <button type="button" class="btn btn-sm btn-outline-warning btn-po-status-update action-icon-btn" data-id="<?php echo (int)($r['id'] ?? 0); ?>" data-bs-toggle="tooltip" title="Update Status" aria-label="Update Status">
                                                        <i class="ri ri-refresh-line"></i>
                                                    </button>
                                                <?php elseif ($requiresEditReview): ?>
                                                    <button type="button" class="btn btn-sm btn-outline-secondary action-icon-btn" disabled title="Wajib review buyer via Edit Data dulu" aria-label="Update Status">
                                                        <i class="ri ri-refresh-line"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <button type="button" class="btn btn-sm btn-outline-secondary action-icon-btn" disabled title="Butuh izin edit" aria-label="Update Status">
                                                        <i class="ri ri-refresh-line"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="po-table-footer">
                    <div class="row g-2">
                        <div class="col-md-6">
                            <div class="po-footer-block">
                                <strong>Pagination Saat Ini</strong>
                                <span class="po-footer-value"><?php echo number_format($poPageCount); ?> nota</span>
                                <span>Nilai Rp <?php echo number_format($poPageValue, 2, ',', '.'); ?></span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="po-footer-block">
                                <strong>Total Range Filter</strong>
                                <span class="po-footer-value"><?php echo number_format((int)($filteredSummary['total_count'] ?? 0)); ?> nota</span>
                                <span>Nilai Rp <?php echo number_format((float)($filteredSummary['total_value'] ?? 0), 2, ',', '.'); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="tab-pane fade <?php echo $activeTab === 'rincian' ? 'show active' : ''; ?>" id="po-tab-rincian" role="tabpanel">
                <div class="po-table-wrap">
                    <table class="table table-sm table-striped align-middle po-table po-rincian-table">
                        <colgroup>
                            <col style="width:13%">
                            <col class="po-col-type">
                            <col style="width:26%">
                            <col class="po-col-value">
                            <col class="po-col-value">
                            <col class="po-col-value">
                            <col style="width:14%">
                            <col class="po-col-status">
                        </colgroup>
                        <thead>
                            <tr>
                                <th>PO &amp; Tanggal</th>
                                <th class="po-col-type">Tipe Belanja</th>
                                <th>Rincian</th>
                                <th class="po-col-value">Qty</th>
                                <th class="po-col-value">UOM Isi</th>
                                <th class="po-col-status">Nilai</th>
                                <th>Rekening Pembayaran</th>
                                <th class="text-center po-col-status">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($line_rows ?? [])): ?>
                                <tr><td colspan="8" class="text-center text-muted py-3">Belum ada data rincian PO.</td></tr>
                            <?php else: ?>
                                <?php foreach (($line_rows ?? []) as $lr): ?>
                                    <?php
                                        $st = strtoupper((string)($lr['status'] ?? 'DRAFT'));
                                        $lineName = trim((string)($lr['snapshot_item_name'] ?? ''));
                                        if ($lineName === '') {
                                            $lineName = trim((string)($lr['snapshot_material_name'] ?? ''));
                                        }
                                        if ($lineName === '') {
                                            $lineName = trim((string)($lr['snapshot_line_description'] ?? '-'));
                                        }
                                        $lineBrand = trim((string)($lr['snapshot_brand_name'] ?? ''));
                                        $lineDesc  = trim((string)($lr['snapshot_line_description'] ?? ''));
                                        $paymentAcct = trim((string)($lr['payment_account_name'] ?? ''));
                                    ?>
                                    <tr>
                                        <td class="rincian-text-left po-mobile-wide" data-label="PO &amp; Tanggal">
                                            <div class="po-cell-title"><?php echo html_escape((string)($lr['po_no'] ?? '-')); ?></div>
                                            <span class="po-cell-subtext"><?php echo html_escape((string)($lr['request_date'] ?? '-')); ?></span>
                                        </td>
                                        <td class="rincian-text-left" data-label="Tipe Belanja">
                                            <span class="po-rincian-main"><?php echo html_escape((string)($lr['purchase_type_name'] ?? '-')); ?></span>
                                        </td>
                                        <td class="rincian-text-left" data-label="Rincian">
                                            <div class="po-rincian-main"><?php echo html_escape($lineName); ?></div>
                                            <?php if ($lineBrand !== ''): ?>
                                            <span class="po-cell-subtext"><?php echo html_escape($lineBrand); ?></span>
                                            <?php endif; ?>
                                            <?php if ($lineDesc !== '' && $lineDesc !== $lineName): ?>
                                            <span class="po-cell-subtext"><?php echo html_escape($lineDesc); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="rincian-number" data-label="Qty">
                                            <span class="po-rincian-main"><?php echo number_format((float)($lr['qty_buy'] ?? 0), 2, ',', '.'); ?></span>
                                        </td>
                                        <td class="rincian-number" data-label="UOM Isi">
                                            <span class="po-rincian-main">
                                                <?php echo number_format((float)($lr['content_per_buy'] ?? 0), 2, ',', '.'); ?>
                                                <?php echo html_escape((string)($lr['snapshot_content_uom_code'] ?? '-')); ?>
                                            </span>
                                        </td>
                                        <td class="rincian-number" data-label="Nilai">
                                            <span class="po-rincian-main"><?php echo number_format((float)($lr['line_subtotal'] ?? 0), 2, ',', '.'); ?></span>
                                        </td>
                                        <td class="rincian-text-left" data-label="Rekening Pembayaran">
                                            <span class="po-rincian-main"><?php echo html_escape($paymentAcct !== '' ? $paymentAcct : '-'); ?></span>
                                        </td>
                                        <td class="rincian-center" data-label="Status">
                                            <span class="badge <?php echo $statusBadgeClass($st); ?> po-status-pill po-status-pill--xs"><?php echo html_escape($st); ?></span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="po-table-footer">
                    <div class="row g-2">
                        <div class="col-md-6">
                            <div class="po-footer-block">
                                <strong>Pagination Saat Ini</strong>
                                <span class="po-footer-value"><?php echo number_format($poLinePageCount); ?> baris</span>
                                <span>Qty <?php echo number_format($poLinePageQty, 2, ',', '.'); ?> | Nilai Rp <?php echo number_format($poLinePageValue, 2, ',', '.'); ?></span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="po-footer-block">
                                <strong>Total Range Filter</strong>
                                <span class="po-footer-value"><?php echo number_format((int)($lineSummary['total_lines'] ?? 0)); ?> baris</span>
                                <span>Qty <?php echo number_format((float)($lineSummary['total_qty_buy'] ?? 0), 2, ',', '.'); ?> | Nilai Rp <?php echo number_format((float)($lineSummary['total_value'] ?? 0), 2, ',', '.'); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="tab-pane fade <?php echo $activeTab === 'paid' ? 'show active' : ''; ?>" id="po-tab-paid" role="tabpanel">
                <div class="po-table-wrap">
                    <table class="table table-sm table-striped align-middle po-table">
                        <colgroup>
                            <col style="width:11%">
                            <col class="po-col-po">
                            <col class="po-col-vendor">
                            <col class="po-col-purchase">
                            <col class="po-col-value">
                            <col class="po-col-action">
                        </colgroup>
                        <thead>
                            <tr>
                                <th>Tgl Paid</th>
                                <th class="po-col-po">PO</th>
                                <th class="po-col-vendor">Vendor</th>
                                <th class="po-col-purchase">Pembelian</th>
                                <th class="text-end po-col-value">Nilai</th>
                                <th class="po-col-action text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($paidRows)): ?>
                                <tr><td colspan="6" class="text-center text-muted py-3">Belum ada PO PAID pada rentang tanggal ini.</td></tr>
                            <?php else: ?>
                                <?php foreach ($paidRows as $pr): ?>
                                    <tr>
                                        <td data-label="Tgl Paid">
                                            <div class="po-cell-title" style="color:#0f766e;"><?php echo html_escape((string)($pr['paid_date'] ?? '-')); ?></div>
                                        </td>
                                        <td data-label="PO &amp; Tanggal" class="po-mobile-wide">
                                            <div class="po-cell-title"><?php echo html_escape((string)($pr['po_no'] ?? '-')); ?></div>
                                            <span class="po-cell-subtext"><?php echo html_escape((string)($pr['request_date'] ?? '-')); ?></span>
                                        </td>
                                        <td data-label="Vendor">
                                            <div class="po-cell-title"><?php echo html_escape((string)($pr['vendor_name'] ?? '-')); ?></div>
                                            <span class="po-cell-subtext"><?php echo html_escape((string)($pr['destination_type'] ?? '-')); ?></span>
                                        </td>
                                        <td data-label="Pembelian">
                                            <div><?php echo html_escape((string)($pr['purchase_type_name'] ?? '-')); ?></div>
                                            <span class="po-cell-subtext"><?php echo html_escape(trim((string)($pr['payment_account_name'] ?? '')) !== '' ? (string)$pr['payment_account_name'] : '-'); ?></span>
                                        </td>
                                        <td class="text-end" data-label="Nilai">
                                            <div class="po-money-main"><?php echo number_format((float)($pr['grand_total'] ?? 0), 2, ',', '.'); ?></div>
                                        </td>
                                        <td class="po-action-cell" data-label="Aksi">
                                            <a href="<?php echo site_url('purchase-orders/detail/' . (int)($pr['id'] ?? 0)); ?>" class="btn btn-sm btn-outline-secondary action-icon-btn" title="Detail"><i class="ri ri-eye-line"></i></a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="po-table-footer">
                    <div class="row g-2">
                        <div class="col-md-6">
                            <div class="po-footer-block">
                                <strong>Pagination Saat Ini</strong>
                                <span class="po-footer-value"><?php echo number_format($poPaidPageCount); ?> nota</span>
                                <span>Nilai Rp <?php echo number_format($poPaidPageValue, 2, ',', '.'); ?></span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="po-footer-block">
                                <strong>Total Range Filter (Tgl Paid)</strong>
                                <span class="po-footer-value"><?php echo number_format((int)($paidSummary['total_count'] ?? 0)); ?> nota</span>
                                <span>Nilai Rp <?php echo number_format((float)($paidSummary['total_value'] ?? 0), 2, ',', '.'); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    function parseJsonResponse(response) {
        return response.text().then(function (txt) {
            var parsed = null;
            try {
                parsed = JSON.parse(txt);
            } catch (e) {
                var plainText = String(txt || '').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
                var messageMatch = plainText.match(/Message:\s*([^\r\n]+)/i);
                throw new Error((messageMatch && messageMatch[1]) ? messageMatch[1].trim() : 'Server gagal memproses perubahan status. Muat ulang halaman lalu coba lagi.');
            }
            return { status: response.status, json: parsed };
        });
    }

    var currentTab = <?php echo json_encode($activeTab); ?>;
    var filterBtn = document.getElementById('btn-filter-po');
    if (filterBtn) {
        filterBtn.addEventListener('click', function () {
            var q = document.getElementById('po-q').value || '';
            var status = document.getElementById('po-status').value || 'ALL';
            var dateStart = document.getElementById('po-date-start').value || '';
            var dateEnd = document.getElementById('po-date-end').value || '';
            var url = <?php echo json_encode(site_url('purchase-orders')); ?>
                + '?tab=' + encodeURIComponent(currentTab)
                + '&q=' + encodeURIComponent(q)
                + '&date_start=' + encodeURIComponent(dateStart)
                + '&date_end=' + encodeURIComponent(dateEnd);
            if (currentTab !== 'paid') {
                url += '&status=' + encodeURIComponent(status);
            }
            window.location.href = url;
        });
    }

    var clearBtn = document.getElementById('btn-clear-po');
    if (clearBtn) {
        clearBtn.addEventListener('click', function () {
            var url = <?php echo json_encode(site_url('purchase-orders')); ?>
                + '?tab=' + encodeURIComponent(currentTab)
                + '&date_start=' + encodeURIComponent(<?php echo json_encode(date('Y-m-d')); ?>)
                + '&date_end=' + encodeURIComponent(<?php echo json_encode(date('Y-m-d')); ?>);
            if (currentTab !== 'paid') {
                url += '&status=ALL';
            }
            window.location.href = url;
        });
    }

    var canEditPo = <?php echo $canEditPo ? 'true' : 'false'; ?>;
    if (!canEditPo) {
        document.querySelectorAll('.po-status-next').forEach(function (el) {
            el.disabled = true;
        });
    }

    var endpoint = <?php echo json_encode(site_url('purchase/order/status-update')); ?>;
    document.querySelectorAll('.btn-po-status-update').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (!canEditPo) {
                window.alert('Akses edit status tidak tersedia untuk akun ini.');
                return;
            }

            var id = Number(btn.getAttribute('data-id') || 0);
            if (!id) {
                return;
            }

            var select = document.querySelector('.po-status-next[data-id="' + id + '"]');
            var status = select ? (select.value || '') : '';
            if (!status) {
                window.alert('Pilih status tujuan terlebih dahulu.');
                return;
            }

            if (window.FinanceUI && typeof window.FinanceUI.setButtonLoading === 'function') {
                window.FinanceUI.setButtonLoading(btn, 'Memproses...');
            } else {
                btn.disabled = true;
            }

            fetch(endpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-Purchase-Mutation-CSRF': <?php echo json_encode($purchaseMutationCsrfToken); ?> },
                body: JSON.stringify({ purchase_order_id: id, status: status })
            })
            .then(parseJsonResponse)
            .then(function (res) {
                if (res.status >= 400 || !res.json || !res.json.ok) {
                    throw new Error((res.json && res.json.message) ? res.json.message : 'Gagal update status');
                }
                window.location.reload();
            })
            .catch(function (err) {
                if (window.FinanceUI && typeof window.FinanceUI.clearButtonLoading === 'function') {
                    window.FinanceUI.clearButtonLoading(btn);
                } else {
                    btn.disabled = false;
                }
                window.alert(err.message || 'Gagal update status');
            });
        });
    });
})();
</script>
