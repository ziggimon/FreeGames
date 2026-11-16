<?php
declare(strict_types=1);

/** Database access for giveaways and the run log. */
final class Repo
{
    private PDO $pdo;

    public function __construct(array $db)
    {
        $this->pdo = new PDO($db['dsn'], $db['user'], $db['pass'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        $this->pdo->exec("SET time_zone = '+00:00'");
    }

    public static function key(string $title, string $startUtc): string
    {
        $norm = strtolower(trim(preg_replace('/[^a-z0-9]+/i', ' ', $title) ?? $title));
        return sha1($norm . '|' . substr($startUtc, 0, 10));
    }

    /**
     * Insert or update a giveaway. API data overwrites seed data; seed data never
     * overwrites fields that are already filled. Returns the row.
     */
    public function upsert(array $g): array
    {
        $g['uniq_key'] = self::key($g['title'], $g['start_at']);
        $cols = ['uniq_key', 'epic_id', 'namespace', 'title', 'description', 'seller', 'offer_type',
                 'url', 'image_wide', 'image_tall', 'original_price', 'start_at', 'end_at', 'source',
                 'notified_upcoming_at', 'notified_live_at'];
        $row = [];
        foreach ($cols as $c) {
            $row[$c] = $g[$c] ?? null;
        }
        $row['source'] ??= 'api';

        if ($row['source'] === 'api') {
            $update = [];
            foreach (['epic_id', 'namespace', 'title', 'description', 'seller', 'offer_type', 'url',
                      'image_wide', 'image_tall', 'original_price', 'start_at', 'end_at'] as $c) {
                $update[] = "$c = COALESCE(VALUES($c), $c)";
            }
            $update[] = "source = 'api'";
        } else {
            $update = [];
            foreach (['description', 'seller', 'url', 'image_wide', 'image_tall', 'original_price'] as $c) {
                $update[] = "$c = COALESCE($c, VALUES($c))";
            }
        }

        $sql = 'INSERT INTO giveaways (' . implode(',', $cols) . ') VALUES (:' . implode(',:', $cols) . ')'
             . ' ON DUPLICATE KEY UPDATE ' . implode(', ', $update);
        $this->pdo->prepare($sql)->execute($row);

        $st = $this->pdo->prepare('SELECT * FROM giveaways WHERE uniq_key = ?');
        $st->execute([$row['uniq_key']]);
        return $st->fetch();
    }

    /** Remove API rows that haven't started and are no longer announced (Epic swapped them). */
    public function pruneVanishedUpcoming(array $keepKeys, string $nowUtc): int
    {
        $sql = "DELETE FROM giveaways WHERE source = 'api' AND start_at > ?";
        $args = [$nowUtc];
        if ($keepKeys) {
            $sql .= ' AND uniq_key NOT IN (' . implode(',', array_fill(0, count($keepKeys), '?')) . ')';
            $args = array_merge($args, $keepKeys);
        }
        $st = $this->pdo->prepare($sql);
        $st->execute($args);
        return $st->rowCount();
    }

    public function markNotified(array $ids, string $field, string $nowUtc): void
    {
        if (!$ids || !in_array($field, ['notified_live_at', 'notified_upcoming_at'], true)) {
            return;
        }
        $in = implode(',', array_fill(0, count($ids), '?'));
        $this->pdo->prepare("UPDATE giveaways SET $field = ? WHERE id IN ($in)")
            ->execute(array_merge([$nowUtc], array_values($ids)));
    }

    /** Everything that is upcoming, live, or ended within the history window. Newest first. */
    public function listForPage(int $historyDays): array
    {
        $st = $this->pdo->prepare(
            'SELECT * FROM giveaways
              WHERE COALESCE(end_at, start_at) >= UTC_TIMESTAMP() - INTERVAL ? DAY
              ORDER BY start_at DESC, title ASC'
        );
        $st->execute([$historyDays]);
        return $st->fetchAll();
    }

    public function logRun(bool $ok, int $found, int $newLive, int $newUp, bool $mailed, ?string $msg): void
    {
        $this->pdo->prepare('INSERT INTO run_log (ok, found, new_live, new_upcoming, mailed, message) VALUES (?,?,?,?,?,?)')
            ->execute([(int) $ok, $found, $newLive, $newUp, (int) $mailed, $msg ? mb_substr($msg, 0, 1000) : null]);
    }

    public function lastRun(): ?array
    {
        $r = $this->pdo->query('SELECT * FROM run_log ORDER BY id DESC LIMIT 1')->fetch();
        return $r ?: null;
    }

    public function lastOkRun(): ?array
    {
        $r = $this->pdo->query('SELECT * FROM run_log WHERE ok = 1 ORDER BY id DESC LIMIT 1')->fetch();
        return $r ?: null;
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }
}
