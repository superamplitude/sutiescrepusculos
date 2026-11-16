<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
\App\Core\DB::pdo()->exec("CREATE TABLE IF NOT EXISTS suites_catalog_items (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(180) NOT NULL,
 category ENUM('bar','service') NOT NULL,
 unit VARCHAR(35) NOT NULL DEFAULT 'un',
 price DECIMAL(14,2) NOT NULL,
 active TINYINT(1) NOT NULL DEFAULT 1,
 created_at DATETIME NOT NULL,
 updated_at DATETIME NOT NULL,
 INDEX idx_catalog_category_active (category, active, name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
echo "SUITES_CATALOG_SCHEMA_READY\n";
