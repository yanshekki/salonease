<?php
/**
 * Migration 005 - 客戶忠誠度積分（Loyalty Points）
 * 
 * Phase 2 專業功能擴展。
 * 為客戶加入 points 欄位，支援銷售時自動累積積分。
 */

return [
    'description' => '為 customers 表新增 points 欄位，支援客戶忠誠度積分系統',

    'up' => function(PDO $pdo) {
        // 安全新增欄位（MySQL 相容寫法）
        try {
            $pdo->exec("ALTER TABLE customers ADD COLUMN points INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '客戶忠誠度積分' AFTER notes");
            echo "✓ customers.points 欄位新增完成\n";
        } catch (PDOException $e) {
            // 如果欄位已存在則忽略
            if (strpos($e->getMessage(), 'Duplicate column') === false) {
                throw $e;
            }
            echo "✓ customers.points 欄位已存在，跳過\n";
        }

        // 建立索引方便查詢高分客戶
        try {
            $pdo->exec("CREATE INDEX idx_customers_points ON customers (points)");
            echo "✓ idx_customers_points 索引建立完成\n";
        } catch (PDOException $e) {
            echo "✓ 索引可能已存在，跳過\n";
        }
    },

    'down' => function(PDO $pdo) {
        try {
            $pdo->exec("DROP INDEX idx_customers_points ON customers");
        } catch (PDOException $e) {}
        
        try {
            $pdo->exec("ALTER TABLE customers DROP COLUMN points");
            echo "✓ customers.points 欄位已移除\n";
        } catch (PDOException $e) {}
    }
];
