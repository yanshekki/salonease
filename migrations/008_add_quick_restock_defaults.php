<?php
/**
 * Migration 008 - 快速補貨預設數量設定
 * 
 * 讓 A23/A24 的快速入庫按鈕可以從設定頁自訂預設值。
 */

return [
    'description' => '新增快速補貨預設數量設定（quick_restock_5/10/20）',

    'up' => function(PDO $pdo) {
        $columns = [
            'quick_restock_5'  => "INT UNSIGNED NOT NULL DEFAULT 5 COMMENT '快速入庫預設 +5 件'",
            'quick_restock_10' => "INT UNSIGNED NOT NULL DEFAULT 10 COMMENT '快速入庫預設 +10 件'",
            'quick_restock_20' => "INT UNSIGNED NOT NULL DEFAULT 20 COMMENT '快速入庫預設 +20 件'"
        ];

        foreach ($columns as $col => $definition) {
            try {
                $pdo->exec("ALTER TABLE settings ADD COLUMN $col $definition");
                echo "✓ settings.$col 欄位新增完成\n";
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), 'Duplicate column') !== false) {
                    echo "✓ $col 欄位已存在\n";
                } else {
                    throw $e;
                }
            }
        }
    },

    'down' => function(PDO $pdo) {
        foreach (['quick_restock_5', 'quick_restock_10', 'quick_restock_20'] as $col) {
            try {
                $pdo->exec("ALTER TABLE settings DROP COLUMN $col");
                echo "✓ settings.$col 欄位已移除\n";
            } catch (PDOException $e) {}
        }
    }
];
