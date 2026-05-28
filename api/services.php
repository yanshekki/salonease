<?php
/**
 * SalonEase - 服務項目管理 API
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';
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
        require_csrf();

        $name = sanitize_string(post('name', ''));
        $duration = (int)post('duration_min', 60);
        $price = (float)post('price', 0);
        $category = sanitize_string(post('category', ''));

        if ($err = validate_required($name, '服務名稱')) json_error($err);
        if ($err = validate_money($price, '價格')) json_error($err);
        if ($err = validate_positive_int($duration, '療程時間', 5)) json_error($err);
        if ($err = validate_length($name, '服務名稱', 100, 1)) json_error($err);

        db_exec(
            "INSERT INTO services (name, duration_min, price, category, is_active) VALUES (?, ?, ?, ?, 1)",
            [$name, $duration, $price, $category]
        );

        $newId = db_last_id();
        log_activity('service.created', $newId, 'service', [
            'name' => $name,
            'price' => $price,
            'duration_min' => $duration
        ]);

        json_success(['id' => (int)$newId], '服務項目新增成功');
        break;

    case 'update':
        if (!is_post()) json_error('只接受 POST 請求', 405);
        require_csrf();

        $id = (int)post('id');
        $name = sanitize_string(post('name', ''));
        $duration = (int)post('duration_min', 60);
        $price = (float)post('price', 0);
        $category = sanitize_string(post('category', ''));

        if ($err = validate_required($id, '服務 ID')) json_error($err);
        if ($err = validate_required($name, '服務名稱')) json_error($err);
        if ($err = validate_money($price, '價格')) json_error($err);
        if ($err = validate_positive_int($duration, '療程時間', 5)) json_error($err);
        if ($err = validate_length($name, '服務名稱', 100, 1)) json_error($err);

        db_exec(
            "UPDATE services SET name = ?, duration_min = ?, price = ?, category = ? WHERE id = ?",
            [$name, $duration, $price, $category, $id]
        );

        log_activity('service.updated', $id, 'service', [
            'name' => $name,
            'price' => $price
        ]);

        json_success(null, '服務項目已更新');
        break;

    case 'toggle':
        if (!is_post()) json_error('只接受 POST 請求', 405);
        require_csrf();

        $id = (int)post('id');
        $newStatus = (int)post('status');

        db_exec("UPDATE services SET is_active = ? WHERE id = ?", [$newStatus, $id]);
        json_success(null, $newStatus ? '服務已啟用' : '服務已停用');
        break;

    default:
        json_error('未知的操作', 400);
}
