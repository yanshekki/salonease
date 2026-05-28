<?php
/**
 * Migration 007 - 積分累積率設定
 * 
 * Phase 2 客戶忠誠度系統補完。
 * 補齊 A12 未完成的設定功能：
 * - 新增 points_earn_rate（每消費多少元 = 1 點，預設 10）
 * - 同時確保 points_redemption_rate 存在（相容舊環境）
 * 讓忠誠度積分累積與兌換率皆可從設定頁調整。
 */

return [
    'description' => '新增積分累積率設定（points_earn_rate），並確保兌換率欄位存在',

    'up' => function(PDO $pdo) {
        // 確保 points_redemption_rate 存在（若 006 未執行）
        try {
            $pdo->exec("ALTER TABLE settings ADD COLUMN points_redemption_rate INT UNSIGNED NOT NULL DEFAULT 10 COMMENT '多少積分 = $1 折扣（預設 10 點 = $1）'");
            echo "✓ settings.points_redemption_rate 欄位新增完成（預設 10）\n";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate column') !== false) {
                echo "✓ points_redemption_rate 欄位已存在\n";
            } else {
                throw $e;
            }
        }

        // 新增 points_earn_rate
        try {
            $pdo->exec("ALTER TABLE settings ADD COLUMN points_earn_rate INT UNSIGNED NOT NULL DEFAULT 10 COMMENT '每消費多少元累積 1 點（預設 10 元 = 1 點）'");
            echo "✓ settings.points_earn_rate 欄位新增完成（預設 10）\n";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate column') !== false) {
                echo "✓ points_earn_rate 欄位已存在，跳過\n";
            } else {
                throw $e;
            }
        }
    },

    'down' => function(PDO $pdo) {
        try {
            $pdo->exec("ALTER TABLE settings DROP COLUMN points_earn_rate");
            echo "✓ settings.points_earn_rate 欄位已移除\n";
        } catch (PDOException $e) {}

        try {
            $pdo->exec("ALTER TABLE settings DROP COLUMN points_redemption_rate");
            echo "✓ settings.points_redemption_rate 欄位已移除\n";
        } catch (PDOException $e) {}
    }
];
