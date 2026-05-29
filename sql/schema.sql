-- =====================================================
-- SalonEase 香港小型美容院管理系統 - 完整資料庫結構
-- 版本：0.1.0 (Phase 0)
-- 字元集：utf8mb4_unicode_ci（完整支援繁體中文 + 香港用語）
-- 引擎：InnoDB（支援交易）
-- =====================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- =====================================================
-- 1. settings - 美容院基本設定（單一資料列）
-- =====================================================
DROP TABLE IF EXISTS `settings`;
CREATE TABLE `settings` (
  `id` TINYINT NOT NULL DEFAULT 1,
  `salon_name` VARCHAR(100) NOT NULL DEFAULT 'SalonEase 美容中心',
  `address` VARCHAR(255) DEFAULT NULL,
  `phone` VARCHAR(20) DEFAULT NULL,
  `email` VARCHAR(100) DEFAULT NULL,
  `logo_path` VARCHAR(255) DEFAULT NULL,
  `printer_width` ENUM('58','80') NOT NULL DEFAULT '58',
  `default_commission_service` DECIMAL(5,2) NOT NULL DEFAULT 40.00 COMMENT '服務佣金預設百分比',
  `default_commission_retail` DECIMAL(5,2) NOT NULL DEFAULT 15.00 COMMENT '零售佣金預設百分比',
  `default_commission_open` DECIMAL(5,2) NOT NULL DEFAULT 5.00 COMMENT '開單佣金預設百分比',
  `default_low_stock_threshold` INT NOT NULL DEFAULT 5 COMMENT '低庫存警示預設門檻',
  `needs_attention_days_threshold` INT NOT NULL DEFAULT 45 COMMENT '付款計劃需要關注：建立超過 N 天 + 進度低',
  `needs_attention_progress_threshold` INT NOT NULL DEFAULT 30 COMMENT '付款計劃需要關注：已付期數低於 N% 視為進度低',
  `business_hours` JSON DEFAULT NULL COMMENT '營業時間 JSON',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='美容院全域設定';

-- 插入預設設定
INSERT INTO `settings` (`id`, `salon_name`, `address`, `phone`, `printer_width`, `default_low_stock_threshold`, `needs_attention_days_threshold`, `needs_attention_progress_threshold`) VALUES
(1, 'SalonEase 美容中心', '香港九龍尖沙咀彌敦道 100 號 8 樓', '2123 4567', '58', 5, 45, 30);

-- =====================================================
-- 2. staff - 員工帳號與佣金設定
-- =====================================================
DROP TABLE IF EXISTS `staff`;
CREATE TABLE `staff` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(80) NOT NULL COMMENT '員工姓名',
  `phone` VARCHAR(20) DEFAULT NULL,
  `email` VARCHAR(100) DEFAULT NULL,
  `role` ENUM('admin','manager','therapist','reception') NOT NULL DEFAULT 'therapist' COMMENT '角色',
  `password_hash` VARCHAR(255) NOT NULL,
  `commission_rate_service` DECIMAL(5,2) DEFAULT NULL COMMENT '個人服務佣金率（NULL 則用全域預設）',
  `commission_rate_retail` DECIMAL(5,2) DEFAULT NULL,
  `commission_rate_open` DECIMAL(5,2) DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_staff_email` (`email`),
  INDEX `idx_staff_active` (`is_active`),
  INDEX `idx_staff_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='員工帳號';

-- =====================================================
-- 3. customers - 客戶主檔
-- =====================================================
DROP TABLE IF EXISTS `customers`;
CREATE TABLE `customers` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(80) NOT NULL,
  `phone` VARCHAR(20) NOT NULL,
  `email` VARCHAR(100) DEFAULT NULL,
  `gender` ENUM('M','F','O') DEFAULT NULL,
  `birthday` DATE DEFAULT NULL,
  `notes` TEXT,
  `total_spent` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `visit_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `first_visit_at` TIMESTAMP NULL DEFAULT NULL,
  `last_visit_at` TIMESTAMP NULL DEFAULT NULL,
  `created_by` INT UNSIGNED DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_customers_phone` (`phone`),
  INDEX `idx_customers_name` (`name`),
  INDEX `idx_customers_last_visit` (`last_visit_at`),
  INDEX `idx_customers_phone_search` (`phone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='客戶資料';

-- =====================================================
-- 4. rooms - 房間（容量限制）
-- =====================================================
DROP TABLE IF EXISTS `rooms`;
CREATE TABLE `rooms` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(50) NOT NULL COMMENT '房間名稱（如 1 號房、VIP 房）',
  `capacity` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_rooms_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='房間';

-- =====================================================
-- 5. services - 服務項目（療程）
-- =====================================================
DROP TABLE IF EXISTS `services`;
CREATE TABLE `services` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL COMMENT '服務名稱',
  `duration_min` SMALLINT UNSIGNED NOT NULL DEFAULT 60,
  `price` DECIMAL(8,2) NOT NULL,
  `category` VARCHAR(50) DEFAULT NULL COMMENT '面部護理 / 身體護理 / 醫美等',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_services_active` (`is_active`),
  INDEX `idx_services_category` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='服務項目';

-- =====================================================
-- 6. products - 零售產品
-- =====================================================
DROP TABLE IF EXISTS `products`;
CREATE TABLE `products` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `sku` VARCHAR(30) DEFAULT NULL,
  `price` DECIMAL(8,2) NOT NULL,
  `cost` DECIMAL(8,2) DEFAULT NULL COMMENT '成本價（佣金計算參考）',
  `stock_qty` INT NOT NULL DEFAULT 0,
  `low_stock_threshold` INT DEFAULT NULL COMMENT '低庫存警示門檻（NULL 則使用全域預設）',
  `category` VARCHAR(50) DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_products_active` (`is_active`),
  INDEX `idx_products_sku` (`sku`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='零售產品';

-- =====================================================
-- 7. packages - 套票定義（療程卡模板）
-- =====================================================
DROP TABLE IF EXISTS `packages`;
CREATE TABLE `packages` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL COMMENT '套票名稱（如「經典面部護理 10 次」）',
  `total_sessions` TINYINT UNSIGNED NOT NULL COMMENT '總次數',
  `price` DECIMAL(8,2) NOT NULL COMMENT '售價',
  `validity_days` SMALLINT UNSIGNED NOT NULL DEFAULT 365,
  `description` TEXT,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_packages_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='套票模板';

-- =====================================================
-- 8. customer_packages - 客戶持有套票（剩餘次數追蹤）
-- =====================================================
DROP TABLE IF EXISTS `customer_packages`;
CREATE TABLE `customer_packages` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `customer_id` INT UNSIGNED NOT NULL,
  `package_id` INT UNSIGNED NOT NULL,
  `purchase_date` DATE NOT NULL,
  `expiry_date` DATE NOT NULL,
  `total_sessions` TINYINT UNSIGNED NOT NULL,
  `remaining_sessions` TINYINT UNSIGNED NOT NULL,
  `sale_id` INT UNSIGNED DEFAULT NULL COMMENT '購買時的銷售單 ID',
  `notes` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`package_id`) REFERENCES `packages`(`id`),
  INDEX `idx_cp_customer` (`customer_id`),
  INDEX `idx_cp_expiry` (`expiry_date`),
  INDEX `idx_cp_remaining` (`remaining_sessions`),
  INDEX `idx_cp_sale` (`sale_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='客戶套票持有記錄';

-- =====================================================
-- 9. appointments - 預約主檔
-- =====================================================
DROP TABLE IF EXISTS `appointments`;
CREATE TABLE `appointments` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `customer_id` INT UNSIGNED NOT NULL,
  `staff_id` INT UNSIGNED NOT NULL COMMENT '負責美容師',
  `room_id` INT UNSIGNED DEFAULT NULL,
  `start_time` DATETIME NOT NULL,
  `end_time` DATETIME NOT NULL,
  `status` ENUM('pending','confirmed','completed','cancelled','no_show') NOT NULL DEFAULT 'pending',
  `notes` TEXT,
  `created_by` INT UNSIGNED DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`),
  FOREIGN KEY (`staff_id`) REFERENCES `staff`(`id`),
  FOREIGN KEY (`room_id`) REFERENCES `rooms`(`id`),
  INDEX `idx_appt_start` (`start_time`),
  INDEX `idx_appt_staff_time` (`staff_id`, `start_time`),
  INDEX `idx_appt_room_time` (`room_id`, `start_time`),
  INDEX `idx_appt_status` (`status`),
  INDEX `idx_appt_customer` (`customer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='預約';

-- =====================================================
-- 10. appointment_items - 預約包含的服務項目
-- =====================================================
DROP TABLE IF EXISTS `appointment_items`;
CREATE TABLE `appointment_items` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `appointment_id` INT UNSIGNED NOT NULL,
  `service_id` INT UNSIGNED NOT NULL,
  `staff_id` INT UNSIGNED DEFAULT NULL COMMENT '實際執行人員（可與預約負責人不同）',
  `price_at_time` DECIMAL(8,2) NOT NULL,
  `duration_min` SMALLINT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`appointment_id`) REFERENCES `appointments`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`service_id`) REFERENCES `services`(`id`),
  FOREIGN KEY (`staff_id`) REFERENCES `staff`(`id`),
  INDEX `idx_ai_appointment` (`appointment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='預約服務明細';

-- =====================================================
-- 11. sales - 銷售單（POS 結帳主檔）
-- =====================================================
DROP TABLE IF EXISTS `sales`;
CREATE TABLE `sales` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `customer_id` INT UNSIGNED DEFAULT NULL,
  `staff_id` INT UNSIGNED NOT NULL COMMENT '開單人',
  `appointment_id` INT UNSIGNED DEFAULT NULL,
  `sale_date` DATE NOT NULL,
  `subtotal` DECIMAL(10,2) NOT NULL,
  `discount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `total` DECIMAL(10,2) NOT NULL,
  `payment_method` ENUM('cash','fps','card','wechat','alipay','other') NOT NULL DEFAULT 'cash',
  `payment_ref` VARCHAR(50) DEFAULT NULL,
  `notes` TEXT,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`),
  FOREIGN KEY (`staff_id`) REFERENCES `staff`(`id`),
  FOREIGN KEY (`appointment_id`) REFERENCES `appointments`(`id`),
  INDEX `idx_sales_date` (`sale_date`),
  INDEX `idx_sales_customer` (`customer_id`),
  INDEX `idx_sales_staff` (`staff_id`),
  INDEX `idx_sales_appointment` (`appointment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='銷售單';

-- =====================================================
-- 12. sale_items - 銷售明細（服務 / 產品 / 套票使用）
-- =====================================================
DROP TABLE IF EXISTS `sale_items`;
CREATE TABLE `sale_items` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `sale_id` INT UNSIGNED NOT NULL,
  `item_type` ENUM('service','product','package_redemption') NOT NULL,
  `ref_id` INT UNSIGNED DEFAULT NULL COMMENT 'services.id / products.id / customer_packages.id',
  `name` VARCHAR(100) NOT NULL COMMENT '交易時的名稱快照',
  `qty` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `unit_price` DECIMAL(8,2) NOT NULL,
  `line_total` DECIMAL(10,2) NOT NULL,
  `staff_id` INT UNSIGNED DEFAULT NULL COMMENT '執行人員（用於佣金計算）',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`sale_id`) REFERENCES `sales`(`id`) ON DELETE CASCADE,
  INDEX `idx_si_sale` (`sale_id`),
  INDEX `idx_si_type` (`item_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='銷售明細';

-- =====================================================
-- 13. package_usages - 套票扣減記錄
-- =====================================================
DROP TABLE IF EXISTS `package_usages`;
CREATE TABLE `package_usages` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `customer_package_id` INT UNSIGNED NOT NULL,
  `sale_id` INT UNSIGNED NOT NULL,
  `appointment_id` INT UNSIGNED DEFAULT NULL,
  `sessions_used` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `used_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `staff_id` INT UNSIGNED DEFAULT NULL,
  `notes` VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`customer_package_id`) REFERENCES `customer_packages`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`sale_id`) REFERENCES `sales`(`id`),
  FOREIGN KEY (`appointment_id`) REFERENCES `appointments`(`id`),
  INDEX `idx_pu_customer_package` (`customer_package_id`),
  INDEX `idx_pu_sale` (`sale_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='套票使用記錄';

-- =====================================================
-- 14. commissions - 佣金快照（歷史記錄）
-- =====================================================
DROP TABLE IF EXISTS `commissions`;
CREATE TABLE `commissions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `sale_id` INT UNSIGNED NOT NULL,
  `staff_id` INT UNSIGNED NOT NULL,
  `amount` DECIMAL(8,2) NOT NULL,
  `type` ENUM('service','retail','open') NOT NULL,
  `rate` DECIMAL(5,2) NOT NULL COMMENT '當時使用的百分比',
  `calculated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`sale_id`) REFERENCES `sales`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`staff_id`) REFERENCES `staff`(`id`),
  INDEX `idx_comm_staff` (`staff_id`),
  INDEX `idx_comm_sale` (`sale_id`),
  INDEX `idx_comm_type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='佣金記錄（快照）';

-- =====================================================
-- 15. activity_logs - 簡單操作日誌（可選，MVP 先保留）
-- =====================================================
DROP TABLE IF EXISTS `activity_logs`;
CREATE TABLE `activity_logs` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `staff_id` INT UNSIGNED DEFAULT NULL,
  `action` VARCHAR(50) NOT NULL,
  `entity_type` VARCHAR(30) DEFAULT NULL,
  `entity_id` INT UNSIGNED DEFAULT NULL,
  `details` JSON DEFAULT NULL,
  `ip` VARCHAR(45) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_log_staff` (`staff_id`),
  INDEX `idx_log_action` (`action`),
  INDEX `idx_log_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='操作日誌';

-- =====================================================
-- 16. cart_templates - 常用購物車組合（POS 快速開單）
-- =====================================================
DROP TABLE IF EXISTS `cart_templates`;
CREATE TABLE `cart_templates` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='常用購物車組合模板（POS 快速重複開單）';

-- =====================================================
-- 17. payment_methods - 自訂付款方法 + 手續費規則（Phase 1 新增）
-- =====================================================
DROP TABLE IF EXISTS `payment_methods`;
CREATE TABLE `payment_methods` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(30) NOT NULL UNIQUE COMMENT '內部代碼：fps, payme, stripe_card, cash 等（程式用）',
  `name` VARCHAR(60) NOT NULL COMMENT '前台顯示名稱，例如「轉數快 (FPS)」',
  `fee_model` ENUM('none','fixed','percent','fixed_plus_percent') NOT NULL DEFAULT 'none' COMMENT '手續費計算方式',
  `fee_fixed` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '固定手續費金額（HKD）',
  `fee_percent` DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT '百分比費率（0.00-100.00）',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '是否啟用',
  `sort_order` SMALLINT UNSIGNED NOT NULL DEFAULT 100 COMMENT '顯示排序（數字越小越靠前）',
  `notes` TEXT COMMENT '備註說明（香港市場實際收費參考）',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_pm_active_sort` (`is_active`, `sort_order`),
  INDEX `idx_pm_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='自訂付款方法 + 手續費規則（支援一單多付與分期）';

-- 預設 8 種香港常用付款方法（與 migration 009 一致）
INSERT INTO `payment_methods` (`code`, `name`, `fee_model`, `fee_fixed`, `fee_percent`, `is_active`, `sort_order`, `notes`) VALUES
('cash', '現金', 'none', 0.00, 0.00, 1, 10, '最常用，無手續費。適合小額消費。'),
('fps', '轉數快 (FPS)', 'none', 0.00, 0.00, 1, 20, '香港銀行轉帳，個人及商戶多數免費或僅收極低固定費。'),
('payme', 'PayMe', 'percent', 0.00, 1.50, 1, 30, 'HSBC PayMe 商戶收款手續費約 1.2%~1.5%。'),
('card', '信用卡 / 八達通', 'fixed_plus_percent', 2.35, 3.40, 1, 40, 'Stripe 等國際收單：典型 3.4% + HK$2.35（視銀行而定）。'),
('alipay_hk', 'AlipayHK', 'percent', 0.00, 2.00, 1, 50, '支付寶香港商戶手續費約 1.5%~2.5%，視交易類型。'),
('wechat_hk', 'WeChat Pay HK', 'percent', 0.00, 2.00, 1, 60, '微信支付香港商戶手續費約 1.5%~2.5%。'),
('bank_transfer', '銀行轉帳', 'none', 0.00, 0.00, 1, 70, '傳統銀行電匯或 FPS 企業戶，費率多數由客戶承擔或雙方協商。'),
('other', '其他', 'none', 0.00, 0.00, 1, 999, '現金以外無法歸類的方式（例如支票、禮券等）。');

-- =====================================================
-- 18. payments - 多筆付款記錄（Phase 2 新增）
-- =====================================================
DROP TABLE IF EXISTS `payments`;
CREATE TABLE `payments` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `sale_id` INT UNSIGNED NOT NULL,
  `payment_method_id` INT UNSIGNED NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL COMMENT '客戶本次實際支付金額',
  `fee_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '商戶實際承擔的手續費',
  `fee_borne_by` ENUM('merchant','customer') NOT NULL DEFAULT 'merchant',
  `paid_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `staff_id` INT UNSIGNED NOT NULL,
  `ref_number` VARCHAR(120) DEFAULT NULL,
  `notes` TEXT,
  `is_refund` TINYINT(1) NOT NULL DEFAULT 0,
  `refund_of_payment_id` BIGINT UNSIGNED DEFAULT NULL,
  `installment_no` TINYINT UNSIGNED DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_payments_sale_time` (`sale_id`, `paid_at`),
  INDEX `idx_payments_method` (`payment_method_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='銷售單多筆付款記錄（Phase 2）';

-- 為 sales 表新增 Phase 2 欄位
ALTER TABLE `sales` 
  ADD COLUMN `amount_paid` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '已收款總額' AFTER `total`,
  ADD COLUMN `payment_status` ENUM('unpaid','partial','paid','refunded','overpaid') NOT NULL DEFAULT 'unpaid' COMMENT '付款狀態' AFTER `amount_paid`,
  ADD COLUMN `primary_payment_method_id` INT UNSIGNED DEFAULT NULL COMMENT '主要付款方式ID' AFTER `payment_status`;

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================
-- 完成提示
-- 執行本檔後，請繼續執行 seeds.sql 插入測試資料
-- =====================================================
