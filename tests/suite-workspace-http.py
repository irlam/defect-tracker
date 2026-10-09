#!/usr/bin/env python3
"""Two disposable package instances, real HTTP, synthetic Suite transport only."""
import argparse
import base64
import http.cookies
import json
import os
from pathlib import Path
import re
import socket
import sqlite3
import subprocess
import tempfile
import time
import urllib.error
import urllib.parse
import urllib.request

parser=argparse.ArgumentParser();parser.add_argument('--php',default='php');args=parser.parse_args()
source=Path(__file__).resolve().parents[1]
class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self,*args,**kwargs):return None
opener=urllib.request.build_opener(NoRedirect)
checks=0
def check(value,label):
    global checks
    assert value,label
    checks+=1
class Instance:
    def __init__(self,base,site,instance,org):
        self.host=f'{site}.defectnotice.site';self.cookies={};self.base=base/site;self.base.mkdir()
        self.package=self.base/'package'
        subprocess.run(['python3',str(source/'bin/build-suite-staging.py'),str(self.package)],capture_output=True,check=True)
        for name in ['uploads','sessions']:(self.base/name).mkdir(mode=0o700)
        self.database=self.base/'fixture.sqlite';db=sqlite3.connect(self.database)
        sql=(self.package/'staging/schema.mysql.sql').read_text();sql=re.sub(r'^--.*$','',sql,flags=re.M);sql=sql.replace('INT AUTO_INCREMENT PRIMARY KEY','INTEGER PRIMARY KEY AUTOINCREMENT').replace(' ENGINE=InnoDB','')
        db.executescript(sql);db.execute("INSERT INTO projects VALUES(1,?,'active')",(site+' fixture',));db.execute("INSERT INTO suite_instance_binding VALUES(1,?,?,?,1,'defects')",(instance,org,org));db.commit();db.close()
        self.identity={'instance_id':instance,'organization_id':org,'project_id':org,'module_key':'defects','user_id':8 if site=='alpha' else 11,'role':'manager','name':site+' manager','email':site+'@example.invalid','session_expires_at':int(time.time())+600,'session_token':('b' if site=='alpha' else 'e')*64}
        self.reply=self.base/'suite.json';self.allowed=True;self.write_reply()
        config={'staging_only':True,'module_key':'defects','suite_origin':'https://suite.defecttracker.uk','origin':'https://'+self.host,'instance_id':instance,'organization_id':org,'project_id':org,'local_project_id':1,'key':'a'*64,'database':{'host':'localhost','port':3306,'name':site+'_stage','username':site+'_stage','password':'fixture-only'},'upload_root':str(self.base/'uploads'),'session_root':str(self.base/'sessions')}
        # Fixture-only substitutions. No transport/SQLite override exists in the package.
        (self.package/'config').mkdir()
        guard="if (PHP_SAPI !== 'cli' && realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) { http_response_code(404); exit; }"
        code="<?php\ndeclare(strict_types=1);\n"+guard+"\nreturn json_decode("+json.dumps(json.dumps(config))+",true,32,JSON_THROW_ON_ERROR);\n"
        configfile=self.package/'config/runtime.suite.private.php';configfile.write_text(code);configfile.chmod(0o600)
        bootstrap=self.package/'staging/bootstrap.php';text=bootstrap.read_text()
        text=text.replace('$db=Configuration::connect($config);',"$db=new PDO('sqlite:"+str(self.database)+"');$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->exec('PRAGMA foreign_keys=ON');")
        text=text.replace('$gateway=new Gateway($config);',"$gateway=new Gateway($config,static function($url,$payload,$key){$reply=json_decode(file_get_contents("+json.dumps(str(self.reply))+"),true);if(($payload['action']??'')==='revoke')return ['ok'=>$reply['ok']];return $reply;});")
        bootstrap.write_text(text)
        router=self.base/'router.php';router.write_text("<?php $_SERVER['HTTPS']='on'; $path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH); if($path==='/')$_SERVER['SCRIPT_FILENAME']=__DIR__.'/package/public/index.php'; if($path==='/') {require __DIR__.'/package/public/index.php';return true;} return false;")
        with socket.socket()as sock:sock.bind(('127.0.0.1',0));self.port=sock.getsockname()[1]
        self.logs=open(self.base/'server.log','w+b');self.server=subprocess.Popen([args.php,'-S',f'127.0.0.1:{self.port}','-t',str(self.package/'public'),str(router)],stdout=self.logs,stderr=self.logs)
        for _ in range(100):
            try:
                with socket.create_connection(('127.0.0.1',self.port),timeout=.1):break
            except OSError:time.sleep(.02)
        else:raise AssertionError('Fixture server did not start')
    def write_reply(self):self.reply.write_text(json.dumps({'ok':self.allowed,'identity':self.identity}))
    def request(self,path,method='GET',data=None,headers=None,cookies=None):
        h={'Host':self.host,'Cookie':'; '.join(k+'='+v for k,v in (self.cookies if cookies is None else cookies).items())};h.update(headers or {})
        if isinstance(data,dict):data=json.dumps(data).encode();h.setdefault('Content-Type','application/json')
        request=urllib.request.Request(f'http://127.0.0.1:{self.port}'+path,data=data,headers=h,method=method)
        try:r=opener.open(request,timeout=5)
        except urllib.error.HTTPError as error:r=error
        body=r.read();
        for header in r.headers.get_all('Set-Cookie')or[]:
            parsed=http.cookies.SimpleCookie();parsed.load(header)
            for key,value in parsed.items():
                if value.value:self.cookies[key]=value.value
                else:self.cookies.pop(key,None)
        return r.code,body,r.headers
    def login(self):
        status,body,headers=self.request('/suite-login.php');check(status==303,'login redirects to Suite')
        pending=headers.get_all('Set-Cookie')[0];check('SameSite=None' in pending and 'secure' in pending.lower() and 'httponly' in pending.lower() and 'domain='not in pending.lower(),'cross-site POST pending cookie')
        state=urllib.parse.parse_qs(urllib.parse.urlparse(headers['Location']).query)['state'][0]
        data=urllib.parse.urlencode({'state':state,'code':'c'*64}).encode();before=dict(self.cookies)
        status,body,headers=self.request('/suite-login.php','POST',data,{'Content-Type':'application/x-www-form-urlencoded'});check(status==303,'callback succeeds')
        check('__Host-defects-suite'in self.cookies,'new session cookie')
        check('SameSite=Lax'in '\n'.join(headers.get_all('Set-Cookie')),'normal session cookie Lax')
        status,body,_=self.request('/');check(status==200,'authenticated workspace')
        check(self.identity['session_token'].encode()not in body,'no Suite token in HTML')
        self.csrf=re.search(rb'name="csrf-token" content="([a-f0-9]+)"',body)[1].decode()
        return before,data
    def close(self):self.server.terminate();self.server.wait(timeout=5);self.logs.close()
with tempfile.TemporaryDirectory(prefix='defects-workspace-')as directory:
    instances=[]
    try:
        alpha=Instance(Path(directory),'alpha',3,7);instances.append(alpha)
        beta=Instance(Path(directory),'beta',4,8);instances.append(beta)
        for site in instances:
            check(site.request('/api.php')[0]==401,'anonymous API denied')
            check(site.request('/config/runtime.suite.private.php')[0]==404,'private config outside document root')
            check(site.request('/database/schema.sql')[0]==404,'legacy schema absent')
        before,callback=alpha.login();alpha_session=dict(alpha.cookies)
        status,body,_=alpha.request('/api.php?action=create','POST',{'title':'ALPHA only','description':'Fixture defect','priority':'high'}, {'X-CSRF-Token':alpha.csrf});check(status==200,(status,body));defect=json.loads(body)['id']
        check(alpha.request('/api.php?action=create','POST',{'title':'Denied'}, {'X-CSRF-Token':'bad'})[0]==403,'CSRF denied')
        check(alpha.request('/api.php?action=create','POST',{'title':'Denied','project_id':2},{'X-CSRF-Token':alpha.csrf})[0]==422,'foreign project denied')
        check(alpha.request('/api.php?action=update','POST',{'id':defect,'title':'ALPHA edited','description':'Saved','priority':'critical','status':'in_progress'},{'X-CSRF-Token':alpha.csrf})[0]==200,'manager edit')
        check(json.loads(alpha.request('/api.php')[1])['defects'][0]['title']=='ALPHA edited','edit persists on fresh request')
        check(alpha.request('/api.php?action=update','POST',{'id':999,'title':'Denied'},{'X-CSRF-Token':alpha.csrf})[0]==422,'missing record denied')
        # Actual multipart upload through PHP's HTTP upload handling.
        png=base64.b64decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jf1sAAAAASUVORK5CYII=')
        boundary='DefectsFixtureBoundary';body=(f'--{boundary}\r\nContent-Disposition: form-data; name="defect_id"\r\n\r\n{defect}\r\n--{boundary}\r\nContent-Disposition: form-data; name="file"; filename="fixture.png"\r\nContent-Type: image/png\r\n\r\n').encode()+png+f'\r\n--{boundary}--\r\n'.encode()
        status,result,_=alpha.request('/api.php?action=upload','POST',body,{'Content-Type':'multipart/form-data; boundary='+boundary,'X-CSRF-Token':alpha.csrf});check(status==200,(status,result));attachment=json.loads(result)['attachment_id']
        check(alpha.request('/files.php?id='+attachment)[1]==png,'private file download')
        check(alpha.request('/uploads/'+attachment)[0]==404,'no public upload path')
        beta.login();check(beta.request('/api.php',cookies=alpha_session)[0]==401,'Alpha cookie denied on Beta')
        beta.login();check(json.loads(beta.request('/api.php')[1])['defects']==[],'separate Beta data')
        check(beta.request('/files.php?id='+attachment)[0]==404,'Alpha file denied on Beta')
        status,result,_=beta.request('/api.php?action=create','POST',{'title':'BETA only'},{'X-CSRF-Token':beta.csrf});check(status==200,'Beta create')
        check(b'BETA only'not in alpha.request('/export.php')[1],'Alpha export excludes Beta')
        check(b'ALPHA edited'not in beta.request('/export.php')[1],'Beta export excludes Alpha')
        alpha.identity['role']='viewer';alpha.write_reply()
        check(alpha.request('/api.php?action=update','POST',{'id':defect,'title':'Denied'},{'X-CSRF-Token':alpha.csrf})[0]==403,'fresh viewer downgrade denies edit')
        check(alpha.request('/api.php?action=upload','POST',body,{'Content-Type':'multipart/form-data; boundary='+boundary,'X-CSRF-Token':alpha.csrf})[0]==403,'viewer upload denied')
        check(alpha.request('/export.php')[0]==200 and alpha.request('/files.php?id='+attachment)[0]==200,'viewer exports and files allowed')
        check(b'Add a defect'not in alpha.request('/')[1],'viewer form hidden')
        logout=urllib.parse.urlencode({'csrf':alpha.csrf}).encode();check(alpha.request('/suite-logout.php','POST',logout,{'Content-Type':'application/x-www-form-urlencoded'})[0]==200,'logout')
        check(alpha.request('/api.php',cookies=alpha_session)[0]==401,'old browser ID denied after logout')
        alpha.identity['role']='manager';alpha.write_reply();alpha.login();alpha.allowed=False;alpha.write_reply();check(alpha.request('/api.php')[0]==401,'Suite revocation denies active session')
        for site in instances:
            site.logs.flush();check(site.identity['session_token'].encode()not in (site.base/'server.log').read_bytes(),'token absent from request logs')
        check(not(source/'config/runtime.suite.private.php').exists(),'no source runtime credential file created')
    finally:
        for site in instances:site.close()
print(json.dumps({'passed':True,'checks':checks,'instances':2,'live_requests':0,'suite_transport':'synthetic'}))
