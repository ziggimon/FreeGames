<?php
declare(strict_types=1);

/** Shared formatting/grouping helpers for the page and the email. */
final class View
{
    public static function local(?string $utc): ?DateTimeImmutable
    {
        if (!$utc) {
            return null;
        }
        return (new DateTimeImmutable($utc, new DateTimeZone('UTC')))->setTimezone(app_tz());
    }

    public static function state(array $g, int $now): string
    {
        $s = strtotime($g['start_at'] . ' UTC');
        $e = $g['end_at'] ? strtotime($g['end_at'] . ' UTC') : null;
        if ($s > $now) {
            return 'upcoming';
        }
        if ($e === null || $e > $now) {
            return 'live';
        }
        return 'past';
    }

    /** "12 – 19 Nov" / "26 Nov – 3 Dec" / "18 Dec" */
    public static function range(array $g): string
    {
        $s = self::local($g['start_at']);
        $e = self::local($g['end_at']);
        if (!$e) {
            return $s->format('j M');
        }
        // Single-day drops (e.g. the Christmas mystery games)
        if (($e->getTimestamp() - $s->getTimestamp()) <= 86400 + 3600) {
            return $s->format('D j M');
        }
        if ($s->format('mY') === $e->format('mY')) {
            return $s->format('j') . ' – ' . $e->format('j M');
        }
        return $s->format('j M') . ' – ' . $e->format('j M');
    }

    /** Epic weeks run Thursday → Thursday. Returns the local Thursday that starts the giveaway's week. */
    public static function weekStart(array $g): DateTimeImmutable
    {
        $s = self::local($g['start_at'])->setTime(0, 0);
        $dow = (int) $s->format('N'); // 1 Mon … 7 Sun, Thursday = 4
        $back = ($dow - 4 + 7) % 7;
        return $s->modify("-$back days");
    }

    /**
     * Group past giveaways into months → weeks, newest first.
     * @return array<string, array{label:string, count:int, weeks: array<string, array{label:string, sub:string, games:array}>}>
     */
    public static function groupArchive(array $games): array
    {
        $months = [];
        foreach ($games as $g) {
            $wk = self::weekStart($g);
            $mKey = $wk->format('Y-m');
            $wKey = $wk->format('Y-m-d');
            if (!isset($months[$mKey])) {
                $months[$mKey] = ['label' => $wk->format('F Y'), 'short' => $wk->format('M y'), 'count' => 0, 'weeks' => []];
            }
            if (!isset($months[$mKey]['weeks'][$wKey])) {
                $end = $wk->modify('+7 days');
                $months[$mKey]['weeks'][$wKey] = [
                    'label' => 'Week ' . (int) $wk->format('W'),
                    'sub'   => $wk->format('j M') . ' – ' . $end->format('j M'),
                    'games' => [],
                ];
            }
            $months[$mKey]['weeks'][$wKey]['games'][] = $g;
            // Stretch the label when a giveaway runs longer than a week.
            $gEnd = self::local($g['end_at']);
            if ($gEnd && $gEnd > $wk->modify('+7 days 12 hours')) {
                $months[$mKey]['weeks'][$wKey]['sub'] = $wk->format('j M') . ' – ' . $gEnd->format('j M');
            }
            $months[$mKey]['count']++;
        }
        krsort($months);
        foreach ($months as &$m) {
            krsort($m['weeks']);
            foreach ($m['weeks'] as &$w) {
                usort($w['games'], static fn($a, $b) => [$b['start_at'], $a['title']] <=> [$a['start_at'], $b['title']]);
            }
        }
        return $months;
    }

    /** Resize Epic CDN images on the fly; leave other hosts alone. */
    public static function img(?string $url, int $w): ?string
    {
        if (!$url) {
            return null;
        }
        if (str_contains($url, 'epicgames.com') && !str_contains($url, '?')) {
            return $url . '?w=' . $w . '&quality=medium&resize=1';
        }
        return $url;
    }

    /** Deterministic neon gradient for games without artwork. */
    public static function gradient(string $title): string
    {
        $hue = hexdec(substr(md5($title), 0, 4)) % 360;
        $h2 = ($hue + 55) % 360;
        return "linear-gradient(135deg, hsl({$hue} 85% 38%), hsl({$h2} 90% 22%))";
    }

    public static function storeUrl(array $g): string
    {
        return $g['url'] ?: 'https://store.epicgames.com/en-US/browse?q=' . rawurlencode($g['title']) . '&sortBy=relevancy&sortDir=DESC&count=40';
    }
}
