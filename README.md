# Free Loot — Epic Games Store free-games monitor

A daily cron job reads Epic's free-games feed, stores every giveaway in MySQL,
and sends a gaming-styled email when a new free game **goes live** or **is
announced**. A PHP page at **freegames.cxx.dk** shows what's free now, what's next,
and the past 12 months grouped by month → Epic week (Thu → Thu), newest first.

No Composer packages. Needs PHP 8.1+ with `pdo_mysql`, `curl`, `openssl` and `mbstring`.

## Files

```
/var/www/freegames.cxx.dk/
├── config.php            ← YOUR SETTINGS: DB + Simply.com SMTP user/pass (create from config.sample.php)
├── config.sample.php
├── bootstrap.php
├── src/                  Repo (MySQL), EpicClient, Smtp, EmailRenderer, View
├── bin/
│   ├── check.php         the cron job
│   ├── import-seed.php   one-off: load past-year history from data/seed.json
│   ├── test-mail.php     send a test email
│   └── enrich-seed.php   dev only (adds Steam artwork to seed.json) — optional
├── data/seed.json        past giveaways 13 Nov 2025 → 12 Nov 2026 (source: PC Gamer list)
├── sql/schema.sql
├── deploy/               nginx server block + /etc/cron.d file
└── public/               ← nginx web root (index.php, assets/)
```

**Credentials live in `/var/www/freegames.cxx.dk/config.php`**, under `'mail'`:
`username` (full mailbox address on Simply.com), `password`, `from`, `to`.
The DB password goes under `'db' => ['pass']`. The file sits outside the web root.

## Install

**Don't upload:** `config.php` (local test copy), `public/email-preview.html`.

1. **Upload** the folder to `/var/www/freegames.cxx.dk/` (everything except the two files above).

2. **Database:**
   ```bash
   sudo mysql -e "CREATE DATABASE epic_free CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
     CREATE USER 'epic_free'@'localhost' IDENTIFIED BY 'CHANGE_ME';
     GRANT SELECT, INSERT, UPDATE, DELETE ON epic_free.* TO 'epic_free'@'localhost';"
   sudo mysql epic_free < /var/www/freegames.cxx.dk/sql/schema.sql
   ```

3. **Config:**
   ```bash
   cd /var/www/freegames.cxx.dk
   sudo cp config.sample.php config.php
   sudo chown root:nginx config.php && sudo chmod 640 config.php
   sudo nano config.php        # DB pass, SMTP username/password, from, to
   ```
   Simply.com SMTP: `smtp.simply.com`, port `587` + `tls` (default) or `465` + `ssl`.
   `from` must be a mailbox or alias on your cxx.dk Simply.com account.

4. **Load the history and test:**
   ```bash
   sudo -u nginx php bin/import-seed.php
   sudo -u nginx php bin/test-mail.php          # prints the SMTP conversation
   sudo -u nginx php bin/check.php              # first real run, emails the current games
   ```

5. **Web:** the server block uses the `/run/php-fpm-cxx.sock` PHP-FPM socket and plain
   `fastcgi_params`, so it doesn't need Debian's `snippets/` folder.
   ```bash
   sudo cp deploy/nginx-freegames.cxx.dk.conf /etc/nginx/conf.d/freegames.cxx.dk.conf
   sudo nginx -t && sudo systemctl reload nginx
   sudo certbot --nginx -d freegames.cxx.dk
   ```
   (Point the `freegames.cxx.dk` DNS A/AAAA record at the server first.)

6. **Cron:**
   ```bash
   sudo cp deploy/freeloot.cron /etc/cron.d/freeloot && sudo chmod 644 /etc/cron.d/freeloot
   sudo touch /var/log/freeloot.log && sudo chown nginx /var/log/freeloot.log
   ```

## Day to day

| Command | What it does |
|---|---|
| `php bin/check.php` | Normal run (what cron does) |
| `php bin/check.php --no-mail` | Update the DB without emailing |
| `php bin/check.php --force-mail -v` | Re-send the current state, with the SMTP log |
| `php bin/check.php --preview=/tmp/mail.html` | Render the email to a file |
| `tail -f /var/log/freeloot.log` | See what each run found |

## How it behaves

- **Email triggers:** a game that's newly live, or newly announced as upcoming. Each game
  is emailed at most twice (once announced, once live). If both happen in the same
  run, they go in one email.
- **History:** Epic's feed only has the current and upcoming games, so the past is
  pre-filled from `data/seed.json`. From the first cron run onward, everything is
  recorded with full details (store link, price, artwork). Seed entries link to an
  Epic store search and use Steam artwork when an exact title match was found
  (13 of 96 have a neon placeholder instead).
- **Mystery games** that Epic teases before revealing them are skipped until named.
  Upcoming games that Epic withdraws are removed.
- **Region:** `epic.country` (DK) controls the prices shown and which giveaways count.
- **Mobile giveaways** (Epic's Android/iOS store) aren't in this feed and aren't tracked.
