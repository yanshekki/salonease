<?php
/**
 * SalonEase - CSRF 保護機制
 * 簡單、安全、適合純 PHP 項目使用
 * 
 * 使用方式：
 *   - 表單：在 form 內加入 <?= csrf_field() ?>
 *   - API：在處理 POST 之前呼叫 require_csrf()
 * 
 * 安全性：
 *   - 使用 hash_equals 做 timing-safe 比較
 *   - 登入成功後會重新產生 token
 */

/**
 * 取得或產生目前的 CSRF token
 */
function csrf_token(): string
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = base64_encode(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

/**
 * 輸出 CSRF hidden input（用於表單）
 */
function csrf_field(): string
{
    $token = csrf_token();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * 驗證 CSRF token
 * 
 * @param string|null $token 從 POST 取得的 token
 * @return bool
 */
function verify_csrf_token(?string $token): bool
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }

    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * 要求 CSRF 驗證（失敗會直接中斷）
 * 建議在所有處理 POST 的地方最前面呼叫
 * 
 * @param string|null $token 可選：自行傳入 token（預設取 $_POST['csrf_token']）
 */
function require_csrf(?string $token = null): void
{
    if ($token === null) {
        $token = $_POST['csrf_token'] ?? null;
    }

    if (!verify_csrf_token($token)) {
        if (str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/api/')) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => 'CSRF 驗證失敗，請重新整理頁面後再試',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // 非 API 請求
        http_response_code(403);
        require_once __DIR__ . '/header.php';
        echo '<div class="max-w-md mx-auto mt-12 p-6 bg-red-50 border border-red-200 rounded-xl">';
        echo '<h2 class="text-xl font-semibold text-red-700 mb-3">安全性驗證失敗</h2>';
        echo '<p class="text-red-600">您的請求無法通過安全檢查。請返回上一頁重新操作。</p>';
        echo '<a href="javascript:history.back()" class="mt-4 inline-block text-red-600 hover:underline">返回上一頁</a>';
        echo '</div>';
        require_once __DIR__ . '/footer.php';
        exit;
    }
}

/**
 * 登入成功後重新產生 CSRF token（提升安全性）
 */
function regenerate_csrf_token(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    unset($_SESSION['csrf_token']);
    csrf_token(); // 重新產生
}

/**
 * 登出時清除 CSRF token
 */
function clear_csrf_token(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    unset($_SESSION['csrf_token']);
}