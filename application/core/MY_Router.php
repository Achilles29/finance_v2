<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** Route resolution precedes CI's file/method existence check. No business controller is loaded here. */
class MY_Router extends CI_Router
{
    public ?array $finance_feature_denial = null;

    public function __construct($routing = null)
    {
        parent::__construct($routing);
        require_once APPPATH.'libraries/Feature_policy.php';
        $policy = Feature_policy::runtime();
        if (!$policy->managed()) return;
        $decision = $policy->route($this->fetch_class(), $this->fetch_method(), array_slice($this->uri->rsegments, 2));
        if ($decision['allowed']) return;
        // Route to the existing safe shell even if a restricted optional module is
        // absent from this package. The always-on hook still returns the denial.
        $this->finance_feature_denial = $decision;
        $this->set_directory('');
        $this->set_class('Feature_access');
        $this->set_method('index');
        $this->uri->rsegments = [1=>'feature_access', 2=>'index'];
    }
}
