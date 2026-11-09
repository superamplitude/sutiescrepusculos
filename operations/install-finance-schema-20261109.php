<?php
declare(strict_types=1);
// Additive schema only. Uses the established application database connection.
require dirname(__DIR__).'/app/bootstrap.php';
$pdo=\App\Core\DB::pdo();
$ddl=[
"CREATE TABLE IF NOT EXISTS suites_folio_items (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 reservation_id INT NOT NULL,
 description VARCHAR(250) NOT NULL,
 qty DECIMAL(12,3) NOT NULL,
 unit_price DECIMAL(14,2) NOT NULL,
 line_total DECIMAL(14,2) NOT NULL,
 created_by INT NOT NULL,
 created_at DATETIME NOT NULL,
 voided_at DATETIME NULL,
 voided_by INT NULL,
 INDEX idx_folio_reservation(reservation_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
"CREATE TABLE IF NOT EXISTS suites_folio_closures (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 reservation_id INT NOT NULL,
 lodging_total DECIMAL(14,2) NOT NULL,
 consumption_total DECIMAL(14,2) NOT NULL,
 service_rate DECIMAL(5,2) NOT NULL,
 service_fee DECIMAL(14,2) NOT NULL,
 paid_total DECIMAL(14,2) NOT NULL,
 grand_total DECIMAL(14,2) NOT NULL,
 balance_due DECIMAL(14,2) NOT NULL,
 closed_by INT NOT NULL,
 closed_at DATETIME NOT NULL,
 UNIQUE KEY uniq_folio_reservation(reservation_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
"CREATE TABLE IF NOT EXISTS suites_cash_entries (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 entry_date DATE NOT NULL,
 type ENUM('in','out') NOT NULL,
 category VARCHAR(100) NOT NULL,
 description VARCHAR(500) NOT NULL,
 method VARCHAR(80) NOT NULL,
 amount DECIMAL(14,2) NOT NULL,
 created_by INT NOT NULL,
 created_at DATETIME NOT NULL,
 voided_at DATETIME NULL,
 voided_by INT NULL,
 INDEX idx_cash_date(entry_date,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
];
foreach($ddl as $statement)$pdo->exec($statement);
echo "FINANCE_SCHEMA_READY\n";
