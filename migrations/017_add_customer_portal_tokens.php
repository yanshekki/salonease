<?php
/**
 * Phase 8: Customer Self-Service Portal
 * 
 * 建立 customer_portal_tokens 表，用於安全、無需登入的客戶 Portal 存取。
 * 支援時間限制 token，未來可擴展為可撤銷。
 */

require_once __DIR__ . '/../db.php';

echo "Running migration 017_add_customer_portal_tokens.php...\n";

try {
    db_exec("
        CREATE TABLE IF NOT EXISTS `customer_portal_tokens` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `customer_id` INT UNSIGNED NOT NULL,
            `token` VARCHAR(128) NOT NULL,
            `expires_at` DATETIME NOT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `used_at` DATETIME NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_token` (`token`),
            INDEX `idx_customer` (`customer_id`),
            INDEX `idx_expires` (`expires_at`),
            CONSTRAINT `fk_portal_token_customer` 
                FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        COMMENT='客戶自助服務 Portal 存取 token（Phase 8）'
    ");
    echo "  ✓ customer_portal_tokens 表建立完成\n";

    // 可選：為現有客戶預留欄位或索引（視需要）
    echo "Migration 017 completed successfully.\n";
} catch (Exception $e) {
    echo "Migration 017 failed: " . $e->getMessage() . "\n";
    throw $e;
}
