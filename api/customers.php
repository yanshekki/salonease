<?php
/**
 * SalonEase - 客戶管理 API
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

        $sql = "SELECT id, name, phone, email, gender, birthday, notes, total_spent, visit_count, last_visit_at 
                FROM customers 
                WHERE 1=1";
        $params = [];

        if ($search !== '') {
            $sql .= " AND (name LIKE ? OR phone LIKE ? OR email LIKE ?)";
            $like = "%{$search}%";
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $sql .= " ORDER BY last_visit_at DESC, name ASC LIMIT 100";

        $customers = db_query($sql, $params);
        json_success($customers);
        break;

    case 'get':
        $id = (int)get('id');
        if (!$id) json_error('缺少客戶 ID');

        $customer = db_query_one(
            "SELECT * FROM customers WHERE id = ?",
            [$id]
        );
        if (!$customer) json_error('找不到該客戶', 404);

        json_success($customer);
        break;

    case 'create':
        if (!is_post()) json_error('只接受 POST 請求', 405);
        require_csrf();

        $name = sanitize_string(post('name', ''));
        $phone = trim(post('phone', ''));
        $email = trim(post('email', ''));
        $gender = post('gender', '');
        $birthday = post('birthday', '');
        $notes = sanitize_string(post('notes', ''));

        if ($err = validate_required($name, '姓名')) json_error($err);
        if ($err = validate_required($phone, '電話')) json_error($err);
        if ($err = validate_length($name, '姓名', 50, 1)) json_error($err);
        if ($err = validate_hk_phone($phone)) json_error($err);
        if ($err = validate_email($email)) json_error($err);
        if ($err = validate_length($notes, '備註', 500)) json_error($err);

        // 檢查電話是否已存在
        $exists = db_query_one("SELECT id FROM customers WHERE phone = ?", [$phone]);
        if ($exists) {
            json_error('此電話號碼已被使用');
        }

        db_exec(
            "INSERT INTO customers (name, phone, email, gender, birthday, notes, created_by) 
             VALUES (?, ?, ?, ?, ?, ?, ?)",
            [$name, $phone, $email, $gender, $birthday ?: null, $notes, $_SESSION['staff_id'] ?? null]
        );

        $newId = db_last_id();
        log_activity('customer.created', $newId, 'customer', [
            'name' => $name,
            'phone' => $phone
        ]);

        json_success(['id' => (int)$newId], '客戶新增成功');
        break;

    case 'update':
        if (!is_post()) json_error('只接受 POST 請求', 405);
        require_csrf();

        $id = (int)post('id');
        $name = sanitize_string(post('name', ''));
        $phone = trim(post('phone', ''));
        $email = trim(post('email', ''));
        $gender = post('gender', '');
        $birthday = post('birthday', '');
        $notes = sanitize_string(post('notes', ''));

        if ($err = validate_required($id, '客戶 ID')) json_error($err);
        if ($err = validate_required($name, '姓名')) json_error($err);
        if ($err = validate_required($phone, '電話')) json_error($err);
        if ($err = validate_length($name, '姓名', 50, 1)) json_error($err);
        if ($err = validate_hk_phone($phone)) json_error($err);
        if ($err = validate_email($email)) json_error($err);
        if ($err = validate_length($notes, '備註', 500)) json_error($err);

        // 檢查電話是否被其他客戶使用
        $exists = db_query_one("SELECT id FROM customers WHERE phone = ? AND id != ?", [$phone, $id]);
        if ($exists) {
            json_error('此電話號碼已被其他客戶使用');
        }

        db_exec(
            "UPDATE customers SET name = ?, phone = ?, email = ?, gender = ?, birthday = ?, notes = ? 
             WHERE id = ?",
            [$name, $phone, $email, $gender, $birthday ?: null, $notes, $id]
        );

        log_activity('customer.updated', $id, 'customer', [
            'name' => $name,
            'phone' => $phone
        ]);

        json_success(null, '客戶資料已更新');
        break;

    default:
        json_error('未知的操作', 400);
}
