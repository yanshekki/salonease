<?php
/**
 * Migration 009 - 自訂付款方法 + 手續費設定（Phase 1 基礎）
 *
 * 為「每張帳單支援多次付款 + 分期/周期性付款」功能建立核心基礎。
 * 獨立管理付款方法，支援多種手續費計算模型（香港市場實務導向）。
 *
 * 嚴格遵守：
 * - 僅使用 CREATE TABLE IF NOT EXISTS（安全、可重複執行）
 * - 所有寫入操作在 upgrade.php 提供的 transaction 內
 * - 不影響任何現有 sales / reports / POS 流程
 */

return [
    'description' => '新增 payment_methods 表（支援自訂付款方式 + 多種手續費計算模型）',

    'up' => function(PDO $pdo) {
        $sql = "
            CREATE TABLE IF NOT EXISTS `payment_methods` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `code` VARCHAR(30) NOT NULL UNIQUE COMMENT '內部代碼：fps, payme, stripe_card, cash 等（程式用）',
                `name` VARCHAR(60) NOT NULL COMMENT '前台顯示名稱，例如「轉數快 (FPS)」',
                `fee_model` ENUM('none','fixed','percent','fixed_plus_percent') NOT NULL DEFAULT 'none' COMMENT '手續費計算方式',
                `fee_fixed` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '固定手續費金額（HKD），適用於 fixed 及 fixed_plus_percent',
                `fee_percent` DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT '百分比費率（0.00-100.00），適用於 percent 及 fixed_plus_percent',
                `is_active` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '是否啟用（停用後前台不可選，但歷史記錄仍可見）',
                `sort_order` SMALLINT UNSIGNED NOT NULL DEFAULT 100 COMMENT '顯示排序（數字越小越靠前）',
                `notes` TEXT COMMENT '備註說明（例如香港市場實際收費參考）',
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                INDEX `idx_pm_active_sort` (`is_active`, `sort_order`),
                INDEX `idx_pm_code` (`code`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='自訂付款方法 + 手續費規則（Phase 1 基礎，支援一單多付與分期）';
        ";

        $pdo->exec($sql);
        echo "✓ payment_methods 表建立完成\n";

        // 插入 8 種香港市場常見付款方法（依真實收費參考設定）
        // 資料來源參考：用戶提供 + 2026 年香港主流支付工具實際費率
        $methods = [
            [
                'code' => 'cash',
                'name' => '現金',
                'fee_model' => 'none',
                'fee_fixed' => 0.00,
                'fee_percent' => 0.00,
                'sort_order' => 10,
                'notes' => '最常用，無手續費。適合小額消費。'
            ],
            [
                'code' => 'fps',
                'name' => '轉數快 (FPS)',
                'fee_model' => 'none',
                'fee_fixed' => 0.00,
                'fee_percent' => 0.00,
                'sort_order' => 20,
                'notes' => '香港銀行轉帳，個人及商戶多數免費或僅收極低固定費。'
            ],
            [
                'code' => 'payme',
                'name' => 'PayMe',
                'fee_model' => 'percent',
                'fee_fixed' => 0.00,
                'fee_percent' => 1.50,
                'sort_order' => 30,
                'notes' => 'HSBC PayMe 商戶收款手續費約 1.2%~1.5%。'
            ],
            [
                'code' => 'card',
                'name' => '信用卡 / 八達通',
                'fee_model' => 'fixed_plus_percent',
                'fee_fixed' => 2.35,
                'fee_percent' => 3.40,
                'sort_order' => 40,
                'notes' => 'Stripe 等國際收單：典型 3.4% + HK$2.35（視銀行而定）。八達通商戶費率另計。'
            ],
            [
                'code' => 'alipay_hk',
                'name' => 'AlipayHK',
                'fee_model' => 'percent',
                'fee_fixed' => 0.00,
                'fee_percent' => 2.00,
                'sort_order' => 50,
                'notes' => '支付寶香港商戶手續費約 1.5%~2.5%，視交易類型。'
            ],
            [
                'code' => 'wechat_hk',
                'name' => 'WeChat Pay HK',
                'fee_model' => 'percent',
                'fee_fixed' => 0.00,
                'fee_percent' => 2.00,
                'sort_order' => 60,
                'notes' => '微信支付香港商戶手續費約 1.5%~2.5%。'
            ],
            [
                'code' => 'bank_transfer',
                'name' => '銀行轉帳',
                'fee_model' => 'none',
                'fee_fixed' => 0.00,
                'fee_percent' => 0.00,
                'sort_order' => 70,
                'notes' => '傳統銀行電匯或 FPS 企業戶，費率多數由客戶承擔或雙方協商。'
            ],
            [
                'code' => 'other',
                'name' => '其他',
                'fee_model' => 'none',
                'fee_fixed' => 0.00,
                'fee_percent' => 0.00,
                'sort_order' => 999,
                'notes' => '現金以外無法歸類的方式（例如支票、禮券等）。建議設定為 0 手續費。'
            ],
        ];

        $stmt = $pdo->prepare("
            INSERT INTO payment_methods (code, name, fee_model, fee_fixed, fee_percent, is_active, sort_order, notes)
            VALUES (?, ?, ?, ?, ?, 1, ?, ?)
            ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                fee_model = VALUES(fee_model),
                fee_fixed = VALUES(fee_fixed),
                fee_percent = VALUES(fee_percent),
                sort_order = VALUES(sort_order),
                notes = VALUES(notes)
        ");

        $inserted = 0;
        foreach ($methods as $m) {
            $stmt->execute([
                $m['code'],
                $m['name'],
                $m['fee_model'],
                $m['fee_fixed'],
                $m['fee_percent'],
                $m['sort_order'],
                $m['notes']
            ]);
            $inserted++;
        }

        echo "✓ 已插入/更新 $inserted 種付款方法（香港市場常用配置）\n";
    },

    'down' => function(PDO $pdo) {
        // 僅供開發環境使用，生產環境請勿執行
        $pdo->exec("DROP TABLE IF EXISTS `payment_methods`");
        echo "✓ payment_methods 表已刪除（僅限開發環境）\n";
    }
];
