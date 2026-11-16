<?php
declare(strict_types=1);

/**
 * Minimal SMTP client (AUTH LOGIN, STARTTLS or implicit TLS) — enough for
 * Simply.com's smtp.simply.com without pulling in Composer packages.
 */
final class Smtp
{
    /** @var resource|null */
    private $sock = null;
    private array $log = [];

    public function __construct(private array $cfg)
    {
    }

    /** @param string[] $to */
    public function send(array $to, string $subject, string $html, string $text): void
    {
        $from = $this->cfg['from'];
        $fromName = $this->cfg['from_name'] ?? '';
        $this->connect();
        try {
            $this->cmd('MAIL FROM:<' . $from . '>', [250]);
            foreach ($to as $rcpt) {
                $this->cmd('RCPT TO:<' . $rcpt . '>', [250, 251]);
            }
            $this->cmd('DATA', [354]);
            $msg = $this->buildMessage($from, $fromName, $to, $subject, $html, $text);
            // Dot-stuffing + terminator
            $msg = preg_replace('/^\./m', '..', $msg);
            fwrite($this->sock, $msg . "\r\n.\r\n");
            $this->expect([250]);
            $this->cmd('QUIT', [221]);
        } finally {
            if ($this->sock) {
                fclose($this->sock);
                $this->sock = null;
            }
        }
    }

    public function log(): array
    {
        return $this->log;
    }

    private function connect(): void
    {
        $host = $this->cfg['host'];
        $port = (int) $this->cfg['port'];
        $enc  = $this->cfg['encryption'] ?? 'tls';
        $ctx  = stream_context_create(['ssl' => [
            'verify_peer' => true, 'verify_peer_name' => true, 'peer_name' => $host,
        ]]);
        $remote = ($enc === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
        $sock = @stream_socket_client($remote, $errno, $errstr, 20, STREAM_CLIENT_CONNECT, $ctx);
        if (!$sock) {
            throw new RuntimeException("SMTP connect to $remote failed: $errstr ($errno)");
        }
        stream_set_timeout($sock, 30);
        $this->sock = $sock;
        $this->expect([220]);
        $ehloHost = gethostname() ?: 'localhost';
        $this->cmd('EHLO ' . $ehloHost, [250]);

        if ($enc === 'tls') {
            $this->cmd('STARTTLS', [220]);
            if (!stream_socket_enable_crypto($this->sock, true,
                STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT)) {
                throw new RuntimeException('SMTP STARTTLS negotiation failed');
            }
            $this->cmd('EHLO ' . $ehloHost, [250]);
        }

        if (($this->cfg['username'] ?? '') !== '') {
            $this->cmd('AUTH LOGIN', [334]);
            $this->cmd(base64_encode($this->cfg['username']), [334], true);
            $this->cmd(base64_encode($this->cfg['password']), [235], true);
        }
    }

    private function cmd(string $line, array $ok, bool $secret = false): string
    {
        $this->log[] = 'C: ' . ($secret ? '********' : $line);
        fwrite($this->sock, $line . "\r\n");
        return $this->expect($ok);
    }

    private function expect(array $ok): string
    {
        $resp = '';
        while (($line = fgets($this->sock, 1024)) !== false) {
            $resp .= $line;
            if (strlen($line) < 4 || $line[3] === ' ') {
                break;
            }
        }
        $this->log[] = 'S: ' . trim($resp);
        $code = (int) substr($resp, 0, 3);
        if (!in_array($code, $ok, true)) {
            throw new RuntimeException('SMTP error: ' . trim($resp));
        }
        return $resp;
    }

    private function buildMessage(string $from, string $fromName, array $to, string $subject, string $html, string $text): string
    {
        $boundary = 'b' . bin2hex(random_bytes(12));
        $domain = substr(strrchr($from, '@') ?: '@localhost', 1);
        $headers = [
            'Date: ' . date(DATE_RFC2822),
            'Message-ID: <' . bin2hex(random_bytes(16)) . '@' . $domain . '>',
            'From: ' . ($fromName !== '' ? $this->encodeHeader($fromName) . ' ' : '') . '<' . $from . '>',
            'To: ' . implode(', ', array_map(static fn($a) => '<' . $a . '>', $to)),
            'Subject: ' . $this->encodeHeader($subject),
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
            'X-Mailer: FreeLoot/1.0',
        ];
        $body  = "--$boundary\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n";
        $body .= rtrim(chunk_split(base64_encode($text), 76, "\r\n")) . "\r\n";
        $body .= "--$boundary\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n";
        $body .= rtrim(chunk_split(base64_encode($html), 76, "\r\n")) . "\r\n";
        $body .= "--$boundary--";
        return implode("\r\n", $headers) . "\r\n\r\n" . $body;
    }

    private function encodeHeader(string $s): string
    {
        return preg_match('/[^\x20-\x7E]/', $s) ? '=?UTF-8?B?' . base64_encode($s) . '?=' : $s;
    }
}
