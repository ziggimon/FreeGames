<?php
/**
 * Free Loot — configuration.
 *
 * Copy to config.php (same folder) and fill in the blanks:
 *   cp config.sample.php config.php
 *   chown root:www-data config.php && chmod 640 config.php
 *
 * config.php lives OUTSIDE the web root (public/), so nginx never serves it.
 */
return [
    // Times on the page and in emails are shown in this zone. The DB stores UTC.
    'timezone' => 'Europe/Copenhagen',

    // Public URL of the web page (used for links in the email).
    'site_url' => 'https://games.cxx.dk',

    // MySQL / MariaDB.
    'db' => [
        'dsn'  => 'mysql:host=127.0.0.1;port=3306;dbname=epic_free;charset=utf8mb4',
        'user' => 'epic_free',
        'pass' => '',                      // <-- set this
    ],

    // Epic Games Store region. Country affects which giveaways and prices you see.
    'epic' => [
        'country' => 'DK',
        'locale'  => 'en-US',
    ],

    // How far back the page shows past giveaways.
    'history_days' => 365,

    // Email via Simply.com SMTP.
    //  - Port 587 + 'tls'  (STARTTLS)  — recommended
    //  - Port 465 + 'ssl'  (implicit TLS)
    'mail' => [
        'enabled'    => true,
        'host'       => 'smtp.simply.com',
        'port'       => 587,
        'encryption' => 'tls',             // 'tls' | 'ssl' | 'none'
        'username'   => '',                // <-- full mailbox address, e.g. freegames@cxx.dk
        'password'   => '',                // <-- mailbox password
        'from'       => 'freegames@cxx.dk',// must be a mailbox/alias on the Simply.com account
        'from_name'  => 'Free Loot',
        'to'         => [
            'you@cxx.dk',                  // <-- one or more recipients
        ],
    ],
];
