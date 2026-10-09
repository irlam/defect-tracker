<?php
declare(strict_types=1);
namespace DefectTracker\Suite;
use PDO;
use RuntimeException;
use Throwable;

/** Reviewed register operations for the isolated staging workspace. */
final class Defects
{
    public function __construct(private readonly PDO $db,private readonly array $binding){new Gateway($binding);}
    public function all():array{
        $q=$this->db->prepare('SELECT id,title,description,status,priority,updated_at FROM defects WHERE project_id=? ORDER BY id DESC');$q->execute([$this->binding['local_project_id']]);
        $rows=$q->fetchAll(PDO::FETCH_ASSOC);
        foreach($rows as&$row){$row['id']=(int)$row['id'];$q=$this->db->prepare('SELECT id,original_name,mime,file_size FROM suite_attachments WHERE defect_id=? ORDER BY created_at');$q->execute([$row['id']]);$row['attachments']=$q->fetchAll(PDO::FETCH_ASSOC);}unset($row);return $rows;
    }
    public function find(mixed $id):array{
        $id=self::id($id);$q=$this->db->prepare('SELECT * FROM defects WHERE id=? AND project_id=?');$q->execute([$id,$this->binding['local_project_id']]);$row=$q->fetch(PDO::FETCH_ASSOC);if(!$row)throw new RuntimeException('Defect unavailable.');return $row;
    }
    public function save(array $data,int $userId,mixed $id=null):int{
        foreach(array_keys($data)as$key)if(!in_array($key,['title','description','priority','status','project_id'],true))throw new RuntimeException('Unexpected defect field.');
        if(isset($data['project_id']) && self::id($data['project_id'])!==$this->binding['local_project_id'])throw new RuntimeException('Project selection denied.');
        foreach(['title','description','priority','status']as$field)if(isset($data[$field])&&!is_string($data[$field]))throw new RuntimeException('Invalid defect field.');
        $title=trim($data['title']??'');$description=trim($data['description']??'');$priority=$data['priority']??'normal';$status=$data['status']??'open';
        if($title===''||strlen($title)>190||strlen($description)>10000||preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/',$title.$description)||!in_array($priority,['low','normal','high','critical'],true)||!in_array($status,['open','in_progress','pending','accepted','rejected'],true)||$userId<1)throw new RuntimeException('Invalid defect details.');
        if($id===null){$q=$this->db->prepare('INSERT INTO defects(project_id,title,description,status,priority,created_by,updated_by)VALUES(?,?,?,?,?,?,?)');$q->execute([$this->binding['local_project_id'],$title,$description,'open',$priority,$userId,$userId]);return(int)$this->db->lastInsertId();}
        $row=$this->find($id);$q=$this->db->prepare('UPDATE defects SET title=?,description=?,status=?,priority=?,updated_by=?,updated_at=CURRENT_TIMESTAMP WHERE id=? AND project_id=?');$q->execute([$title,$description,$status,$priority,$userId,$row['id'],$this->binding['local_project_id']]);return(int)$row['id'];
    }
    public function attach(mixed $defectId,array $upload,int $userId):string{
        $defect=$this->find($defectId);
        if(($upload['error']??-1)!==UPLOAD_ERR_OK||!is_string($upload['tmp_name']??null)||!is_uploaded_file($upload['tmp_name']))throw new RuntimeException('Attachment upload failed.');
        $size=filesize($upload['tmp_name']);if(!$size||$size>5242880)throw new RuntimeException('Attachment must be under 5 MB.');
        $mime=(new \finfo(FILEINFO_MIME_TYPE))->file($upload['tmp_name']);if(!in_array($mime,['image/jpeg','image/png','application/pdf'],true))throw new RuntimeException('Use a JPEG, PNG or PDF attachment.');
        $name=substr(preg_replace('/[\x00-\x1F\x7F]/','',basename((string)($upload['name']??'attachment'))),0,190);if($name==='')$name='attachment';
        $id=bin2hex(random_bytes(32));$path=$this->binding['upload_root'].'/'.$id;
        $mask=umask(0077);try{if(!move_uploaded_file($upload['tmp_name'],$path))throw new RuntimeException('Attachment upload failed.');}finally{umask($mask);}
        chmod($path,0600);
        try{$q=$this->db->prepare('INSERT INTO suite_attachments(id,defect_id,original_name,mime,file_size,created_by)VALUES(?,?,?,?,?,?)');$q->execute([$id,$defect['id'],$name,$mime,$size,$userId]);}
        catch(Throwable $e){@unlink($path);throw new RuntimeException('Attachment could not be saved.');}return$id;
    }
    public function attachment(mixed $id):array{
        if(!is_string($id)||!preg_match('/^[a-f0-9]{64}$/D',$id))throw new RuntimeException('Attachment unavailable.');
        $q=$this->db->prepare('SELECT a.* FROM suite_attachments a JOIN defects d ON d.id=a.defect_id WHERE a.id=? AND d.project_id=?');$q->execute([$id,$this->binding['local_project_id']]);$row=$q->fetch(PDO::FETCH_ASSOC);$path=$this->binding['upload_root'].'/'.$id;
        clearstatcache(true,$path);if(!$row||is_link($path)||!is_file($path)||(fileperms($path)&0077)!==0||(int)filesize($path)!==(int)$row['file_size'])throw new RuntimeException('Attachment unavailable.');$row['path']=$path;return$row;
    }
    public static function id(mixed $id):int{if((!is_int($id)&&!is_string($id))||!preg_match('/^[1-9][0-9]*$/D',(string)$id)||(int)$id<1)throw new RuntimeException('Invalid record selection.');return(int)$id;}
}
