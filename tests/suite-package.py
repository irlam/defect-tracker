#!/usr/bin/env python3
import hashlib
import json
from pathlib import Path
import subprocess
import tempfile
source=Path(__file__).resolve().parents[1]
with tempfile.TemporaryDirectory(prefix='defects-package-check-')as directory:
    output=Path(directory)/'package'
    subprocess.run(['python3',str(source/'bin/build-suite-staging.py'),str(output)],capture_output=True,check=True)
    manifest=json.loads((output/'.suite-staging-package.json').read_text())
    assert manifest['staging_only']is True and manifest['tenant_ready']is False
    for name,digest in manifest['files'].items():assert hashlib.sha256((output/name).read_bytes()).hexdigest()==digest
    assert not(output/'database/schema.sql').exists() and not(output/'config/runtime.suite.private.php').exists()
    assert not list(output.rglob('*.sqlite')) and not(output/'.env').exists()
    routes={'index.php','suite-login.php','suite-logout.php','api.php','files.php','export.php'}
    assert {p.name for p in (output/'public').glob('*.php')}==routes
    for path in (output/'public').glob('*.php'):assert "require dirname(__DIR__).'/staging/bootstrap.php';"in path.read_text()
    assert 'Require all denied'in(output/'.htaccess').read_text()
    assert all(part not in name.split('/')for name in manifest['files']for part in ['uploads','sessions','google_upload','tcpdf','backups','logs'])
    assert 'staging_transport'not in(output/'staging/bootstrap.php').read_text()and"new Gateway($config);"in(output/'staging/bootstrap.php').read_text()
print(json.dumps({'passed':True,'package_files':len(manifest['files'])+1,'public_routes':len(routes),'credentials_included':False,'legacy_routes_included':False}))
