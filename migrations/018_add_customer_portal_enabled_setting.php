<?php
/**
 * Phase 8: 客戶自助服務 Portal 總開關
 */

require_once __DIR__ . '/../db.php';

echo "Running migration 018_add_customer_portal_enabled_setting.php...\n";

try {
    db_exec("ALTER TABLE settings ADD COLUMN customer_portal_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER quick_restock_20");
    echo "  ✓ Added customer_portal_enabled column to settings\n";
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
        echo "  - Column already exists, skipping.\n";
    } else {
        throw $e;
    }
}

echo "Migration 018 completed successfully.\n";