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

// A43：本月忠誠度摘要（輕量查詢，與 A41 一致）
$thisMonthStart = date('Y-m-01');
$thisMonthEnd   = date('Y-m-d');

$monthlyEarned = db_query_one("
    SELECT COALESCE(SUM(CAST(JSON_UNQUOTE(JSON_EXTRACT(details, '$.points')) AS UNSIGNED)), 0) AS total
    FROM audit_logs
    WHERE action = 'customer.points_earned'
      AND created_at >= ? AND created_at <= ?
", [$thisMonthStart, $thisMonthEnd . ' 23:59:59']);

$monthlyRedeemed = db_query_one("
    SELECT COALESCE(SUM(CAST(JSON_UNQUOTE(JSON_EXTRACT(details, '$.points_used')) AS UNSIGNED)), 0) AS total
    FROM audit_logs
    WHERE action = 'customer.points_redeemed'
      AND created_at >= ? AND created_at <= ?
", [$thisMonthStart, $thisMonthEnd . ' 23:59:59']);

$activePointsCustomers = db_query_one("
    SELECT COUNT(DISTINCT entity_id) AS cnt
    FROM audit_logs
    WHERE action IN ('customer.points_earned', 'customer.points_redeemed', 'customer.points_adjusted')
      AND entity_type = 'customer'
      AND created_at >= ? AND created_at <= ?
", [$thisMonthStart, $thisMonthEnd . ' 23:59:59']);

$monthlyLoyalty = [
    'earned'   => (int)($monthlyEarned['total'] ?? 0),
    'redeemed' => (int)($monthlyRedeemed['total'] ?? 0),
    'active'   => (int)($activePointsCustomers['cnt'] ?? 0)
];

// A54：Phase 3 - 本月熱門服務 Top 3（簡單數據洞察）
$thisMonthStart = date('Y-m-01');
$topServices = db_query("
    SELECT s.name, COUNT(*) as cnt
    FROM sale_items si
    JOIN services s ON si.service_id = s.id
    JOIN sales sa ON si.sale_id = sa.id
    WHERE sa.sale_date >= ? AND s.is_active = 1
    GROUP BY s.id
    ORDER BY cnt DESC
    LIMIT 3
", [$thisMonthStart]);

// A55：Phase 3 - 本月熱門產品 Top 3（對稱 A54 的服務洞察）
$topProducts = db_query("
    SELECT p.name, COUNT(*) as cnt
    FROM sale_items si
    JOIN products p ON si.product_id = p.id
    JOIN sales sa ON si.sale_id = sa.id
    WHERE sa.sale_date >= ? AND p.is_active = 1
    GROUP BY p.id
    ORDER BY cnt DESC
    LIMIT 3
", [$thisMonthStart]);

// A56：Phase 3 - 本月營業額 vs 上月比較（數據洞察）
$thisMonthStartForSales = date('Y-m-01');
$lastMonthStart = date('Y-m-01', strtotime('first day of last month'));
$lastMonthEnd   = date('Y-m-t', strtotime('first day of last month'));

$thisMonthSales = db_query_one("
    SELECT COALESCE(SUM(total), 0) AS total
    FROM sales 
    WHERE sale_date >= ?
", [$thisMonthStartForSales]);

$lastMonthSales = db_query_one("
    SELECT COALESCE(SUM(total), 0) AS total
    FROM sales 
    WHERE sale_date >= ? AND sale_date <= ?
", [$lastMonthStart, $lastMonthEnd]);

$thisMonthTotal = (float)($thisMonthSales['total'] ?? 0);
$lastMonthTotal = (float)($lastMonthSales['total'] ?? 0);
$salesDiff = $thisMonthTotal - $lastMonthTotal;
$salesDiffPercent = $lastMonthTotal > 0 ? round(($salesDiff / $lastMonthTotal) * 100, 1) : 0;

// A57：Phase 3 - 本月 Top 3 員工銷售（數據洞察）
$topStaff = db_query("
    SELECT st.name, COALESCE(SUM(sa.total), 0) as revenue
    FROM sales sa
    JOIN staff st ON sa.staff_id = st.id
    WHERE sa.sale_date >= ? AND st.is_active = 1
    GROUP BY st.id
    ORDER BY revenue DESC
    LIMIT 3
", [$thisMonthStartForSales]);

// A58：Phase 3 - 本月新客戶數（數據洞察）
$newCustomersThisMonth = db_query_one("
    SELECT COUNT(*) as cnt 
    FROM customers 
    WHERE created_at >= ?
", [$thisMonthStartForSales]);

// A59：Phase 3 - 平均客單價 vs 上月（數據洞察）
$thisMonthTxCount = db_query_one("
    SELECT COUNT(*) as cnt FROM sales WHERE sale_date >= ?
", [$thisMonthStartForSales]);

$lastMonthTxCount = db_query_one("
    SELECT COUNT(*) as cnt FROM sales WHERE sale_date >= ? AND sale_date <= ?
", [$lastMonthStart, $lastMonthEnd]);

$thisMonthAvgTicket = $thisMonthTxCount['cnt'] > 0 ? round($thisMonthTotal / $thisMonthTxCount['cnt'], 0) : 0;
$lastMonthAvgTicket = $lastMonthTxCount['cnt'] > 0 ? round($lastMonthTotal / $lastMonthTxCount['cnt'], 0) : 0;
$avgTicketDiff = $thisMonthAvgTicket - $lastMonthAvgTicket;
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

<!-- A43：本月忠誠度摘要 -->
<div class="row g-3 mb-4">
    <div class="col-12 col-md-4">
        <div class="card h-100 border-success-subtle">
            <div class="card-body">
                <div class="text-uppercase text-muted small">本月累積</div>
                <div class="display-6 fw-semibold text-success"><?= number_format($monthlyLoyalty['earned']) ?></div>
                <div class="small text-muted">點</div>
                <a href="/loyalty.php" class="small text-success text-decoration-none d-inline-block mt-2">查看忠誠度 →</a>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="card h-100 border-warning-subtle">
            <div class="card-body">
                <div class="text-uppercase text-muted small">本月兌換</div>
                <div class="display-6 fw-semibold text-warning"><?= number_format($monthlyLoyalty['redeemed']) ?></div>
                <div class="small text-muted">點</div>
                <a href="/loyalty.php" class="small text-warning text-decoration-none d-inline-block mt-2">查看忠誠度 →</a>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="card h-100 border-info-subtle">
            <div class="card-body">
                <div class="text-uppercase text-muted small">本月有活動</div>
                <div class="display-6 fw-semibold text-info"><?= number_format($monthlyLoyalty['active']) ?></div>
                <div class="small text-muted">位客戶</div>
                <a href="/loyalty.php" class="small text-info text-decoration-none d-inline-block mt-2">查看忠誠度 →</a>
            </div>
        </div>
    </div>
</div>

<!-- A54：Phase 3 - 本月熱門服務 Top 3（數據洞察） -->
<div class="card mb-4">
    <div class="card-body py-3">
        <div class="d-flex align-items-center justify-content-between mb-2">
            <div class="fw-semibold small">本月熱門服務 Top 3</div>
            <a href="/reports.php" class="small text-muted text-decoration-none">查看完整報表 →</a>
        </div>
        <?php if (!empty($topServices)): ?>
            <div class="d-flex flex-wrap gap-2">
                <?php foreach ($topServices as $svc): ?>
                    <span class="badge bg-light text-dark border px-2 py-1 small">
                        <?= e($svc['name']) ?> <span class="text-muted">(<?= (int)$svc['cnt'] ?>)</span>
                    </span>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="small text-muted">本月暫無服務銷售記錄</div>
        <?php endif; ?>
    </div>
</div>

<!-- A55：Phase 3 - 本月熱門產品 Top 3（數據洞察） -->
<div class="card mb-4">
    <div class="card-body py-3">
        <div class="d-flex align-items-center justify-content-between mb-2">
            <div class="fw-semibold small">本月熱門產品 Top 3</div>
            <a href="/reports.php" class="small text-muted text-decoration-none">查看完整報表 →</a>
        </div>
        <?php if (!empty($topProducts)): ?>
            <div class="d-flex flex-wrap gap-2">
                <?php foreach ($topProducts as $prod): ?>
                    <span class="badge bg-light text-dark border px-2 py-1 small">
                        <?= e($prod['name']) ?> <span class="text-muted">(<?= (int)$prod['cnt'] ?>)</span>
                    </span>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="small text-muted">本月暫無產品銷售記錄</div>
        <?php endif; ?>
    </div>
</div>

<!-- A56：Phase 3 - 本月營業額 vs 上月比較（數據洞察） -->
<div class="card mb-4">
    <div class="card-body py-3">
        <div class="fw-semibold small mb-1">本月營業額 vs 上月</div>
        <div class="d-flex align-items-baseline gap-2">
            <div class="fs-5 fw-semibold"><?= format_money($thisMonthTotal) ?></div>
            <?php if ($lastMonthTotal > 0): ?>
                <div class="small <?= $salesDiff >= 0 ? 'text-success' : 'text-danger' ?>">
                    <?= $salesDiff >= 0 ? '+' : '' ?><?= format_money($salesDiff) ?> 
                    (<?= $salesDiffPercent >= 0 ? '+' : '' ?><?= $salesDiffPercent ?>%)
                </div>
            <?php else: ?>
                <div class="small text-muted">上月無數據</div>
            <?php endif; ?>
        </div>
        <div class="small text-muted mt-1">較上月</div>
        <a href="/reports.php" class="small text-muted text-decoration-none d-inline-block mt-2">查看完整報表 →</a>
    </div>
</div>

<!-- A57：Phase 3 - 本月 Top 3 員工銷售（數據洞察） -->
<div class="card mb-4">
    <div class="card-body py-3">
        <div class="d-flex align-items-center justify-content-between mb-2">
            <div class="fw-semibold small">本月 Top 3 員工銷售</div>
            <a href="/reports.php" class="small text-muted text-decoration-none">查看完整報表 →</a>
        </div>
        <?php if (!empty($topStaff)): ?>
            <div class="d-flex flex-wrap gap-2">
                <?php foreach ($topStaff as $staff): ?>
                    <span class="badge bg-light text-dark border px-2 py-1 small">
                        <?= e($staff['name']) ?> <span class="text-muted">(<?= format_money($staff['revenue']) ?>)</span>
                    </span>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="small text-muted">本月暫無銷售記錄</div>
        <?php endif; ?>
    </div>
</div>

<!-- A58：Phase 3 - 本月新客戶數（數據洞察） -->
<div class="card mb-4">
    <div class="card-body py-3">
        <div class="d-flex align-items-center justify-content-between mb-1">
            <div class="fw-semibold small">本月新客戶數</div>
            <a href="/customers.php" class="small text-muted text-decoration-none">查看客戶 →</a>
        </div>
        <div class="fs-5 fw-semibold"><?= (int)($newCustomersThisMonth['cnt'] ?? 0) ?> 位</div>
        <div class="small text-muted">本月新增</div>
    </div>
</div>

<!-- A59：Phase 3 - 平均客單價 vs 上月（數據洞察） -->
<div class="card mb-4">
    <div class="card-body py-3">
        <div class="fw-semibold small mb-1">平均客單價 vs 上月</div>
        <div class="d-flex align-items-baseline gap-2">
            <div class="fs-5 fw-semibold">HK$ <?= number_format($thisMonthAvgTicket) ?></div>
            <div class="small <?= $avgTicketDiff >= 0 ? 'text-success' : 'text-danger' ?>">
                <?= $avgTicketDiff >= 0 ? '+' : '' ?>HK$ <?= number_format($avgTicketDiff) ?>
            </div>
        </div>
        <div class="small text-muted mt-1">較上月</div>
        <a href="/reports.php" class="small text-muted text-decoration-none d-inline-block mt-2">查看完整報表 →</a>
    </div>
</div>

<!-- A60：Phase 3 - 本月交易數 vs 上月（數據洞察） -->
<div class="card mb-4">
    <div class="card-body py-3">
        <div class="fw-semibold small mb-1">本月交易數 vs 上月</div>
        <div class="d-flex align-items-baseline gap-2">
            <div class="fs-5 fw-semibold"><?= (int)($thisMonthTxCount['cnt'] ?? 0) ?> 單</div>
            <div class="small <?= ($thisMonthTxCount['cnt'] - $lastMonthTxCount['cnt']) >= 0 ? 'text-success' : 'text-danger' ?>">
                <?= ($thisMonthTxCount['cnt'] - $lastMonthTxCount['cnt']) >= 0 ? '+' : '' ?><?= (int)($thisMonthTxCount['cnt'] - $lastMonthTxCount['cnt']) ?> 單
            </div>
        </div>
        <div class="small text-muted mt-1">較上月</div>
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
