<?php
/**
 * Migration 012 - 付款計劃「需要關注」門檻可調（Phase 4 A）
 *
 * 讓店長可以在設定頁自訂「需要關注」的條件，而非硬編碼。
 *
 * 新增兩個欄位：
 * - needs_attention_days_threshold：計劃建立超過多少天 + 進度低 = 需要關注（預設 45）
 * - needs_attention_progress_threshold：進度低於多少百分比 = 需要關注（預設 30）
 *
 * 所有原本硬編碼 45 天 / 30% 的地方都會改讀取這兩個設定值。
 */

return [
    'description' => '新增付款計劃需要關注門檻設定欄位（Phase 4 入口整合）',

    'up' => function(PDO $pdo) {
        // 為 settings 表新增兩個門檻欄位（安全寫法）
        $columns = [
            'needs_attention_days_threshold' => "INT NOT NULL DEFAULT 45 COMMENT '需要關注：計劃建立超過 N 天 + 進度低'",
            'needs_attention_progress_threshold' => "INT NOT NULL DEFAULT 30 COMMENT '需要關注：已付期數低於 N% 視為進度低'"
        ];

        foreach ($columns as $col => $definition) {
            try {
                $pdo->exec("ALTER TABLE settings ADD COLUMN $col $definition AFTER default_low_stock_threshold");
                echo "✓ settings.$col 欄位新增完成\n";
            } catch (PDOException $e) {
                if (stripos($e->getMessage(), 'Duplicate column') !== false || stripos($e->getMessage(), 'already exists') !== false) {
                    echo "✓ settings.$col 已存在，跳過\n";
                } else {
                    throw $e;
                }
            }
        }

        // 更新預設值（確保舊資料也有合理預設）
        try {
            $pdo->exec("
                UPDATE settings 
                SET 
                    needs_attention_days_threshold = COALESCE(needs_attention_days_threshold, 45),
                    needs_attention_progress_threshold = COALESCE(needs_attention_progress_threshold, 30)
                WHERE id = 1
            ");
            echo "✓ 付款計劃門檻預設值已確保\n";
        } catch (Exception $e) {
            echo "⚠ 預設值更新失敗（可忽略）：{$e->getMessage()}\n";
        }

        echo "✓ Migration 012 完成：付款計劃需要關注門檻可調已就緒\n";
    },

    'down' => function(PDO $pdo) {
        // 移除欄位（開發環境用，生產不建議執行）
        try {
            $pdo->exec("ALTER TABLE settings DROP COLUMN needs_attention_days_threshold");
            echo "✓ 已移除 needs_attention_days_threshold\n";
        } catch (Exception $e) {
            echo "⚠ 移除失敗（可忽略）：{$e->getMessage()}\n";
        }
        try {
            $pdo->exec("ALTER TABLE settings DROP COLUMN needs_attention_progress_threshold");
            echo "✓ 已移除 needs_attention_progress_threshold\n";
        } catch (Exception $e) {
            echo "⚠ 移除失敗（可忽略）：{$e->getMessage()}\n";
        }
    }
];