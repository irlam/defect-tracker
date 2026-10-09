# Defect Tracker: next Construction Suite integration

9 October 2026. This branch adds an internal, tested staging foundation. It does not enable Suite login or change the live application.

## Existing boundary

Suite already manages companies, projects, memberships and company branding. Defect Tracker has authenticated summary/reference endpoints, but these use its existing shared integration key. Its independent login and multiple role/session implementations remain active. A summary project filter does not establish company isolation.

The compatibility approach is one fixture-only Defect Tracker database and upload store per bound Suite project, following Programme's two-instance pattern. Proposed staging names are `alpha.defectnotice.site` and `beta.defectnotice.site`; no domains, databases, credentials or inventory entries have been created by this change. The gateway accepts only HTTPS subdomains of the canonical Defect Notice domain, never the live root origin. Each future instance needs a distinct Suite instance ID and key, exact company/project binding, local project and server-owned private configuration.

## Implemented here

- `Gateway` validates browser state before redeeming the handoff. It verifies exact integer instance/company/project IDs, the `defects` module, supported current Suite role, positive user ID and unexpired absolute session lifetime. It pins Suite HTTPS requests, verifies TLS, disables redirects and caps responses at 64 KiB. It denies Suite outages without a cached success fallback.
- `ProjectScope` revalidates Suite access on every call, checks exactly one matching database binding and exactly one bound local project, and rejects project selection overrides. The separate staging-only SQL file creates a singleton binding table. It inserts no deployment values and runs no migrations automatically.
- Neither component creates local accounts, assigns a legacy role, writes PHP session variables, provides public endpoints or enables an inventory flag. Session tokens belong in a dedicated server-side store, never URLs/browser storage or the legacy session arrays.
- Regression checks cover state mismatch, foreign identities, invalid types/roles/expiry, project overrides, role downgrade, revocation, outage, wrong/missing/duplicate database bindings and additional local projects. CI runs these on PHP 8.4.

## Required before an accessible staging deployment

1. Build a private configuration loader and deployable package containing schema definitions and fictional fixtures only. The current `database/schema.sql` includes inserts; do not import that file as a fixture schema or copy production data.
2. Generate an allowlist of reviewed public routes and prepend authorization to each packaged PHP entry point. Deny installers, backups, diagnostics, local login/reset/user administration and Google OAuth routes in the Suite instance. Preserve production behavior in its existing deployment.
3. Add the host-only Secure/HttpOnly cookie flow, immutable Suite user-ID mapping and dedicated token storage. Revalidate each request and clear local access on failure. Do not adopt accounts by username/email. Existing `dashboard.php` dumps the PHP session to logs and rewrites local role/session fields; remove those paths from the staging build before adding any Suite token or identity.
4. Explicitly enforce viewer read/export-only permissions on every mutation endpoint, including GET-based actions, multipart uploads, batch/offline sync and attachment deletes. Do not grant administrator privileges based only on an existing local row or session role. Contractor/user permissions require action-level design; this branch deliberately maps no local roles.
5. Gate record/file/report/public-link access and verify the item's bound local project. Move private uploads outside the public document root and serve them through authorization. Block unreviewed public tokens.
6. Partition offline IndexedDB/outbox data by instance and immutable Suite user; prevent old drafts from replaying after account switch or revocation. The first staging build may disable offline writes until this passes.
7. Provision two disposable instances with independent databases/keys/upload paths, configure Suite inventory with readiness false, then run the authenticated manager/viewer/admin, cross-company, ID-tampering, file/export/offline and revocation tests. No readiness promotion is included here.

## Programme evidence carried forward

Owner reported eight demo tasks imported, Alpha manager save succeeded, Alpha viewer view/export and edit restriction passed, Alpha viewer was denied Beta entry and Beta manager was denied Alpha entry. The owner has not explicitly confirmed the manager value after refresh or all Beta data/save checks. These observations do not prove file/ID/offline separation.

The validation policy was closed on 9 October with `policy_removed:true`, `sessions_revoked:1` and readiness flags still false. Access was denied afterwards. The close occurred after the one-hour window had expired, so this is not conclusive proof that an otherwise-valid active session was immediately revoked. Recheck that case within a live window before promotion.

## Local validation

Run `php tests/suite-staging.php`; no live credentials or network are needed. Tests use synthetic identities and an in-memory SQLite database. The MySQL binding migration and actual browser/cURL transport remain staging validation work.
