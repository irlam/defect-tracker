#!/usr/bin/env python3
"""Allowlisted package, with a public document root and no legacy app/runtime data."""
import argparse
import hashlib
import json
from pathlib import Path
import shutil

parser=argparse.ArgumentParser()
parser.add_argument('output')
parser.add_argument('--source-commit')
args=parser.parse_args()
source=Path(__file__).resolve().parents[1]
output=Path(args.output).resolve()
if output==source or source in output.parents or output.exists():
    raise SystemExit('Output must be a new directory outside the source tree.')
output.mkdir(parents=True)
paths=[f'includes/Suite/{name}.php' for name in ['Gateway','Configuration','ProjectScope','UserMap','SessionStore','SignIn','CookiePolicy','Defects']]
paths+=['staging/bootstrap.php','staging/schema.mysql.sql','bin/suite-staging-preflight.php','bin/suite-staging-install.php','bin/suite-staging-cleanup.php']
for relative in paths:
    path=source/relative
    if path.is_symlink():raise SystemExit('Symlink source refused.')
    target=output/relative;target.parent.mkdir(parents=True,exist_ok=True);shutil.copyfile(path,target)
for relative in ['index.php','suite-login.php','suite-logout.php','api.php','files.php','export.php','app.js','style.css']:
    text=(source/'staging/public'/relative).read_text()
    if relative.endswith('.php'):
        old="require dirname(__DIR__).'/bootstrap.php';"
        if text.count(old)!=1:raise SystemExit('Missing explicit controller gate.')
        text=text.replace(old,"require dirname(__DIR__).'/staging/bootstrap.php';")
    target=output/'public'/relative;target.parent.mkdir(exist_ok=True);target.write_text(text)
(output/'.htaccess').write_text('Require all denied\n')
(output/'public/.htaccess').write_text('Options -Indexes\nDirectoryIndex index.php\nRequire all granted\nRewriteEngine On\nRewriteRule ^(?!(?:$|index\\.php$|suite-login\\.php$|suite-logout\\.php$|api\\.php$|files\\.php$|export\\.php$|app\\.js$|style\\.css$)) - [R=404,L]\n')
(output/'.gitignore').write_text('config/runtime.suite.private.php\n.env*\n*.log\nuploads/\nsessions/\n')
(output/'DEPLOYMENT.md').write_text('Suite Defects staging package. Set the hosting document root to httpdocs/public.\nUse only new fixture databases and distinct private configuration/keys/upload/session roots for Alpha and Beta.\nRun bin/suite-staging-install.php --dry-run and --apply only against an empty staging database. MySQL DDL is not transactional; a failed install may leave partial staging tables.\nRun bin/suite-staging-preflight.php before login. Keep Suite inventory readiness flags false until authenticated isolation tests pass.\nLegacy Defect Tracker routes, public tokens, email/push and offline sync are excluded from this initial integration test package.\nSchedule bin/suite-staging-cleanup.php --apply to remove expired private session records.\nDo not deploy this package over the existing live installation.\n')
files={p.relative_to(output).as_posix():hashlib.sha256(p.read_bytes()).hexdigest()for p in output.rglob('*')if p.is_file()}
(output/'.suite-staging-package.json').write_text(json.dumps({'module':'defects','source_commit':args.source_commit,'staging_only':True,'tenant_ready':False,'files':files},indent=2)+'\n')
print(json.dumps({'built':True,'files':len(files)+1,'public_routes':6,'tenant_ready':False}))
