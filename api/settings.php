<?php
/**
 * SalonEase - 系統設定 API
 * GET  /api/settings.php?action=get
 * POST /api/settings.php?action=save_shop
 *
 * 目前主要處理店舖基本資訊（收據用）
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
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
                'printer_width' => '58'
            ];
        }
        json_success($settings);
        break;

    case 'save_shop':
        if (!is_post()) json_error('只接受 POST 請求', 405);

        // 只有 admin / manager 可以改店舖資訊
        if (!in_array($_SESSION['staff_role'] ?? '', ['admin', 'manager'])) {
            json_error('只有管理員或店長可以修改店舖資訊', 403);
        }

        $salon_name = trim(post('salon_name', 'SalonEase 美容中心'));
        $address = trim(post('address'));
        $phone = trim(post('phone'));

        if (!$salon_name) {
            json_error('店舖名稱不能為空');
        }

        try {
            $stmt = db()->prepare("
                UPDATE settings 
                SET salon_name = ?, address = ?, phone = ?, updated_at = NOW()
                WHERE id = 1
            ");
            $stmt->execute([$salon_name, $address, $phone]);

            if ($stmt->rowCount() === 0) {
                // 萬一資料列不存在，就插入
                $stmt = db()->prepare("
                    INSERT INTO settings (id, salon_name, address, phone, printer_width) 
                    VALUES (1, ?, ?, ?, '58')
                    ON DUPLICATE KEY UPDATE 
                        salon_name = VALUES(salon_name),
                        address = VALUES(address),
                        phone = VALUES(phone)
                ");
                $stmt->execute([$salon_name, $address, $phone]);
            }

            json_success(null, '店舖資訊已更新');
        } catch (Exception $e) {
            json_error('儲存失敗：' . $e->getMessage());
        }
        break;

    default:
        json_error('未知的操作', 400);
}