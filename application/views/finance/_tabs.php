<?php
$active = (string)($finance_tab_active ?? '');
$workspace_tabs = [
  ... ((!empty($user_perms['finance.monthly_analysis.index']['can_view']) || !empty($current_user['is_superadmin'])) ? [['label' => 'Analisis Bulanan', 'url' => site_url('finance-reports/monthly-analysis'), 'active' => $active === 'monthly-analysis']] : []),
  ['label' => 'Utang', 'url' => site_url('finance/utang'), 'active' => $active === 'payable'],
  ['label' => 'Piutang', 'url' => site_url('finance/piutang'), 'active' => $active === 'receivable'],
  ['label' => 'Pihak Luar', 'url' => site_url('finance/relasi'), 'active' => $active === 'party'],
  ['label' => 'Keuangan Harian', 'url' => site_url('finance-reports/daily-overview'), 'active' => $active === 'daily-overview'],
  ['label' => 'Estimasi Keuangan', 'url' => site_url('finance-reports/financial-estimation'), 'active' => $active === 'financial-estimation'],
  ['label' => 'Kontrol Keuangan', 'url' => site_url('finance-reports/control'), 'active' => $active === 'control'],
  ['label' => 'Akuntansi dan Jurnal', 'url' => site_url('finance-reports/accounting'), 'active' => $active === 'accounting'],
  ['label' => 'Rekap Rekening', 'url' => site_url('finance-reports/rekap-rekening-harian'), 'active' => $active === 'bank-daily-recap'],
  ['label' => 'Posisi Kas', 'url' => site_url('finance-reports/cash-position'), 'active' => $active === 'cash-position'],
  ['label' => 'Brankas Harian', 'url' => site_url('finance-reports/cash-vault-daily'), 'active' => $active === 'cash-vault'],
  ['label' => 'Rekonsiliasi Kas', 'url' => site_url('finance-reports/cash-reconciliation'), 'active' => $active === 'cash-reconciliation'],
  ['label' => 'Rekon Pendapatan', 'url' => site_url('finance-reports/revenue-reconciliation'), 'active' => $active === 'revenue-reconciliation'],
  ['label' => 'Tutup Periode', 'url' => site_url('finance-reports/period-close'), 'active' => $active === 'period-close'],
  ['label' => 'Target Keuangan', 'url' => site_url('finance-reports/targets'), 'active' => $active === 'target-plan'],
];
$workspace_tab_label = 'Keuangan';
$workspace_tab_aria_label = 'Navigasi workspace keuangan';
?>
<?php $this->load->view('layout/_workspace_tabs', get_defined_vars()); ?>
