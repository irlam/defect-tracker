# Defect Tracker: next Construction Suite integration

9 October 2026. This branch now includes a reviewed, deployable integration test package. It does not change the existing live application. See SUITE-DEPLOYMENT.md for the concrete two-host setup.

## Existing boundary

Suite already manages companies, projects, memberships and company branding. Defect Tracker has authenticated summary/reference endpoints, but these use its existing shared integration key. Its independent login and multiple role/session implementations remain active. A summary project filter does not establish company isolation.

The compatibility approach is one fixture-only Defect Tracker database and upload store per bound Suite project, following Programme's two-instance pattern. Proposed staging names are `alpha.defectnotice.site` and `beta.defectnotice.site`; no domains, databases, credentials or inventory entries have been created by this change. The gateway accepts only HTTPS subdomains of the canonical Defect Notice domain, never the live root origin. Each future instance needs a distinct Suite instance ID and key, exact company/project binding, local project and server-owned private configuration.

## Implemented here

- `Gateway` validates browser state before redeeming the handoff. It verifies exact integer instance/company/project IDs, the `defects` module, supported current Suite role, positive user ID and unexpired absolute session lifetime. It pins Suite HTTPS requests, verifies TLS, disables redirects and caps responses at 64 KiB. It denies Suite outages without a cached success fallback.
- `ProjectScope` revalidates Suite access on every call, checks exactly one matching database binding and exactly one bound local project, and rejects project selection overrides. The separate staging-only SQL file creates a singleton binding table. It inserts no deployment values and runs no migrations automatically.
- The gateway/scope components do not activate inventory flags. The reviewed staging controllers now create only immutable synthetic local user mappings; they never adopt existing accounts by email/username or use legacy PHP sessions. Session tokens belong in a dedicated server-side store, never URLs/browser storage or the legacy session arrays.
- `Configuration` loads only a deployment-owned private PHP file. The default in-tree `config/runtime.suite.private.php` must have the exact direct-request 404 guard, restrictive file permissions and no symlink path. A server-configured outside-root file is also accepted. There is no legacy `.env` or example fallback and no environment/session mutation. Database and username must end in `_stage`; upload and session directories must be separate existing owner-only directories outside the application root. The loader suppresses configuration output, warnings and secret-bearing error details.
- `bin/suite-staging-preflight.php` is CLI-only. It checks local private configuration, a new staging database's binding and its single local project. It performs no writes or Suite requests, and never enables readiness. The configured roots are local-path checks, not proof that another instance cannot use the same storage: pairwise database/credential/storage isolation remains a hosting test.
- `SessionStore` keeps pending browser-state and authenticated Suite-token records in the private session root, using fresh opaque IDs and mode 0600 files. Records bind instance/company/project/origin/key, enforce absolute expiry, and reject malformed or foreign data. Locked consumption and inode checks prevent concurrent callback replay. No PHP session globals are used.
- `SignIn` creates a five-minute pending sign-in, consumes it before redemption, revalidates Suite and database binding, and issues a new browser session ID. Only the server store receives the Suite token. Every current-user check revalidates Suite identity/role; revocation, identity changes, expiry and outages clear local access. Writes require an approved method, matching CSRF token and a reviewed manager/admin role. Viewer/user/contractor mutations remain denied until their action policies are implemented. Logout requires POST/CSRF and removes local access before remote revocation; a false result means remote revocation failed and the Suite token remains subject to absolute expiry/global logout. No automatic retry queue exists yet.
- `CookiePolicy` defines Secure/HttpOnly host-only `__Host-` cookies, Lax for the normal session and None only for the pending cross-site POST handoff, no-store/no-referrer headers and exact configured HTTPS-host validation. It does not accept forwarded-header claims. The assembled controllers and Set-Cookie headers are covered by two-instance HTTP fixtures. Live HTTPS/browser and reverse-proxy behavior remain hosting checks.
- Regression checks cover state mismatch, foreign identities, invalid types/roles/expiry, project overrides, role downgrade, revocation, outage, wrong/missing/duplicate database bindings and additional local projects. CI runs these on PHP 8.4.

## Reviewed deployment package

The builder includes only six protected PHP entry points under `public/`, reviewed Suite components, initialization/preflight/cleanup helpers and a fixture-only schema. Its register supports manager create/edit, private attachments and CSV export; viewers can read/export/download. It omits all legacy administration/installers, Google OAuth, public links, email/push, training and offline sync. Every controller explicitly loads the gate; unknown routes are denied. Private configuration, uploads and sessions are not packaged. There is no production database copy or data migration.

`UserMap` keys on immutable Suite instance/user IDs, creates synthetic usernames/emails with random unknown passwords, refreshes roles and rolls back failed/racing creation. Local users never gain global legacy admin roles. The workspace uses the fresh Suite role for mutations and has no legacy role/session overwrite path.

`SessionStore::pruneExpired` and the CLI cleanup helper remove only expired records belonging to the configured audience. Logout does not need the project database to be reachable. A remote Suite outage ends local access but does not prove successful remote revocation; its boolean result reports that limitation.

Code checks are complete once both package and MySQL CI pass. Hosting still needs two new HTTPS instances, separate stage databases/accounts/private stores, private per-instance keys, the companion Suite validation change and owner-run live acceptance tests. Keep readiness false. Follow SUITE-DEPLOYMENT.md; do not deploy over the existing installation or automatically enable its excluded routes.

## Programme evidence carried forward

Owner reported eight demo tasks imported, Alpha manager save succeeded, Alpha viewer view/export and edit restriction passed, Alpha viewer was denied Beta entry and Beta manager was denied Alpha entry. The owner has not explicitly confirmed the manager value after refresh or all Beta data/save checks. These observations do not prove file/ID/offline separation.

The validation policy was closed on 9 October with `policy_removed:true`, `sessions_revoked:1` and readiness flags still false. Access was denied afterwards. The close occurred after the one-hour window had expired, so this is not conclusive proof that an otherwise-valid active session was immediately revoked. Recheck that case within a live window before promotion.

## Local validation

Run `php tests/suite-staging.php`; no live credentials or network are needed. Tests use synthetic identities and an in-memory SQLite database. The native MySQL schema/installer/mapping run in CI. Actual hosting database grants, browser TLS/cookies and live cURL transport remain staging validation work.

Also run `php tests/suite-configuration.php` and `python3 tests/suite-preflight-http.py`. These check restrictive permissions, missing/unguarded/linked/noisy configurations, invalid database names and ports, private storage paths, database preflight read-only behavior, and direct GET/POST denial of private configuration and the CLI helper. The unconfigured CLI exits 1 with a sanitized JSON failure. No live configuration is needed.

`php tests/suite-session.php` checks the internal sign-in/session lifecycle, replay/state mismatch, fresh role downgrade, viewer writes, CSRF/methods, identity and expiry changes, revocation/outages, host/cookie policy, private file permissions and local logout. `python3 tests/suite-session-race.py` starts two real fixture processes against the same pending record and requires exactly one successful consumption. These are internal/fixture tests, not live browser sign-in proof.

Deployment owner will later create the private file using `Configuration::guard()` followed by `return` and their binding values. Required fields are `staging_only:true`, `module_key: defects`, integer `instance_id`, `organization_id`, `project_id`, `local_project_id`, Suite and instance HTTPS origins, an instance-specific 64-hex key, `database` (`host`, integer `port`, `name`, `username`, `password`), `upload_root` and `session_root`. This document intentionally contains no usable credentials or preset instance IDs. Run the preflight only on the newly configured Defects staging instances, never production or Programme.
