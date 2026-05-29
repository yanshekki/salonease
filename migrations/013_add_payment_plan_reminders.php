<?php
/**
 * Migration 013 - 付款計劃自動提醒系統（Phase 5）
 *
 * 新增兩個表：
 * - plan_reminder_rules：定義每個計劃的提醒規則
 * - plan_notifications：記錄已發送的提醒（避免重複發送 + 歷史查詢）
 */

return [
    'description' => '新增付款計劃提醒規則與通知記錄表（Phase 5 自動化提醒）',

    'up' => function(PDO $pdo) {
        // 1. plan_reminder_rules 表
        $sql1 = "
            CREATE TABLE IF NOT EXISTS `plan_reminder_rules` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `plan_id` INT UNSIGNED NOT NULL,
                `reminder_type` ENUM('before_due', 'after_due') NOT NULL DEFAULT 'before_due' COMMENT 'before_due=到期前, after_due=逾期後',
                `offset_days` TINYINT UNSIGNED NOT NULL DEFAULT 3 COMMENT '提前或延後天數',
                `channel` ENUM('email','sms','both') NOT NULL DEFAULT 'email',
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                `last_sent_at` DATETIME DEFAULT NULL,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                INDEX `idx_rule_plan` (`plan_id`),
                INDEX `idx_rule_active` (`is_active`),
                CONSTRAINT `fk_reminder_rule_plan` FOREIGN KEY (`plan_id`) REFERENCES `sale_payment_plans`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='付款計劃提醒規則（Phase 5）';
        ";
        $pdo->exec($sql1);
        echo "✓ plan_reminder_rules 表建立完成\n";

        // 2. plan_notifications 表（發送記錄）
        $sql2 = "
            CREATE TABLE IF NOT EXISTS `plan_notifications` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `plan_id` INT UNSIGNED NOT NULL,
                `reminder_rule_id` INT UNSIGNED DEFAULT NULL,
                `channel` ENUM('email','sms') NOT NULL,
                `sent_at` DATETIME NOT NULL,
                `status` ENUM('sent','failed') NOT NULL DEFAULT 'sent',
                `subject` VARCHAR(255) DEFAULT NULL,
                `content` TEXT,
                `error_message` TEXT,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                INDEX `idx_notify_plan` (`plan_id`),
                INDEX `idx_notify_sent` (`sent_at`),
                CONSTRAINT `fk_notification_plan` FOREIGN KEY (`plan_id`) REFERENCES `sale_payment_plans`(`id`) ON DELETE CASCADE,
                CONSTRAINT `fk_notification_rule` FOREIGN KEY (`reminder_rule_id`) REFERENCES `plan_reminder_rules`(`id`) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='付款計劃提醒發送記錄（Phase 5）';
        ";
        $pdo->exec($sql2);
        echo "✓ plan_notifications 表建立完成\n";

        echo "✓ Migration 013 完成：付款計劃提醒系統資料表已建立\n";
    },

    'down' => function(PDO $pdo) {
        $pdo->exec("DROP TABLE IF EXISTS `plan_notifications`");
        echo "✓ plan_notifications 表已移除\n";
        $pdo->exec("DROP TABLE IF EXISTS `plan_reminder_rules`");
        echo "✓ plan_reminder_rules 表已移除\n";
    }
];
