<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'controllers/Purchase.php';

class Inventory extends Purchase
{
    public function index()
    {
        redirect('inventory/stock/warehouse');
    }

    public function fifo_audit()
    {
        parent::fifo_audit_index();
    }

    public function lot_audit()
    {
        parent::lot_audit_index();
    }
}