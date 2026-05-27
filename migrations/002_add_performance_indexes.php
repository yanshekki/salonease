<?php
/**
 * Migration 002 - 效能優化索引（範例）
 * 
 * 這是一個展示「日後更新」機制的安全 migration。
 * 實際上線後可刪除或保留作為模板。
 * 
 * 所有操作皆為冪等（重複執行安全）。
 */
return [
    'description' => '為常用查詢欄位新增複合索引，提升報表與 POS 速度（不影響現有數據）',
    
    'up' => function(PDO $pdo) {
        // 範例 1: 為 sales 表加強日期 + 客戶複合查詢
        try {
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_sales_date_customer ON sales (sale_date, customer_id)");
        } catch (PDOException $e) {
            // 舊版 MySQL 不支援 IF NOT EXISTS 時的相容處理
            if (stripos($e->getMessage(), 'duplicate') === false) {
                // 非重複錯誤才拋出
                throw $e;
            }
        }

        // 範例 2: appointments 多欄位查詢優化
        try {
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_appt_status_time ON appointments (status, start_time)");
        } catch (PDOException $e) {
            if (stripos($e->getMessage(), 'duplicate') === false) throw $e;
        }

        // 範例 3: 為 commissions 加速員工報表
        try {
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_comm_staff_calculated ON commissions (staff_id, calculated_at)");
        } catch (PDOException $e) {
            if (stripos($e->getMessage(), 'duplicate') === false) throw $e;
        }

        // 範例 4: 新增一個實用欄位（假設日後需要追蹤「是否已發送低庫存電郵」）
        try {
            $pdo->exec("ALTER TABLE products ADD COLUMN low_stock_alert_sent TINYINT(1) NOT NULL DEFAULT 0 COMMENT '低庫存警示是否已通知' AFTER low_stock_threshold");
        } catch (PDOException $e) {
            // 欄位已存在就忽略
            if (stripos($e->getMessage(), 'duplicate') === false && stripos($e->getMessage(), 'exists') === false) {
                throw $e;
            }
        }

        echo "✓ 效能索引與輔助欄位更新完成（冪等執行）\n";
    }
];
