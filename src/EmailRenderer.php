<?php
declare(strict_types=1);

/** Builds the "drop alert" email (table layout + inline styles for mail clients). */
final class EmailRenderer
{
    private const BG     = '#07080f';
    private const CARD   = '#10121d';
    private const LINE   = '#232741';
    private const TEXT   = '#e8eaf6';
    private const MUTED  = '#8d93b5';
    private const CYAN   = '#22e3ff';
    private const PINK   = '#ff2bd6';
    private const GREEN  = '#39ff88';
    private const FONT   = "'Segoe UI', Roboto, Helvetica, Arial, sans-serif";

    /**
     * @param array $live      all live giveaways
     * @param array $upcoming  all upcoming giveaways
     * @param int[] $newIds    ids that triggered this email (get a NEW badge)
     * @return array{subject:string, html:string, text:string}
     */
    public static function render(array $live, array $upcoming, array $newIds, string $siteUrl): array
    {
        $isNew = static fn(array $g) => in_array((int) $g['id'], $newIds, true);
        $newLive = array_values(array_filter($live, $isNew));
        $newUp   = array_values(array_filter($upcoming, $isNew));

        if ($newLive) {
            $subject = '🎮 Free now: ' . self::titles($newLive);
            $headline = 'NEW LOOT UNLOCKED';
            $kicker = count($newLive) === 1 ? 'A new free game just dropped' : count($newLive) . ' new free games just dropped';
        } else {
            $subject = '🔮 Incoming free games: ' . self::titles($newUp);
            $headline = 'INCOMING LOOT';
            $kicker = 'Epic just revealed the next free ' . (count($newUp) === 1 ? 'game' : 'games');
        }
        if ($newLive && $upcoming) {
            $subject .= ' · Next: ' . self::titles($upcoming);
        }
        $preheader = $kicker . ' on the Epic Games Store — claim before the timer runs out.';

        $f = self::FONT;
        $html = '<!doctype html><html lang="en"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<meta name="color-scheme" content="dark"><meta name="supported-color-schemes" content="dark">'
            . '<title>' . h($subject) . '</title>'
            . '<style>@media (max-width:620px){.wrap{width:100%!important}.px{padding-left:16px!important;padding-right:16px!important}.h1{font-size:30px!important}.thumb{width:110px!important}}</style>'
            . '</head><body style="margin:0;padding:0;background:' . self::BG . ';">'
            . '<div style="display:none;max-height:0;overflow:hidden;opacity:0;">' . h($preheader) . '</div>'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:' . self::BG . ';">'
            . '<tr><td align="center" style="padding:24px 8px;">'
            . '<table role="presentation" class="wrap" width="600" cellpadding="0" cellspacing="0" border="0" style="width:600px;max-width:600px;">';

        // Header
        $html .= '<tr><td class="px" style="padding:0 24px 18px;font-family:' . $f . ';">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr>'
            . '<td style="font-family:' . $f . ';font-size:15px;font-weight:800;letter-spacing:4px;color:' . self::TEXT . ';">'
            . '<span style="color:' . self::CYAN . ';">&#9654;</span> FREE<span style="color:' . self::PINK . ';">LOOT</span></td>'
            . '<td align="right" style="font-family:' . $f . ';font-size:11px;letter-spacing:2px;color:' . self::MUTED . ';text-transform:uppercase;">Epic Store drop alert</td>'
            . '</tr></table></td></tr>'
            . '<tr><td style="height:3px;line-height:3px;font-size:0;background:' . self::CYAN . ';background-image:linear-gradient(90deg,' . self::CYAN . ',' . self::PINK . ');">&nbsp;</td></tr>';

        // Headline
        $html .= '<tr><td class="px" style="padding:34px 24px 8px;font-family:' . $f . ';">'
            . '<div style="font-size:12px;letter-spacing:3px;text-transform:uppercase;color:' . self::GREEN . ';font-weight:700;">&#9679; ' . h($kicker) . '</div>'
            . '<div class="h1" style="font-size:38px;line-height:1.1;font-weight:900;letter-spacing:1px;color:' . self::TEXT . ';margin-top:10px;">' . h($headline) . '</div>'
            . '</td></tr>';

        // Live cards
        if ($live) {
            $html .= self::sectionLabel('Free right now', self::GREEN);
            foreach ($live as $g) {
                $html .= self::bigCard($g, $isNew($g));
            }
        }

        // Upcoming
        if ($upcoming) {
            $html .= self::sectionLabel('Next up', self::CYAN);
            $html .= '<tr><td class="px" style="padding:0 24px;">';
            foreach ($upcoming as $g) {
                $html .= self::smallRow($g, $isNew($g));
            }
            $html .= '</td></tr>';
        }

        // Footer
        $html .= '<tr><td class="px" align="center" style="padding:34px 24px 8px;font-family:' . $f . ';">'
            . self::button('View the full drop history', $siteUrl, 'transparent', self::CYAN, self::CYAN)
            . '</td></tr>'
            . '<tr><td class="px" align="center" style="padding:18px 24px 0;font-family:' . $f . ';font-size:11px;line-height:1.6;color:' . self::MUTED . ';">'
            . 'Times shown in ' . h(app_tz()->getName()) . '. Prices are the normal store price.<br>'
            . 'Sent by your Free Loot monitor at ' . h(parse_url($siteUrl, PHP_URL_HOST) ?: $siteUrl) . ' · not affiliated with Epic Games.'
            . '</td></tr>';

        $html .= '</table></td></tr></table></body></html>';

        return ['subject' => $subject, 'html' => $html, 'text' => self::text($live, $upcoming, $isNew, $headline, $siteUrl)];
    }

    private static function titles(array $games): string
    {
        $t = array_map(static fn($g) => $g['title'], $games);
        return count($t) > 2 ? $t[0] . ', ' . $t[1] . ' +' . (count($t) - 2) : implode(' & ', $t);
    }

    private static function sectionLabel(string $label, string $color): string
    {
        return '<tr><td class="px" style="padding:30px 24px 12px;font-family:' . self::FONT . ';">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr>'
            . '<td style="font-family:' . self::FONT . ';font-size:12px;font-weight:800;letter-spacing:3px;text-transform:uppercase;color:' . $color . ';white-space:nowrap;padding-right:12px;">// ' . h($label) . '</td>'
            . '<td width="100%" style="border-bottom:1px solid ' . self::LINE . ';">&nbsp;</td>'
            . '</tr></table></td></tr>';
    }

    private static function badge(string $txt, string $bg, string $fg): string
    {
        return '<span style="display:inline-block;padding:4px 9px;margin-right:6px;border-radius:4px;background:' . $bg
            . ';color:' . $fg . ';font-size:10px;font-weight:800;letter-spacing:2px;text-transform:uppercase;">' . h($txt) . '</span>';
    }

    private static function button(string $label, string $url, string $bg, string $fg, string $border): string
    {
        return '<a href="' . h($url) . '" style="display:inline-block;padding:13px 26px;border-radius:6px;background:' . $bg
            . ';border:2px solid ' . $border . ';color:' . $fg . ';font-family:' . self::FONT
            . ';font-size:13px;font-weight:800;letter-spacing:2px;text-transform:uppercase;text-decoration:none;">' . h($label) . ' &rarr;</a>';
    }

    private static function when(?string $utc): string
    {
        $d = View::local($utc);
        return $d ? $d->format('D j M · H:i') : '—';
    }

    private static function bigCard(array $g, bool $new): string
    {
        $f = self::FONT;
        $url = View::storeUrl($g);
        $img = View::img($g['image_wide'], 1100);
        $out = '<tr><td class="px" style="padding:0 24px 22px;">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:' . self::CARD . ';border:1px solid ' . self::LINE . ';border-radius:12px;overflow:hidden;">';
        if ($img) {
            $out .= '<tr><td style="padding:0;"><a href="' . h($url) . '"><img src="' . h($img) . '" width="552" alt="' . h($g['title'])
                . '" style="display:block;width:100%;max-width:552px;height:auto;border:0;border-radius:12px 12px 0 0;"></a></td></tr>';
        }
        $out .= '<tr><td style="padding:20px 22px 22px;font-family:' . $f . ';">'
            . '<div style="margin-bottom:12px;">' . self::badge('Free now', self::GREEN, '#04130a') . ($new ? self::badge('New', self::PINK, '#1a0216') : '') . '</div>'
            . '<div style="font-size:24px;line-height:1.2;font-weight:900;color:' . self::TEXT . ';">' . h($g['title']) . '</div>';
        if ($g['seller']) {
            $out .= '<div style="font-size:12px;color:' . self::MUTED . ';margin-top:4px;letter-spacing:1px;text-transform:uppercase;">' . h($g['seller']) . '</div>';
        }
        if ($g['description']) {
            $out .= '<div style="font-size:14px;line-height:1.55;color:#c3c7e0;margin-top:12px;">' . h(mb_strimwidth($g['description'], 0, 240, '…')) . '</div>';
        }
        $out .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:18px;"><tr>'
            . '<td style="font-family:' . $f . ';font-size:13px;color:' . self::MUTED . ';">'
            . ($g['original_price'] ? '<span style="text-decoration:line-through;">' . h($g['original_price']) . '</span> ' : '')
            . '<span style="color:' . self::GREEN . ';font-weight:900;font-size:16px;letter-spacing:1px;">FREE</span><br>'
            . '<span style="font-size:12px;">&#9203; Ends ' . h(self::when($g['end_at'])) . '</span></td>'
            . '<td align="right">' . self::button('Claim it', $url, self::GREEN, '#04130a', self::GREEN) . '</td>'
            . '</tr></table>'
            . '</td></tr></table></td></tr>';
        return $out;
    }

    private static function smallRow(array $g, bool $new): string
    {
        $f = self::FONT;
        $url = View::storeUrl($g);
        $img = View::img($g['image_wide'], 400);
        $thumb = $img
            ? '<img class="thumb" src="' . h($img) . '" width="160" alt="" style="display:block;width:160px;height:auto;border-radius:8px;border:0;">'
            : '<div class="thumb" style="width:160px;height:90px;border-radius:8px;background:' . self::LINE . ';"></div>';
        return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:' . self::CARD . ';border:1px solid ' . self::LINE . ';border-radius:10px;margin-bottom:12px;">'
            . '<tr><td width="160" style="padding:12px;vertical-align:middle;"><a href="' . h($url) . '">' . $thumb . '</a></td>'
            . '<td style="padding:12px 14px 12px 4px;vertical-align:middle;font-family:' . $f . ';">'
            . '<div style="margin-bottom:6px;">' . self::badge('Locked', self::LINE, self::CYAN) . ($new ? self::badge('Just revealed', self::PINK, '#1a0216') : '') . '</div>'
            . '<a href="' . h($url) . '" style="font-size:17px;font-weight:800;color:' . self::TEXT . ';text-decoration:none;">' . h($g['title']) . '</a>'
            . '<div style="font-size:12px;color:' . self::MUTED . ';margin-top:5px;">&#128275; Unlocks ' . h(self::when($g['start_at']))
            . ($g['original_price'] ? ' · <span style="text-decoration:line-through;">' . h($g['original_price']) . '</span>' : '') . '</div>'
            . '</td></tr></table>';
    }

    private static function text(array $live, array $upcoming, callable $isNew, string $headline, string $siteUrl): string
    {
        $t = "FREE LOOT — $headline\n" . str_repeat('=', 40) . "\n\n";
        if ($live) {
            $t .= "FREE RIGHT NOW\n";
            foreach ($live as $g) {
                $t .= ($isNew($g) ? '[NEW] ' : '') . $g['title']
                    . ($g['original_price'] ? ' (normally ' . $g['original_price'] . ')' : '') . "\n"
                    . '  Ends ' . self::when($g['end_at']) . "\n  " . View::storeUrl($g) . "\n\n";
            }
        }
        if ($upcoming) {
            $t .= "NEXT UP\n";
            foreach ($upcoming as $g) {
                $t .= ($isNew($g) ? '[NEW] ' : '') . $g['title'] . "\n  Unlocks " . self::when($g['start_at']) . "\n  " . View::storeUrl($g) . "\n\n";
            }
        }
        return $t . "Full history: $siteUrl\n";
    }
}
