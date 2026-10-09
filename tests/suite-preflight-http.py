#!/usr/bin/env python3
"""Fixture-only HTTP and CLI boundaries. No live database or Suite requests."""
import argparse
import json
import os
from pathlib import Path
import shutil
import socket
import subprocess
import tempfile
import time
import urllib.error
import urllib.request

parser = argparse.ArgumentParser()
parser.add_argument('--php', default='php')
args = parser.parse_args()
root = Path(__file__).resolve().parents[1]
env = os.environ.copy()
env.pop('DEFECTS_SUITE_CONFIG_FILE', None)
proc = subprocess.run([args.php, str(root/'bin/suite-staging-preflight.php')], env=env, capture_output=True, text=True)
assert proc.returncode == 1, proc.stdout
reply = json.loads(proc.stdout)
assert reply['preflight_passed'] is False and reply['writes_performed'] == 0 and reply['suite_contacted'] is False
assert str(root) not in proc.stdout and proc.stderr == ''
guard = subprocess.run([args.php, '-r', f"require {json.dumps(str(root/'includes/Suite/Configuration.php'))}; echo \\DefectTracker\\Suite\\Configuration::guard();"], capture_output=True, text=True, check=True).stdout
with tempfile.TemporaryDirectory(prefix='defects-preflight-http-') as directory:
    fixture = Path(directory)
    (fixture/'config').mkdir()
    (fixture/'bin').mkdir()
    private = fixture/'config/runtime.suite.private.php'
    private.write_text("<?php\ndeclare(strict_types=1);\n" + guard + "\nreturn ['password'=>'fixture-do-not-expose'];\n")
    private.chmod(0o600)
    shutil.copyfile(root/'bin/suite-staging-preflight.php', fixture/'bin/suite-staging-preflight.php')
    with socket.socket() as socket_ref:
        socket_ref.bind(('127.0.0.1', 0))
        port = socket_ref.getsockname()[1]
    with tempfile.TemporaryFile() as logs:
        server = subprocess.Popen([args.php, '-S', f'127.0.0.1:{port}', '-t', str(fixture)], stdout=logs, stderr=logs, env=env)
        try:
            for attempt in range(50):
                try:
                    with socket.create_connection(('127.0.0.1', port), timeout=.2):
                        break
                except OSError:
                    time.sleep(.05)
            else:
                raise AssertionError('Fixture server did not start')
            for path in ['/config/runtime.suite.private.php', '/bin/suite-staging-preflight.php']:
                for method in ['GET', 'POST']:
                    request = urllib.request.Request(f'http://127.0.0.1:{port}{path}', method=method)
                    try:
                        urllib.request.urlopen(request, timeout=3)
                        raise AssertionError('Web entry was not denied')
                    except urllib.error.HTTPError as error:
                        assert error.code == 404
                        assert error.read() == b''
        finally:
            server.terminate()
            server.wait(timeout=5)
print(json.dumps({'passed': True, 'http_denials': 4, 'cli_missing_configuration_denied': True, 'live_requests': 0}))
