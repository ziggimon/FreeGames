<?php
declare(strict_types=1);
/**
 * One-off: load data/seed.json (past giveaways) into the database.
 * Safe to run again — existing rows are kept and only blank fields are filled.
 *
 * Seed dates are local calendar dates; giveaways start/end at 17:00 Copenhagen
 * time (Epic's usual switch-over), which this script converts to UTC.
 */

require dirname(__DIR__) . '/bootstrap.php';

$cfg = app_config();
$repo = new Repo($cfg['db']);
$seed = json_decode((string) file_get_contents(APP_ROOT . '/data/seed.json'), true, flags: JSON_THROW_ON_ERROR);
$cph = new DateTimeZone('Europe/Copenhagen');
$utc = new DateTimeZone('UTC');
$now = gmdate('Y-m-d H:i:s');

$n = 0;
foreach ($seed['giveaways'] as $w) {
    $start = (new DateTimeImmutable($w['start'] . ' 17:00', $cph))->setTimezone($utc)->format('Y-m-d H:i:s');
    $end = (new DateTimeImmutable($w['end'] . ' 17:00', $cph))->setTimezone($utc)->format('Y-m-d H:i:s');
    foreach ($w['games'] as $g) {
        $g = is_string($g) ? ['title' => $g] : $g;
        $repo->upsert([
            'title'                => $g['title'],
            'url'                  => $g['url'] ?? null,
            'image_wide'           => $g['image'] ?? null,
            'seller'               => $g['seller'] ?? null,
            'start_at'             => $start,
            'end_at'               => $end,
            'source'               => 'seed',
            'notified_live_at'     => $now,   // history: never email about these
            'notified_upcoming_at' => $now,
        ]);
        $n++;
    }
}
echo "Imported/updated $n seed giveaways.\n";
