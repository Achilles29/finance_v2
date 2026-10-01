<?php
$reportNavActive = trim((string)($report_nav_active ?? ''));
if ($reportNavActive === '') {
    $activeMenuValue = trim((string)($active_menu ?? ''));
    $map = [
        'pos.report.daily_sales' => 'daily_sales',
        'pos.report.sales' => 'sales',
        'pos.report.cost_control' => 'cost_control',
        'pos.report.sales.detail' => 'sales_detail',
        'pos.report.sales.extra' => 'sales_extra',
        'pos.report.sales.audit' => 'sales_audit',
        'pos.report.payment' => 'payment',
        'pos.report.payment.method' => 'payment_methods',
        'pos.report.payment.account' => 'payment_accounts',
        'pos.report.cashier.close' => 'cashier_close',
        'pos.report.refund' => 'refund',
        'pos.report.void' => 'void',
    ];
    $reportNavActive = $map[$activeMenuValue] ?? '';
}

$navItems = [
    'daily_sales' => ['label' => 'Daily Sales', 'hint' => 'Harian', 'url' => 'pos/reports/daily-sales', 'icon' => 'ri-calendar-check-line'],
    'sales' => ['label' => 'Penjualan', 'hint' => 'Transaksi', 'url' => 'pos/reports/sales', 'icon' => 'ri-receipt-line'],
    'cost_control' => ['label' => 'Cost Control', 'hint' => 'Biaya & HPP', 'url' => 'pos/reports/cost-control', 'icon' => 'ri-funds-box-line'],
    'sales_detail' => ['label' => 'Penjualan Produk', 'hint' => 'Per Item', 'url' => 'pos/reports/sales-detail', 'icon' => 'ri-file-list-3-line'],
    'sales_extra' => ['label' => 'Penjualan Extra', 'hint' => 'Per Extra', 'url' => 'pos/reports/sales-extra', 'icon' => 'ri-add-box-line'],
    'sales_audit' => ['label' => 'Audit Penjualan', 'hint' => 'HPP', 'url' => 'pos/reports/sales-audit', 'icon' => 'ri-shield-search-line'],
    'payment' => ['label' => 'Pembayaran', 'hint' => 'Settlement', 'url' => 'pos/reports/payments', 'icon' => 'ri-bank-card-line'],
    'payment_methods' => ['label' => 'Metode Bayar', 'hint' => 'Per Metode', 'url' => 'pos/reports/payment-methods', 'icon' => 'ri-bank-card-2-line'],
    'payment_accounts' => ['label' => 'Rekening Bayar', 'hint' => 'Per Akun', 'url' => 'pos/reports/payment-accounts', 'icon' => 'ri-wallet-3-line'],
    'cashier_close' => ['label' => 'Tutup Kasir', 'hint' => 'Shift', 'url' => 'pos/reports/cashier-close', 'icon' => 'ri-safe-2-line'],
    'refund' => ['label' => 'Refund', 'hint' => 'Retur', 'url' => 'pos/reports/refunds', 'icon' => 'ri-arrow-go-back-line'],
    'void' => ['label' => 'Void', 'hint' => 'Batal', 'url' => 'pos/reports/voids', 'icon' => 'ri-close-circle-line'],
];
?>
<style>
  .pos-report-tier-nav {
    display: flex;
    flex-wrap: wrap;
    gap: .5rem;
    margin-bottom: 1rem;
  }
  .pos-report-tier-link {
    display: inline-flex;
    align-items: center;
    gap: .55rem;
    border-radius: 10px;
    font-weight: 700;
    padding: .58rem .88rem;
    border: 1px solid #dfd5cb;
    background: #fff;
    color: #51453d;
    text-decoration: none;
    box-shadow: 0 6px 14px rgba(67, 89, 113, .04);
  }
  .pos-report-tier-link:hover {
    color: #3f342d;
    border-color: #d9c8bc;
    background: #fff8f3;
  }
  .pos-report-tier-link.is-active {
    background: #9f2141;
    border-color: #9f2141;
    color: #fff;
    box-shadow: 0 8px 18px rgba(159, 33, 65, .18);
  }
  .pos-report-tier-dot {
    width: .58rem;
    height: .58rem;
    border-radius: .18rem;
    background: #6a5c54;
    display: inline-block;
    flex: 0 0 .58rem;
  }
  .pos-report-tier-link.is-active .pos-report-tier-dot {
    background: #fff;
    opacity: .95;
  }
  .pos-report-tier-copy {
    display: flex;
    flex-direction: column;
    line-height: 1.05;
  }
  .pos-report-tier-copy small {
    font-size: .69rem;
    font-weight: 600;
    opacity: .72;
  }
  @media (max-width: 767.98px) {
    .pos-report-tier-link {
      width: 100%;
      justify-content: flex-start;
    }
  }
</style>
<div class="pos-report-tier-nav mb-3" role="navigation" aria-label="Tab Penghubung Laporan POS">
  <?php foreach ($navItems as $navKey => $navItem): ?>
    <a href="<?php echo site_url($navItem['url']); ?>" class="pos-report-tier-link<?php echo $reportNavActive === $navKey ? ' is-active' : ''; ?>">
      <span class="pos-report-tier-dot" aria-hidden="true"></span>
      <span class="pos-report-tier-copy">
        <span><?php echo html_escape($navItem['label']); ?></span>
        <small><?php echo html_escape((string)($navItem['hint'] ?? '')); ?></small>
      </span>
    </a>
  <?php endforeach; ?>
</div>
