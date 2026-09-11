<?php
/** Temporary acceptance-test mode. An absent config preserves normal delivery. */
class TestNotificationRouter
{
    private $db;
    private $config;
    private $transport;

    public function __construct($db, $config = null, $transport = null)
    {
        $this->db = $db;
        if ($config === null) {
            require_once __DIR__ . '/SiteMail.php';
            try {
                $saved = SiteMail::load();
                if (!empty($saved['test_mode'])) {
                    $config = $saved;
                    $transport = function ($to, $subject, $body, $headers) use ($saved) {
                        return SiteMail::send($saved, $to, $subject, $body);
                    };
                }
            } catch (Throwable $e) { $config = []; }
        }
        $path = __DIR__ . '/../config/notification-test.local.php';
        if ($config === null && is_file($path)) {
            try {
                $config = require $path;
                if (!is_array($config)) $config = [];
            } catch (Throwable $e) {
                $config = [];
            }
        }
        $this->config = $config;
        $this->transport = $transport ?: function ($to, $subject, $body, $headers) {
            return mail($to, $subject, $body, $headers);
        };
    }

    public function active()
    {
        return $this->config !== null;
    }

    /** Null means normal routing; every array result prevents audience fallback. */
    public function route($defectId, $event)
    {
        if (!$this->active()) return null;
        $result = ['success' => false, 'error' => 'Notification suppressed by acceptance-test mode'];
        try {
            $config = $this->config;
            if (!is_array($config) || empty($config['project_id']) || !$defectId) return $result;
            $stmt = $this->db->prepare('SELECT project_id FROM defects WHERE id = ?');
            if (!$stmt->execute([$defectId])) return $result;
            $projectId = $stmt->fetchColumn();
            if (!$projectId || (string)$projectId !== (string)$config['project_id']) return $result;
            foreach (['recipient', 'sender'] as $key) {
                $value = $config[$key] ?? '';
                if (!is_string($value) || preg_match('/[\r\n]/', $value) || !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    throw new RuntimeException('Invalid mail configuration');
                }
            }
            if (($config['send_enabled'] ?? false) !== true) return $result;
            $events = ['created', 'assigned', 'status_changed', 'comment_added', 'push'];
            if (!in_array($event, $events, true)) return $result;
            $accepted = ($this->transport)(
                $config['recipient'],
                '[TEST - DO NOT ACTION] Defect Tracker notification',
                "Acceptance test only. No site action required.\nDefect: " . (int)$defectId . "\nEvent: " . $event . "\n",
                ['From' => $config['sender'], 'Content-Type' => 'text/plain; charset=UTF-8']
            );
            error_log('Acceptance-test notification ' . ($accepted ? 'accepted by mail transport' : 'failed') . ' for defect ' . (int)$defectId);
            return $accepted
                ? ['success' => true, 'recipients' => 1, 'message' => 'Accepted by mail transport; inbox delivery unverified']
                : ['success' => false, 'error' => 'Test mail transport failed; no fallback recipients'];
        } catch (Throwable $e) {
            error_log('Acceptance-test notification failed; no fallback recipients');
            return $result;
        }
    }
}
