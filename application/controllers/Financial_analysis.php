<?php
defined('BASEPATH') OR exit('No direct script access allowed');
require_once APPPATH . 'libraries/Report_workspace.php';

class Financial_analysis extends MY_Controller
{
    public function index()
    {
        $page='finance.monthly_analysis.index';
        $this->require_permission($page,'view');
        $export=in_array($this->input->get('export',true),['csv','matrix'],true);
        if ($export) { $this->require_permission($page,'export'); }
        $this->output->set_header('Cache-Control: private, no-store');
        $this->load->model('Monthly_finance_model');
        $data=['page_title'=>'Analisis Keuangan Bulanan','active_menu'=>'finance.monthly_analysis','finance_tab_active'=>'monthly-analysis',
            'error'=>'','report'=>[],'filters'=>Report_workspace::financialFilters([]),'can_export'=>$this->can($page,'export'),
            'can_purchase'=>$this->can('purchase.order.index','view'),'can_accounting'=>$this->can('finance.accounting.index','view'),
            'can_pos'=>$this->can('pos.report.sales.index','view'),
            'accounting'=>null,'accounting_error'=>'','inventory'=>null,'comparison'=>null,'month_options'=>[]];
        try {
            $data['filters']=$f=Report_workspace::financialFilters($this->input->get(NULL,true)?:[]);
            if (!$this->db->query('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY')) { throw new RuntimeException('Snapshot laporan tidak tersedia.'); }
            $data['report']=$this->Monthly_finance_model->report($f);
            if(in_array($f['view'],['efficiency','waste'],true)){
                $this->load->model('Finance_inventory_analysis_model');
                $data['inventory']=$this->Finance_inventory_analysis_model->report($f);
                if($export){$this->exportInventory($data['inventory'],$f);return;}
            }
            if($f['view']==='reconcile' && $data['report']['sales']['available']){
                $data['comparison']=Monthly_finance_sales::comparison($this->db,$f);
            }
            if ($export) {
                if ($this->input->get('export',true)==='matrix') {
                    $this->exportMatrix($data['report'],$f);
                    return;
                }
                if ($f['view']==='sales') {
                    if (!$data['report']['sales']['available']) { throw new InvalidArgumentException($data['report']['sales']['reason']); }
                    Report_workspace::export('omzet-pos-'.$f['month'],['event_date'=>'Tanggal pengakuan','document_no'=>'Dokumen',
                        'order_no'=>'Nota POS','event_type'=>'Jenis','billing'=>'Tagihan sebelum refund','tax'=>'Pajak penjualan',
                        'refund'=>'Refund','value'=>'Omzet neto tanpa pajak'],$data['report']['sales']['rows']);
                    return;
                }
                Report_workspace::export('keuangan-'.$f['month'].'-'.$f['currency'],['mutation_date'=>'Tanggal','mutation_no'=>'No mutasi',
                    'account_name'=>'Rekening','currency_code'=>'Mata uang','mutation_type'=>'Arah','flow_name'=>'Kelompok arus','category_name'=>'Kategori',
                    'amount'=>'Jumlah','ref_module'=>'Modul sumber','ref_table'=>'Tabel sumber','ref_id'=>'ID sumber','document_no'=>'Referensi',
                    'po_no'=>'Nomor PO','reversal_of_mutation_id'=>'Pembalik mutasi ID','notes'=>'Catatan'],$data['report']['rows']);
                return;
            }
            $data['month_options']=$this->Monthly_finance_model->monthOptions($f);
            if ($data['can_accounting'] && $f['currency']==='IDR' && $f['view']==='balances') {
                try {
                    $this->load->model('Finance_accounting_model');
                    if ($this->Finance_accounting_model->ready()) { $data['accounting']=$this->Finance_accounting_model->statements($f['month']); }
                } catch(Throwable $e) { $data['accounting_error']='Ringkasan jurnal belum tersedia. Arus kas di atas tetap berasal dari mutasi rekening.'; }
            }
        } catch(Throwable $e) {
            log_message('error','Monthly financial analysis: '.$e->getMessage());
            $data['error']=$e instanceof InvalidArgumentException?$e->getMessage():'Analisis belum dapat dimuat. Periksa kesiapan data atau hubungi administrator.';
            $this->output->set_status_header($e instanceof InvalidArgumentException?422:503);
        } finally {
            $this->db->query('ROLLBACK');
        }
        $this->render('reports/financial',$data);
    }

    private function exportInventory(array $report,array $f): void
    {
        if($f['view']==='waste'){
            Report_workspace::export('susut-'.$f['month'],['event_date'=>'Tanggal','movement_no'=>'Mutasi','kind'=>'Jenis stok',
                'division_name'=>'Divisi','location'=>'Lokasi','asset_name'=>'Bahan / component','profile'=>'Profil / lot','brand'=>'Merk',
                'loss_kind'=>'Kategori','reason'=>'Alasan','qty'=>'Qty isi','unit'=>'Satuan','value'=>'Nilai IDR','reversal'=>'Pembalik ID',
                'source_table'=>'Tabel sumber','source_id'=>'ID sumber','notes'=>'Catatan'],$report['losses'],['qty']);return;
        }
        $columns=['month'=>'Bulan','division_name'=>'Divisi','location'=>'Lokasi','revenue'=>'Omzet alokasi neto','hpp'=>'HPP POS','hpp_ratio'=>'HPP per omzet persen',
            'opname_before'=>'Opname bulan sebelumnya','opening'=>'Awal buku','purchase'=>'PO langsung','sr'=>'SR',
            'incoming'=>'Masuk buku','used'=>'Pemakaian ledger','transferred'=>'Transfer keluar','waste'=>'Waste','spoil'=>'Spoil',
            'process_loss'=>'Susut proses','variance'=>'Selisih hitung','minus'=>'Koreksi minus','plus'=>'Koreksi plus','closing'=>'Sisa buku',
            'opname_after'=>'Opname akhir','closing_component'=>'Sisa component','reconciliation_gap'=>'Selisih persamaan nilai buku','supply_ratio'=>'Rasio pengadaan persen','usage_ratio'=>'Rasio pemakaian persen'];
        $moneyFields=array_diff(array_keys($columns),['month','division_name','location','supply_ratio','usage_ratio','hpp_ratio']);$rows=[];
        foreach($report['groups'] as $group){
            $row=$group;foreach(['opening','incoming','waste','spoil','process_loss','variance','minus','plus','closing','reconciliation_gap'] as $key){$row[$key]=$group['material'][$key]??null;}
            $row['opname_before']=$group['material']['opname_before']['value']??null;$row['opname_after']=$group['material']['opname_after']['value']??null;
            $row['hpp']=$report['hpp_available']?$group['hpp']:null;$row['closing_component']=$group['component']['closing']??null;$row['hpp_ratio']=$group['hpp_ratio'];
            if(!$report['revenue_available']){$row['revenue']=null;}
            foreach($moneyFields as $key){$row[$key]=$row[$key]===null?'':number_format($row[$key]/100,2,'.','');}
            foreach(['supply_ratio','usage_ratio','hpp_ratio'] as $key){$row[$key]=$row[$key]===null?'':number_format($row[$key],2,'.','');}$rows[]=$row;
        }
        Report_workspace::export('efisiensi-'.$f['month_from'].'-'.$f['month_to'],$columns,$rows,array_merge($moneyFields,['supply_ratio','usage_ratio','hpp_ratio']));
    }

    private function exportMatrix(array $report, array $f): void
    {
        $columns=['source'=>'Sumber','flow'=>'Kelompok','direction'=>'Arah / basis'];
        $numeric=[];
        foreach ($report['months'] as $month) { $columns[$month]=$month; $numeric[]=$month; }
        $columns['total']='Total rentang'; $numeric[]='total';
        $rows=[];
        if ($report['sales']['available']) {
            $row=['source'=>'Omzet POS neto tanpa pajak','flow'=>'Penjualan POS, bukan mutasi kas','direction'=>'NETO']; $total=0;
            foreach ($report['monthly'] as $month) { $row[$month['label']]=number_format($month['omzet']/100,2,'.',''); $total+=$month['omzet']; }
            $row['total']=number_format($total/100,2,'.',''); $rows[]=$row;
        }
        foreach ($report['matrix'] as $source) {
            $row=['source'=>$source['label'],'flow'=>$report['flow_options'][$source['flow']],'direction'=>$source['direction']];
            foreach ($source['values'] as $month=>$value) { $row[$month]=number_format($value/100,2,'.',''); }
            $row['total']=number_format($source['total']/100,2,'.',''); $rows[]=$row;
        }
        Report_workspace::export('perbandingan-'.$f['month_from'].'-'.$f['month_to'].'-'.$f['currency'],$columns,$rows,$numeric);
    }
}
