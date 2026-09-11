# Temporary acceptance-test email routing

This is a testing facility, not production email delivery. The live site must be quiet while enabled: other NotificationHelper alerts and push-sender requests are suppressed, not queued or replayed. Direct integrations outside these two paths are not covered.

1. Deploy the router and its NotificationHelper/push sender integration together.
2. Verify the numeric ID of the disposable test project in the live database or project editor.
3. Create `config/notification-test.local.php` on the server (ignored by Git) with the following contents, replacing the placeholders. Do not use the example addresses.

```php
<?php
return [
    'project_id' => 123, // Replace with the verified test project ID.
    'recipient' => 'your-test-recipient@example.com',
    'sender' => 'verified-sender@your-domain.example',
    'send_enabled' => false,
];
```

4. Confirm PHP mail is configured through Plesk's mail transport and the sender is authorised for the domain. Set `send_enabled` to `true` only after checking every value. No SMTP passwords belong in this file.
5. Create a clearly labelled test defect in that project. Confirm the recipient receives the message; server acceptance alone does not prove delivery. Check the PHP error log for acceptance/failure. The mail includes only the defect number and event, not site content.
6. Exercise assignment, supported status changes and comments where the application invokes NotificationHelper. Verify each expected message. This patch does not add missing workflow calls.
7. Remove the local config file after testing to restore normal notifications. Merely setting `send_enabled` to false continues suppressing notifications.

A present but invalid config fails closed. Missing config restores legacy delivery, so do not create test defects before configuring the server. Email failure never falls back to managers or contractors. No historical alerts are replayed.

Local validation: `php tests/test-notification-router.php`. It uses a fake mail transport and sends no email.

## Admin panel setup (preferred)

Deploy `admin/email_settings.php`, `classes/SiteMail.php`, the router/helper/sender changes and navigation together. Open **System → Email Settings** as an administrator. Enter the mailbox password, select the disposable test project, save, then send the website test email. Enable test-project notifications only during the acceptance-test window.

The sender/server are fixed to this installation's Netcup account and tests go only to the owner's nominated recipient. No password is committed. Settings are stored in `.defecttracker-mail.json` in the parent of the application folder, with owner-only permissions on Linux. The PHP account must be allowed to write there; it must be outside every public document root. Back up this file securely. If Plesk's open_basedir prevents access, configure an allowed private location before using this feature; do not move the file into the public directory.

Do not also install the older `notification-test.local.php` configuration when using the panel. Remove that file if migrating from it. The existing assignment/comment workflow gaps remain; only events invoking NotificationHelper generate test emails. This release provides controlled acceptance testing, not general email delivery to all users.
