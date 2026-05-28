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

$user = get_logged_in_user();

// 簡單統計（Phase 0 最小實作）
$today = date('Y-m-d');
$todaySales = db_query_one("SELECT COALESCE(SUM(total),0) AS total FROM sales WHERE sale_date = ?", [$today]);
$todayAppointments = db_query_one("SELECT COUNT(*) AS cnt FROM appointments WHERE DATE(start_time) = ? AND status IN ('pending','confirmed')", [$today]);
$activeCustomers = db_query_one("SELECT COUNT(*) AS cnt FROM customers");

// Phase 2 A3：低量警示計算（尊重每個產品自訂門檻）
$globalThreshold = db_query_one("SELECT default_low_stock_threshold FROM settings WHERE id = 1");
$globalLow = (int)($globalThreshold['default_low_stock_threshold'] ?? 5);

$allActiveProducts = db_query("SELECT stock_qty, low_stock_threshold FROM products WHERE is_active = 1");

$lowStockCount = 0;
$totalShortage = 0;
foreach ($allActiveProducts as $p) {
    $threshold = $p['low_stock_threshold'] !== null ? (int)$p['low_stock_threshold'] : $globalLow;
    $stock = (int)$p['stock_qty'];
    if ($stock <= $threshold) {
        $lowStockCount++;
        $totalShortage += ($threshold - $stock);
    }
}
$lowStock = [
    'cnt' => $lowStockCount,
    'total_shortage' => $totalShortage
];
?>
<?php include __DIR__ . '/includes/header.php'; ?>

<!-- 系統狀態提示 -->
<div class="alert alert-info border-0 mb-4" style="background-color: #F8F5F0; color: #5A5A5C; border-radius: 1rem;">
    目前系統已進入 <span class="fw-medium text-dark">維護階段</span>。
    核心功能已完成，未來會以穩定性及小優化為主。如有新需求，歡迎提出。
</div>

<!-- 四大統計卡片 -->
<div class="row g-3 mb-4">
    <!-- 今日營業額 -->
    <div class="col-12 col-md-6 col-lg-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-uppercase text-muted small mb-1" style="letter-spacing: 0.5px;">今日營業額</div>
                <div class="display-6 fw-semibold text-dark"><?= format_money($todaySales['total'] ?? 0) ?></div>
                <div class="small text-success mt-2">較昨日 · 即時更新</div>
            </div>
        </div>
    </div>

    <!-- 今日預約 -->
    <div class="col-12 col-md-6 col-lg-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-uppercase text-muted small mb-1" style="letter-spacing: 0.5px;">今日待處理預約</div>
                <div class="display-6 fw-semibold"><?= (int)($todayAppointments['cnt'] ?? 0) ?> 個</div>
                <a href="/appointments.php" class="small text-success text-decoration-none d-inline-block mt-2">查看全部預約 →</a>
            </div>
        </div>
    </div>

    <!-- 客戶總數 -->
    <div class="col-12 col-md-6 col-lg-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-uppercase text-muted small mb-1" style="letter-spacing: 0.5px;">累計客戶</div>
                <div class="display-6 fw-semibold"><?= (int)($activeCustomers['cnt'] ?? 0) ?></div>
                <a href="/customers.php" class="small text-success text-decoration-none d-inline-block mt-2">管理客戶 →</a>
            </div>
        </div>
    </div>

    <!-- 低庫存警示（A40 強化） -->
    <div class="col-12 col-md-6 col-lg-3">
        <div class="card h-100 border-danger-subtle">
            <div class="card-body">
                <div class="text-uppercase text-muted small mb-1" style="letter-spacing: 0.5px;">低庫存警示</div>
                <div class="display-6 fw-semibold text-danger"><?= (int)($lowStock['cnt'] ?? 0) ?> 項</div>
                <div class="small text-muted mt-1">
                    共缺 <span class="fw-semibold text-danger"><?= (int)($lowStock['total_shortage'] ?? 0) ?></span> 件
                </div>
                <a href="/products.php?low-stock-only=1" class="small text-danger text-decoration-none d-inline-block mt-2">查看需補貨產品 →</a>
            </div>
        </div>
    </div>
</div>

<!-- 快速操作卡片 -->
<div class="card mb-4">
    <div class="card-body">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <div class="fw-semibold fs-5">快速操作</div>
            <div class="small text-muted">使用快捷鍵更快</div>
        </div>

        <div class="row g-3">
            <div class="col-12 col-sm-6 col-lg-3">
                <a href="/pos.php" class="d-flex align-items-center gap-3 p-3 border rounded-3 text-decoration-none text-dark quick-action-card">
                    <span class="fs-3">🛒</span>
                    <div>
                        <div class="fw-medium">POS 銷售</div>
                        <div class="text-muted" style="font-size: 10px;">F9 / Ctrl+Enter 結帳</div>
                    </div>
                </a>
            </div>

            <div class="col-12 col-sm-6 col-lg-3">
                <a href="/appointments.php" class="d-flex align-items-center gap-3 p-3 border rounded-3 text-decoration-none text-dark quick-action-card">
                    <span class="fs-3">📅</span>
                    <div>
                        <div class="fw-medium">新增預約</div>
                        <div class="text-muted" style="font-size: 10px;">按 N 快速新增</div>
                    </div>
                </a>
            </div>

            <div class="col-12 col-sm-6 col-lg-3">
                <a href="/customers.php" class="d-flex align-items-center gap-3 p-3 border rounded-3 text-decoration-none text-dark quick-action-card">
                    <span class="fs-3">👥</span>
                    <div>
                        <div class="fw-medium">客戶管理</div>
                        <div class="text-muted" style="font-size: 10px;">搜尋電話最快</div>
                    </div>
                </a>
            </div>

            <div class="col-12 col-sm-6 col-lg-3">
                <a href="/settings.php" class="d-flex align-items-center gap-3 p-3 border rounded-3 text-decoration-none text-dark quick-action-card">
                    <span class="fs-3">⚙️</span>
                    <div>
                        <div class="fw-medium">系統設定</div>
                        <div class="text-muted" style="font-size: 10px;">員工、房間、佣金</div>
                    </div>
                </a>
            </div>
        </div>
    </div>
</div>

<!-- 目前登入者提示 -->
<div class="text-center small text-muted mt-4">
    歡迎回來，<?= e($user['name']) ?>（<?= e($user['role']) ?>）。<br>
    按 <span class="fw-semibold text-dark">?</span> 查看目前頁面所有快捷鍵。
</div>

<?php 
$extraJs = 'hotkeys.js';   // 載入熱鍵系統
include __DIR__ . '/includes/footer.php'; 
?>
