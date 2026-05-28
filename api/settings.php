<?php
/**
 * SalonEase - 系統設定 API
 * GET  /api/settings.php?action=get
 * POST /api/settings.php?action=save_shop
 *
 * 店舖資訊 + 打印預設 + 佣金預設比率
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../db.php';

require_login();

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {

    case 'get':
        // 讀取全域設定（單一資料列 id=1）
        $settings = db_query_one("SELECT * FROM settings WHERE id = 1");
        if (!$settings) {
            // 極端情況 fallback
            $settings = [
                'salon_name' => 'SalonEase 美容中心',
                'address' => '',
                'phone' => '',
                'printer_width' => '58',
                'default_commission_service' => 40.00,
                'default_commission_retail' => 15.00,
                'default_commission_open' => 5.00,
                'default_low_stock_threshold' => 5
            ];
        }
        json_success($settings);
        break;

    case 'save_shop':
        if (!is_post()) json_error('只接受 POST 請求', 405);

        // CSRF 保護 - Phase 1 已套用
        require_csrf();

        // 只有 admin / manager 可以改店舖資訊
        if (!in_array($_SESSION['staff_role'] ?? '', ['admin', 'manager'])) {
            json_error('只有管理員或店長可以修改店舖資訊', 403);
        }

        $salon_name = trim(post('salon_name', 'SalonEase 美容中心'));
        $address = trim(post('address'));
        $phone = trim(post('phone'));
        $printer_width = post('printer_width', '58');
        $printer_width = in_array($printer_width, ['58','80']) ? $printer_width : '58';

        // 佣金預設比率（0~100）
        $service_rate = max(0, min(100, (float)post('default_commission_service', 40)));
        $retail_rate  = max(0, min(100, (float)post('default_commission_retail', 15)));
        $open_rate    = max(0, min(100, (float)post('default_commission_open', 5)));
        $low_stock_threshold = max(0, (int)post('default_low_stock_threshold', 5));

        if (!$salon_name) {
            json_error('店舖名稱不能為空');
        }

        try {
            $stmt = db()->prepare("
                UPDATE settings 
                SET 
                    salon_name = ?,
                    address = ?,
                    phone = ?,
                    printer_width = ?,
                    default_commission_service = ?,
                    default_commission_retail = ?,
                    default_commission_open = ?,
                    default_low_stock_threshold = ?,
                    updated_at = NOW()
                WHERE id = 1
            ");
            $stmt->execute([
                $salon_name, $address, $phone, $printer_width,
                $service_rate, $retail_rate, $open_rate, $low_stock_threshold
            ]);

            if ($stmt->rowCount() === 0) {
                // 資料列不存在則插入
                $stmt = db()->prepare("
                    INSERT INTO settings 
                    (id, salon_name, address, phone, printer_width, 
                     default_commission_service, default_commission_retail, default_commission_open, default_low_stock_threshold)
                    VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE 
                        salon_name = VALUES(salon_name),
                        address = VALUES(address),
                        phone = VALUES(phone),
                        printer_width = VALUES(printer_width),
                        default_commission_service = VALUES(default_commission_service),
                        default_commission_retail = VALUES(default_commission_retail),
                        default_commission_open = VALUES(default_commission_open),
                        default_low_stock_threshold = VALUES(default_low_stock_threshold)
                ");
                $stmt->execute([
                    $salon_name, $address, $phone, $printer_width,
                    $service_rate, $retail_rate, $open_rate, $low_stock_threshold
                ]);
            }

            json_success(null, '設定已成功儲存');
        } catch (Exception $e) {
            json_error('儲存失敗：' . $e->getMessage());
        }
        break;

    default:
        json_error('未知的操作', 400);
}