# Defects Suite staging deployment

The reviewed package is published after passing checks to `deploy/suite-staging` in `irlam/defect-tracker`. It provides Suite sign-in, a fixture Defects register, manager create/edit, private JPEG/PNG/PDF attachments, viewer restrictions, CSV export and logout. It does not replace the live Defect Notice installation or include its legacy administration, notifications, public links, training or offline sync. Those routes remain outside this first integration test package.

## Hosting batch

Prepare two new hosts: `alpha.defectnotice.site` and `beta.defectnotice.site`, with valid HTTPS and separate empty MySQL databases and scoped database users. Database and username must end in `_stage`. Keep each database login limited to its own database. Use each host's existing PHP 8.4 handler with PDO MySQL, cURL and fileinfo.

Each host's Git repository uses `https://github.com/irlam/defect-tracker.git`. Select Manual deployment before cloning, then select branch `deploy/suite-staging`. Use distinct Plesk names `defects-alpha.git` and `defects-beta.git`; deploy into that host's `httpdocs`. Set its **document root to `httpdocs/public`**. Pull Updates and Deploy only after selecting the deployment branch. The branch contains only the 26-file reviewed package; private config/data are excluded and ignored.

Create separate owner-only private upload and session directories outside each application's `httpdocs` and public document root. Each must have mode 0700. PHP must be able to access those exact private directories; if hosting restrictions block them, permit only the relevant application's private paths. Do not share directories or widen access to unrelated domains.

## Private configuration

Create `httpdocs/config/runtime.suite.private.php` mode 0600. It starts with:

```php
<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli' && realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) { http_response_code(404); exit; }
return [
    'staging_only' => true,
    'module_key' => 'defects',
    'suite_origin' => 'https://suite.defecttracker.uk',
    'origin' => 'https://alpha.defectnotice.site',
    'instance_id' => 3,
    'organization_id' => 7,
    'project_id' => 7,
    'local_project_id' => 1,
    'key' => 'ENTER_ALPHA_INSTANCE_KEY_PRIVATELY',
    'database' => [
        'host' => 'ENTER_DATABASE_HOST',
        'port' => 3306,
        'name' => 'ENTER_ALPHA_DATABASE_NAME_ENDING_STAGE',
        'username' => 'ENTER_ALPHA_DATABASE_USER_ENDING_STAGE',
        'password' => 'ENTER_DATABASE_PASSWORD_PRIVATELY',
    ],
    'upload_root' => 'ENTER_ABSOLUTE_ALPHA_PRIVATE_UPLOAD_DIRECTORY',
    'session_root' => 'ENTER_ABSOLUTE_ALPHA_PRIVATE_SESSION_DIRECTORY',
];
```

For Beta use its own key/database/user/private directories, origin `https://beta.defectnotice.site`, instance 4, organization/project 8 and local project 1. Instance keys are unique 64-character lowercase hexadecimal values, generated and configured privately server-side. Never put real keys or passwords in Git, a public document, chat or command output. The template intentionally fails validation until its placeholders are replaced.

## Initialization

On each new Defects host run through Plesk's PHP task runner:

```bash
cd alpha.defectnotice.site/httpdocs && /usr/local/php84/bin/php bin/suite-staging-install.php --dry-run
cd alpha.defectnotice.site/httpdocs && /usr/local/php84/bin/php bin/suite-staging-install.php --apply
cd alpha.defectnotice.site/httpdocs && /usr/local/php84/bin/php bin/suite-staging-preflight.php
```

Use Beta's directory for Beta. The installer refuses a nonempty database. It creates one demo project and its immutable binding, with no users or defects. It never imports the legacy schema/data dump. MySQL DDL is not transactional: a failed installation may leave partial tables in the new staging database; do not rerun against partial tables or redirect it to an existing live database.

## Suite registration and test window

Deploy the companion Suite change that supports the fixed Defects validation pair. Preserve the existing inventory entries 1/2. With no validation window open, append the following records to Suite's existing private `programme-staging-instances.json` (keep mode 0600):

```json
{"id":3,"organization_id":7,"project_id":7,"module_key":"defects","origin":"https://alpha.defectnotice.site","isolation_verified":false,"gateway_verified":false}
{"id":4,"organization_id":8,"project_id":8,"module_key":"defects","origin":"https://beta.defectnotice.site","isolation_verified":false,"gateway_verified":false}
```

Add Suite deployment-only `SUITE_INSTANCE_KEY_3` and `SUITE_INSTANCE_KEY_4` with the matching per-instance keys. Do not change keys 1/2. The old Programme-only validation helper expects an inventory of exactly two entries and a pinned old source; use the new helper after this registration:

```bash
cd suite.defecttracker.uk/httpdocs && /usr/local/php84/bin/php bin/staging-window.php --dry-run defects
cd suite.defecttracker.uk/httpdocs && /usr/local/php84/bin/php bin/staging-window.php --activate defects
cd suite.defecttracker.uk/httpdocs && /usr/local/php84/bin/php bin/staging-window.php --close defects
```

It admits only the six existing Alpha/Beta fixture accounts, and only for the selected module's fixed instance pair. It does not activate readiness or grant production users access. The same helper accepts `programme` as its final argument for a later Programme retest. Only one module window can be open at once; opening or closing one module cannot remove another module's policy.

## Hosting acceptance checks

While the Defects window is active, use Suite fixture accounts to enter each host at `/suite-login.php`. Check manager create/edit/save/refresh, attachment upload/download, CSV export, viewer read/export with all writes denied, Alpha-to-Beta and Beta-to-Alpha sign-in denial, copied session/record/file IDs denied, and separate database/user/storage access. Close the window **before expiry** and confirm an already-open session loses access. Check HTTPS host-only cookies, SameSite=None only for pending handoff state, no token in browser output/logs, wrong host denial and direct private-path denial.

Schedule `bin/suite-staging-cleanup.php --apply` on each Defects host to remove its expired private session records. The first workspace deliberately makes no offline caches or outbox; existing legacy offline submissions must not be replayed into it.

Readiness remains false until live checks are complete. Local fixture tests use synthetic Suite transport; GitHub checks additionally test native MySQL 8.4 schema, installer, account mapping and concurrent provisioning. These do not establish live hosting isolation or browser TLS/cookie behavior.
