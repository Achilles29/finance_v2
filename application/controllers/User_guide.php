<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class User_guide extends MY_Controller
{
    public function index()
    {
        $this->require_permission('system.guide.index', 'view');
        $this->output->set_header('Cache-Control: private, no-store');
        if ($this->input->method(true) !== 'GET') {
            $this->output->set_header('Allow: GET');
            show_error('Panduan hanya dapat dibuka untuk dibaca.', 405); return;
        }
        $this->load->library('Finance_user_guide', [], 'guide');
        $server = $this->can('system.guide.server', 'view');
        $categories = $this->guide->categories($server);
        $audiences = $this->guide->audiences($server);
        $params = [];
        foreach (['category'=>32, 'audience'=>32, 'q'=>160, 'article'=>80] as $key=>$limit) {
            $value = $this->input->get($key, false) ?? '';
            if (!is_string($value) || strlen($value) > $limit || !mb_check_encoding($value, 'UTF-8')
                || preg_match('/[\x00-\x1f\x7f]/', $value)) {
                show_error('Pilihan panduan tidak valid. Buka kembali halaman Panduan Aplikasi.', 400); return;
            }
            $params[$key] = trim($value);
        }
        if (($params['category'] !== '' && !isset($categories[$params['category']]))
            || ($params['audience'] !== '' && !isset($audiences[$params['audience']]))) {
            show_404(); return;
        }
        // Filter restricted articles BEFORE selection, search, navigation and rendering.
        $articles = $this->guide->articles($server);
        if ($params['article'] !== '' && !isset($articles[$params['article']])) { show_404(); return; }
        $matches = $this->guide->search($articles, $params['category'], $params['audience'], $params['q']);
        $selected = $params['article'] !== '' ? $params['article'] : (array_key_first($matches) ?? '');
        if ($selected !== '' && !isset($matches[$selected])) { show_404(); return; }
        $article = $selected !== '' ? $matches[$selected] : null;
        if ($article) {
            foreach ($article['links'] as &$link) {
                $link['allowed'] = $this->can($link['permission'], 'view');
            }
            unset($link);
        }
        // Navigation receives titles only, not all article bodies/commands.
        $chapters = [];
        foreach ($matches as $id=>$match) $chapters[$id] = $match['title'];
        $this->render('system/user_guide', ['page_title'=>'Panduan Aplikasi', 'active_menu'=>'system.guide',
            'categories'=>$categories, 'audiences'=>$audiences, 'params'=>$params, 'chapters'=>$chapters,
            'article'=>$article, 'selected'=>$selected, 'server_access'=>$server,
            'guide_reviewed'=>Finance_user_guide::REVIEWED, 'guide_edition'=>Finance_user_guide::EDITION,
            'release_label'=>$this->guide->releaseLabel(dirname(APPPATH).'/app-manifest.json')]);
    }
}
