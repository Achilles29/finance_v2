<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** Bearer-only server-to-server API; no browser session authorizes this API. */
class Roast_connect extends CI_Controller
{
    private function run(string $action): void
    {
        if ($this->input->method(true) !== 'GET') { $this->reply(['ok'=>false,'message'=>'GET required.'],405); return; }
        $this->load->model('Roast_connect_model');
        try {
            $scope = $this->Roast_connect_model->authorize((string)$this->input->get_request_header('Authorization',false));
            $envelope = ['ok'=>true,'protocol'=>'namua-finance/1','instance_id'=>$scope['instance_id'],'name'=>$scope['name'],
                'scope'=>['location_scope'=>'DIVISION','division_id'=>$scope['division_id'],'destination_type'=>$scope['destination_type']],
                'capabilities'=>['catalog','availability'],'posting_enabled'=>false,'generated_at'=>gmdate('c')];
            if ($action === 'catalog') {
                $raw = $this->input->get('page',false);
                $page = $raw === null ? 1 : filter_var($raw,FILTER_VALIDATE_INT);
                if (!$page || $page < 1 || $page > 30) throw new RuntimeException('Halaman katalog tidak valid.',422);
                $items = $this->Roast_connect_model->materials($scope,$page);
                $envelope += ['items'=>$items,'next_page'=>count($items)===500 ? $page+1 : null];
            } elseif ($action === 'material') {
                $id = filter_var($this->input->get('id',false),FILTER_VALIDATE_INT);
                if (!$id || $id < 1) throw new RuntimeException('ID bahan tidak valid.',422);
                $items = $this->Roast_connect_model->materials($scope,1,$id);
                $envelope['item'] = $items[0] ?? null;
            }
            $this->reply($envelope);
        } catch (RuntimeException $error) {
            $status = in_array($error->getCode(),[401,403,422,503],true) ? $error->getCode() : 503;
            $this->reply(['ok'=>false,'message'=>$status===503 ? 'Konektor Finance belum tersedia. Periksa pemasangan modul.' : $error->getMessage()],$status);
        } catch (Throwable $error) {
            log_message('error','Roast Connect catalog request failed.');
            $this->reply(['ok'=>false,'message'=>'Konektor Finance belum dapat melayani permintaan.'],503);
        }
    }

    private function reply(array $data,int $status=200): void
    {
        $this->output->set_status_header($status)->set_content_type('application/json','utf-8')
            ->set_header('Cache-Control: no-store')->set_header('X-Content-Type-Options: nosniff')
            ->set_output(json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
    }
    public function health(): void { $this->run('health'); }
    public function catalog(): void { $this->run('catalog'); }
    public function material(): void { $this->run('material'); }
}
