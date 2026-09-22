<?php
require_once __DIR__ . '/../config/env.php';

class SiteMail {
    public static function path() { return dirname(__DIR__, 2) . '/.defecttracker-mail.json'; }

    public static function load() {
        $defaults = [
            'host' => Environment::get('MAIL_HOST', ''),
            'port' => (int) Environment::get('MAIL_PORT', '465'),
            'encryption' => strtolower((string) Environment::get('MAIL_ENCRYPTION', 'ssl')),
            'username' => Environment::get('MAIL_USERNAME', ''),
            'password' => Environment::get('MAIL_PASSWORD', ''),
            'sender' => Environment::get('MAIL_FROM', ''),
            'sender_name' => Environment::get('MAIL_FROM_NAME', 'Defect Tracker'),
            'recipient' => Environment::get('MAIL_TEST_RECIPIENT', ''),
            'project_id' => null,
            'test_mode' => false,
            'send_enabled' => false,
        ];
        if (!is_file(self::path())) return $defaults;
        $data = json_decode(file_get_contents(self::path()), true);
        if (!is_array($data)) throw new RuntimeException('Email settings could not be read.');
        return array_merge($defaults, $data);
    }

    public static function save(array $data) {
        $path = self::path();
        $old = umask(0077);
        try {
            $tmp = tempnam(dirname($path), '.mail-');
            if (!$tmp) throw new RuntimeException('Email settings directory is not writable.');
            try {
                if (file_put_contents($tmp, json_encode($data, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT), LOCK_EX) === false || !rename($tmp, $path)) {
                    throw new RuntimeException('Email settings could not be saved.');
                }
            } finally {
                if (is_file($tmp)) @unlink($tmp);
            }
        } finally { umask($old); }
    }

    public static function send(array $config, $to, $subject, $body) {
        $config = array_merge(self::load(), $config);
        if (!filter_var($to, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/', $to . $subject)) throw new RuntimeException('Invalid email.');
        foreach (['host','username','password','sender'] as $key) {
            if (empty($config[$key])) throw new RuntimeException('Email settings are incomplete.');
        }
        if (!filter_var($config['sender'], FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Sender email is invalid.');
        if (!function_exists('curl_init')) throw new RuntimeException('PHP cURL is required.');

        $port = max(1, min(65535, (int)($config['port'] ?? 465)));
        $encryption = strtolower((string)($config['encryption'] ?? 'ssl'));
        $scheme = $encryption === 'ssl' ? 'smtps' : 'smtp';
        $from = (string)$config['sender'];
        $fromName = trim((string)($config['sender_name'] ?? 'Defect Tracker'));
        $domain = substr(strrchr($from, '@') ?: '@localhost', 1);
        $message = 'From: '.$fromName.' <'.$from.">\r\nTo: ".$to."\r\nSubject: ".$subject."\r\nDate: ".gmdate('D, d M Y H:i:s')." +0000\r\nMessage-ID: <".bin2hex(random_bytes(16)).'@'.$domain.">\r\nMIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n".chunk_split(base64_encode($body),76,"\r\n");
        $input = fopen('php://temp','r+'); fwrite($input,$message); rewind($input);
        $curl = curl_init($scheme.'://'.$config['host'].':'.$port);
        try {
            $opts = [
                CURLOPT_USERNAME => (string)$config['username'],
                CURLOPT_PASSWORD => (string)$config['password'],
                CURLOPT_MAIL_FROM => $from,
                CURLOPT_MAIL_RCPT => [$to],
                CURLOPT_UPLOAD => true,
                CURLOPT_INFILE => $input,
                CURLOPT_INFILESIZE => strlen($message),
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT => 25,
                CURLOPT_RETURNTRANSFER => true,
            ];
            if ($encryption === 'tls') $opts[CURLOPT_USE_SSL] = CURLUSESSL_ALL;
            curl_setopt_array($curl, $opts);
            if (curl_exec($curl) === false) throw new RuntimeException('SMTP delivery failed. Check the server settings and credentials.');
            return true;
        } finally { curl_close($curl); fclose($input); }
    }
}
