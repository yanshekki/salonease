<?php
/**
 * Migration 015 - Twilio SMS 設定（Phase 5）
 *
 * 加入 Twilio 相關設定欄位，讓 SMS 可以真正發送。
 */

return [
    'description' => '新增 Twilio SMS 設定欄位（Phase 5）',

    'up' => function(PDO $pdo) {
        $columns = [
            'twilio_account_sid'   => "VARCHAR(64) DEFAULT NULL COMMENT 'Twilio Account SID'",
            'twilio_auth_token'    => "VARCHAR(64) DEFAULT NULL COMMENT 'Twilio Auth Token'",
            'twilio_from_number'   => "VARCHAR(20) DEFAULT NULL COMMENT 'Twilio 發送號碼（+852...）'",
            'reminder_sms_enabled' => "TINYINT(1) NOT NULL DEFAULT 0 COMMENT '是否啟用實際發送 SMS 提醒'"
        ];

        foreach ($columns as $col => $definition) {
            try {
                $pdo->exec("ALTER TABLE settings ADD COLUMN $col $definition AFTER reminder_from_email");
                echo "✓ settings.$col 欄位新增完成\n";
            } catch (PDOException $e) {
                if (stripos($e->getMessage(), 'Duplicate column') !== false || stripos($e->getMessage(), 'already exists') !== false) {
                    echo "✓ settings.$col 已存在，跳過\n";
                } else {
                    throw $e;
                }
            }
        }

        // 設定預設值
        try {
            $pdo->exec("
                UPDATE settings 
                SET 
                    twilio_account_sid = COALESCE(twilio_account_sid, NULL),
                    twilio_auth_token = COALESCE(twilio_auth_token, NULL),
                    twilio_from_number = COALESCE(twilio_from_number, NULL),
                    reminder_sms_enabled = COALESCE(reminder_sms_enabled, 0)
                WHERE id = 1
            ");
            echo "✓ Twilio SMS 設定預設值已確保\n";
        } catch (Exception $e) {
            echo "⚠ 預設值更新失敗（可忽略）：{$e->getMessage()}\n";
        }

        echo "✓ Migration 015 完成\n";
    },

    'down' => function(PDO $pdo) {
        $cols = ['twilio_account_sid', 'twilio_auth_token', 'twilio_from_number', 'reminder_sms_enabled'];
        foreach ($cols as $col) {
            try {
                $pdo->exec("ALTER TABLE settings DROP COLUMN $col");
                echo "✓ 已移除 $col\n";
            } catch (Exception $e) {
                echo "⚠ 移除 $col 失敗（可忽略）：{$e->getMessage()}\n";
            }
        }
    }
];
