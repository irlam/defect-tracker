#!/usr/bin/env python3
"""Two real processes must not consume the same pending sign-in."""
import argparse
import json
import os
from pathlib import Path
import subprocess
import tempfile
import time

parser = argparse.ArgumentParser()
parser.add_argument('--php', default='php')
args = parser.parse_args()
root = Path(__file__).resolve().parents[1]
with tempfile.TemporaryDirectory(prefix='defects-session-race-') as directory:
    base = Path(directory)
    store = base/'sessions'
    store.mkdir(mode=0o700)
    worker = base/'worker.php'
    worker.write_text('''<?php
declare(strict_types=1);
require ''' + json.dumps(str(root/'includes/Suite/Gateway.php')) + ''';
require ''' + json.dumps(str(root/'includes/Suite/SessionStore.php')) + ''';
$binding=['staging_only'=>true,'module_key'=>'defects','suite_origin'=>'https://suite.defecttracker.uk','origin'=>'https://alpha.defectnotice.site','instance_id'=>3,'organization_id'=>7,'project_id'=>7,'local_project_id'=>1,'key'=>str_repeat('a',64)];
$store=new \\DefectTracker\\Suite\\SessionStore(''' + json.dumps(str(store)) + ''',$binding);
if($argv[1]==='create'){echo $store->create(['kind'=>'pending','state'=>str_repeat('f',64),'expires_at'=>time()+300]);exit;}
touch(__DIR__.'/ready-'.$argv[3]);
$deadline=microtime(true)+5;
while(!is_file(__DIR__.'/start')){if(microtime(true)>$deadline)exit(2);usleep(1000);}
try{$store->consume($argv[2]);echo '{"consumed":true}';}catch(RuntimeException $e){echo '{"consumed":false}';}
''')
    session_id = subprocess.run([args.php, str(worker), 'create'], capture_output=True, text=True, check=True).stdout
    children = [subprocess.Popen([args.php, str(worker), 'consume', session_id, str(i)], stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True) for i in range(2)]
    try:
        deadline = time.monotonic()+5
        while not all((base/f'ready-{i}').exists() for i in range(2)):
            assert time.monotonic() < deadline, 'Workers failed to reach barrier'
            time.sleep(.01)
        (base/'start').touch()
        results = []
        for child in children:
            stdout, stderr = child.communicate(timeout=5)
            assert child.returncode == 0 and stderr == '', stderr
            results.append(json.loads(stdout)['consumed'])
        assert sorted(results) == [False, True], results
        assert not list(store.iterdir()), 'Consumed record was retained'
    finally:
        for child in children:
            if child.poll() is None:
                child.kill()
                child.wait()
print(json.dumps({'passed': True, 'concurrent_consumers': 2, 'successful_consumptions': 1, 'live_requests': 0}))
