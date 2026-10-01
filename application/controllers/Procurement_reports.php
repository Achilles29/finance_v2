<?php
defined('BASEPATH') OR exit('No direct script access allowed');
require_once APPPATH . 'libraries/Report_workspace.php';

class Procurement_reports extends MY_Controller
{
    public function sr() { $this->report('sr'); }
    public function materials() { $this->report('materials'); }

    private function report(string $kind): void
    {
        $page=$kind==='sr'?'procurement.sr_report.index':'procurement.material_report.index';
        $this->require_permission($page,'view');
        $export=$this->input->get('export',true)==='csv';
        if ($export) { $this->require_permission($page,'export'); }
        $this->output->set_header('Cache-Control: private, no-store');
        $this->load->model('Procurement_report_model');
        $data=['page_title'=>$kind==='sr'?'Laporan SR':'Bahan Baku ke Divisi','active_menu'=>'procurement.report.'.$kind,
            'kind'=>$kind,'error'=>'','report'=>[],'divisions'=>[],'filters'=>Report_workspace::filters([]),
            'can_export'=>$this->can($page,'export'),'can_sr'=>$this->can('procurement.store_request.index','view'),
            'can_purchase'=>$this->can('purchase.order.index','view')];
        try {
            $data['filters']=$f=Report_workspace::filters($this->input->get(NULL,true)?:[]);
            if (!$this->db->query('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY')) { throw new RuntimeException('Snapshot laporan tidak tersedia.'); }
            $data['divisions']=$this->Procurement_report_model->divisions();
            $scope=$this->active_division_id();
            if ($scope!==null) {
                $scope=$this->Procurement_report_model->scopedDivision($scope);
                $data['filters']['division_id']=$f['division_id']=$scope;
                $data['divisions']=array_values(array_filter($data['divisions'],static fn($d)=>(int)$d['id']===$scope));
            }
            $data['report']=$this->Procurement_report_model->report($kind,$f);
            if ($export) {
                Report_workspace::export($kind.'-'.$f['month'],['event_date'=>'Tanggal','source'=>'Sumber','document_no'=>'Dokumen',
                    'delivery_no'=>'Pengiriman / receipt','division_name'=>'Divisi','destination'=>'Tujuan','status'=>'Status',
                    'category_name'=>'Kategori','display_name'=>'Material / item','brand'=>'Merk','profile_key'=>'Profil','description'=>'Deskripsi',
                    'qty_buy'=>'Qty beli','buy_unit'=>'Satuan beli','qty_content'=>'Qty isi','content_unit'=>'Satuan isi',
                    'fulfilled_qty'=>'Qty terpenuhi','pending_qty'=>'Qty belum terpenuhi','line_status'=>'Status baris permintaan','unit_cost'=>'Biaya per isi','value'=>'Nilai stok sistem'], $data['report']['rows']);
                return;
            }
        } catch(Throwable $e) {
            log_message('error','Procurement report: '.$e->getMessage());
            $data['error']=$e instanceof InvalidArgumentException?$e->getMessage():'Laporan belum dapat dimuat. Periksa kesiapan data atau hubungi administrator.';
            $this->output->set_status_header($e instanceof InvalidArgumentException?422:503);
        } finally {
            $this->db->query('ROLLBACK');
        }
        $this->render('reports/procurement',$data);
    }
}
