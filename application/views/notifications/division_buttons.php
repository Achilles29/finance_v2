<?php if (in_array(strtoupper((string)($notification_status ?? '')), ['SUBMITTED', 'VERIFIED'], true)): ?>
<?php foreach ((array)($notification_channels ?? []) as $notification_button_channel): ?>
<button type="button" class="btn btn-sm btn-outline-<?= $notification_button_channel === 'WA' ? 'success' : 'info' ?>"
  data-module-notify="<?= html_escape($notification_button_channel) ?>"
  data-notify-url="<?= site_url('procurement/division-po-sr/notify/' . (int)$notification_request_id) ?>"
  data-notify-csrf="<?= html_escape($notification_csrf ?? '') ?>">
  <i class="ri ri-<?= $notification_button_channel === 'WA' ? 'whatsapp' : 'telegram' ?>-line me-1" aria-hidden="true"></i>Kirim <?= $notification_button_channel === 'WA' ? 'WA' : 'Telegram' ?>
</button>
<?php endforeach; ?>
<?php endif; ?>
