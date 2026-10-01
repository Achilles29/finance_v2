<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('BASEPATH', dirname(__DIR__, 2) . '/system/');
class MY_Controller
{
    public $input;
    public $output;
    public $Loyalty_model;
    public array $permission = [];
    public bool $allowed = true;
    protected function require_permission($code, $action): void
    {
        $this->permission = [$code, $action];
        if (!$this->allowed) throw new DomainException('forbidden');
    }
}
require dirname(__DIR__, 2) . '/application/controllers/Loyalty.php';
$controller = (new ReflectionClass(Loyalty::class))->newInstanceWithoutConstructor();
$controller->input = new class {
    public string $verb = 'GET';
    public function method($upper): string { return $this->verb; }
};
$controller->output = new class {
    public array $headers = [];
    public array $data = [];
    public int $status = 200;
    public function set_header($header): self { $this->headers[] = $header; return $this; }
    public function set_status_header($status): self { $this->status = $status; return $this; }
    public function set_content_type($type): self { return $this; }
    public function set_output($body): self { $this->data = json_decode($body, true, 512, JSON_THROW_ON_ERROR); return $this; }
};
$controller->Loyalty_model = new class {
    public array $calls = [];
    public array $result = ['ok' => true];
    public function delete_voucher_issue($id): array { $this->calls[] = $id; return $this->result; }
};
$check = static function (bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException($message);
    echo 'PASS: ' . $message . PHP_EOL;
};
$controller->voucher_delete('7');
$check($controller->output->status === 405 && $controller->Loyalty_model->calls === [] && in_array('Allow: POST', $controller->output->headers, true), 'GET cannot delete vouchers');
$check($controller->permission === ['loyalty.voucher_campaign.index', 'delete'], 'existing delete permission enforced');
$controller->input->verb = 'POST';
$controller->allowed = false;
try { $controller->voucher_delete('7'); throw new RuntimeException('Permission bypass'); } catch (DomainException $error) {}
$check($controller->Loyalty_model->calls === [], 'denied permission never reaches delete model');
$controller->allowed = true;
$controller->voucher_delete('7');
$check($controller->output->data === ['ok' => true, 'id' => 7] && $controller->Loyalty_model->calls === [7], 'authorized POST returns success JSON');
$controller->Loyalty_model->result = ['ok' => false, 'message' => 'Voucher yang sudah dipakai tidak bisa dihapus.'];
$controller->voucher_delete('8');
$check($controller->output->status === 422 && $controller->output->data['message'] === $controller->Loyalty_model->result['message'], 'business rejection retains readable JSON message');
