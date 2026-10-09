<?php
declare(strict_types=1);
namespace DefectTracker\Suite;
use Closure;
use RuntimeException;

/** Staging-only identity client. Never write its tokens into legacy PHP sessions. No routes are enabled by this class. */
final class Gateway
{
    private readonly ?Closure $transport;
    public function __construct(private readonly array $binding, ?callable $transport=null)
    {
        if (($binding['staging_only'] ?? null) !== true || ($binding['module_key'] ?? null) !== 'defects') throw new RuntimeException('Staging Defects binding required.');
        foreach (['instance_id','organization_id','project_id','local_project_id'] as $field) {
            if (!is_int($binding[$field]??null)||$binding[$field]<1) throw new RuntimeException('Invalid project binding.');
        }
        if (($binding['suite_origin']??'')!=='https://suite.defecttracker.uk') throw new RuntimeException('Invalid Suite origin.');
        $origin=(string)($binding['origin']??'');$parts=parse_url($origin);
        $host=(string)($parts['host']??'');
        if (!$parts||($parts['scheme']??'')!=='https'||isset($parts['user'])||isset($parts['pass'])||isset($parts['port'])||isset($parts['query'])||isset($parts['fragment'])||($parts['path']??'')!==''||!filter_var($host,FILTER_VALIDATE_DOMAIN,FILTER_FLAG_HOSTNAME)||!str_ends_with($host,'.defectnotice.site')) throw new RuntimeException('Invalid staging Defect Tracker origin.');
        if (!preg_match('/^[a-f0-9]{64}$/D',(string)($binding['key']??''))) throw new RuntimeException('Instance authentication is not configured.');
        $this->transport=$transport===null?null:Closure::fromCallable($transport);
    }

    public function begin(): array
    {
        $state=bin2hex(random_bytes(32));
        return ['state'=>$state,'url'=>$this->binding['suite_origin'].'/launch.php?instance_id='.$this->binding['instance_id'].'&state='.$state];
    }

    public function redeem(string $code,string $state,string $cookieState): array
    {
        if (!preg_match('/^[a-f0-9]{64}$/D',$code)||!preg_match('/^[a-f0-9]{64}$/D',$state)||!hash_equals($state,$cookieState)) throw new RuntimeException('Invalid browser sign-in state.');
        $identity=$this->identity($this->request('module-handoff',['code'=>$code,'state'=>$state]));
        if (!preg_match('/^[a-f0-9]{64}$/D',(string)($identity['session_token']??'')))throw new RuntimeException('Invalid tool session.');
        return $identity;
    }

    public function validate(string $token): array
    {
        if (!preg_match('/^[a-f0-9]{64}$/D',$token))throw new RuntimeException('Invalid tool session.');
        return $this->identity($this->request('module-session',['action'=>'validate','session_token'=>$token]));
    }

    public function revoke(string $token): void
    {
        if (!preg_match('/^[a-f0-9]{64}$/D',$token))throw new RuntimeException('Invalid tool session.');
        $this->request('module-session',['action'=>'revoke','session_token'=>$token]);
    }

    private function identity(array $reply): array
    {
        $identity=$reply['identity']??null;
        if (!is_array($identity))throw new RuntimeException('Invalid Suite identity.');
        foreach (['instance_id','organization_id','project_id'] as $field) {
            if (($identity[$field]??null)!==$this->binding[$field])throw new RuntimeException('Project binding mismatch.');
        }
        if (($identity['module_key']??'')!=='defects'||!is_int($identity['user_id']??null)||$identity['user_id']<1||!is_int($identity['session_expires_at']??null)||$identity['session_expires_at']<=time()||!is_string($identity['role']??null))throw new RuntimeException('Invalid Suite identity.');
        $roles=['platform_admin','admin','manager','site_manager','user','contractor','viewer'];
        if (!in_array($identity['role'], $roles, true)) throw new RuntimeException('Unsupported Suite role.');
        foreach (['name','email'] as $field)if (!is_string($identity[$field]??null)||strlen($identity[$field])>255)throw new RuntimeException('Invalid Suite identity.');
        $identity['local_project_id']=$this->binding['local_project_id'];
        return $identity;
    }

    private function request(string $endpoint,array $payload): array
    {
        $payload['instance_id']=$this->binding['instance_id'];
        $url=$this->binding['suite_origin'].'/api/v1/'.$endpoint.'.php';
        try {
            if ($this->transport!==null) {
                $reply=($this->transport)($url,$payload,$this->binding['key']);
            } else {
                if (!function_exists('curl_init'))throw new RuntimeException('Unavailable.');
                $body='';$ch=curl_init($url);
                curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode($payload,JSON_THROW_ON_ERROR),CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>2,CURLOPT_TIMEOUT=>5,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,
                    CURLOPT_HTTPHEADER=>['Content-Type: application/json','Accept: application/json','X-Construction-Suite-Key: '.$this->binding['key']],
                    CURLOPT_WRITEFUNCTION=>static function($handle,string $chunk)use(&$body):int {if(strlen($body)+strlen($chunk)>65536)return 0;$body.=$chunk;return strlen($chunk);}]);
                $ok=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
                if (!$ok||$status!==200)throw new RuntimeException('Unavailable.');
                $reply=json_decode($body,true,8,JSON_THROW_ON_ERROR);
            }
            if (!is_array($reply)||($reply['ok']??false)!==true)throw new RuntimeException('Access denied.');
            return $reply;
        } catch (\Throwable $e) {throw new RuntimeException('Suite access could not be verified.');}
    }
}
