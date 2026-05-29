<?php
/**
 * Migration 011 - 分期 / 周期性付款支援（Phase 3 核心）
 *
 * 為完整實現「每張帳單支援多次付款 + 分期/周期性付款」功能，建立分期計劃表。
 *
 * 主要內容：
 * - 新增 sale_payment_plans 表（支援 installment 及 recurring 兩種模式）
 * - 為 payments 表增加 plan_id 欄位（關聯到分期計劃）
 * - 為 payments 表增加 installment_no（記錄是第幾期）
 *
 * 設計目標：
 * - 一張銷售單可以對應一個分期計劃
 * - 每期付款會在 payments 表記錄，並標記 installment_no
 * - 支援手動記錄分期進度（Phase 3 先不做自動扣款）
 */

return [
    'description' => '新增 sale_payment_plans 表 + payments 欄位（Phase 3 分期/周期性付款支援）',

    'up' => function(PDO $pdo) {
        // 1. 建立 sale_payment_plans 表
        $sql = "
            CREATE TABLE IF NOT EXISTS `sale_payment_plans` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `sale_id` INT UNSIGNED NOT NULL,
                `plan_type` ENUM('installment','recurring') NOT NULL COMMENT 'installment=分期, recurring=周期性',
                `total_installments` TINYINT UNSIGNED NOT NULL DEFAULT 1,
                `installment_amount` DECIMAL(10,2) NOT NULL COMMENT '每期金額',
                `frequency` ENUM('weekly','biweekly','monthly','quarterly') DEFAULT NULL COMMENT 'recurring 模式使用',
                `start_date` DATE NOT NULL,
                `end_date` DATE DEFAULT NULL,
                `status` ENUM('active','completed','cancelled') NOT NULL DEFAULT 'active',
                `notes` TEXT,
                `created_by` INT UNSIGNED DEFAULT NULL,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                INDEX `idx_plan_sale` (`sale_id`),
                INDEX `idx_plan_status` (`status`),
                CONSTRAINT `fk_plan_sale` FOREIGN KEY (`sale_id`) REFERENCES `sales`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='銷售單分期/周期性付款計劃（Phase 3）';
        ";
        $pdo->exec($sql);
        echo "✓ sale_payment_plans 表建立完成\n";

        // 2. 為 payments 表新增欄位（安全寫法）
        $paymentsColumns = [
            'plan_id' => "INT UNSIGNED DEFAULT NULL COMMENT '關聯的分期計劃'",
            'installment_no' => "TINYINT UNSIGNED DEFAULT NULL COMMENT '這是第幾期付款'"
        ];

        foreach ($paymentsColumns as $col => $definition) {
            try {
                $pdo->exec("ALTER TABLE payments ADD COLUMN $col $definition AFTER installment_no");
                echo "✓ payments.$col 欄位新增完成\n";
            } catch (PDOException $e) {
                if (stripos($e->getMessage(), 'Duplicate column') !== false || stripos($e->getMessage(), 'already exists') !== false) {
                    echo "✓ payments.$col 已存在，跳過\n";
                } else {
                    throw $e;
                }
            }
        }

        // 3. 建立外鍵（plan_id）
        try {
            $pdo->exec("ALTER TABLE payments ADD CONSTRAINT fk_payments_plan FOREIGN KEY (plan_id) REFERENCES sale_payment_plans(id) ON DELETE SET NULL");
            echo "✓ payments.plan_id 外鍵建立完成\n";
        } catch (PDOException $e) {
            if (stripos($e->getMessage(), 'Duplicate') === false) {
                echo "⚠ payments.plan_id 外鍵可能已存在或建立失敗: " . $e->getMessage() . "\n";
            } else {
                echo "✓ payments.plan_id 外鍵已存在\n";
            }
        }

        // 4. 建立索引
        try {
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_payments_plan ON payments (plan_id, installment_no)");
            echo "✓ idx_payments_plan 索引建立完成\n";
        } catch (PDOException $e) {
            echo "✓ 索引可能已存在\n";
        }
    },

    'down' => function(PDO $pdo) {
        try { $pdo->exec("ALTER TABLE payments DROP FOREIGN KEY fk_payments_plan"); } catch (Throwable $e) {}
        try { $pdo->exec("ALTER TABLE payments DROP COLUMN plan_id"); } catch (Throwable $e) {}
        try { $pdo->exec("ALTER TABLE payments DROP COLUMN installment_no"); } catch (Throwable $e) {}
        try { $pdo->exec("DROP TABLE IF EXISTS sale_payment_plans"); } catch (Throwable $e) {}
        echo "✓ Phase 3 分期計劃相關結構已移除（僅限開發環境）\n";
    }
];
