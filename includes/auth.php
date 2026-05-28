<?php
/**
 * SalonEase - 認證與授權
 * 使用原生 Session + password_hash
 * 所有受保護頁面與 API 必須 require 本檔
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * 檢查使用者是否已登入
 * 未登入則導向登入頁
 */
function require_login(): void
{
    if (empty($_SESSION['staff_id'])) {
        // API 請求回傳 JSON，頁面請求則 redirect
        if (str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/api/')) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'message' => '請先登入',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        header('Location: /login.php?redirect=' . urlencode($_SERVER['REQUEST_URI'] ?? '/'));
        exit;
    }
}

/**
 * 取得目前登入使用者資料
 */
function get_logged_in_user(): ?array
{
    if (empty($_SESSION['staff_id'])) {
        return null;
    }

    static $user = null;
    if ($user === null) {
        require_once __DIR__ . '/../db.php';
        $user = db_query_one(
            "SELECT id, name, role, email, phone FROM staff WHERE id = ? AND is_active = 1",
            [$_SESSION['staff_id']]
        );
    }
    return $user;
}

/**
 * 檢查角色權限
 * 用法：require_role('admin') 或 require_role(['admin','manager'])
 */
function require_role(string|array $roles): void
{
    require_login();

    $user = get_logged_in_user();
    if (!$user) {
        exit('認證失敗');
    }

    $allowed = is_array($roles) ? $roles : [$roles];
    if (!in_array($user['role'], $allowed, true)) {
        if (str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/api/')) {
            json_error('權限不足', 403);
        }
        http_response_code(403);
        include __DIR__ . '/header.php';
        echo '<div class="max-w-md mx-auto mt-12 p-6 bg-red-50 border border-red-200 rounded-xl">';
        echo '<h2 class="text-xl font-semibold text-red-700 mb-3">權限不足</h2>';
        echo '<p class="text-red-600">您沒有權限瀏覽此頁面。請聯絡管理員。</p>';
        echo '<a href="/dashboard.php" class="mt-4 inline-block text-red-600 hover:underline">返回首頁</a>';
        echo '</div>';
        include __DIR__ . '/footer.php';
        exit;
    }
}

/**
 * 處理登入（驗證帳號密碼）
 */
function attempt_login(string $email, string $password): array
{
    require_once __DIR__ . '/../db.php';

    $user = db_query_one(
        "SELECT id, name, role, password_hash, is_active FROM staff WHERE email = ?",
        [$email]
    );

    if (!$user) {
        return ['success' => false, 'message' => '帳號或密碼錯誤'];
    }

    if (!$user['is_active']) {
        return ['success' => false, 'message' => '此帳號已被停用'];
    }

    if (!password_verify($password, $user['password_hash'])) {
        return ['success' => false, 'message' => '帳號或密碼錯誤'];
    }

    // 登入成功
    session_regenerate_id(true);
    $_SESSION['staff_id'] = (int)$user['id'];
    $_SESSION['staff_name'] = $user['name'];
    $_SESSION['staff_role'] = $user['role'];
    $_SESSION['login_time'] = time();

    return ['success' => true, 'user' => $user];
}

/**
 * 登出
 */
function logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }
    session_destroy();
}

/**
 * 簡單檢查目前登入者是否為管理員
 */
function is_admin(): bool
{
    $user = get_logged_in_user();
    return $user && $user['role'] === 'admin';
}
