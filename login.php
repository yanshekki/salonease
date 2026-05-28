<?php
/**
 * SalonEase - 登入頁
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 已登入直接導向 dashboard
if (!empty($_SESSION['staff_id'])) {
    header('Location: /dashboard.php');
    exit;
}

$error = '';
$email = '';

// 無論 GET 或 POST，都需要 functions.php（e() 函數）
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/csrf.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/includes/auth.php';

    // CSRF 保護（Phase 1）
    require_csrf();

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = '請輸入帳號與密碼';
    } else {
        $result = attempt_login($email, $password);
        if ($result['success']) {
            $redirect = $_GET['redirect'] ?? '/dashboard.php';
            // 簡單防止 Open Redirect，只允許內部路徑
            if (!str_starts_with($redirect, '/') || str_contains($redirect, '//')) {
                $redirect = '/dashboard.php';
            }
            header('Location: ' . $redirect);
            exit;
        } else {
            $error = $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-HK">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>登入 · SalonEase 香港美容院管理系統</title>
    <!-- Bootstrap 5.3.3 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap 自訂主題 -->
    <link rel="stylesheet" href="assets/css/bootstrap-custom.css">
    <!-- 過渡期保留（之後移除） -->
    <link rel="stylesheet" href="assets/css/app.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Noto+Sans+TC:wght@400;500;700&display=swap');
        body { font-family: "Noto Sans TC", system-ui, -apple-system, sans-serif; }
    </style>
</head>
<body class="bg-body d-flex align-items-center justify-content-center min-vh-100">
    <div class="w-100" style="max-width: 420px; padding: 0 1rem;">
        <!-- Logo 與標題 -->
        <div class="text-center mb-4">
            <div class="d-inline-flex align-items-center justify-content-center bg-dark text-white rounded-3 mb-3" style="width: 64px; height: 64px; font-weight: 700; font-size: 1.75rem;">
                SE
            </div>
            <h1 class="h3 fw-semibold text-dark">SalonEase</h1>
            <p class="text-muted small mt-1">香港小型美容院管理系統</p>
        </div>

        <!-- 登入卡片 -->
        <div class="card shadow-sm border-0">
            <div class="card-body p-4 p-sm-5">
                <h2 class="h5 fw-semibold mb-4">員工登入</h2>

                <?php if ($error): ?>
                    <div class="alert alert-danger small py-2">
                        <?= e($error) ?>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label small fw-medium">電郵地址</label>
                        <input type="email" name="email" value="<?= e($email) ?>" required
                               class="form-control" placeholder="admin@salonease.hk" autocomplete="email">
                    </div>

                    <div class="mb-4">
                        <label class="form-label small fw-medium">密碼</label>
                        <input type="password" name="password" required
                               class="form-control" placeholder="••••••••" autocomplete="current-password">
                    </div>

                    <?= csrf_field() ?>

                    <button type="submit" class="btn btn-dark w-100 py-2 fw-medium">
                        登入系統
                    </button>
                </form>

                <div class="mt-4 pt-3 border-top text-center">
                    <p class="small text-muted mb-0">
                        測試帳號：<span class="font-mono">admin@salonease.hk</span><br>
                        密碼：<span class="font-mono">admin123</span>
                    </p>
                </div>
            </div>
        </div>

        <p class="text-center text-muted small mt-4">
            &copy; <?= date('Y') ?> SalonEase · 專業 · 簡單 · 高效
        </p>
    </div>

    <script>
        // 登入頁熱鍵（使用項目統一的 toast）
        document.addEventListener('keydown', function(e) {
            // 避免在 input/textarea 內觸發 ? 提示
            if (document.activeElement.tagName === 'INPUT' || 
                document.activeElement.tagName === 'TEXTAREA') {
                if (e.key === 'Escape') {
                    document.querySelectorAll('input').forEach(i => i.value = '');
                }
                return;
            }

            if (e.key === '?') {
                e.preventDefault();
                if (typeof SalonEase !== 'undefined' && SalonEase.toast) {
                    SalonEase.toast('登入頁快捷鍵：\nEsc - 清除欄位\nEnter - 提交表單', 'info');
                } else {
                    alert('登入頁快捷鍵：\nEsc - 清除欄位\nEnter - 提交表單');
                }
            }

            if (e.key === 'Escape') {
                document.querySelectorAll('input').forEach(i => i.value = '');
            }
        });
    </script>
</body>
</html>
