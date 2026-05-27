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

$currentUser = get_logged_in_user();
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="zh-HK">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(APP_NAME ?? 'SalonEase') ?> · <?= e($pageTitle ?? '管理系統') ?></title>
    
    <!-- Bootstrap 5.3.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Alpine.js CDN（暫時保留，之後可逐步移除） -->
    <script src="https://unpkg.com/alpinejs@3.14.1/dist/cdn.min.js" defer></script>
    
    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- 共用樣式 -->
    <link rel="stylesheet" href="/assets/css/app.css">
    <?php if (isset($extraCss) && $extraCss): ?>
        <link rel="stylesheet" href="/assets/css/<?= e($extraCss) ?>">
    <?php endif; ?>
    
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Noto+Sans+TC:wght@400;500;700&family=Inter:wght@400;500;600&display=swap');
        
        :root {
            --font-sans: "Noto Sans TC", system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            
            /* Bootstrap 品牌顏色覆蓋 - 保留原有 SalonEase 感覺 */
            --bs-primary: #2C2C2E;
            --bs-primary-rgb: 44, 44, 46;
            --bs-secondary: #8FA68F;
            --bs-success: #8FA68F;
            --bs-danger: #c62828;
            --bs-body-bg: #FDF8F3;
            --bs-body-color: #2C2C2E;
            --bs-border-color: #EDE5DC;
        }
        
        body { 
            font-family: var(--font-sans); 
        }
        
        /* 保留原有導航 active 樣式 */
        .nav-active { 
            background-color: #2C2C2E; 
            color: white; 
        }
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

                <!-- 主要導覽 (Desktop) -->
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
                    <a href="/commissions.php" 
                       class="px-4 py-2 rounded-xl hover:bg-gray-100 transition <?= $currentPage === 'commissions' ? 'nav-active' : '' ?>">
                        佣金
                    </a>
                    <a href="/staff.php" 
                       class="px-4 py-2 rounded-xl hover:bg-gray-100 transition <?= $currentPage === 'staff' ? 'nav-active' : '' ?>">
                        員工
                    </a>
                </div>

                <!-- 手機版選單按鈕 -->
                <div class="md:hidden flex items-center">
                    <button onclick="toggleMobileNav()" 
                            class="w-10 h-10 flex items-center justify-center text-2xl text-[#2C2C2E] hover:bg-gray-100 active:bg-gray-200 rounded-xl transition active:scale-95"
                            aria-label="選單">
                        ☰
                    </button>
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

            <!-- 手機版下拉選單 -->
            <div id="mobile-nav" class="hidden md:hidden border-t bg-white">
                <div class="px-2 py-1 flex flex-col text-sm">
                    <a href="/dashboard.php" class="py-3 px-4 rounded-xl active:bg-gray-100 <?= $currentPage === 'dashboard' ? 'font-medium text-[#2C2C2E] bg-[#F8F5F0]' : '' ?>">概覽</a>
                    <a href="/pos.php" class="py-3 px-4 rounded-xl active:bg-gray-100 <?= $currentPage === 'pos' ? 'font-medium text-[#2C2C2E] bg-[#F8F5F0]' : '' ?>">POS 銷售</a>
                    <a href="/appointments.php" class="py-3 px-4 rounded-xl active:bg-gray-100 <?= $currentPage === 'appointments' ? 'font-medium text-[#2C2C2E] bg-[#F8F5F0]' : '' ?>">預約管理</a>
                    <a href="/customers.php" class="py-3 px-4 rounded-xl active:bg-gray-100 <?= $currentPage === 'customers' ? 'font-medium text-[#2C2C2E] bg-[#F8F5F0]' : '' ?>">客戶</a>
                    <a href="/reports.php" class="py-3 px-4 rounded-xl active:bg-gray-100 <?= $currentPage === 'reports' ? 'font-medium text-[#2C2C2E] bg-[#F8F5F0]' : '' ?>">報表</a>
                    <a href="/commissions.php" class="py-3 px-4 rounded-xl active:bg-gray-100 <?= $currentPage === 'commissions' ? 'font-medium text-[#2C2C2E] bg-[#F8F5F0]' : '' ?>">佣金</a>
                    <a href="/staff.php" class="py-3 px-4 rounded-xl active:bg-gray-100 <?= $currentPage === 'staff' ? 'font-medium text-[#2C2C2E] bg-[#F8F5F0]' : '' ?>">員工</a>
                    <div class="border-t my-1 mx-2"></div>
                    <a href="/settings.php" class="py-3 px-4 rounded-xl active:bg-gray-100">系統設定</a>
                    <a href="/logout.php" class="py-3 px-4 rounded-xl text-red-600 active:bg-red-50" onclick="return confirm('確定要登出嗎？')">登出</a>
                </div>
            </div>
        </div>
    </nav>

    <script>
        function toggleMobileNav() {
            const nav = document.getElementById('mobile-nav');
            if (nav) {
                nav.classList.toggle('hidden');
            }
        }
    </script>

    <!-- 主要內容區域（各頁面自己開 <main> 與關閉） -->
    <div class="flex-1 max-w-screen-2xl mx-auto w-full px-4 sm:px-6 pt-4 sm:pt-6 pb-20">
        <!-- 頁面標題（可被覆蓋） -->
        <?php if (isset($pageTitle) && $pageTitle): ?>
            <div class="mb-6">
                <h1 class="text-2xl font-semibold"><?= e($pageTitle) ?></h1>
                <?php if (isset($pageSubtitle)): ?>
                    <p class="text-[#5A5A5C] text-sm mt-0.5"><?= e($pageSubtitle) ?></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
