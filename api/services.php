<?php
/**
 * SalonEase - 服務項目管理 API
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../db.php';

require_login();

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {

    case 'list':
        $search = trim(get('search', ''));
        $category = get('category', '');
        $status = get('status', '');

        $sql = "SELECT id, name, duration_min, price, category, is_active FROM services WHERE 1=1";
        $params = [];

        if ($search !== '') {
            $sql .= " AND name LIKE ?";
            $params[] = "%{$search}%";
        }
        if ($category !== '') {
            $sql .= " AND category = ?";
            $params[] = $category;
        }
        if ($status !== '') {
            $sql .= " AND is_active = ?";
            $params[] = (int)$status;
        }

        $sql .= " ORDER BY is_active DESC, name ASC";

        $services = db_query($sql, $params);
        json_success($services);
        break;

    case 'create':
        if (!is_post()) json_error('只接受 POST 請求', 405);

        $name = trim(post('name'));
        $duration = (int)post('duration_min', 60);
        $price = (float)post('price');
        $category = trim(post('category'));

        if (!$name || $price <= 0) {
            json_error('服務名稱與價格為必填');
        }

        db_exec(
            "INSERT INTO services (name, duration_min, price, category, is_active) VALUES (?, ?, ?, ?, 1)",
            [$name, $duration, $price, $category]
        );

        json_success(['id' => (int)db_last_id()], '服務項目新增成功');
        break;

    case 'update':
        if (!is_post()) json_error('只接受 POST 請求', 405);

        $id = (int)post('id');
        $name = trim(post('name'));
        $duration = (int)post('duration_min', 60);
        $price = (float)post('price');
        $category = trim(post('category'));

        if (!$id || !$name) {
            json_error('缺少必要資料');
        }

        db_exec(
            "UPDATE services SET name = ?, duration_min = ?, price = ?, category = ? WHERE id = ?",
            [$name, $duration, $price, $category, $id]
        );

        json_success(null, '服務項目已更新');
        break;

    case 'toggle':
        if (!is_post()) json_error('只接受 POST 請求', 405);

        $id = (int)post('id');
        $newStatus = (int)post('status');

        db_exec("UPDATE services SET is_active = ? WHERE id = ?", [$newStatus, $id]);
        json_success(null, $newStatus ? '服務已啟用' : '服務已停用');
        break;

    default:
        json_error('未知的操作', 400);
}
