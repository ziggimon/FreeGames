<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

$cfg = app_config();
$error = null;
$rows = [];
$lastRun = null;
try {
    $repo = new Repo($cfg['db']);
    $rows = $repo->listForPage((int) ($cfg['history_days'] ?? 365));
    $lastRun = $repo->lastOkRun();
} catch (Throwable $e) {
    $error = 'The loot database is offline right now.';
    error_log('[freeloot] ' . $e->getMessage());
}

$now = time();
$live = $upcoming = $past = [];
foreach ($rows as $g) {
    match (View::state($g, $now)) {
        'live'     => $live[] = $g,
        'upcoming' => $upcoming[] = $g,
        default    => $past[] = $g,
    };
}
usort($upcoming, static fn($a, $b) => $a['start_at'] <=> $b['start_at']);
$archive = View::groupArchive($past);
$weeks = array_sum(array_map(static fn($m) => count($m['weeks']), $archive));

$iso = static fn(?string $utc) => $utc ? gmdate('Y-m-d\TH:i:s\Z', strtotime($utc . ' UTC')) : '';
$lastScan = $lastRun ? View::local($lastRun['ran_at'])->format('D j M, H:i') : 'not yet';

/** Card art: real image or neon placeholder. */
$art = static function (array $g, int $w, string $class = 'art') {
    $src = View::img($g['image_wide'], $w);
    if ($src) {
        return '<img class="' . $class . '" src="' . h($src) . '" alt="" loading="lazy" decoding="async">';
    }
    return '<div class="' . $class . ' art-ph" style="background:' . h(View::gradient($g['title'])) . '"><span>' . h($g['title']) . '</span></div>';
};
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Free Loot · Epic free games tracker</title>
<meta name="description" content="Which games are free on the Epic Games Store right now, what's next, and everything given away in the past year.">
<meta name="theme-color" content="#06070d">
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><rect width='32' height='32' rx='7' fill='%2306070d'/><path d='M11 8l14 8-14 8z' fill='%2322e3ff'/></svg>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Chakra+Petch:wght@500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/app.css?v=2">
</head>
<body>
<div class="bg-grid" aria-hidden="true"></div>

<header class="top">
  <div class="wrap top-in">
    <a class="logo" href="./"><span class="logo-play">&#9654;</span>FREE<b>LOOT</b></a>
    <div class="scan"><span class="dot<?= $error ? ' dot-bad' : '' ?>"></span>Last scan <strong><?= h($lastScan) ?></strong></div>
  </div>
</header>

<main class="wrap">
<?php if ($error): ?>
  <div class="alert"><?= h($error) ?> Try again in a bit.</div>
<?php endif; ?>

  <!-- FREE NOW -->
  <section class="block" id="now">
    <h2 class="label label-green"><span class="pulse"></span>Free right now</h2>
    <?php if (!$live): ?>
      <p class="empty">No active giveaway right now. Check back Thursday at 17:00.</p>
    <?php endif; ?>
    <div class="hero-list">
    <?php foreach ($live as $g): ?>
      <article class="hero">
        <a class="hero-art" href="<?= h(View::storeUrl($g)) ?>" target="_blank" rel="noopener"><?= $art($g, 1400) ?></a>
        <div class="hero-body">
          <div class="badges"><span class="badge b-green">Free now</span><?php if ($g['offer_type'] === 'BUNDLE'): ?><span class="badge b-dim">Bundle</span><?php endif; ?></div>
          <h3 class="hero-title"><?= h($g['title']) ?></h3>
          <?php if ($g['seller']): ?><div class="seller"><?= h($g['seller']) ?></div><?php endif; ?>
          <?php if ($g['description']): ?><p class="desc"><?= h($g['description']) ?></p><?php endif; ?>
          <div class="price">
            <?php if ($g['original_price']): ?><s><?= h($g['original_price']) ?></s><?php endif; ?>
            <span class="free">FREE</span>
          </div>
          <div class="timer" data-until="<?= h($iso($g['end_at'])) ?>">
            <div class="timer-label">Ends in</div>
            <div class="timer-digits"><span data-d>--</span><i>d</i><span data-h>--</span><i>h</i><span data-m>--</span><i>m</i><span data-s>--</span><i>s</i></div>
            <div class="timer-at"><?= h(View::local($g['end_at'])?->format('D j M · H:i')) ?></div>
          </div>
          <a class="btn btn-green" href="<?= h(View::storeUrl($g)) ?>" target="_blank" rel="noopener">Claim it <span>&rarr;</span></a>
        </div>
      </article>
    <?php endforeach; ?>
    </div>
  </section>

  <!-- NEXT UP -->
  <?php if ($upcoming): ?>
  <section class="block" id="next">
    <h2 class="label label-cyan">Next drop</h2>
    <div class="next-grid">
    <?php foreach ($upcoming as $g): ?>
      <a class="next" href="<?= h(View::storeUrl($g)) ?>" target="_blank" rel="noopener">
        <div class="next-art"><?= $art($g, 800) ?><span class="lock">&#128274;</span></div>
        <div class="next-body">
          <div class="badges"><span class="badge b-cyan">Locked</span></div>
          <h3><?= h($g['title']) ?></h3>
          <div class="next-meta">
            <span class="countdown" data-until="<?= h($iso($g['start_at'])) ?>" data-short>--</span>
            <span class="muted">· <?= h(View::range($g)) ?><?= $g['original_price'] ? ' · <s>' . h($g['original_price']) . '</s>' : '' ?></span>
          </div>
        </div>
      </a>
    <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <!-- ARCHIVE -->
  <section class="block" id="history">
    <div class="hist-head">
      <h2 class="label label-pink">Drop history</h2>
      <div class="stats">
        <div><b><?= count($past) ?></b><span>games</span></div>
        <div><b><?= $weeks ?></b><span>weeks</span></div>
        <div><b><?= count($archive) ?></b><span>months</span></div>
      </div>
    </div>

    <nav class="months" aria-label="Jump to month">
      <input class="search" type="search" placeholder="Search games…" aria-label="Search games" data-search>
      <?php foreach ($archive as $key => $m): ?>
        <a href="#m-<?= h($key) ?>" data-month-link="<?= h($key) ?>"><?= h($m['short']) ?></a>
      <?php endforeach; ?>
    </nav>

    <?php foreach ($archive as $key => $m): ?>
    <div class="month" id="m-<?= h($key) ?>" data-month="<?= h($key) ?>">
      <h3 class="month-title"><?= h($m['label']) ?> <span><?= $m['count'] ?> <?= $m['count'] === 1 ? 'game' : 'games' ?></span></h3>
      <?php foreach ($m['weeks'] as $w): ?>
      <div class="week">
        <div class="week-rail">
          <div class="week-no"><?= h($w['label']) ?></div>
          <div class="week-dates"><?= h($w['sub']) ?></div>
        </div>
        <div class="cards">
          <?php foreach ($w['games'] as $g): ?>
          <a class="card" href="<?= h(View::storeUrl($g)) ?>" target="_blank" rel="noopener" data-title="<?= h(mb_strtolower($g['title'])) ?>">
            <div class="card-art"><?= $art($g, 600) ?></div>
            <div class="card-body">
              <h4><?= h($g['title']) ?></h4>
              <div class="card-meta"><?= h(View::range($g)) ?><?= $g['original_price'] ? ' · <s>' . h($g['original_price']) . '</s>' : '' ?></div>
            </div>
          </a>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endforeach; ?>
    <p class="empty" data-noresults hidden>No games match that search.</p>
  </section>
</main>

<footer class="foot wrap">
  Times in <?= h(app_tz()->getName()) ?> · Updated daily from the Epic Games Store · Not affiliated with Epic Games.
</footer>

<script src="assets/app.js?v=2" defer></script>
</body>
</html>
