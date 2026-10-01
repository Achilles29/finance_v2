<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** Always-on customer product boundary, independent of CI's optional application hooks flag. */
class MY_Hooks extends CI_Hooks
{
    public function call_hook($which = '')
    {
        if ($which === 'pre_controller') {
            require_once APPPATH.'libraries/Feature_policy.php';
            $policy = Feature_policy::runtime();
            if ($policy->managed()) {
                $router =& load_class('Router', 'core');
                $uri =& load_class('URI', 'core');
                $input =& load_class('Input', 'core');
                $decision = $router->finance_feature_denial ?? $policy->route($router->fetch_class(), $router->fetch_method(), array_slice($uri->rsegments,2));
                $payload = $input->post(null, false);
                if (stripos((string)$input->get_request_header('Content-Type'), 'application/json') === 0) {
                    $decoded = json_decode($input->raw_input_stream, true);
                    $payload = is_array($decoded) ? $decoded : [];
                }
                if ($decision['allowed']) $decision = $policy->payload($router->fetch_class(), $router->fetch_method(), (array)$payload, array_slice($uri->rsegments,2)) ?? $decision;
                if (!$decision['allowed']) {
                    $json = $input->is_cli_request() || !in_array($input->method(true), ['GET','HEAD'], true)
                        || $input->is_ajax_request() || str_contains(strtolower((string)$input->get_request_header('Accept')), 'application/json')
                        || empty($decision['page']);
                    if ($json) {
                        $output =& load_class('Output','core');
                        $output->set_status_header(403)->set_header('Cache-Control: no-store, private')->set_content_type('application/json')
                            ->set_output(json_encode(['ok'=>false,'code'=>$decision['code'],'message'=>'Fitur ini belum termasuk paket Anda. Lihat informasi upgrade.',
                                'feature'=>$decision['feature_code'],'feature_name'=>$decision['feature_name'],'edition'=>$decision['edition'],
                                'upgrade_path'=>'system/feature-access'], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));
                        $output->_display();
                    } else {
                        // Do not instantiate the restricted controller: its constructor may query or mutate business data.
                        require_once APPPATH.'controllers/Feature_access.php';
                        $locked = new Feature_access();
                        $locked->_locked($decision);
                        $locked->output->_display();
                    }
                    exit($input->is_cli_request() ? 3 : 0);
                }
            }
        }
        return parent::call_hook($which);
    }
}
