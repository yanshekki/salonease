<?php
/**
 * Migration 006 - 積分兌換率設定
 * 
 * Phase 2 客戶忠誠度系統。
 * 允許從設定頁調整「多少積分 = $1 折扣」。
 */

return [
    'description' => '新增積分兌換率設定（points_redemption_rate）',

    'up' => function(PDO $pdo) {
        try {
            $pdo->exec("ALTER TABLE settings ADD COLUMN points_redemption_rate INT UNSIGNED NOT NULL DEFAULT 10 COMMENT '多少積分 = $1 折扣（預設 10 點 = $1）'");
            echo "✓ settings.points_redemption_rate 欄位新增完成（預設 10）\n";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate column') !== false) {
                echo "✓ 欄位已存在，跳過\n";
            } else {
                throw $e;
            }
        }
    },

    'down' => function(PDO $pdo) {
        try {
            $pdo->exec("ALTER TABLE settings DROP COLUMN points_redemption_rate");
            echo "✓ settings.points_redemption_rate 欄位已移除\n";
        } catch (PDOException $e) {}
    }
];
