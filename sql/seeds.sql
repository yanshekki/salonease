-- =====================================================
-- SalonEase 測試資料種子檔（開發 / Demo 用）
-- 執行前請先執行 schema.sql
-- 預設密碼：admin123 / staff123 （上線後務必修改）
-- =====================================================

SET NAMES utf8mb4;

-- 1. 預設管理員（admin）
-- 密碼：admin123
INSERT INTO `staff` (`name`, `phone`, `email`, `role`, `password_hash`, `is_active`) VALUES
('系統管理員', '9123 4567', 'admin@salonease.hk', 'admin', 
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1);

-- 2. 美容師（therapist）
-- 密碼：staff123
INSERT INTO `staff` (`name`, `phone`, `email`, `role`, `password_hash`, `commission_rate_service`, `is_active`) VALUES
('陳美玲', '9234 5678', 'chan.meiling@salonease.hk', 'therapist', 
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 45.00, 1),
('李嘉欣', '9345 6789', 'lee.kayan@salonease.hk', 'therapist', 
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 40.00, 1);

-- 3. 前台（reception）
INSERT INTO `staff` (`name`, `phone`, `email`, `role`, `password_hash`, `commission_rate_open`, `is_active`) VALUES
('王小明', '9456 7890', 'wong.siuming@salonease.hk', 'reception', 
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 8.00, 1);

-- 4. 房間
INSERT INTO `rooms` (`name`, `capacity`, `is_active`) VALUES
('1 號房', 1, 1),
('2 號房', 1, 1),
('VIP 房', 2, 1);

-- 5. 服務項目
INSERT INTO `services` (`name`, `duration_min`, `price`, `category`, `is_active`) VALUES
('經典面部護理 60 分鐘', 60, 680.00, '面部護理', 1),
('深層清潔面部護理 90 分鐘', 90, 980.00, '面部護理', 1),
('全身芳香按摩 75 分鐘', 75, 850.00, '身體護理', 1),
('肩頸舒緩按摩 30 分鐘', 30, 380.00, '身體護理', 1),
('水光針療程（單次）', 45, 1280.00, '醫美', 1),
('眼部抗老護理 45 分鐘', 45, 520.00, '面部護理', 1);

-- 6. 零售產品
INSERT INTO `products` (`name`, `sku`, `price`, `cost`, `stock_qty`, `category`, `is_active`) VALUES
('La Mer 修復面霜 60ml', 'LM-60', 1850.00, 920.00, 8, '護膚品', 1),
('Sisley 玫瑰面膜 50ml', 'SS-50', 980.00, 480.00, 12, '護膚品', 1),
('Dermal 保濕精華 30ml', 'DM-30', 380.00, 160.00, 25, '護膚品', 1),
('天然海鹽磨砂膏 200g', 'NS-200', 168.00, 65.00, 30, '身體護理', 1);

-- 7. 套票模板
INSERT INTO `packages` (`name`, `total_sessions`, `price`, `validity_days`, `description`, `is_active`) VALUES
('經典面部護理 10 次卡', 10, 5800.00, 365, '買 10 次經典面部護理，享 8 折優惠，有效期一年', 1),
('全身芳香按摩 5 次卡', 5, 3800.00, 180, '買 5 次全身芳香按摩，享 9 折，有效期半年', 1),
('水光針療程 6 次套票', 6, 6800.00, 365, '醫美水光針療程 6 次套票，附送眼部護理一次', 1);

-- 8. 測試客戶
INSERT INTO `customers` (`name`, `phone`, `email`, `gender`, `birthday`, `notes`, `created_by`) VALUES
('黃美華', '9123 4567', 'wong.may@sample.com', 'F', '1985-03-12', '對玫瑰精油過敏', 1),
('張小瑜', '9234 5678', NULL, 'F', '1992-07-25', '偏好下午時段', 1),
('陳志強', '9345 6789', 'chan.chi@sample.com', 'M', '1978-11-05', '第一次來店，介紹眼部護理', 2);

-- 9. 給第一位客戶買一套票（模擬）
-- 注意：實際購買應透過 POS 交易產生，此處僅供測試
INSERT INTO `customer_packages` (`customer_id`, `package_id`, `purchase_date`, `expiry_date`, `total_sessions`, `remaining_sessions`, `notes`) VALUES
(1, 1, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 365 DAY), 10, 10, '測試資料 - 經典面部護理 10 次卡');

-- 完成提示
SELECT 'SalonEase 種子資料插入完成！' AS message;
SELECT '預設登入帳號：admin@salonease.hk / admin123' AS login_info;
