<?php
/**
 * SalonEase - 員工管理 API
 * 路徑：/api/staff.php?action=...
 * 
 * 支援動作：
 * - list     (GET)  取得員工列表（可搜尋）
 * - get      (GET)  取得單一員工詳情
 * - create   (POST) 新增員工
 * - update   (POST) 更新員工資料
 * - toggle   (POST) 啟用/停用員工
 * - reset_pw (POST) 重設密碼
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../db.php';

require_login();

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {

    // 取得員工列表
    case 'list':
        $search = trim(get('search', ''));
        $role = get('role', '');
        $status = get('status', '');

        $sql = "SELECT id, name, phone, email, role, is_active, created_at 
                FROM staff 
                WHERE 1=1";
        $params = [];

        if ($search !== '') {
            $sql .= " AND (name LIKE ? OR email LIKE ? OR phone LIKE ?)";
            $like = "%{$search}%";
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }
        if ($role !== '') {
            $sql .= " AND role = ?";
            $params[] = $role;
        }
        if ($status !== '') {
            $sql .= " AND is_active = ?";
            $params[] = (int)$status;
        }

        $sql .= " ORDER BY is_active DESC, name ASC";

        $staffList = db_query($sql, $params);
        json_success($staffList);
        break;

    // 取得單一員工
    case 'get':
        $id = (int)get('id');
        if (!$id) json_error('缺少員工 ID');

        $staff = db_query_one(
            "SELECT id, name, phone, email, role, commission_rate_service, commission_rate_retail, commission_rate_open, is_active 
             FROM staff WHERE id = ?",
            [$id]
        );
        if (!$staff) json_error('找不到該員工', 404);

        json_success($staff);
        break;

    // 新增員工
    case 'create':
        if (!is_post()) json_error('只接受 POST 請求', 405);
        require_csrf();

        $name = trim(post('name'));
        $email = trim(post('email'));
        $phone = trim(post('phone'));
        $role = post('role', 'therapist');
        $password = post('password');

        if (!$name || !$email || !$password) {
            json_error('姓名、電郵及密碼為必填');
        }

        // 檢查電郵是否已存在
        $exists = db_query_one("SELECT id FROM staff WHERE email = ?", [$email]);
        if ($exists) {
            json_error('此電郵地址已被使用');
        }

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        db_exec(
            "INSERT INTO staff (name, phone, email, role, password_hash, is_active) 
             VALUES (?, ?, ?, ?, ?, 1)",
            [$name, $phone, $email, $role, $passwordHash]
        );

        $newId = db_last_id();
        json_success(['id' => (int)$newId], '員工新增成功');
        break;

    // 更新員工資料
    case 'update':
        if (!is_post()) json_error('只接受 POST 請求', 405);
        require_csrf();

        $id = (int)post('id');
        $name = trim(post('name'));
        $phone = trim(post('phone'));
        $role = post('role');

        if (!$id || !$name) {
            json_error('缺少必要資料');
        }

        // 取得目前角色，用於權限檢查
        $current = db_query_one("SELECT role FROM staff WHERE id = ?", [$id]);
        if (!$current) json_error('找不到該員工');

        // 只有 admin 可以改其他 admin 的角色
        $currentUser = get_logged_in_user();
        if (!$currentUser) {
            json_error('請先登入', 401);
        }
        if ($current['role'] === 'admin' && $role !== 'admin' && $currentUser['role'] !== 'admin') {
            json_error('無法更改其他管理員的角色');
        }

        db_exec(
            "UPDATE staff SET name = ?, phone = ?, role = ? WHERE id = ?",
            [$name, $phone, $role, $id]
        );

        json_success(null, '員工資料已更新');
        break;

    // 啟用 / 停用
    case 'toggle':
        if (!is_post()) json_error('只接受 POST 請求', 405);
        require_csrf();

        $id = (int)post('id');
        $newStatus = (int)post('status'); // 1 = 啟用, 0 = 停用

        $currentUser = get_logged_in_user();
        if (!$currentUser) {
            json_error('請先登入', 401);
        }

        if ($id === $currentUser['id']) {
            json_error('不能停用自己的帳號');
        }

        db_exec("UPDATE staff SET is_active = ? WHERE id = ?", [$newStatus, $id]);
        json_success(null, $newStatus ? '員工已啟用' : '員工已停用');
        break;

    // 重設密碼
    case 'reset_pw':
        if (!is_post()) json_error('只接受 POST 請求', 405);
        require_csrf();

        $id = (int)post('id');
        $newPassword = post('password');

        if (!$id || !$newPassword || strlen($newPassword) < 6) {
            json_error('密碼至少需要 6 個字元');
        }

        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        db_exec("UPDATE staff SET password_hash = ? WHERE id = ?", [$hash, $id]);

        json_success(null, '密碼已重設');
        break;

    default:
        json_error('未知的操作', 400);
}
