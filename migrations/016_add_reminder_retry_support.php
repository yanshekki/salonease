<?php
/**
 * Phase 7 A - 提醒失敗重試支援
 *
 * 為 plan_notifications 加入重試計數欄位，讓 cron 可以自動重試失敗的提醒。
 */

require_once __DIR__ . '/../db.php';

echo "Running migration 016_add_reminder_retry_support.php...\n";

try {
    // 加入重試相關欄位（如果不存在）
    db_exec("
        ALTER TABLE plan_notifications 
        ADD COLUMN retry_count INT NOT NULL DEFAULT 0 AFTER status,
        ADD COLUMN last_retry_at DATETIME NULL AFTER retry_count
    ");
    echo "  ✓ Added retry_count + last_retry_at to plan_notifications\n";
} catch (Exception $e) {
    // 如果欄位已存在，忽略錯誤
    if (strpos($e->getMessage(), 'Duplicate column') !== false || strpos($e->getMessage(), 'already exists') !== false) {
        echo "  - Columns already exist, skipping.\n";
    } else {
        throw $e;
    }
}

echo "Migration 016 completed successfully.\n";