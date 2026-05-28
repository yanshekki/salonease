<?php
/**
 * Migration 010 - 多筆付款記錄核心 + 銷售狀態（Phase 2 基礎）
 *
 * 為「每張帳單支援多次付款 + 分期/周期性付款」功能建立核心付款記錄層。
 *
 * 主要內容：
 * - 新增 payments 表（支援多筆付款、手續費、退款、分期標記）
 * - 為 sales 表新增 amount_paid、payment_status、primary_payment_method_id
 * - 為既有銷售單建立對應的 payment 記錄（legacy 遷移）
 *
 * 嚴格遵守：
 * - 使用 CREATE TABLE IF NOT EXISTS 及 ALTER TABLE 安全寫法
 * - 所有操作在 upgrade.php 提供的 transaction 內
 * - 向後相容：不破壞現有 sales / reports / POS 流程
 */

return [
    'description' => '新增 payments 表 + sales 付款狀態欄位 + 既有資料遷移（Phase 2 核心）',

    'up' => function(PDO $pdo) {
        // 1. 建立 payments 表
        $sql = "
            CREATE TABLE IF NOT EXISTS `payments` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `sale_id` INT UNSIGNED NOT NULL,
                `payment_method_id` INT UNSIGNED NOT NULL,
                `amount` DECIMAL(10,2) NOT NULL COMMENT '客戶本次實際支付金額（扣減帳單餘額用）',
                `fee_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '商戶實際承擔的手續費',
                `fee_borne_by` ENUM('merchant','customer') NOT NULL DEFAULT 'merchant' COMMENT '手續費由誰承擔',
                `paid_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `staff_id` INT UNSIGNED NOT NULL COMMENT '收款員工',
                `ref_number` VARCHAR(120) DEFAULT NULL COMMENT 'FPS 參考號 / 交易單號',
                `notes` TEXT,
                `is_refund` TINYINT(1) NOT NULL DEFAULT 0,
                `refund_of_payment_id` BIGINT UNSIGNED DEFAULT NULL,
                `installment_no` TINYINT UNSIGNED DEFAULT NULL COMMENT '分期期數',
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                INDEX `idx_payments_sale_time` (`sale_id`, `paid_at`),
                INDEX `idx_payments_method` (`payment_method_id`),
                INDEX `idx_payments_staff` (`staff_id`),
                CONSTRAINT `fk_payments_sale` FOREIGN KEY (`sale_id`) REFERENCES `sales`(`id`) ON DELETE CASCADE,
                CONSTRAINT `fk_payments_method` FOREIGN KEY (`payment_method_id`) REFERENCES `payment_methods`(`id`),
                CONSTRAINT `fk_payments_staff` FOREIGN KEY (`staff_id`) REFERENCES `staff`(`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='銷售單多筆付款記錄（Phase 2 核心，支援一單多付與分期）';
        ";

        $pdo->exec($sql);
        echo "✓ payments 表建立完成\n";

        // 2. 為 sales 表新增欄位（安全寫法）
        $salesColumns = [
            'amount_paid' => "DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '已收款總額（非退款 payments 合計）'",
            'payment_status' => "ENUM('unpaid','partial','paid','refunded','overpaid') NOT NULL DEFAULT 'unpaid' COMMENT '付款狀態'",
            'primary_payment_method_id' => "INT UNSIGNED DEFAULT NULL COMMENT '主要付款方式（用於快速顯示）'"
        ];

        foreach ($salesColumns as $col => $definition) {
            try {
                $pdo->exec("ALTER TABLE sales ADD COLUMN $col $definition AFTER total");
                echo "✓ sales.$col 欄位新增完成\n";
            } catch (PDOException $e) {
                if (stripos($e->getMessage(), 'Duplicate column') !== false || stripos($e->getMessage(), 'already exists') !== false) {
                    echo "✓ sales.$col 已存在，跳過\n";
                } else {
                    throw $e;
                }
            }
        }

        // 3. 為既有銷售單建立對應 payment 記錄（legacy 遷移）
        echo "開始為既有銷售單建立 payment 記錄...\n";

        $legacyMethods = $pdo->query("
            SELECT DISTINCT payment_method 
            FROM sales 
            WHERE payment_method IS NOT NULL
        ")->fetchAll(PDO::FETCH_COLUMN);

        // 建立 payment_method 對應表（更安全的做法）
        $existingMethods = $pdo->query("SELECT id, code FROM payment_methods")->fetchAll(PDO::FETCH_KEY_PAIR);
        $existingMethodIds = array_flip($existingMethods); // 用來快速驗證 ID 是否存在

        $methodMap = [];
        foreach ($legacyMethods as $pm) {
            $code = strtolower($pm);
            if (isset($existingMethods[$code])) {
                $methodMap[$pm] = $existingMethods[$code];
            } else {
                // 安全 fallback：確保 'other' 存在
                $otherId = $existingMethods['other'] ?? null;
                if (!$otherId) {
                    $pdo->exec("INSERT IGNORE INTO payment_methods (code, name, fee_model) VALUES ('other', '其他', 'none')");
                    $otherId = $pdo->lastInsertId();
                    $existingMethods['other'] = $otherId;
                    $existingMethodIds[$otherId] = 'other';
                }
                $methodMap[$pm] = $otherId;
            }
        }

        // 取得所有需要遷移的銷售單
        $salesToMigrate = $pdo->query("
            SELECT id, customer_id, staff_id, total, payment_method, sale_date, created_at 
            FROM sales 
            WHERE amount_paid = 0 OR amount_paid IS NULL
        ")->fetchAll(PDO::FETCH_ASSOC);

        $migratedCount = 0;
        $skippedCount = 0;
        $insertStmt = $pdo->prepare("
            INSERT INTO payments (sale_id, payment_method_id, amount, fee_amount, fee_borne_by, paid_at, staff_id, created_at)
            VALUES (?, ?, ?, 0, 'merchant', ?, ?, ?)
        ");

        foreach ($salesToMigrate as $sale) {
            $methodId = $methodMap[$sale['payment_method']] ?? null;

            // 最終安全檢查：確保這個 ID 真的存在於 payment_methods
            if (!$methodId || !isset($existingMethodIds[$methodId])) {
                // 找不到有效的付款方法，跳過這筆（避免 FK 錯誤）
                $skippedCount++;
                continue;
            }

            $insertStmt->execute([
                $sale['id'],
                $methodId,
                $sale['total'],
                $sale['sale_date'] . ' 12:00:00',
                $sale['staff_id'],
                $sale['created_at']
            ]);

            // 更新 sales
            $pdo->prepare("
                UPDATE sales 
                SET amount_paid = ?, payment_status = 'paid', primary_payment_method_id = ?
                WHERE id = ?
            ")->execute([$sale['total'], $methodId, $sale['id']]);

            $migratedCount++;
        }

        echo "✓ 已為 $migratedCount 筆既有銷售單建立 payment 記錄";
        if ($skippedCount > 0) {
            echo "（跳過 $skippedCount 筆無法對應付款方法的舊資料）";
        }
        echo "\n";

        // 4. 建立索引（如果尚未存在）
        try {
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_sales_payment_status ON sales (payment_status, sale_date)");
            echo "✓ idx_sales_payment_status 建立完成\n";
        } catch (PDOException $e) {
            echo "✓ 索引可能已存在\n";
        }
    },

    'down' => function(PDO $pdo) {
        // 僅供開發環境使用
        try { $pdo->exec("ALTER TABLE sales DROP COLUMN amount_paid"); } catch (Throwable $e) {}
        try { $pdo->exec("ALTER TABLE sales DROP COLUMN payment_status"); } catch (Throwable $e) {}
        try { $pdo->exec("ALTER TABLE sales DROP COLUMN primary_payment_method_id"); } catch (Throwable $e) {}
        try { $pdo->exec("DROP TABLE IF EXISTS payments"); } catch (Throwable $e) {}
        echo "✓ Phase 2 相關結構已移除（僅限開發環境）\n";
    }
];
