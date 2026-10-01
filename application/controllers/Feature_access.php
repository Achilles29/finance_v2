<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** Authenticated product information only. Never grants an entitlement or links to Control administration. */
class Feature_access extends MY_Controller
{
    public function index(): void { $this->upgrade(); }
    public function upgrade(): void
    {
        require_once APPPATH.'libraries/Feature_policy.php';
        $policy = Feature_policy::runtime();
        $this->output->set_header('Cache-Control: no-store, private');
        $this->render('system/feature_upgrade', ['page_title'=>'Paket & Upgrade','policy'=>$policy]);
    }
    public function _locked(array $decision): void
    {
        $this->output->set_status_header(403)->set_header('Cache-Control: no-store, private');
        $this->render('system/feature_locked', ['page_title'=>'Fitur memerlukan upgrade','feature_decision'=>$decision]);
    }
}
