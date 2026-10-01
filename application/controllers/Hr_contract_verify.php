<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Hr_contract_verify extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Hr_contract_model');
    }

    public function index(string $token = '')
    {
        $token = trim($token);
        $row = $this->Hr_contract_model->get_contract_by_token($token);

        if (!$row) {
            $this->output->set_status_header(404);
            $this->load->view('hr_contract/verify_public', [
                'row' => null,
                'token' => $token,
            ]);
            return;
        }

        $proof = $this->Hr_contract_model->refresh_document_verification((int)$row['id']);
        if (!empty($proof['final_document_hash'])) {
            $row = $this->Hr_contract_model->get_contract_detail((int)$row['id']) ?: $row;
            $row['final_document_hash'] = $proof['final_document_hash'];
            $row['document_issued_at'] = $proof['document_issued_at'];
        }

        $approvalMap = [];
        foreach ((array)($row['approvals'] ?? []) as $a) {
            $approvalMap[(string)$a['approver_role']] = $a;
        }

        $signatureMap = [];
        foreach ((array)($row['signatures'] ?? []) as $s) {
            $signatureMap[(string)$s['signer_role']] = $s;
        }

        $this->load->view('hr_contract/verify_public', [
            'row' => $row,
            'token' => $token,
            'approval_map' => $approvalMap,
            'signature_map' => $signatureMap,
        ]);
    }
}
