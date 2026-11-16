<?php
declare(strict_types=1);
/**
 * Daily check: fetch Epic's current + upcoming free games, store them, and
 * email when something new goes live or is announced.
 *
 * Usage:
 *   php bin/check.php                 normal run (cron)
 *   php bin/check.php --no-mail       update the DB only, never send (doesn't mark as notified)
 *   php bin/check.php --force-mail    send an email with the current state even if nothing is new
 *   php bin/check.php --preview=FILE  write the email HTML to FILE (no sending, no marking)
 *   php bin/check.php -v              verbose (prints SMTP conversation)
 */

require dirname(__DIR__) . '/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    exit("CLI only\n");
}

$opts = getopt('v', ['no-mail', 'force-mail', 'preview:']);
$verbose = isset($opts['v']);
$noMail = isset($opts['no-mail']);
$force = isset($opts['force-mail']);
$preview = $opts['preview'] ?? null;

// Prevent overlapping runs.
$lock = fopen(sys_get_temp_dir() . '/freeloot-check.lock', 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
    fwrite(STDERR, "Another run is in progress.\n");
    exit(0);
}

$cfg = app_config();
$repo = null;
try {
    $repo = new Repo($cfg['db']);
    $client = new EpicClient($cfg['epic']['country'] ?? 'DK', $cfg['epic']['locale'] ?? 'en-US');
    $found = $client->fetchFreeGames();

    $now = time();
    $nowUtc = gmdate('Y-m-d H:i:s', $now);
    $keys = [];
    $live = $upcoming = [];
    foreach ($found as $g) {
        $row = $repo->upsert($g);
        $keys[] = $row['uniq_key'];
        $state = View::state($row, $now);
        if ($state === 'live') {
            $live[$row['id']] = $row;
        } elseif ($state === 'upcoming') {
            $upcoming[$row['id']] = $row;
        }
    }
    $pruned = $repo->pruneVanishedUpcoming($keys, $nowUtc);

    $byStart = static fn($a, $b) => [$a['start_at'], $a['title']] <=> [$b['start_at'], $b['title']];
    uasort($live, $byStart);
    uasort($upcoming, $byStart);

    $newLive = array_keys(array_filter($live, static fn($r) => $r['notified_live_at'] === null));
    $newUp = array_keys(array_filter($upcoming, static fn($r) => $r['notified_upcoming_at'] === null));

    printf("[%s] Epic feed: %d free (%d live, %d upcoming) · new live %d · new upcoming %d · pruned %d\n",
        $nowUtc, count($found), count($live), count($upcoming), count($newLive), count($newUp), $pruned);
    foreach ($live as $r) {
        echo '  LIVE      ' . $r['title'] . ' (until ' . $r['end_at'] . " UTC)\n";
    }
    foreach ($upcoming as $r) {
        echo '  UPCOMING  ' . $r['title'] . ' (from ' . $r['start_at'] . " UTC)\n";
    }

    $mailed = false;
    $newIds = array_map('intval', array_merge($newLive, $newUp));

    if ($preview !== null) {
        $mail = EmailRenderer::render(array_values($live), array_values($upcoming), $newIds ?: array_map('intval', array_keys($live)), $cfg['site_url']);
        file_put_contents($preview, $mail['html']);
        echo "Preview written to $preview\nSubject: {$mail['subject']}\n";
    } elseif (($newIds || $force) && !$noMail && ($cfg['mail']['enabled'] ?? false)) {
        if (!$live && !$upcoming) {
            echo "Nothing to send.\n";
        } else {
            $mail = EmailRenderer::render(array_values($live), array_values($upcoming), $newIds, $cfg['site_url']);
            $smtp = new Smtp($cfg['mail']);
            try {
                $smtp->send((array) $cfg['mail']['to'], $mail['subject'], $mail['html'], $mail['text']);
            } finally {
                if ($verbose) {
                    echo implode("\n", $smtp->log()), "\n";
                }
            }
            $mailed = true;
            // A game we announced as "live" never needs an "upcoming" mail afterwards.
            $repo->markNotified(array_keys($live), 'notified_live_at', $nowUtc);
            $repo->markNotified(array_merge(array_keys($live), array_keys($upcoming)), 'notified_upcoming_at', $nowUtc);
            echo "Email sent: {$mail['subject']}\n";
        }
    }

    $repo->logRun(true, count($found), count($newLive), count($newUp), $mailed, $pruned ? "pruned $pruned" : null);
} catch (Throwable $e) {
    fwrite(STDERR, '[' . gmdate('c') . '] ERROR: ' . $e->getMessage() . "\n");
    try {
        $repo?->logRun(false, 0, 0, 0, false, $e->getMessage());
    } catch (Throwable) {
    }
    exit(1);
}
