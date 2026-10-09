<?php
declare(strict_types=1);
namespace DefectTracker\Suite;
use PDO;
use PDOException;
use RuntimeException;
use Throwable;

/** Only called after SignIn::current has verified Suite and the database binding. */
final class UserMap
{
    public function __construct(private readonly PDO $db, private readonly array $binding) { new Gateway($binding); }

    public function resolve(array $identity): array
    {
        foreach (['instance_id','organization_id','project_id','local_project_id'] as $field) if (($identity[$field]??null)!==$this->binding[$field]) throw new RuntimeException('User binding mismatch.');
        if (($identity['module_key']??null)!=='defects' || !is_int($identity['user_id']??null) || $identity['user_id']<1 || !is_int($identity['session_expires_at']??null) || $identity['session_expires_at']<=time() || !is_string($identity['name']??null)) throw new RuntimeException('Invalid user identity.');
        $role=$identity['role']??null;
        if (!in_array($role,['platform_admin','admin','manager','site_manager','user','contractor','viewer'],true)) throw new RuntimeException('Invalid user role.');
        $type=in_array($role,['platform_admin','admin','manager','site_manager'],true)?'manager':'viewer';
        $legacyRole=$type==='manager'?'project_manager':'client';
        $name=substr($identity['name'],0,100);
        for($attempt=0;$attempt<2;$attempt++) {
            if($this->db->inTransaction()) throw new RuntimeException('User mapping requires its own transaction.');
            $this->db->beginTransaction();
            try {
                $stmt=$this->db->prepare('SELECT u.id,u.username,u.full_name,u.user_type,u.role,u.is_active FROM suite_user_map m JOIN users u ON u.id=m.local_user_id WHERE m.instance_id=? AND m.suite_user_id=?');
                $stmt->execute([$identity['instance_id'],$identity['user_id']]);$user=$stmt->fetch(PDO::FETCH_ASSOC);
                if(!$user) {
                    $stmt=$this->db->prepare('SELECT COUNT(*) FROM suite_user_map WHERE instance_id=? AND suite_user_id=?');$stmt->execute([$identity['instance_id'],$identity['user_id']]);
                    if((int)$stmt->fetchColumn()!==0) throw new RuntimeException('Broken user mapping.');
                    $username='suite_'.$identity['instance_id'].'_'.$identity['user_id'];
                    $email=$username.'@example.invalid';
                    $password=password_hash(bin2hex(random_bytes(32)),PASSWORD_DEFAULT);
                    $stmt=$this->db->prepare("INSERT INTO users(username,email,password,full_name,user_type,role,status,is_active) VALUES(?,?,?,?,?,?,'active',1)");
                    $stmt->execute([$username,$email,$password,$name,$type,$legacyRole]);$id=(int)$this->db->lastInsertId();
                    $stmt=$this->db->prepare('INSERT INTO suite_user_map(instance_id,suite_user_id,local_user_id) VALUES(?,?,?)');$stmt->execute([$identity['instance_id'],$identity['user_id'],$id]);
                    $user=['id'=>$id,'username'=>$username,'full_name'=>$name,'user_type'=>$type,'role'=>$legacyRole,'is_active'=>1];
                } elseif($user['full_name']!==$name || $user['user_type']!==$type || $user['role']!==$legacyRole || (int)$user['is_active']!==1) {
                    $stmt=$this->db->prepare("UPDATE users SET full_name=?,user_type=?,role=?,status='active',is_active=1 WHERE id=?");$stmt->execute([$name,$type,$legacyRole,$user['id']]);
                    $user['full_name']=$name;$user['user_type']=$type;$user['role']=$legacyRole;$user['is_active']=1;
                }
                $this->db->commit();$user['id']=(int)$user['id'];return $user;
            } catch(Throwable $e) {
                if($this->db->inTransaction())$this->db->rollBack();
                if($attempt===0 && $e instanceof PDOException && (string)$e->getCode()==='23000')continue;
                throw new RuntimeException('Suite user could not be mapped.');
            }
        }
        throw new RuntimeException('Suite user could not be mapped.');
    }
}
