<?php
/**
 * SalonEase - 套票管理 API
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../db.php';

require_login();

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {

    case 'list':
        $status = get('status', '');

        $sql = "SELECT id, name, total_sessions, price, validity_days, is_active FROM packages WHERE 1=1";
        $params = [];

        if ($status !== '') {
            $sql .= " AND is_active = ?";
            $params[] = (int)$status;
        }

        $sql .= " ORDER BY is_active DESC, name ASC";

        $packages = db_query($sql, $params);
        json_success($packages);
        break;

    case 'create':
        if (!is_post()) json_error('只接受 POST 請求', 405);

        $name = trim(post('name'));
        $sessions = (int)post('total_sessions');
        $price = (float)post('price');
        $validity = (int)post('validity_days', 365);

        if (!$name || $sessions <= 0 || $price <= 0) {
            json_error('套票名稱、總次數與價格為必填');
        }

        db_exec(
            "INSERT INTO packages (name, total_sessions, price, validity_days, is_active) VALUES (?, ?, ?, ?, 1)",
            [$name, $sessions, $price, $validity]
        );

        json_success(['id' => (int)db_last_id()], '套票新增成功');
        break;

    case 'update':
        if (!is_post()) json_error('只接受 POST 請求', 405);

        $id = (int)post('id');
        $name = trim(post('name'));
        $sessions = (int)post('total_sessions');
        $price = (float)post('price');
        $validity = (int)post('validity_days', 365);

        if (!$id || !$name) {
            json_error('缺少必要資料');
        }

        db_exec(
            "UPDATE packages SET name = ?, total_sessions = ?, price = ?, validity_days = ? WHERE id = ?",
            [$name, $sessions, $price, $validity, $id]
        );

        json_success(null, '套票已更新');
        break;

    case 'toggle':
        if (!is_post()) json_error('只接受 POST 請求', 405);

        $id = (int)post('id');
        $newStatus = (int)post('status');

        db_exec("UPDATE packages SET is_active = ? WHERE id = ?", [$newStatus, $id]);
        json_success(null, $newStatus ? '套票已啟用' : '套票已停用');
        break;

    // 取得客戶持有的有效套票（給 POS 使用）
    case 'customer_packages':
        $customer_id = (int)get('customer_id');
        if (!$customer_id) {
            json_error('缺少客戶 ID');
        }

        $sql = "
            SELECT 
                cp.id,
                cp.remaining_sessions,
                cp.expiry_date,
                p.name,
                p.total_sessions
            FROM customer_packages cp
            JOIN packages p ON cp.package_id = p.id
            WHERE cp.customer_id = ?
              AND cp.remaining_sessions > 0
              AND cp.expiry_date >= CURDATE()
            ORDER BY cp.expiry_date ASC
        ";

        $packages = db_query($sql, [$customer_id]);
        json_success($packages);
        break;

    default:
        json_error('未知的操作', 400);
}
