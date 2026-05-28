<?php
/**
 * Migration 004 - 操作審計日誌（Audit Logs）
 * 
 * Phase 1 安全性強化項目。
 * 用於記錄重要操作（銷售、佣金、套票扣減、設定修改、員工權限變更等），
 * 提供可追溯性與合規性。
 */

return [
    'description' => '新增 audit_logs 表，支援操作審計與追溯',

    'up' => function(PDO $pdo) {
        $sql = "
            CREATE TABLE IF NOT EXISTS `audit_logs` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `staff_id` INT UNSIGNED DEFAULT NULL COMMENT '執行操作的員工（NULL 表示系統）',
                `action` VARCHAR(50) NOT NULL COMMENT '操作類型，例如：sale.created, staff.updated, commission.adjusted',
                `entity_type` VARCHAR(50) DEFAULT NULL COMMENT '受影響的實體類型，例如：sale, staff, customer_package',
                `entity_id` BIGINT UNSIGNED DEFAULT NULL COMMENT '受影響的實體 ID',
                `details` JSON DEFAULT NULL COMMENT '操作細節（JSON 格式）',
                `ip_address` VARCHAR(45) DEFAULT NULL,
                `user_agent` VARCHAR(255) DEFAULT NULL,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                INDEX `idx_audit_staff` (`staff_id`),
                INDEX `idx_audit_action` (`action`),
                INDEX `idx_audit_entity` (`entity_type`, `entity_id`),
                INDEX `idx_audit_created` (`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='操作審計日誌';
        ";

        $pdo->exec($sql);

        echo "✓ audit_logs 表建立完成\n";
    },

    'down' => function(PDO $pdo) {
        $pdo->exec("DROP TABLE IF EXISTS `audit_logs`");
        echo "✓ audit_logs 表已刪除\n";
    }
];