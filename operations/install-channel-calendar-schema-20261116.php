<?php
declare(strict_types=1);
// Additive and isolated OTA calendar feed registry for the existing Suites map.
// Does not change any existing table, user, reservation or iCal feed.
require dirname(__DIR__).'/app/bootstrap.php';
$pdo=\App\Core\DB::pdo();
$pdo->exec("CREATE TABLE IF NOT EXISTS suites_ical_channel_feeds (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 room_id INT NOT NULL,
 channel VARCHAR(32) NOT NULL,
 import_url VARCHAR(1900) NOT NULL,
 active TINYINT(1) NOT NULL DEFAULT 1,
 last_sync_at DATETIME NULL,
 last_error VARCHAR(650) NULL,
 created_at DATETIME NOT NULL,
 updated_at DATETIME NOT NULL,
 KEY idx_room_channel (room_id,channel),
 KEY idx_channel_active (channel,active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
echo "SUITES_CHANNEL_CALENDAR_SCHEMA_READY\n";
