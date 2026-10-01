<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Menu_book extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->helper('url');
        $this->load->model('Business_profile_model');
    }

    public function index()
    {
        if ($this->render_customer_template()) return;
        $data = $this->view_identity();

        $this->load->view('menu_book/index', $data);
    }

    public function page($page = null)
    {
        if ($page === null) {
            redirect('menu_book');
        }

        $allowed_pages = [
            'cover'        => 'menu_book/page_00_cover',
            'opening'      => 'menu_book/page_01_opening',
        ];

        $this->_load_menu_page($page, $allowed_pages);
    }

    public function food($page = null)
    {
        if ($page === null) {
            redirect('menu_book');
        }

        $allowed_pages = [
            'main_character'      => 'menu_book/food/page_01_main_character',
            'nusantara_comfort'   => 'menu_book/food/page_02_nusantara_comfort',
            'flame_flavor'        => 'menu_book/food/page_03_flame_flavor',
            'asian_signatures'    => 'menu_book/food/page_04_asian_signatures',
            'bowl_spice_noodles'  => 'menu_book/food/page_05_bowl_spice_noodles',
            'bites_of_joy'        => 'menu_book/food/page_06_bites_of_joy',
            'dessert_collection'  => 'menu_book/food/page_07_dessert_collection',
            'extras_sides'        => 'menu_book/food/page_08_extras_sides',
        ];

        $this->_load_menu_page($page, $allowed_pages);
    }

    public function beverage($page = null)
    {
        if ($page === null) {
            redirect('menu_book');
        }

        $allowed_pages = [
            'namua_signatures'       => 'menu_book/beverage/page_09_namua_signatures',
            'house_masterpieces'     => 'menu_book/beverage/page_10_house_masterpieces',
            'coffee_atelier'         => 'menu_book/beverage/page_11_coffee_atelier',
            'cold_creamy_creations'  => 'menu_book/beverage/page_12_cold_creamy_creations',
            'spark_refresh'          => 'menu_book/beverage/page_13_spark_refresh',
            'blended_delights'       => 'menu_book/beverage/page_14_blended_delights',
            'tea_tradition'          => 'menu_book/beverage/page_15_tea_tradition',
            'sweet_scoops'           => 'menu_book/beverage/page_16_sweet_scoops',
            'kopsu_literan'          => 'menu_book/beverage/page_17_kopsu',
            'namua_bundle_club'      => 'menu_book/beverage/page_18_namua_bundle_club',
            'extras_enhancers'       => 'menu_book/beverage/page_19_extras_enhancers',
        ];

        $this->_load_menu_page($page, $allowed_pages);
    }

    public function flipbook()
    {
        if ($this->render_customer_template()) return;
        $data = $this->view_identity();
        $this->load->view('menu_book/flipbook', $data);
    }

    private function _load_menu_page($page, $allowed_pages)
    {
        if ($this->render_customer_template()) return;
        if (!array_key_exists($page, $allowed_pages)) {
            show_404();
            return;
        }

        $data = $this->view_identity() + ['page' => $page];

        $this->load->view($allowed_pages[$page], $data);
    }

    private function render_customer_template(): bool
    {
        $template = $this->Business_profile_model->menu_book_template();
        if ($template === 'legacy_namua') return false;
        if ($template === 'disabled') { show_404(); return true; }
        $items = [];
        if ($this->db->table_exists('mst_product') && $this->db->field_exists('show_landing', 'mst_product')) {
            // Publication is opt-in. Do not call the landing sales ranking (which reads orders).
            $items = $this->db->select('p.product_name, p.description, p.selling_price, c.name AS category_name')
                ->from('mst_product p')->join('mst_product_category c', 'c.id = p.product_category_id', 'left')
                ->where('p.is_active', 1)->where('p.show_landing', 1)
                ->order_by('c.sort_order', 'ASC')->order_by('c.name', 'ASC')->order_by('p.product_name', 'ASC')
                ->get()->result_array();
        }
        $this->load->view('menu_book/customer', $this->view_identity() + ['items' => $items]);
        return true;
    }

    /** Browser metadata may follow the local customer; legacy menu artwork/content stays a separate template. */
    private function view_identity(): array
    {
        $profile = $this->Business_profile_model->profile();
        $name = trim((string)($profile['display_name'] ?? ''));
        $name = $name !== '' ? $name : 'Finance';
        return ['title' => 'Menu Book — ' . $name, 'business_profile' => $profile];
    }
}
