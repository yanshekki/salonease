<?php
/**
 * Migration 003 - 常用購物車組合（Cart Templates）
 * 
 * 讓員工可以儲存常用療程組合，方便快速重複開單。
 * 這是提升 POS 開單速度的重要功能。
 */

return [
    'description' => '新增 cart_templates 表，支援常用購物車組合儲存與快速載入',

    'up' => function(PDO $pdo) {
        $sql = "
            CREATE TABLE IF NOT EXISTS `cart_templates` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `staff_id` INT UNSIGNED NOT NULL COMMENT '建立此模板的員工',
                `name` VARCHAR(100) NOT NULL COMMENT '模板名稱，例如「經典面部護理套餐」',
                `items` JSON NOT NULL COMMENT '購物車項目快照（type, ref_id, name, unit_price, qty）',
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                INDEX `idx_ct_staff` (`staff_id`),
                INDEX `idx_ct_name` (`name`),
                INDEX `idx_ct_active` (`is_active`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='常用購物車組合模板';
        ";

        $pdo->exec($sql);

        echo "✓ cart_templates 表建立完成\n";
    },

    'down' => function(PDO $pdo) {
        $pdo->exec("DROP TABLE IF EXISTS `cart_templates`");
        echo "✓ cart_templates 表已刪除\n";
    }
];