<?php
// Deployment: install this file as .codex/telegram_webhook_bridge.php ONLY on
// the internal development server. No token or executable command belongs here.
defined('BASEPATH') OR exit('No direct script access allowed');

return static function (string $body, string $secret): ?bool {
    $socket = @stream_socket_client('unix:///var/lib/finance-config/codex-bridge.sock', $errno, $error, 1);
    if (!is_resource($socket)) return null;
    stream_set_timeout($socket, 2);
    $request = json_encode(['secret' => $secret, 'update' => json_decode($body, true)], JSON_UNESCAPED_SLASHES) . "\n";
    $offset = 0;
    while ($offset < strlen($request)) {
        $written = @fwrite($socket, substr($request, $offset));
        if ($written === false || $written === 0) { fclose($socket); return null; }
        $offset += $written;
    }
    $reply = @fgets($socket, 1024);
    fclose($socket);
    $decoded = is_string($reply) ? json_decode($reply, true) : null;
    return is_array($decoded) && ($decoded['ok'] ?? false) === true ? true : null;
};
