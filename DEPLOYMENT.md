Suite Defects staging package. Set the hosting document root to httpdocs/public.
Use only new fixture databases and distinct private configuration/keys/upload/session roots for Alpha and Beta.
Run bin/suite-staging-install.php --dry-run and --apply only against an empty staging database. MySQL DDL is not transactional; a failed install may leave partial staging tables.
Run bin/suite-staging-preflight.php before login. Keep Suite inventory readiness flags false until authenticated isolation tests pass.
Legacy Defect Tracker routes, public tokens, email/push and offline sync are excluded from this initial integration test package.
Schedule bin/suite-staging-cleanup.php --apply to remove expired private session records.
Do not deploy this package over the existing live installation.
