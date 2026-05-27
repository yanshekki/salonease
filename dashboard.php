<?php
/**
 * SalonEase - 首頁概覽（Dashboard）
 */
require_once __DIR__ . '/includes/auth.php';
require_login();

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/db.php';

$pageTitle = '今日概覽';
$pageSubtitle = 'SalonEase 香港小型美容院管理系統';

$user = get_current_user();

// 簡單統計（Phase 0 最小實作）
$today = date('Y-m-d');
$todaySales = db_query_one("SELECT COALESCE(SUM(total),0) AS total FROM sales WHERE sale_date = ?", [$today]);
$todayAppointments = db_query_one("SELECT COUNT(*) AS cnt FROM appointments WHERE DATE(start_time) = ? AND status IN ('pending','confirmed')", [$today]);
$activeCustomers = db_query_one("SELECT COUNT(*) AS cnt FROM customers");
$lowStock = db_query_one("SELECT COUNT(*) AS cnt FROM products WHERE stock_qty < 10 AND is_active = 1");
?>
<?php include __DIR__ . '/includes/header.php'; ?>

<div class="mb-4 p-3 bg-[#F8F5F0] border border-[#EDE5DC] rounded-2xl text-sm text-[#5A5A5C]">
    目前系統已進入 <span class="font-medium text-[#2C2C2E]">維護階段</span>。
    核心功能已完成，未來會以穩定性及小優化為主。如有新需求，歡迎提出。
</div>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
    <!-- 今日營業額 -->
    <div class="bg-white rounded-2xl p-5 border border-gray-100">
        <div class="text-xs uppercase tracking-wider text-[#8A8A8C] mb-1">今日營業額</div>
        <div class="text-3xl font-semibold text-[#2C2C2E]"><?= format_money($todaySales['total'] ?? 0) ?></div>
        <div class="text-xs mt-3 text-[#8FA68F]">較昨日 · 即時更新</div>
    </div>

    <!-- 今日預約 -->
    <div class="bg-white rounded-2xl p-5 border border-gray-100">
        <div class="text-xs uppercase tracking-wider text-[#8A8A8C] mb-1">今日待處理預約</div>
        <div class="text-3xl font-semibold"><?= (int)($todayAppointments['cnt'] ?? 0) ?> 個</div>
        <a href="/appointments.php" class="inline-block mt-3 text-xs text-[#8FA68F] hover:underline">查看全部預約 →</a>
    </div>

    <!-- 客戶總數 -->
    <div class="bg-white rounded-2xl p-5 border border-gray-100">
        <div class="text-xs uppercase tracking-wider text-[#8A8A8C] mb-1">累計客戶</div>
        <div class="text-3xl font-semibold"><?= (int)($activeCustomers['cnt'] ?? 0) ?></div>
        <a href="/customers.php" class="inline-block mt-3 text-xs text-[#8FA68F] hover:underline">管理客戶 →</a>
    </div>

    <!-- 低庫存警示 -->
    <div class="bg-white rounded-2xl p-5 border border-gray-100">
        <div class="text-xs uppercase tracking-wider text-[#8A8A8C] mb-1">庫存警示</div>
        <div class="text-3xl font-semibold text-[#C97C7C]"><?= (int)($lowStock['cnt'] ?? 0) ?> 項</div>
        <div class="text-xs mt-3">零售產品庫存不足 10 件</div>
    </div>
</div>

<!-- 快速入口（Hotkey 重點提示） -->
<div class="bg-white rounded-2xl border border-gray-100 p-6">
    <div class="flex items-center justify-between mb-4">
        <div class="font-semibold text-lg">快速操作</div>
        <div class="text-xs text-[#8A8A8C]">使用快捷鍵更快</div>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-sm">
        <a href="/pos.php" 
           class="group flex items-center justify-center gap-x-3 border border-gray-200 hover:border-[#8FA68F] hover:bg-[#F8F5F0] transition rounded-2xl py-4 active:scale-[0.985]">
            <span class="text-2xl">🛒</span>
            <div>
                <div class="font-medium">POS 銷售</div>
                <div class="text-[10px] text-[#8A8A8C] group-hover:text-[#8FA68F]">F9 / Ctrl+Enter 結帳</div>
            </div>
        </a>

        <a href="/appointments.php" 
           class="group flex items-center justify-center gap-x-3 border border-gray-200 hover:border-[#8FA68F] hover:bg-[#F8F5F0] transition rounded-2xl py-4 active:scale-[0.985]">
            <span class="text-2xl">📅</span>
            <div>
                <div class="font-medium">新增預約</div>
                <div class="text-[10px] text-[#8A8A8C]">按 N 快速新增</div>
            </div>
        </a>

        <a href="/customers.php" 
           class="group flex items-center justify-center gap-x-3 border border-gray-200 hover:border-[#8FA68F] hover:bg-[#F8F5F0] transition rounded-2xl py-4 active:scale-[0.985]">
            <span class="text-2xl">👥</span>
            <div>
                <div class="font-medium">客戶管理</div>
                <div class="text-[10px] text-[#8A8A8C]">搜尋電話最快</div>
            </div>
        </a>

        <a href="/settings.php" 
           class="group flex items-center justify-center gap-x-3 border border-gray-200 hover:border-[#8FA68F] hover:bg-[#F8F5F0] transition rounded-2xl py-4 active:scale-[0.985]">
            <span class="text-2xl">⚙️</span>
            <div>
                <div class="font-medium">系統設定</div>
                <div class="text-[10px] text-[#8A8A8C]">員工、房間、佣金</div>
            </div>
        </a>
    </div>
</div>

<!-- 目前登入者提示 -->
<div class="mt-8 text-center text-xs text-[#8A8A8C]">
    歡迎回來，<?= e($user['name']) ?>（<?= e($user['role']) ?>）。<br>
    按 <span class="font-semibold text-[#2C2C2E]">?</span> 查看目前頁面所有快捷鍵。
</div>

<?php 
$extraJs = 'hotkeys.js';   // 載入熱鍵系統
include __DIR__ . '/includes/footer.php'; 
?>
