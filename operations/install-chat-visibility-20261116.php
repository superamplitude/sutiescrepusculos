<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
\App\Core\DB::pdo()->exec("CREATE TABLE IF NOT EXISTS suites_chat_visibility (
 conversation_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
 archived_at DATETIME NULL,
 deleted_at DATETIME NULL,
 updated_by BIGINT UNSIGNED NULL,
 updated_at DATETIME NOT NULL,
 KEY idx_visibility_archived(archived_at),
 KEY idx_visibility_deleted(deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
echo "CHAT_VISIBILITY_SCHEMA_READY\n";
