<?php
/**
 * Migration 014 - 付款計劃提醒 Email 設定（Phase 5）
 *
 * 在 settings 表新增兩個欄位：
 * - reminder_email_enabled：是否啟用實際寄送提醒（預設 0，安全起見）
 * - reminder_from_email：提醒郵件的寄件者 Email（可自訂）
 */

return [
    'description' => '新增提醒 Email 發送控制設定（Phase 5）',

    'up' => function(PDO $pdo) {
        $columns = [
            'reminder_email_enabled' => "TINYINT(1) NOT NULL DEFAULT 0 COMMENT '是否啟用實際寄送付款計劃提醒'",
            'reminder_from_email' => "VARCHAR(100) DEFAULT NULL COMMENT '提醒郵件寄件者 Email（留空則使用 settings.email）'"
        ];

        foreach ($columns as $col => $definition) {
            try {
                $pdo->exec("ALTER TABLE settings ADD COLUMN $col $definition AFTER needs_attention_progress_threshold");
                echo "✓ settings.$col 欄位新增完成\n";
            } catch (PDOException $e) {
                if (stripos($e->getMessage(), 'Duplicate column') !== false || stripos($e->getMessage(), 'already exists') !== false) {
                    echo "✓ settings.$col 已存在，跳過\n";
                } else {
                    throw $e;
                }
            }
        }

        // 確保預設值
        try {
            $pdo->exec("
                UPDATE settings 
                SET 
                    reminder_email_enabled = COALESCE(reminder_email_enabled, 0),
                    reminder_from_email = COALESCE(reminder_from_email, NULL)
                WHERE id = 1
            ");
            echo "✓ 提醒 Email 設定預設值已確保\n";
        } catch (Exception $e) {
            echo "⚠ 預設值更新失敗（可忽略）：{$e->getMessage()}\n";
        }

        echo "✓ Migration 014 完成\n";
    },

    'down' => function(PDO $pdo) {
        try {
            $pdo->exec("ALTER TABLE settings DROP COLUMN reminder_email_enabled");
            echo "✓ 已移除 reminder_email_enabled\n";
        } catch (Exception $e) {
            echo "⚠ 移除失敗（可忽略）：{$e->getMessage()}\n";
        }

        try {
            $pdo->exec("ALTER TABLE settings DROP COLUMN reminder_from_email");
            echo "✓ 已移除 reminder_from_email\n";
        } catch (Exception $e) {
            echo "⚠ 移除失敗（可忽略）：{$e->getMessage()}\n";
        }
    }
];
