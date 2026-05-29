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
                'default_low_stock_threshold' => 5,
                'needs_attention_days_threshold' => 45,
                'needs_attention_progress_threshold' => 30,
                'reminder_email_enabled' => 0,
                'reminder_from_email' => '',
                'reminder_sms_enabled' => 0,
                'twilio_account_sid' => '',
                'twilio_auth_token' => '',
                'twilio_from_number' => '',
                'points_earn_rate' => 10,
                'points_redemption_rate' => 10,
                'quick_restock_5' => 5,
                'quick_restock_10' => 10,
                'quick_restock_20' => 20
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

        // Phase 4 A：付款計劃需要關注門檻
        $needs_days = max(7, min(365, (int)post('needs_attention_days_threshold', 45)));
        $needs_progress = max(5, min(90, (int)post('needs_attention_progress_threshold', 30)));

        // Phase 5：提醒 Email 設定
        $reminder_email_enabled = (int)post('reminder_email_enabled', 0);
        $reminder_from = trim(post('reminder_from_email', ''));

        // Phase 5：Twilio SMS 設定
        $reminder_sms_enabled = (int)post('reminder_sms_enabled', 0);
        $twilio_sid   = trim(post('twilio_account_sid', ''));
        $twilio_token = trim(post('twilio_auth_token', ''));
        $twilio_from  = trim(post('twilio_from_number', ''));

        // 忠誠度積分率（A18）
        $points_earn_rate = max(1, min(100, (int)post('points_earn_rate', 10)));
        $points_redemption_rate = max(1, min(100, (int)post('points_redemption_rate', 10)));

        // 快速補貨預設數量（A38）
        $quick_restock_5  = max(1, min(100, (int)post('quick_restock_5', 5)));
        $quick_restock_10 = max(1, min(100, (int)post('quick_restock_10', 10)));
        $quick_restock_20 = max(1, min(100, (int)post('quick_restock_20', 20)));

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
                    needs_attention_days_threshold = ?,
                    needs_attention_progress_threshold = ?,
                    reminder_email_enabled = ?,
                    reminder_from_email = ?,
                    reminder_sms_enabled = ?,
                    twilio_account_sid = ?,
                    twilio_auth_token = ?,
                    twilio_from_number = ?,
                    points_earn_rate = ?,
                    points_redemption_rate = ?,
                    quick_restock_5 = ?,
                    quick_restock_10 = ?,
                    quick_restock_20 = ?,
                    updated_at = NOW()
                WHERE id = 1
            ");
            $stmt->execute([
                $salon_name, $address, $phone, $printer_width,
                $service_rate, $retail_rate, $open_rate, $low_stock_threshold,
                $needs_days, $needs_progress,
                $reminder_email_enabled, $reminder_from,
                $reminder_sms_enabled, $twilio_sid, $twilio_token, $twilio_from,
                $points_earn_rate, $points_redemption_rate,
                $quick_restock_5, $quick_restock_10, $quick_restock_20
            ]);

            if ($stmt->rowCount() === 0) {
                // 資料列不存在則插入
                $stmt = db()->prepare("
                    INSERT INTO settings 
                    (id, salon_name, address, phone, printer_width, 
                     default_commission_service, default_commission_retail, default_commission_open, default_low_stock_threshold,
                     needs_attention_days_threshold, needs_attention_progress_threshold,
                     reminder_email_enabled, reminder_from_email,
                     reminder_sms_enabled, twilio_account_sid, twilio_auth_token, twilio_from_number,
                     points_earn_rate, points_redemption_rate,
                     quick_restock_5, quick_restock_10, quick_restock_20)
                    VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE 
                        salon_name = VALUES(salon_name),
                        address = VALUES(address),
                        phone = VALUES(phone),
                        printer_width = VALUES(printer_width),
                        default_commission_service = VALUES(default_commission_service),
                        default_commission_retail = VALUES(default_commission_retail),
                        default_commission_open = VALUES(default_commission_open),
                        default_low_stock_threshold = VALUES(default_low_stock_threshold),
                        needs_attention_days_threshold = VALUES(needs_attention_days_threshold),
                        needs_attention_progress_threshold = VALUES(needs_attention_progress_threshold),
                        reminder_email_enabled = VALUES(reminder_email_enabled),
                        reminder_from_email = VALUES(reminder_from_email),
                        reminder_sms_enabled = VALUES(reminder_sms_enabled),
                        twilio_account_sid = VALUES(twilio_account_sid),
                        twilio_auth_token = VALUES(twilio_auth_token),
                        twilio_from_number = VALUES(twilio_from_number),
                        points_earn_rate = VALUES(points_earn_rate),
                        points_redemption_rate = VALUES(points_redemption_rate),
                        quick_restock_5 = VALUES(quick_restock_5),
                        quick_restock_10 = VALUES(quick_restock_10),
                        quick_restock_20 = VALUES(quick_restock_20)
                ");
                $stmt->execute([
                    $salon_name, $address, $phone, $printer_width,
                    $service_rate, $retail_rate, $open_rate, $low_stock_threshold,
                    $needs_days, $needs_progress,
                    $reminder_email_enabled, $reminder_from,
                    $reminder_sms_enabled, $twilio_sid, $twilio_token, $twilio_from,
                    $points_earn_rate, $points_redemption_rate,
                    $quick_restock_5, $quick_restock_10, $quick_restock_20
                ]);
            }

            log_activity('settings.updated', 1, 'settings', [
                'salon_name' => $salon_name,
                'updated_fields' => 'shop_info + commission_defaults + loyalty_rates + quick_restock_defaults + payment_plan_attention_thresholds'
            ]);

            json_success(null, '設定已成功儲存');
        } catch (Exception $e) {
            json_error('儲存失敗：' . $e->getMessage());
        }
        break;

    case 'test_sms':
        if (!is_post()) json_error('只接受 POST 請求', 405);
        require_csrf();

        if (!in_array($_SESSION['staff_role'] ?? '', ['admin', 'manager'])) {
            json_error('只有管理員或店長可以測試 SMS', 403);
        }

        $testPhone = trim(post('test_phone'));
        if (empty($testPhone)) {
            json_error('請輸入測試手機號碼');
        }

        $testMessage = "【SalonEase 測試】這是一則 SMS 測試訊息。時間：" . date('Y-m-d H:i:s');

        $success = send_sms($testPhone, $testMessage);

        if ($success) {
            json_success(null, '測試 SMS 已發送（請檢查手機）');
        } else {
            json_error('SMS 發送失敗，請檢查 Twilio 設定或錯誤日誌');
        }
        break;

    case 'reminder_stats':
        if (!in_array($_SESSION['staff_role'] ?? '', ['admin', 'manager'])) {
            json_error('權限不足', 403);
        }

        $stats = [
            'last_7_days' => [
                'email_sent' => 0,
                'email_failed' => 0,
                'sms_sent' => 0,
                'sms_failed' => 0,
                'total' => 0
            ],
            'last_30_days' => [
                'email_sent' => 0,
                'email_failed' => 0,
                'sms_sent' => 0,
                'sms_failed' => 0,
                'total' => 0
            ]
        ];

        // Last 7 days
        $rows7 = db_query("
            SELECT channel, status, COUNT(*) as cnt
            FROM plan_notifications
            WHERE sent_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY channel, status
        ");
        foreach ($rows7 as $row) {
            $ch = $row['channel'];
            $st = $row['status'];
            $key = "{$ch}_{$st}";
            if (isset($stats['last_7_days'][$key])) {
                $stats['last_7_days'][$key] = (int)$row['cnt'];
            }
        }
        $stats['last_7_days']['total'] = 
            $stats['last_7_days']['email_sent'] + $stats['last_7_days']['email_failed'] +
            $stats['last_7_days']['sms_sent'] + $stats['last_7_days']['sms_failed'];

        // Last 30 days
        $rows30 = db_query("
            SELECT channel, status, COUNT(*) as cnt
            FROM plan_notifications
            WHERE sent_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY channel, status
        ");
        foreach ($rows30 as $row) {
            $ch = $row['channel'];
            $st = $row['status'];
            $key = "{$ch}_{$st}";
            if (isset($stats['last_30_days'][$key])) {
                $stats['last_30_days'][$key] = (int)$row['cnt'];
            }
        }
        $stats['last_30_days']['total'] = 
            $stats['last_30_days']['email_sent'] + $stats['last_30_days']['email_failed'] +
            $stats['last_30_days']['sms_sent'] + $stats['last_30_days']['sms_failed'];

        json_success($stats);
        break;

    default:
        json_error('未知的操作', 400);
}