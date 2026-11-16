-- Free Loot schema (MySQL 8 / MariaDB 10.5+)
-- All DATETIME columns are UTC.

CREATE TABLE IF NOT EXISTS giveaways (
    id                    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uniq_key              CHAR(40)      NOT NULL,            -- sha1(normalised title | start date)
    epic_id               VARCHAR(64)   NULL,
    namespace             VARCHAR(64)   NULL,
    title                 VARCHAR(255)  NOT NULL,
    description           TEXT          NULL,
    seller                VARCHAR(255)  NULL,
    offer_type            VARCHAR(32)   NULL,
    url                   VARCHAR(512)  NULL,
    image_wide            VARCHAR(1024) NULL,
    image_tall            VARCHAR(1024) NULL,
    original_price        VARCHAR(32)   NULL,                -- formatted, e.g. "DKK 95.00"
    start_at              DATETIME      NOT NULL,
    end_at                DATETIME      NULL,
    source                ENUM('api','seed') NOT NULL DEFAULT 'api',
    notified_upcoming_at  DATETIME      NULL,
    notified_live_at      DATETIME      NULL,
    created_at            DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at            DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_giveaway (uniq_key),
    KEY idx_start (start_at),
    KEY idx_end (end_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS run_log (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ran_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ok            TINYINT(1)   NOT NULL,
    found         INT          NOT NULL DEFAULT 0,
    new_live      INT          NOT NULL DEFAULT 0,
    new_upcoming  INT          NOT NULL DEFAULT 0,
    mailed        TINYINT(1)   NOT NULL DEFAULT 0,
    message       VARCHAR(1000) NULL,
    KEY idx_ran (ran_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
