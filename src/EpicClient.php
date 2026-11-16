<?php
declare(strict_types=1);

/**
 * Reads Epic's public free-games promotions feed (the same one the store's
 * "Free Games" row uses). It only contains the current and upcoming giveaways.
 */
final class EpicClient
{
    private const ENDPOINT = 'https://store-site-backend-static-ipv4.ak.epicgames.com/freeGamesPromotions';

    public function __construct(private string $country = 'DK', private string $locale = 'en-US')
    {
    }

    /** @return array raw decoded JSON */
    public function fetchRaw(): array
    {
        $url = self::ENDPOINT . '?' . http_build_query([
            'locale' => $this->locale, 'country' => $this->country, 'allowCountries' => $this->country,
        ]);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_USERAGENT      => 'FreeLoot/1.0 (+personal free-games tracker)',
            CURLOPT_HTTPHEADER     => ['Accept: application/json'],
        ]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err  = curl_error($ch);
        if ($body === false || $code !== 200) {
            throw new RuntimeException("Epic API request failed (HTTP $code) $err");
        }
        $data = json_decode((string) $body, true);
        if (!is_array($data) || !isset($data['data']['Catalog']['searchStore']['elements'])) {
            throw new RuntimeException('Epic API returned an unexpected payload');
        }
        return $data;
    }

    /**
     * @return array<int, array> normalised giveaways (only 100%-off offers),
     *         each with 'state' => 'live'|'upcoming'
     */
    public function fetchFreeGames(): array
    {
        return self::parse($this->fetchRaw(), $this->locale);
    }

    public static function parse(array $data, string $locale = 'en-US'): array
    {
        $out = [];
        foreach ($data['data']['Catalog']['searchStore']['elements'] as $e) {
            $promos = $e['promotions'] ?? null;
            if (!$promos) {
                continue;
            }
            $title = trim((string) ($e['title'] ?? ''));
            // Epic sometimes teases "Mystery Game" placeholders; they're replaced later.
            if ($title === '' || preg_match('/mystery\s+game/i', $title)) {
                continue;
            }
            foreach (['promotionalOffers' => 'live', 'upcomingPromotionalOffers' => 'upcoming'] as $bucket => $state) {
                foreach ($promos[$bucket] ?? [] as $group) {
                    foreach ($group['promotionalOffers'] ?? [] as $offer) {
                        $pct = $offer['discountSetting']['discountPercentage'] ?? null;
                        if ($pct !== 0) {
                            continue; // a sale, not a giveaway
                        }
                        $out[] = [
                            'state'          => $state,
                            'epic_id'        => $e['id'] ?? null,
                            'namespace'      => $e['namespace'] ?? null,
                            'title'          => $title,
                            'description'    => self::cleanDescription($e['description'] ?? null, $title),
                            'seller'         => $e['seller']['name'] ?? null,
                            'offer_type'     => $e['offerType'] ?? null,
                            'url'            => self::storeUrl($e, $locale),
                            'image_wide'     => self::image($e, ['OfferImageWide', 'DieselStoreFrontWide', 'featuredMedia', 'DieselGameBoxWide']),
                            'image_tall'     => self::image($e, ['OfferImageTall', 'DieselGameBoxTall', 'Thumbnail']),
                            'original_price' => self::price($e),
                            'start_at'       => self::utc($offer['startDate'] ?? null),
                            'end_at'         => self::utc($offer['endDate'] ?? null),
                            'source'         => 'api',
                        ];
                    }
                }
            }
        }
        return array_values(array_filter($out, static fn($g) => $g['start_at'] !== null));
    }

    private static function utc(?string $iso): ?string
    {
        if (!$iso) {
            return null;
        }
        return (new DateTimeImmutable($iso))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    private static function storeUrl(array $e, string $locale): string
    {
        $slug = null;
        foreach (array_merge($e['offerMappings'] ?? [], $e['catalogNs']['mappings'] ?? []) as $m) {
            if (($m['pageType'] ?? '') === 'productHome' && !empty($m['pageSlug'])) {
                $slug = $m['pageSlug'];
                break;
            }
        }
        $slug ??= ($e['offerMappings'][0]['pageSlug'] ?? null);
        if (!$slug && !empty($e['productSlug'])) {
            $slug = preg_replace('#/home$#', '', $e['productSlug']);
        }
        $slug ??= ($e['urlSlug'] ?? null);

        $base = 'https://store.epicgames.com/' . $locale;
        if (!$slug) {
            return $base . '/free-games';
        }
        return $base . (($e['offerType'] ?? '') === 'BUNDLE' ? '/bundles/' : '/p/') . rawurlencode($slug);
    }

    private static function image(array $e, array $types): ?string
    {
        $imgs = $e['keyImages'] ?? [];
        foreach ($types as $t) {
            foreach ($imgs as $img) {
                if (($img['type'] ?? '') === $t && !empty($img['url'])) {
                    return $img['url'];
                }
            }
        }
        return $imgs[0]['url'] ?? null;
    }

    private static function price(array $e): ?string
    {
        $tp = $e['price']['totalPrice'] ?? null;
        if (!$tp || empty($tp['originalPrice'])) {
            return null;
        }
        return $tp['fmtPrice']['originalPrice'] ?? null;
    }

    private static function cleanDescription(?string $d, string $title): ?string
    {
        $d = trim((string) $d);
        if ($d === '' || strcasecmp($d, $title) === 0) {
            return null;
        }
        return mb_strimwidth($d, 0, 600, '…');
    }
}
