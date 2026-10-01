<?php
defined('BASEPATH') OR exit('No direct script access allowed');
require_once APPPATH . 'libraries/Report_workspace.php';
require_once APPPATH . 'libraries/Finance_mutation_policy.php';
require_once APPPATH . 'libraries/Monthly_finance_sales.php';

/** Cash analysis only. Never treats inventory transfers or purchase orders as cash. */
class Monthly_finance_model extends CI_Model
{
    private function rows(string $sql, array $binds = []): array
    {
        $q = $this->db->query($sql, $binds);
        if (!$q) { throw new RuntimeException('Query analisis keuangan gagal.'); }
        return $q->result_array();
    }

    public function accounts(): array
    {
        // Include inactive accounts: their historical transactions remain real.
        return $this->rows('SELECT id,account_name,currency_code,is_active FROM fin_company_account ORDER BY currency_code,account_name');
    }

    public function monthOptions(array $f): array
    {
        $row=$this->rows('SELECT MIN(mutation_date) first_date FROM fin_account_mutation_log')[0];
        return Report_workspace::monthOptions($f,empty($row['first_date'])?null:substr($row['first_date'],0,7));
    }

    public static function classify(array $row): array
    {
        $module = strtoupper((string)($row['origin_module'] ?? ''));
        $table = (string)($row['origin_table'] ?? '');
        $category = (string)($row['origin_category'] ?? '');
        if ($module === 'FINANCE_TRANSFER') { return ['TRANSFER','Transfer antar rekening','transfer']; }
        if ($module === 'PURCHASE') { return ['PURCHASE:'.(int)($row['purchase_type_id']??0),'Purchase / '.(($row['purchase_type_name']??'')?:'Tanpa tipe'),'purchase']; }
        if ($module === 'POS') {
            if (($row['pos_payment_type']??'')==='DEPOSIT') { return ['POS_DEPOSIT','DP / deposit pelanggan (bukan omzet)','deposit']; }
            if ($table==='pos_refund') { return ['POS_REFUND','Refund pelanggan / kas','sales']; }
            if (!empty($row['reversal_of_mutation_id'])) { return ['POS_REVERSAL','Pembalik pembayaran POS (void / koreksi)','sales']; }
            if (($row['mutation_type']??'')==='OUT') { return ['POS_PAYMENT_OUT','Kas keluar pembayaran POS (koreksi / pembatalan)','sales']; }
            return ['POS','Pembayaran POS tercatat (sebelum refund / pembalik)','sales'];
        }
        if ($module === 'PAYROLL') {
            $labels = ['pay_salary_disbursement'=>'Gaji','pay_meal_disbursement'=>'Uang makan','pay_cash_advance'=>'Kasbon karyawan'];
            return ['PAYROLL:'.$table, $labels[$table] ?? 'Payroll lainnya','payroll'];
        }
        if (in_array($module,['FINANCE_PAYABLE','FINANCE_RECEIVABLE'],true)) {
            $labels=['fin_payable'=>'Penerimaan utang','fin_payable_payment'=>'Pembayaran utang',
                'fin_receivable'=>'Pemberian piutang','fin_receivable_payment'=>'Penerimaan piutang'];
            return [$module.':'.$table,$labels[$table]??'Utang / piutang','financing'];
        }
        if (Finance_mutation_policy::manual_module($module) && isset(Finance_mutation_policy::categories()[$category])) {
            return [$category,Finance_mutation_policy::label($category),Finance_mutation_policy::categories()[$category]['effect']];
        }
        return ['UNCLASSIFIED','Belum diklasifikasikan','unclassified'];
    }

    public static function flows(): array
    {
        return ['sales'=>'Pembayaran POS & refund','business'=>'Pembelian & pengeluaran usaha',
            'debt'=>'Utang / pinjaman','receivable'=>'Piutang / kasbon','equity'=>'Modal / prive',
            'deposit'=>'DP / deposit pelanggan','other_income'=>'Pendapatan lain-lain',
            'correction'=>'Koreksi saldo','unclassified'=>'Belum diklasifikasikan','transfer'=>'Transfer internal'];
    }

    private static function flow(array $r): string
    {
        if ($r['category_code']==='PAYROLL:pay_cash_advance') { return 'receivable'; }
        if ($r['effect']==='financing') { return str_starts_with($r['category_code'],'FINANCE_PAYABLE:')?'debt':'receivable'; }
        if (in_array($r['category_code'],['OWNER_CAPITAL','OWNER_DRAWING'],true)) { return 'equity'; }
        if ($r['category_code']==='BALANCE_CORRECTION') { return 'correction'; }
        if (in_array($r['effect'],['purchase','payroll','expense'],true)) { return 'business'; }
        if ($r['effect']==='income') { return 'other_income'; }
        return in_array($r['effect'],['sales','deposit','transfer'],true)?$r['effect']:'unclassified';
    }

    public function report(array $f): array
    {
        $f=Report_workspace::financialFilters($f);
        $months=Report_workspace::financialMonths($f);
        $flowLabels=self::flows();
        if ($f['flow']!=='' && !isset($flowLabels[$f['flow']])) { throw new InvalidArgumentException('Kelompok arus tidak valid.'); }
        $accounts = $this->accounts();
        if ($f['account_id'] && !array_filter($accounts, static fn($a)=>(int)$a['id']===$f['account_id'] && $a['currency_code']===$f['currency'])) {
            throw new InvalidArgumentException('Rekening tidak ditemukan atau berbeda mata uang.');
        }
        $hasCategory = $this->db->field_exists('report_category','fin_account_mutation_log');
        $categorySql = $hasCategory ? 'COALESCE(o.report_category,m.report_category)' : 'NULL';
        $originTable = 'COALESCE(o.ref_table,m.ref_table)';
        $originId = 'COALESCE(o.ref_id,m.ref_id)';
        $sql = "SELECT m.*,a.account_name,a.currency_code,COALESCE(o.ref_module,m.ref_module) origin_module,
            $originTable origin_table, $categorySql origin_category,
            p.id purchase_order_id,p.po_no,p.purchase_type_id,t.type_name purchase_type_name,
            COALESCE(o.ref_no,m.ref_no) document_no, posp.payment_type pos_payment_type
            FROM fin_account_mutation_log m JOIN fin_company_account a ON a.id=m.account_id
            LEFT JOIN fin_account_mutation_log o ON o.id=m.reversal_of_mutation_id
            LEFT JOIN pur_purchase_payment_plan pp ON $originTable='pur_purchase_payment_plan' AND pp.id=$originId
            LEFT JOIN pur_purchase_order p ON p.id=CASE WHEN $originTable='pur_purchase_order' THEN $originId ELSE pp.purchase_order_id END
            LEFT JOIN mst_purchase_type t ON t.id=p.purchase_type_id
            LEFT JOIN pos_payment_line posl ON $originTable='pos_payment_line' AND posl.id=$originId
            LEFT JOIN pos_payment posp ON posp.id=CASE WHEN $originTable='pos_payment' THEN $originId ELSE posl.payment_id END
            WHERE m.mutation_date BETWEEN ? AND ? AND a.currency_code=?";
        $binds = [$f['month_from'].'-01', min(date('Y-m-d'),Report_workspace::month($f['month_to'])[1]),$f['currency']];
        if ($f['account_id']) { $sql.=' AND a.id=?'; $binds[]=$f['account_id']; }
        // Keep originals AND dated reversals. Removing them retrospectively
        // would rewrite a previous month's cash balances.
        $rows = $this->rows($sql.' ORDER BY m.mutation_date DESC,m.id DESC LIMIT 50001',$binds);
        if (count($rows)>50000) { throw new InvalidArgumentException('Data melebihi 50.000 mutasi. Pilih satu rekening; laporan tidak dipotong diam-diam.'); }
        $selected=[]; $daily=[]; $monthly=[]; $groups=[]; $options=[]; $matrix=[]; $flows=[];
        $empty=['in'=>0,'out'=>0,'net'=>0,'transfer_in'=>0,'transfer_out'=>0,'count'=>0,'reversals'=>0,'unclassified'=>0,
            'business_in'=>0,'business_out'=>0,'sales_in'=>0,'sales_out'=>0,'debt_in'=>0,'debt_out'=>0,'non_sales_in'=>0];
        $totals=$empty;
        foreach ($months as $month) { $monthly[$month]=['label'=>$month]+$empty; }
        foreach ($flowLabels as $code=>$label) { $flows[$code]=['code'=>$code,'label'=>$label,'in'=>0,'out'=>0,'count'=>0]; }
        for ($day=$f['from']; $day<=min($f['to'],date('Y-m-d')); $day=date('Y-m-d',strtotime($day.' +1 day'))) {
            $daily[$day]=['label'=>$day]+$empty;
        }
        $compare=$empty;
        $compareEnd=Report_workspace::month($f['compare'])[1];
        if ($f['month']===date('Y-m')) { $compareEnd=$f['compare'].'-'.sprintf('%02d',min((int)date('d'),(int)substr($compareEnd,8,2))); }
        foreach($rows as $r) {
            [$code,$label,$effect]=self::classify($r); $options[$code]=$label;
            $r['category_code']=$code; $r['category_name']=$label; $r['effect']=$effect;
            $r['flow_code']=self::flow($r); $r['flow_name']=$flowLabels[$r['flow_code']];
            if ($f['category']!=='' && $code!==$f['category']) { continue; }
            if ($f['flow']!=='' && $r['flow_code']!==$f['flow']) { continue; }
            if ($f['direction']!=='' && $r['mutation_type']!==$f['direction']) { continue; }
            if ($f['q']!=='' && stripos(implode(' ',[$r['mutation_no'],$r['ref_no'],$r['notes'],$r['account_name'],$r['po_no'],$label]),$f['q'])===false) { continue; }
            $r['amount_cents']=Report_workspace::cents($r['amount']);
            $month=substr($r['mutation_date'],0,7);
            self::add($monthly[$month],$r);
            $key=$code.':'.$r['mutation_type'];
            if (!isset($matrix[$key])) {
                $matrix[$key]=['key'=>$key,'code'=>$code,'label'=>$label,'flow'=>$r['flow_code'],'direction'=>$r['mutation_type'],
                    'values'=>array_fill_keys($months,0),'total'=>0,'count'=>0];
            }
            $matrix[$key]['values'][$month]+=$r['amount_cents'];
            $matrix[$key]['total']+=$r['amount_cents']; $matrix[$key]['count']++;
            // A running month is compared against the same elapsed day count.
            if ($month===$f['compare'] && $r['mutation_date']<=$compareEnd) { self::add($compare,$r); }
            if ($month!==$f['month']) { continue; }
            $day=$r['mutation_date'];
            self::add($daily[$day],$r);
            if ($f['day']!=='' && $r['mutation_date']!==$f['day']) { continue; }
            $selected[]=$r; self::add($totals,$r);
            $flows[$r['flow_code']][strtolower($r['mutation_type'])]+=$r['amount_cents'];
            $flows[$r['flow_code']]['count']++;
            if (!isset($groups[$code])) { $groups[$code]=['code'=>$code,'label'=>$label,'effect'=>$effect]+$empty; }
            self::add($groups[$code],$r);
        }
        ksort($monthly); ksort($daily); asort($options);
        $rank=array_flip(array_keys($flowLabels));
        uasort($matrix,static fn($a,$b)=>($rank[$a['flow']]<=>$rank[$b['flow']]) ?: ($b['total']<=>$a['total']) ?: strcmp($a['key'],$b['key']));
        uasort($groups,static fn($a,$b)=>($b['out']+$b['in']+$b['transfer_in']+$b['transfer_out'])<=>($a['out']+$a['in']+$a['transfer_in']+$a['transfer_out']));
        $balances=$this->balances($f);
        $sales=Monthly_finance_sales::read($this->db,$f);
        foreach ($monthly as &$month) { $month['omzet']=$sales['available']?0:null; } unset($month);
        foreach ($daily as &$day) { $day['omzet']=$sales['available']?0:null; } unset($day);
        foreach ($sales['daily'] as $date=>$sale) {
            $monthly[substr($date,0,7)]['omzet']+=$sale['value'];
            if (isset($daily[$date])) { $daily[$date]['omzet']=$sale['value']; }
        }
        return ['totals'=>$totals,'compare'=>$compare,'monthly'=>array_values($monthly),'daily'=>array_values($daily),
            'groups'=>array_values($groups),'category_options'=>$options,'accounts'=>$accounts,'balances'=>$balances,
            'rows'=>$selected,'pager'=>Report_workspace::page($selected,$f),'category_ready'=>$hasCategory,
            'partial_month'=>$f['month']===date('Y-m'),'compare_available'=>in_array($f['compare'],$months,true),
            'months'=>$months,'matrix'=>array_values($matrix),'flows'=>$flows,'flow_options'=>$flowLabels,'sales'=>$sales];
    }

    private static function add(array &$total,array $row): void
    {
        $direction=$row['mutation_type']==='IN'?'in':'out';
        $key=$row['effect']==='transfer'?'transfer_'.$direction:$direction;
        $total[$key]+=$row['amount_cents'];
        $total['net']+=($direction==='in'?1:-1)*$row['amount_cents'];
        $total['count']++;
        $total['reversals']+=!empty($row['reversal_of_mutation_id'])?1:0;
        $total['unclassified']+=$row['effect']==='unclassified'?1:0;
        if (in_array($row['flow_code'],['business','sales','debt'],true)) { $total[$row['flow_code'].'_'.$direction]+=$row['amount_cents']; }
        if ($direction==='in' && !in_array($row['flow_code'],['sales','transfer'],true)) { $total['non_sales_in']+=$row['amount_cents']; }
    }

    private function balances(array $f): array
    {
        $end=$f['month']===date('Y-m')?date('Y-m-d'):$f['to'];
        $sql="SELECT a.id,a.account_name,a.opening_balance,
            COALESCE(SUM(CASE WHEN m.mutation_date<? THEN CASE WHEN m.mutation_type='IN' THEN m.amount ELSE -m.amount END ELSE 0 END),0) prior_net,
            COALESCE(SUM(CASE WHEN m.mutation_date>=? AND m.mutation_type='IN' THEN m.amount ELSE 0 END),0) inflow,
            COALESCE(SUM(CASE WHEN m.mutation_date>=? AND m.mutation_type='OUT' THEN m.amount ELSE 0 END),0) outflow
            FROM fin_company_account a LEFT JOIN fin_account_mutation_log m ON m.account_id=a.id AND m.mutation_date<=?
            WHERE a.currency_code=?";
        $params=[$f['from'],$f['from'],$f['from'],$end,$f['currency']];
        if ($f['account_id']) { $sql.=' AND a.id=?'; $params[]=$f['account_id']; }
        $rows=$this->rows($sql.' GROUP BY a.id,a.account_name,a.opening_balance ORDER BY a.account_name',$params);
        $total=['opening'=>0,'in'=>0,'out'=>0,'closing'=>0];
        foreach($rows as &$r) {
            $r['opening']=Report_workspace::cents($r['opening_balance'])+Report_workspace::cents($r['prior_net']);
            $r['in']=Report_workspace::cents($r['inflow']); $r['out']=Report_workspace::cents($r['outflow']);
            $r['closing']=$r['opening']+$r['in']-$r['out'];
            foreach($total as $k=>$_) { $total[$k]+=$r[$k]; }
        }
        unset($r);
        return ['rows'=>$rows,'total'=>$total,'to'=>$end];
    }
}
