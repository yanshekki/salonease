<?php
/**
 * SalonEase - 共用頁首
 * 包含 Tailwind + Alpine.js CDN、全域導覽、使用者資訊、底部熱鍵提示
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';

$currentUser = get_current_user();
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="zh-HK">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(APP_NAME ?? 'SalonEase') ?> · <?= e($pageTitle ?? '管理系統') ?></title>
    
    <!-- Tailwind CSS CDN + 自訂主題 -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'salon-dark': '#2C2C2E',
                        'salon-sage': '#8FA68F',
                        'salon-gold': '#C9A86C',
                        'salon-rose': '#C9A8A0',
                        'salon-bg': '#FDF8F3',
                    }
                }
            }
        }
    </script>
    
    <!-- Alpine.js CDN -->
    <script src="https://unpkg.com/alpinejs@3.14.1/dist/cdn.min.js" defer></script>
    
    <!-- 共用樣式 -->
    <link rel="stylesheet" href="/assets/css/app.css">
    <?php if (isset($extraCss) && $extraCss): ?>
        <link rel="stylesheet" href="/assets/css/<?= e($extraCss) ?>">
    <?php endif; ?>
    
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Noto+Sans+TC:wght@400;500;700&family=Inter:wght@400;500;600&display=swap');
        :root { --font-sans: "Noto Sans TC", system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        body { font-family: var(--font-sans); }
        .nav-active { background-color: #2C2C2E; color: white; }
    </style>
</head>
<body class="bg-[#FDF8F3] text-[#2C2C2E] min-h-screen flex flex-col">
    
    <!-- 頂部導覽列 -->
    <nav class="bg-white border-b border-gray-200 sticky top-0 z-50">
        <div class="max-w-screen-2xl mx-auto">
            <div class="px-6 h-16 flex items-center justify-between">
                <!-- Logo + 店名 -->
                <div class="flex items-center gap-x-3">
                    <a href="/dashboard.php" class="flex items-center gap-x-2.5 group">
                        <div class="w-9 h-9 bg-[#2C2C2E] text-white rounded-xl flex items-center justify-center font-bold text-xl tracking-[-1px] group-active:scale-95 transition">SE</div>
                        <div>
                            <div class="font-semibold text-lg leading-none"><?= e(APP_NAME ?? 'SalonEase') ?></div>
                            <div class="text-[10px] text-[#8A8A8C] -mt-0.5">香港美容院管理</div>
                        </div>
                    </a>
                </div>

                <!-- 主要導覽 -->
                <div class="hidden md:flex items-center gap-x-1 text-sm">
                    <a href="/dashboard.php" 
                       class="px-4 py-2 rounded-xl hover:bg-gray-100 transition <?= $currentPage === 'dashboard' ? 'nav-active' : '' ?>">
                        概覽
                    </a>
                    <a href="/pos.php" 
                       class="px-4 py-2 rounded-xl hover:bg-gray-100 transition <?= $currentPage === 'pos' ? 'nav-active' : '' ?>">
                        POS 銷售
                    </a>
                    <a href="/appointments.php" 
                       class="px-4 py-2 rounded-xl hover:bg-gray-100 transition <?= $currentPage === 'appointments' ? 'nav-active' : '' ?>">
                        預約管理
                    </a>
                    <a href="/customers.php" 
                       class="px-4 py-2 rounded-xl hover:bg-gray-100 transition <?= $currentPage === 'customers' ? 'nav-active' : '' ?>">
                        客戶
                    </a>
                    <a href="/reports.php" 
                       class="px-4 py-2 rounded-xl hover:bg-gray-100 transition <?= $currentPage === 'reports' ? 'nav-active' : '' ?>">
                        報表
                    </a>
                    <a href="/staff.php" 
                       class="px-4 py-2 rounded-xl hover:bg-gray-100 transition <?= $currentPage === 'staff' ? 'nav-active' : '' ?>">
                        員工
                    </a>
                </div>

                <!-- 使用者區塊 -->
                <div class="flex items-center gap-x-3">
                    <?php if ($currentUser): ?>
                        <div class="text-right hidden sm:block">
                            <div class="text-sm font-medium"><?= e($currentUser['name']) ?></div>
                            <div class="text-[10px] text-[#8A8A8C]"><?= e(ucfirst($currentUser['role'])) ?></div>
                        </div>
                        <a href="/settings.php" class="w-9 h-9 bg-[#F3EDE6] hover:bg-[#EDE5DC] rounded-2xl flex items-center justify-center text-lg transition" title="設定">
                            ⚙️
                        </a>
                        <a href="/logout.php" 
                           class="text-sm px-4 py-2 rounded-xl border border-gray-200 hover:bg-red-50 hover:text-red-600 hover:border-red-200 transition flex items-center gap-x-1.5"
                           onclick="return confirm('確定要登出嗎？')">
                            <span>登出</span>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- 主要內容區域（各頁面自己開 <main> 與關閉） -->
    <div class="flex-1 max-w-screen-2xl mx-auto w-full px-6 pt-6 pb-20">
        <!-- 頁面標題（可被覆蓋） -->
        <?php if (isset($pageTitle) && $pageTitle): ?>
            <div class="mb-6">
                <h1 class="text-2xl font-semibold"><?= e($pageTitle) ?></h1>
                <?php if (isset($pageSubtitle)): ?>
                    <p class="text-[#5A5A5C] text-sm mt-0.5"><?= e($pageSubtitle) ?></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
