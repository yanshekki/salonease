<?php
/**
 * SalonEase - 認證 API
 * POST /api/auth.php?action=login
 * POST /api/auth.php?action=logout
 * GET  /api/auth.php?action=me
 * GET  /api/auth.php?action=ping
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'login':
        if (!is_post()) json_error('只接受 POST 請求', 405);

        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (!$email || !$password) {
            json_error('請輸入帳號與密碼');
        }

        $result = attempt_login($email, $password);
        if ($result['success']) {
            json_success(['user' => [
                'id' => $_SESSION['staff_id'],
                'name' => $_SESSION['staff_name'],
                'role' => $_SESSION['staff_role'],
            ]], '登入成功');
        } else {
            json_error($result['message']);
        }
        break;

    case 'logout':
        logout();
        json_success(null, '已登出');
        break;

    case 'me':
        require_login();
        $user = get_logged_in_user();
        json_success($user);
        break;

    case 'ping':
        // 前端用來保持 session 活躍
        if (!empty($_SESSION['staff_id'])) {
            $_SESSION['last_ping'] = time();
            json_success(['logged_in' => true]);
        } else {
            json_error('未登入', 401);
        }
        break;

    default:
        json_error('未知的操作', 400);
}
