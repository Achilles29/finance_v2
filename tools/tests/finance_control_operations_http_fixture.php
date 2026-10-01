<?php
// Socket-only synthetic test router. Never loads application database/secret config.
declare(strict_types=1);
$socket=(string)getenv('FINANCE_CONTROL_TEST_SOCKET');$nonce=(string)getenv('FINANCE_CONTROL_TEST_NONCE');
if(PHP_SAPI!=='cli-server'||($_SERVER['REMOTE_ADDR']??'')!=='127.0.0.1'||!preg_match('~\A/tmp/finance-mutation-test-[A-Za-z0-9]+/db.sock\z~D',$socket)||strlen($nonce)!==64||!hash_equals($nonce,(string)($_SERVER['HTTP_X_FIXTURE_NONCE']??''))){http_response_code(404);exit;}
$root=dirname(__DIR__,2);define('BASEPATH',$root.'/system/');define('APPPATH',$root.'/application/');define('FCPATH',$root.'/');define('ENVIRONMENT','testing');date_default_timezone_set('Asia/Jakarta');
function is_php($v):bool{return version_compare(PHP_VERSION,$v,'>=');}
function log_message($level,$message):void{}
function show_error($message='',$status=500):void{http_response_code($status);throw new RuntimeException('fixture_denied');}
function show_404():void{http_response_code(404);throw new RuntimeException('fixture_not_found');}
function &get_instance(){return $GLOBALS['context'];}
require BASEPATH.'core/Model.php';require BASEPATH.'database/DB.php';
$context=new stdClass();$context->db=DB(['dbdriver'=>'mysqli','hostname'=>$socket,'username'=>'root','password'=>'','database'=>'fixture_finance','db_debug'=>false,'save_queries'=>false,'pconnect'=>false,'char_set'=>'utf8mb4'],true);
$context->load=new class{
    public function model($name):void{if(!in_array($name,['Finance_insight_model','Finance_control_operation_model'],true))throw new RuntimeException('Unexpected fixture model');require_once APPPATH.'models/'.$name.'.php';get_instance()->$name=new $name();}
};
$context->session=new class{public function userdata($key){return str_repeat('a',64);}};
$context->current_user=['id'=>(int)($_SERVER['HTTP_X_FIXTURE_ACTOR']??1)];
$context->input=new class{
    public $raw_input_stream;
    public function __construct(){$this->raw_input_stream=file_get_contents('php://input');}
    public function method($upper=false){return $upper?$_SERVER['REQUEST_METHOD']:strtolower($_SERVER['REQUEST_METHOD']);}
    public function get_request_header($key,$clean=false){return $_SERVER['HTTP_'.strtoupper(str_replace('-','_',$key))]??null;}
    public function post($key,$clean=false){return $_POST[$key]??null;}
    public function get($key,$clean=false){return $_GET[$key]??null;}
    public function ip_address(){return '127.0.0.1';}
};
$context->output=new class{
    public $body='';
    public function set_status_header($status){http_response_code($status);return $this;}
    public function set_content_type($type){header('Content-Type: '.$type);return $this;}
    public function set_header($header){header($header);return $this;}
    public function set_output($body){$this->body=$body;return $this;}
};
class MY_Controller{
    public function __construct(){}
    public function __get($name){return get_instance()->$name;}
    protected function can($page,$action):bool{return ($_SERVER['HTTP_X_FIXTURE_DENIED_PAGE']??'')!==$page;}
    protected function require_permission($page,$action):void{if(!$this->can($page,$action))show_error('denied',403);}
}
require APPPATH.'controllers/Finance_insights.php';
try{
    $controller=new Finance_insights();$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
    if($path==='/upload')$controller->evidence_upload();
    elseif(preg_match('~^/download/(\d+)$~',$path,$m))$controller->evidence_download($m[1]);
    elseif(preg_match('~^/save/([a-z-]+)$~',$path,$m))$controller->save($m[1]);
    elseif(preg_match('~^/lookup/([a-z-]+)$~',$path,$m))$controller->lookup($m[1]);
    else show_404();
    echo $context->output->body;
}catch(Throwable $e){if(http_response_code()<400)http_response_code(500);header('Content-Type: application/json');echo json_encode(['fixture_error'=>$e->getMessage()]);}
