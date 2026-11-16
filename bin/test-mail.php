<?php
declare(strict_types=1);
/**
 * Send a test email using the SMTP settings in config.php (prints the SMTP log).
 *   php bin/test-mail.php
 */

require dirname(__DIR__) . '/bootstrap.php';

$cfg = app_config();
$smtp = new Smtp($cfg['mail']);
$html = '<div style="background:#07080f;color:#e8eaf6;padding:30px;font-family:Arial,sans-serif;">'
      . '<h1 style="color:#22e3ff;margin:0">&#9654; Free Loot test</h1><p>If you can read this, SMTP works.</p></div>';
try {
    $smtp->send((array) $cfg['mail']['to'], 'Free Loot · SMTP test', $html, "Free Loot test — SMTP works.\n");
    echo implode("\n", $smtp->log()), "\n\nOK — test mail sent to " . implode(', ', (array) $cfg['mail']['to']) . "\n";
} catch (Throwable $e) {
    echo implode("\n", $smtp->log()), "\n\nFAILED: ", $e->getMessage(), "\n";
    exit(1);
}
