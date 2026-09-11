<?php
/** SMTP settings are stored outside the website document root. */
class SiteMail {
    public static function path() { return dirname(__DIR__, 2) . '/.defecttracker-mail.json'; }
    public static function load() {
        if (!is_file(self::path())) return [];
        $data = json_decode(file_get_contents(self::path()), true);
        if (!is_array($data)) throw new RuntimeException('Email settings could not be read.');
        return $data;
    }
    public static function save(array $data) {
        $path = self::path();
        $old = umask(0077);
        try {
            $tmp = tempnam(dirname($path), '.mail-');
            if (!$tmp) throw new RuntimeException('Email settings directory is not writable.');
            try {
                if (file_put_contents($tmp, json_encode($data, JSON_THROW_ON_ERROR), LOCK_EX) === false || !rename($tmp, $path)) {
                    throw new RuntimeException('Email settings could not be saved.');
                }
            } finally { if (is_file($tmp)) unlink($tmp); }
        } finally { umask($old); }
    }
    public static function send(array $config, $to, $subject, $body) {
        if (!filter_var($to, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/', $to . $subject)) throw new RuntimeException('Invalid email.');
        if (empty($config['password'])) throw new RuntimeException('Save the mailbox password first.');
        if (!function_exists('curl_init')) throw new RuntimeException('PHP cURL is required.');
        $from = 'admin@defecttracker.uk';
        $message = 'From: Defect Tracker <'.$from.">\r\nTo: ".$to."\r\nSubject: ".$subject."\r\nDate: ".gmdate('D, d M Y H:i:s')." +0000\r\nMessage-ID: <".bin2hex(random_bytes(16))."@defecttracker.uk>\r\nMIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n".chunk_split(base64_encode($body), 76, "\r\n");
        $input = fopen('php://temp', 'r+');
        fwrite($input, $message); rewind($input);
        $curl = curl_init('smtps://mxe97d.netcup.net:465');
        try {
            curl_setopt_array($curl, [CURLOPT_USERNAME => $from, CURLOPT_PASSWORD => $config['password'], CURLOPT_MAIL_FROM => $from, CURLOPT_MAIL_RCPT => [$to], CURLOPT_UPLOAD => true, CURLOPT_INFILE => $input, CURLOPT_INFILESIZE => strlen($message), CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_TIMEOUT => 25, CURLOPT_RETURNTRANSFER => true]);
            if (curl_exec($curl) === false) throw new RuntimeException('SMTP delivery failed. Check the password and server connection.');
            return true;
        } finally { curl_close($curl); fclose($input); }
    }
}
