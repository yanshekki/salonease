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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/includes/auth.php';
    require_once __DIR__ . '/includes/functions.php';

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = '請輸入帳號與密碼';
    } else {
        $result = attempt_login($email, $password);
        if ($result['success']) {
            $redirect = $_GET['redirect'] ?? '/dashboard.php';
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
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="assets/css/app.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Noto+Sans+TC:wght@400;500;700&display=swap');
        body { font-family: "Noto Sans TC", system-ui, -apple-system, sans-serif; }
    </style>
</head>
<body class="bg-[#FDF8F3] min-h-screen flex items-center justify-center">
    <div class="w-full max-w-md px-6">
        <!-- Logo 與標題 -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-16 h-16 bg-[#2C2C2E] text-white rounded-2xl mb-4">
                <span class="text-3xl font-bold tracking-wider">SE</span>
            </div>
            <h1 class="text-3xl font-semibold text-[#2C2C2E]">SalonEase</h1>
            <p class="text-[#5A5A5C] mt-1">香港小型美容院管理系統</p>
        </div>

        <!-- 登入卡片 -->
        <div class="bg-white rounded-2xl shadow-lg p-8 border border-gray-100">
            <h2 class="text-xl font-semibold text-[#2C2C2E] mb-6">員工登入</h2>

            <?php if ($error): ?>
                <div class="mb-4 p-3 bg-red-50 border border-red-200 text-red-600 rounded-xl text-sm">
                    <?= e($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-5">
                <div>
                    <label class="block text-sm font-medium text-[#2C2C2E] mb-1.5">電郵地址</label>
                    <input type="email" name="email" value="<?= e($email) ?>" required
                           class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-[#8FA68F] focus:border-transparent outline-none text-sm"
                           placeholder="admin@salonease.hk" autocomplete="email">
                </div>

                <div>
                    <label class="block text-sm font-medium text-[#2C2C2E] mb-1.5">密碼</label>
                    <input type="password" name="password" required
                           class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-[#8FA68F] focus:border-transparent outline-none text-sm"
                           placeholder="••••••••" autocomplete="current-password">
                </div>

                <button type="submit"
                        class="w-full mt-2 py-3.5 bg-[#2C2C2E] hover:bg-black text-white font-medium rounded-xl transition-colors active:scale-[0.985]">
                    登入系統
                </button>
            </form>

            <div class="mt-6 pt-6 border-t text-center">
                <p class="text-xs text-[#8A8A8C]">
                    測試帳號：<span class="font-mono">admin@salonease.hk</span><br>
                    密碼：<span class="font-mono">admin123</span>
                </p>
            </div>
        </div>

        <p class="text-center text-xs text-[#8A8A8C] mt-6">
            &copy; <?= date('Y') ?> SalonEase · 專業 · 簡單 · 高效
        </p>
    </div>

    <script>
        // 簡單全域熱鍵提示
        document.addEventListener('keydown', function(e) {
            if (e.key === '?') {
                alert('登入頁快捷鍵：\nEsc - 清除欄位\nEnter - 提交表單');
            }
            if (e.key === 'Escape') {
                document.querySelectorAll('input').forEach(i => i.value = '');
            }
        });
    </script>
</body>
</html>
