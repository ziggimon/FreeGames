<?php
declare(strict_types=1);
/**
 * Dev helper: add artwork to data/seed.json entries that have none, using Steam's
 * public store search (exact title match only). Games not on Steam keep a
 * generated placeholder on the page. Not needed on the server.
 *   php bin/enrich-seed.php
 */

$file = dirname(__DIR__) . '/data/seed.json';
$seed = json_decode((string) file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);
$norm = static fn(string $s) => strtolower(trim(preg_replace('/[^a-z0-9]+/i', ' ', str_replace(['™', '®', "'", '’'], '', $s))));

$cache = [];
$hit = $miss = 0;
foreach ($seed['giveaways'] as &$w) {
    foreach ($w['games'] as &$g) {
        $g = is_string($g) ? ['title' => $g] : $g;
        if (!empty($g['image'])) {
            continue;
        }
        $t = $g['title'];
        if (!array_key_exists($t, $cache)) {
            $url = 'https://store.steampowered.com/api/storesearch/?' . http_build_query(['term' => $t, 'cc' => 'DK', 'l' => 'english']);
            $res = json_decode((string) @file_get_contents($url), true);
            $cache[$t] = null;
            foreach ($res['items'] ?? [] as $it) {
                if ($norm($it['name']) === $norm($t)) {
                    $cache[$t] = 'https://shared.cloudflare.steamstatic.com/store_item_assets/steam/apps/' . (int) $it['id'] . '/header.jpg';
                    break;
                }
            }
            usleep(350000);
        }
        if ($cache[$t]) {
            $g['image'] = $cache[$t];
            $hit++;
        } else {
            $miss++;
            echo "no match: $t\n";
        }
    }
}
unset($w, $g);
file_put_contents($file, json_encode($seed, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
echo "images found: $hit, no match: $miss\n";
