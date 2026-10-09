<?php
declare(strict_types=1);
namespace DefectTracker\Suite;

use RuntimeException;
use Throwable;

/** HTTP controllers must enforce CookiePolicy before using this internal flow. */
final class SignIn
{
    public function __construct(private readonly Gateway $gateway, private readonly ProjectScope $scope, private readonly SessionStore $store) {}

    public function begin(): array
    {
        $this->scope->assertDatabase();
        $pending = $this->gateway->begin();
        $id = $this->store->create(['kind'=>'pending', 'state'=>$pending['state'], 'expires_at'=>time()+300]);
        return ['pending_id'=>$id, 'url'=>$pending['url']];
    }

    public function accept(string $pendingId, string $code, string $state): array
    {
        $token = null;
        try {
            $pending = $this->store->consume($pendingId);
            if ($pending['kind'] !== 'pending') throw new RuntimeException();
            $identity = $this->gateway->redeem($code, $state, $pending['state']);
            $token = $identity['session_token'];
            $current = $this->scope->current($token);
            if ($current['user_id'] !== $identity['user_id'] || $current['session_expires_at'] !== $identity['session_expires_at']) throw new RuntimeException();
            $csrf = bin2hex(random_bytes(32));
            $sessionId = $this->store->create(['kind'=>'authenticated', 'token'=>$token, 'csrf'=>$csrf, 'user_id'=>$current['user_id'], 'expires_at'=>$current['session_expires_at']]);
            return ['session_id'=>$sessionId, 'csrf'=>$csrf, 'identity'=>self::browserIdentity($current)];
        } catch (Throwable $e) {
            if ($token !== null) { try { $this->gateway->revoke($token); } catch (Throwable $ignored) {} }
            throw new RuntimeException('Suite sign-in could not be verified.');
        }
    }

    public function current(string $sessionId): array
    {
        try {
            $record = $this->store->read($sessionId);
            if ($record['kind'] !== 'authenticated') throw new RuntimeException();
            $identity = $this->scope->current($record['token']);
            if ($identity['user_id'] !== $record['user_id'] || $identity['session_expires_at'] !== $record['expires_at']) throw new RuntimeException();
            return ['identity'=>self::browserIdentity($identity), 'csrf'=>$record['csrf']];
        } catch (Throwable $e) {
            try { $this->store->remove($sessionId); } catch (Throwable $ignored) {}
            throw new RuntimeException('Suite access could not be verified.');
        }
    }

    public function requireWrite(string $sessionId, string $method, string $csrf): array
    {
        $current = $this->current($sessionId);
        if (!in_array($method, ['POST','PUT','PATCH','DELETE'], true) || !hash_equals($current['csrf'], $csrf)) throw new RuntimeException('Change request denied.');
        // Conservative staging policy until legacy actions have been reviewed.
        if (!in_array($current['identity']['role'], ['platform_admin','admin','manager','site_manager'], true)) throw new RuntimeException('Changes are not available to this account.');
        return $current['identity'];
    }

    public function logout(string $sessionId, string $method, string $csrf): bool
    {
        if ($method !== 'POST') throw new RuntimeException('Logout request denied.');
        $record = $this->store->read($sessionId);
        if ($record['kind'] !== 'authenticated' || !hash_equals($record['csrf'], $csrf)) throw new RuntimeException('Logout request denied.');
        $record = $this->store->consume($sessionId); // Local access ends before network work.
        try { $this->gateway->revoke($record['token']); return true; }
        catch (Throwable $e) { return false; } // Local logout succeeds; remote session remains subject to Suite expiry/global logout.
    }

    private static function browserIdentity(array $identity): array
    {
        return array_intersect_key($identity, array_flip(['instance_id','organization_id','project_id','local_project_id','module_key','user_id','role','name','email','session_expires_at']));
    }
}
