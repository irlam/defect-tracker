<?php
declare(strict_types=1);
namespace DefectTracker\Suite;

use Closure;
use RuntimeException;
use Throwable;

/** Dedicated private files, never the legacy PHP session or browser storage. */
final class SessionStore
{
    private readonly string $audience;
    private readonly Closure $clock;

    public function __construct(private readonly string $directory, array $binding, ?callable $clock = null)
    {
        new Gateway($binding);
        $audience = [];
        foreach (['instance_id','organization_id','project_id','local_project_id','origin','module_key','key'] as $field) $audience[$field] = $binding[$field];
        $this->audience = hash('sha256', json_encode($audience, JSON_THROW_ON_ERROR));
        $this->clock = $clock === null ? static fn(): int => time() : Closure::fromCallable($clock);
        $this->checkDirectory();
    }

    public function create(array $record): string
    {
        $this->checkDirectory();
        $record['audience'] = $this->audience;
        $this->checkRecord($record);
        $id = bin2hex(random_bytes(32));
        $path = $this->path($id);
        $mask = umask(0077);
        try {
            $handle = @fopen($path, 'x+b');
        } finally { umask($mask); }
        if ($handle === false) throw new RuntimeException('Private session unavailable.');
        try {
            $data = json_encode($record, JSON_THROW_ON_ERROR);
            if (strlen($data) > 8192 || @fwrite($handle, $data) !== strlen($data) || !@fflush($handle)) throw new RuntimeException('Private session unavailable.');
        } catch (Throwable $e) {
            @unlink($path);
            throw new RuntimeException('Private session unavailable.');
        } finally { fclose($handle); }
        return $id;
    }

    public function read(string $id): array { return $this->access($id, false); }
    public function consume(string $id): array { return $this->access($id, true); }

    public function remove(string $id): void
    {
        $this->checkDirectory();
        $path = $this->path($id);
        if (is_link($path)) throw new RuntimeException('Private session unavailable.');
        if (is_file($path) && !@unlink($path)) throw new RuntimeException('Private session unavailable.');
    }

    /** Bounded cleanup of this audience's expired records; no Suite calls. */
    public function pruneExpired(bool $apply = false, int $limit = 1000): array
    {
        $this->checkDirectory();
        if($limit<1||$limit>1000)throw new RuntimeException('Invalid cleanup limit.');
        $checked=0;$expired=0;$removed=0;
        foreach(glob($this->directory.'/'.$this->audience.'-*.json')?:[]as$path){
            if($checked++ >= $limit)break;
            $stat=@lstat($path);if(!$stat||($stat['mode']&0170000)!==0100000||($stat['mode']&0077)!==0||$stat['size']>8192)continue;
            $handle=@fopen($path,'rb');if(!$handle)continue;
            try{
                if(!flock($handle,LOCK_EX))continue;
                clearstatcache(true,$path);$named=@stat($path);$opened=fstat($handle);
                if(!$named||!$opened||$named['ino']!==$opened['ino']||$named['dev']!==$opened['dev'])continue;
                $record=json_decode((string)stream_get_contents($handle,8193),true,8);
                if(!is_array($record)||($record['audience']??null)!==$this->audience||!is_int($record['expires_at']??null)||$record['expires_at']>($this->clock)())continue;
                $expired++;if($apply&&@unlink($path))$removed++;
            }finally{flock($handle,LOCK_UN);fclose($handle);}
        }
        return ['expired'=>$expired,'removed'=>$removed];
    }

    private function access(string $id, bool $consume): array
    {
        $this->checkDirectory();
        $path = $this->path($id);
        clearstatcache(true, $path);
        $stat = @lstat($path);
        if (!$stat || ($stat['mode'] & 0170000) !== 0100000 || ($stat['mode'] & 0077) !== 0 || $stat['size'] > 8192) throw new RuntimeException('Private session unavailable.');
        $handle = @fopen($path, 'rb');
        if (!$handle) throw new RuntimeException('Private session unavailable.');
        try {
            if (!flock($handle, LOCK_EX)) throw new RuntimeException();
            clearstatcache(true, $path);
            $named = @stat($path); $opened = fstat($handle);
            if (!$named || !$opened || $named['ino'] !== $opened['ino'] || $named['dev'] !== $opened['dev']) throw new RuntimeException();
            $data = stream_get_contents($handle, 8193);
            if (!is_string($data) || strlen($data) > 8192) throw new RuntimeException();
            $record = json_decode($data, true, 8, JSON_THROW_ON_ERROR);
            if (!is_array($record)) throw new RuntimeException();
            $this->checkRecord($record);
            // Unlink while locked. A second consumer holding the old inode must fail.
            if ($consume && !@unlink($path)) throw new RuntimeException();
            return $record;
        } catch (Throwable $e) {
            throw new RuntimeException('Private session unavailable.');
        } finally { flock($handle, LOCK_UN); fclose($handle); }
    }

    private function checkRecord(array $record): void
    {
        if (($record['audience'] ?? null) !== $this->audience || !is_int($record['expires_at'] ?? null) || $record['expires_at'] <= ($this->clock)()) throw new RuntimeException('Private session unavailable.');
        $hex = static fn(mixed $v): bool => is_string($v) && preg_match('/^[a-f0-9]{64}$/D', $v) === 1;
        if (($record['kind'] ?? '') === 'pending') {
            if (array_diff(array_keys($record), ['kind','state','expires_at','audience'])) throw new RuntimeException('Private session unavailable.');
            if (!$hex($record['state'] ?? null) || isset($record['token'])) throw new RuntimeException('Private session unavailable.');
        } elseif (($record['kind'] ?? '') === 'authenticated') {
            if (array_diff(array_keys($record), ['kind','token','csrf','user_id','expires_at','audience'])) throw new RuntimeException('Private session unavailable.');
            if (!$hex($record['token'] ?? null) || !$hex($record['csrf'] ?? null) || !is_int($record['user_id'] ?? null) || $record['user_id'] < 1 || isset($record['state'])) throw new RuntimeException('Private session unavailable.');
        } else { throw new RuntimeException('Private session unavailable.'); }
    }

    private function path(string $id): string
    {
        if (!preg_match('/^[a-f0-9]{64}$/D', $id)) throw new RuntimeException('Private session unavailable.');
        return $this->directory . '/' . $this->audience . '-' . hash('sha256', $id) . '.json';
    }

    private function checkDirectory(): void
    {
        clearstatcache(true, $this->directory);
        if (realpath($this->directory) !== $this->directory || !is_dir($this->directory) || (fileperms($this->directory) & 0077) !== 0 || !is_writable($this->directory)) throw new RuntimeException('Private session unavailable.');
        for ($path = $this->directory; $path !== '/'; $path = dirname($path)) if (is_link($path)) throw new RuntimeException('Private session unavailable.');
    }
}
