<?php
// Existing WA security tests isolate their own forms. The new integration view
// is rendered and asserted separately by module_notifications_ui_smoke.php.
class ModuleNotificationViewStub
{
    public function view(string $path, array $data): void
    {
        if ($path !== 'notifications/settings') throw new RuntimeException('Unexpected partial');
    }
}
