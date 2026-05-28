<?php
/**
 * SalonEase - 營業報表
 */
require_once __DIR__ . '/includes/auth.php';
require_role(['admin', 'manager']);

$pageTitle = '報表';
$pageSubtitle = '營業數據與分析';
include __DIR__ . '/includes/header.php';
?>
<div class="max-w-6xl mx-auto" x-data="reportsApp()">
    <div x-show="loading" class="text-center small text-muted mb-3">載入中...</div>

    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
        <div>
            <h2 class="h4 fw-semibold mb-1">營業報表</h2>
            <p class="text-muted small mb-0">查看銷售、付款及套票使用情況</p>
        </div>

        <!-- 快速日期選擇 + 員工篩選 -->
        <div class="d-flex flex-wrap align-items-center gap-2">
            <button @click="setQuickRange('today')" 
                    :class="activeRange === 'today' ? 'btn btn-dark' : 'btn btn-outline-secondary'"
                    class="btn btn-sm">今日</button>
            <button @click="setQuickRange('week')" 
                    :class="activeRange === 'week' ? 'btn btn-dark' : 'btn btn-outline-secondary'"
                    class="btn btn-sm">本週</button>
            <button @click="setQuickRange('month')" 
                    :class="activeRange === 'month' ? 'btn btn-dark' : 'btn btn-outline-secondary'"
                    class="btn btn-sm">本月</button>
            
            <div class="d-flex align-items-center border rounded px-2 py-1 small bg-white">
                <input type="date" x-model="from" @change="activeRange='custom'; loadAll()" class="form-control form-control-sm border-0 p-0" style="width: 120px;">
                <span class="text-muted mx-1">至</span>
                <input type="date" x-model="to" @change="activeRange='custom'; loadAll()" class="form-control form-control-sm border-0 p-0" style="width: 120px;">
            </div>

            <!-- 員工篩選 -->
            <select x-model="selectedStaffId" @change="loadAll()" class="form-select form-select-sm" style="width: auto;">
                <option value="">全部員工</option>
                <template x-for="s in staffList" :key="s.id">
                    <option :value="s.id" x-text="s.name"></option>
                </template>
            </select>
        </div>
    </div>

    <!-- 四大統計卡片 -->
    <div class="row g-3 mb-4">
        <div class="col-12 col-sm-6 col-md-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-uppercase text-muted small mb-1">總營業額</div>
                    <div class="fs-4 fw-semibold" x-text="formatMoney(summary.total_sales)">HK$ 0</div>
                    <div class="small" :class="getChangeClass(summary.total_sales, prevSummary.total_sales)" x-text="getChangeText(summary.total_sales, prevSummary.total_sales)"></div>
                    <div class="small text-success mt-1" x-text="summary.total_transactions + ' 單'"></div>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-md-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-uppercase text-muted small mb-1">平均每單</div>
                    <div class="fs-4 fw-semibold" x-text="formatMoney(summary.avg_ticket)">HK$ 0</div>
                    <div class="small" :class="getChangeClass(summary.avg_ticket, prevSummary.avg_ticket)" x-text="getChangeText(summary.avg_ticket, prevSummary.avg_ticket)"></div>
                    <div class="small text-muted mt-1">折扣總額 <span x-text="formatMoney(summary.total_discount)"></span></div>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-md-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-uppercase text-muted small mb-1">套票扣減次數</div>
                    <div class="fs-4 fw-semibold text-purple" x-text="summary.package_sessions"></div>
                    <div class="small" :class="getChangeClass(summary.package_sessions, prevSummary.package_sessions)" x-text="getChangeText(summary.package_sessions, prevSummary.package_sessions)"></div>
                    <div class="small text-muted mt-1">客戶使用療程卡</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-md-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-uppercase text-muted small mb-1">查詢期間</div>
                    <div class="fw-medium" x-text="from + ' ~ ' + to"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- A61：Phase 3 - 本月 Top 3 員工銷售（簡單摘要） -->
    <div class="mb-3" x-show="staffRanking.length > 0">
        <div class="small text-muted mb-1">本月 Top 3 員工</div>
        <div class="d-flex gap-2 flex-wrap">
            <template x-for="s in staffRanking.slice(0,3)" :key="s.staff_id">
                <span class="badge bg-light border small px-2 py-1">
                    <span x-text="s.staff_name"></span>
                    <span class="text-muted ms-1" x-text="formatMoney(s.total_sales)"></span>
                </span>
            </template>
        </div>
    </div>

    <!-- A63：Phase 3 - 熱門服務 Top 3（簡單摘要） -->
    <div class="mb-3" x-show="topServices.length > 0">
        <div class="small text-muted mb-1">熱門服務 Top 3</div>
        <div class="d-flex gap-2 flex-wrap">
            <template x-for="s in topServices.slice(0,3)" :key="s.name">
                <span class="badge bg-light border small px-2 py-1">
                    <span x-text="s.name"></span>
                    <span class="text-muted ms-1" x-text="s.qty + ' 次'"></span>
                </span>
            </template>
        </div>
    </div>

    <!-- A64：Phase 3 - 熱門產品 Top 3（簡單摘要） -->
    <div class="mb-3" x-show="topProducts.length > 0">
        <div class="small text-muted mb-1">熱門產品 Top 3</div>
        <div class="d-flex gap-2 flex-wrap">
            <template x-for="p in topProducts.slice(0,3)" :key="p.name">
                <span class="badge bg-light border small px-2 py-1">
                    <span x-text="p.name"></span>
                    <span class="text-muted ms-1" x-text="p.qty + ' 件'"></span>
                </span>
            </template>
        </div>
    </div>

    <!-- A65：Phase 3 - 套票扣減 Top 3（簡單摘要） -->
    <div class="mb-3" x-show="packageRedemptions.length > 0">
        <div class="small text-muted mb-1">套票扣減 Top 3</div>
        <div class="d-flex gap-2 flex-wrap">
            <template x-for="p in packageRedemptions.slice(0,3)" :key="p.package_name">
                <span class="badge bg-light border small px-2 py-1">
                    <span x-text="p.package_name"></span>
                    <span class="text-muted ms-1" x-text="p.total_sessions + ' 次'"></span>
                </span>
            </template>
        </div>
    </div>

    <!-- A91：Phase 3 - 簡單摘要小標題（本期 vs 上期 對比） -->
    <div class="small fw-semibold text-muted mb-2 mt-1">本期 vs 上期 對比</div>
    <div class="small text-muted mb-2" style="font-size:0.75rem;">顯示當前查詢期間與上期比較</div>
    <div class="small text-muted mb-3" style="font-size:0.7rem;">（隨上方日期與員工篩選即時更新）</div>

    <!-- A67：Phase 3 - 本月交易數 vs 上月（簡單摘要） -->
    <div class="mb-3">
        <div class="small text-muted mb-1">本月交易數 vs 上月</div>
        <div class="d-flex align-items-baseline gap-2">
            <span class="fw-medium" x-text="summary.total_transactions + ' 單'"></span>
            <span class="small" :class="getChangeClass(summary.total_transactions, prevSummary.total_transactions)" x-text="getChangeText(summary.total_transactions, prevSummary.total_transactions)"></span>
        </div>
    </div>

    <!-- A68：Phase 3 - 本月套票扣減 vs 上月（簡單摘要） -->
    <div class="mb-3">
        <div class="small text-muted mb-1">本月套票扣減 vs 上月</div>
        <div class="d-flex align-items-baseline gap-2">
            <span class="fw-medium" x-text="summary.package_sessions + ' 次'"></span>
            <span class="small" :class="getChangeClass(summary.package_sessions, prevSummary.package_sessions)" x-text="getChangeText(summary.package_sessions, prevSummary.package_sessions)"></span>
        </div>
    </div>

    <!-- A69：Phase 3 - 本月平均每單 vs 上月（簡單摘要） -->
    <div class="mb-3">
        <div class="small text-muted mb-1">本月平均每單 vs 上月</div>
        <div class="d-flex align-items-baseline gap-2">
            <span class="fw-medium" x-text="formatMoney(summary.avg_ticket)"></span>
            <span class="small" :class="getChangeClass(summary.avg_ticket, prevSummary.avg_ticket)" x-text="getChangeText(summary.avg_ticket, prevSummary.avg_ticket)"></span>
        </div>
    </div>

    <!-- A89：Phase 3 - 本月套票扣減次數 vs 上月（簡單摘要） -->
    <div class="mb-3">
        <div class="small text-muted mb-1">本月套票扣減次數 vs 上月</div>
        <div class="d-flex align-items-baseline gap-2">
            <span class="fw-medium" x-text="summary.package_sessions + ' 次'"></span>
            <span class="small" :class="getChangeClass(summary.package_sessions, prevSummary.package_sessions)" x-text="getChangeText(summary.package_sessions, prevSummary.package_sessions)"></span>
        </div>
    </div>

    <!-- A90：Phase 3 - 本月總營業額 vs 上月（簡單摘要） -->
    <div class="mb-3">
        <div class="small text-muted mb-1">本月總營業額 vs 上月</div>
        <div class="d-flex align-items-baseline gap-2">
            <span class="fw-medium" x-text="formatMoney(summary.total_sales)"></span>
            <span class="small" :class="getChangeClass(summary.total_sales, prevSummary.total_sales)" x-text="getChangeText(summary.total_sales, prevSummary.total_sales)"></span>
        </div>
    </div>

    <!-- 圖表 -->
    <div class="small fw-semibold text-muted mb-2">圖表</div>
    <div class="small text-muted mb-3" style="font-size:0.7rem;">付款方式與熱門服務收入分佈</div>
    <div class="small text-muted mb-3" style="font-size:0.65rem;">（隨查詢條件即時更新，可使用上方快速日期按鈕或自訂日期範圍）</div>

    <!-- 簡單視覺圖表 -->
    <div class="row g-3 mb-3">
        <div class="col-12 col-md-6">
            <div class="card h-100">
                <div class="card-body">
                    <div class="fw-semibold mb-3">付款方式分佈</div>
                    <canvas id="paymentChart" height="140"></canvas>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6">
            <div class="card h-100">
                <div class="card-body">
                    <div class="fw-semibold mb-3">熱門服務收入</div>
                    <canvas id="servicesChart" height="140"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- 銷售趨勢圖表（A125 起步） -->
    <div class="card mb-3">
        <div class="card-body">
            <div class="fw-semibold mb-3 d-flex align-items-center">
                銷售趨勢
                <span class="badge ms-2 small" :class="getChangeClass(summary.total_sales, prevSummary.total_sales)">
                    {{ getChangeText(summary.total_sales, prevSummary.total_sales) }}
                </span>
                <span class="ms-2 small text-muted">總 {{ formatMoney(summary.total_sales) }}</span>
                <span class="ms-2 small text-muted">變 {{ formatMoney(summary.total_sales - prevSummary.total_sales) }}</span>
                <span class="ms-2 small text-muted">日均 {{ formatMoney(summary.total_sales / Math.max(1, Math.ceil( (new Date(to) - new Date(from)) / (1000*60*60*24) )) ) }}</span>
                <span class="ms-2 small text-muted">上期 {{ formatMoney(prevSummary.total_sales) }}</span>
            </div>

            <!-- A142：loading 與空資料提示 -->
            <div v-if="dailySalesLoading" class="text-center py-4 text-muted small">
                <span class="spinner-border spinner-border-sm me-2"></span> 載入每日銷售數據中...
            </div>
            <div v-else-if="dailySalesData.length === 0" class="text-center py-4 text-muted small border rounded bg-light">
                此日期範圍內暫無銷售記錄
            </div>
            <canvas v-show="!dailySalesLoading && dailySalesData.length > 0" id="salesTrendChart" height="80"></canvas>

            <div class="small text-muted mt-1" style="font-size:0.7rem;">
                每日平均 ({{ Math.max(1, Math.ceil( (new Date(to) - new Date(from)) / (1000*60*60*24) )) }} 日)：{{ formatMoney( summary.total_sales / Math.max(1, Math.ceil( (new Date(to) - new Date(from)) / (1000*60*60*24) )) ) }}
            </div>
            <div class="small text-muted mt-1" style="font-size:0.7rem;">
                總變化：{{ formatMoney(summary.total_sales - prevSummary.total_sales) }}
            </div>
            <div class="small text-muted mt-1" style="font-size:0.7rem;">
                總銷售：{{ formatMoney(summary.total_sales) }}
            </div>
        </div>
    </div>

    <!-- 員工銷售排行 -->
    <div class="card mb-3">
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <div class="fw-semibold">員工銷售排行</div>
                <button @click="exportStaffRankingCSV()" class="btn btn-sm btn-outline-secondary">
                    📄 匯出 CSV
                </button>
            </div>

            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light small text-muted">
                        <tr>
                            <th>員工</th>
                            <th class="text-end">單數</th>
                            <th class="text-end">營業額</th>
                            <th class="text-end">平均每單</th>
                            <th class="text-end">套票扣減</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="s in staffRanking" :key="s.staff_id">
                            <tr>
                                <td class="fw-medium" x-text="s.staff_name"></td>
                                <td class="text-end" x-text="s.transaction_count"></td>
                                <td class="text-end fw-semibold" x-text="formatMoney(s.total_sales)"></td>
                                <td class="text-end" x-text="formatMoney(s.avg_ticket)"></td>
                                <td class="text-end text-purple fw-medium" x-text="s.package_sessions + ' 次'"></td>
                            </tr>
                        </template>
                        <tr x-show="staffRanking.length === 0">
                            <td colspan="5" class="py-4 text-center text-muted">查詢期間暫無銷售記錄</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- A143：庫存分析（周轉率 + 缺貨趨勢） -->
    <div class="card mb-3">
        <div class="card-body">
            <div class="fw-semibold mb-3 d-flex align-items-center justify-content-between">
                庫存分析
                <span class="small text-muted" x-show="inventoryLoading">載入中...</span>
            </div>

            <div class="row g-3">
                <!-- 庫存周轉率 -->
                <div class="col-12 col-lg-6">
                    <div class="fw-medium small mb-2">庫存周轉率（前 8 名）</div>
                    <div x-show="inventoryLoading" class="text-center py-3 text-muted small">
                        <span class="spinner-border spinner-border-sm me-2"></span> 計算中...
                    </div>
                    <canvas x-show="!inventoryLoading && inventoryTurnover.length > 0" id="inventoryTurnoverChart" height="120"></canvas>
                    <div x-show="!inventoryLoading && inventoryTurnover.length === 0" class="text-muted small py-3 text-center border rounded bg-light">
                        查詢期間無產品銷售記錄
                    </div>

                    <div class="small mt-2" style="max-height: 120px; overflow:auto;" x-show="inventoryTurnover.length > 0">
                        <template x-for="p in inventoryTurnover.slice(0,5)">
                            <div class="d-flex justify-content-between small py-0.5 border-bottom">
                                <div x-text="p.name"></div>
                                <div class="text-muted">
                                    銷 <span x-text="p.sales_qty"></span> / 庫 <span x-text="p.stock_qty"></span> 
                                    <span class="fw-medium text-success" x-text="p.turnover + 'x'"></span>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- 缺貨趨勢 -->
                <div class="col-12 col-lg-6">
                    <div class="fw-medium small mb-2">缺貨趨勢（低庫存產品數）</div>
                    <div x-show="inventoryLoading" class="text-center py-3 text-muted small">
                        <span class="spinner-border spinner-border-sm me-2"></span> 計算中...
                    </div>
                    <canvas x-show="!inventoryLoading && stockoutTrend.length > 0" id="stockoutTrendChart" height="120"></canvas>
                    <div x-show="!inventoryLoading && stockoutTrend.length === 0" class="text-muted small py-3 text-center border rounded bg-light">
                        查詢期間無缺貨趨勢數據
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- A144：員工表現趨勢 -->
    <div class="card mb-3">
        <div class="card-body">
            <div class="fw-semibold mb-3 d-flex align-items-center justify-content-between">
                員工表現趨勢
                <span class="small text-muted" x-show="staffPerformanceLoading">載入中...</span>
            </div>

            <div x-show="staffPerformanceLoading" class="text-center py-4 text-muted small">
                <span class="spinner-border spinner-border-sm me-2"></span> 載入員工表現數據中...
            </div>

            <div x-show="!staffPerformanceLoading && staffPerformanceTrend.length === 0" class="text-muted small py-4 text-center border rounded bg-light">
                查詢期間暫無員工銷售記錄
            </div>

            <canvas x-show="!staffPerformanceLoading && staffPerformanceTrend.length > 0" id="staffPerformanceChart" height="140"></canvas>

            <div class="small text-muted mt-2" style="font-size:0.75rem;" x-show="staffPerformanceTrend.length > 0">
                顯示前 5 位員工的每日銷售走勢（隨日期範圍與員工篩選即時更新）
            </div>
        </div>
    </div>

    <div class="row g-3">
        
        <!-- 付款方式分佈 -->
        <div class="col-12 col-lg-6">
            <div class="card h-100">
                <div class="card-body">
                    <div class="fw-semibold mb-3">付款方式分佈</div>
                    <div x-show="paymentBreakdown.length > 0">
                        <template x-for="item in paymentBreakdown" :key="item.method">
                            <div class="d-flex justify-content-between align-items-center small py-1 border-bottom">
                                <div class="d-flex align-items-center gap-3">
                                    <span class="fw-medium" style="width: 80px;" x-text="getPaymentLabel(item.method)"></span>
                                    <span class="text-muted" x-text="item.count + ' 單'"></span>
                                </div>
                                <div class="fw-semibold" x-text="formatMoney(item.amount)"></div>
                            </div>
                        </template>
                    </div>
                    <div x-show="paymentBreakdown.length === 0" class="text-muted small py-4 text-center">
                        查詢期間暫無銷售記錄
                    </div>
                </div>
            </div>
        </div>

        <!-- 套票扣減明細 -->
        <div class="col-12 col-lg-6">
            <div class="card h-100">
                <div class="card-body">
                    <div class="fw-semibold mb-3">套票扣減統計</div>
                    <div x-show="packageRedemptions.length > 0">
                        <template x-for="p in packageRedemptions" :key="p.package_name">
                            <div class="d-flex justify-content-between small py-1 border-bottom">
                                <div x-text="p.package_name"></div>
                                <div class="text-end">
                                    <span class="fw-medium" x-text="p.total_sessions + ' 次'"></span>
                                    <span class="text-muted ms-1 small" x-text="'(' + p.times + ' 單)'"></span>
                                </div>
                            </div>
                        </template>
                    </div>
                    <div x-show="packageRedemptions.length === 0" class="text-muted small py-4 text-center">
                        查詢期間無套票扣減
                    </div>
                </div>
            </div>
        </div>

        <!-- 熱門服務 -->
        <div class="col-12 col-lg-6">
            <div class="card h-100">
                <div class="card-body">
                    <div class="fw-semibold mb-3">熱門服務 Top 5</div>
                    <div x-show="topServices.length > 0" class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead class="small text-muted">
                                <tr>
                                    <th>服務名稱</th>
                                    <th class="text-end">次數</th>
                                    <th class="text-end">收入</th>
                                </tr>
                            </thead>
                            <tbody class="small">
                                <template x-for="s in topServices" :key="s.name">
                                    <tr>
                                        <td x-text="s.name"></td>
                                        <td class="text-end" x-text="s.qty"></td>
                                        <td class="text-end fw-medium" x-text="formatMoney(s.revenue)"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                    <div x-show="topServices.length === 0" class="text-muted small py-4 text-center">暫無數據</div>
                </div>
            </div>
        </div>

        <!-- 熱門產品 -->
        <div class="col-12 col-lg-6">
            <div class="card h-100">
                <div class="card-body">
                    <div class="fw-semibold mb-3">熱門產品 Top 5</div>
                    <div x-show="topProducts.length > 0" class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead class="small text-muted">
                                <tr>
                                    <th>產品名稱</th>
                                    <th class="text-end">售出</th>
                                    <th class="text-end">收入</th>
                                </tr>
                            </thead>
                            <tbody class="small">
                                <template x-for="p in topProducts" :key="p.name">
                                    <tr>
                                        <td x-text="p.name"></td>
                                        <td class="text-end" x-text="p.qty"></td>
                                        <td class="text-end fw-medium" x-text="formatMoney(p.revenue)"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                    <div x-show="topProducts.length === 0" class="text-muted small py-4 text-center">暫無數據</div>
                </div>
            </div>
        </div>

    </div>

    <div class="mt-4 small text-muted text-center">
        數據以結帳日期（sale_date）為準 · 按 F5 刷新頁面
    </div>
</div>

<!-- Chart.js CDN（極輕量，僅本頁使用） -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

<script>
function reportsApp() {
    return {
        from: '<?= date('Y-m-d') ?>',
        to: '<?= date('Y-m-d') ?>',
        activeRange: 'today',
        selectedStaffId: '',          // 新增：員工篩選
        staffList: [],                // 員工下拉清單
        staffRanking: [],             // 員工銷售排行數據
        loading: false,

        // 圖表實例
        paymentChart: null,
        servicesChart: null,
        salesTrendChart: null,   // A125 新增：銷售趨勢圖表
        dailySalesData: [],      // A141 新增：真實每日銷售數據
        dailySalesLoading: false, // A142 新增：每日銷售數據載入中狀態

        // A143：庫存報表
        inventoryTurnover: [],
        stockoutTrend: [],
        inventoryLoading: false,
        inventoryTurnoverChart: null,
        stockoutTrendChart: null,

        // A144：員工表現趨勢
        staffPerformanceTrend: [],
        staffPerformanceLoading: false,
        staffPerformanceChart: null,

        summary: {
            total_sales: 0,
            total_transactions: 0,
            avg_ticket: 0,
            total_discount: 0,
            package_sessions: 0
        },
        prevSummary: {
            total_sales: 0,
            total_transactions: 0,
            avg_ticket: 0,
            total_discount: 0,
            package_sessions: 0
        },
        paymentBreakdown: [],
        topServices: [],
        topProducts: [],
        packageRedemptions: [],

        init() {
            this.loadStaffList();
            this.setQuickRange('today');
        },

        async loadAll() {
            this.loading = true;
            try {
                await Promise.all([
                    this.loadSummary(),
                    this.loadPrevSummary(),
                    this.loadPaymentBreakdown(),
                    this.loadTopServices(),
                    this.loadTopProducts(),
                    this.loadPackageRedemptions(),
                    this.loadStaffRanking(),
                    this.loadDailySales(),   // A141
                    this.loadInventoryTurnover(), // A143
                    this.loadStockoutTrend(),     // A143
                    this.loadStaffPerformanceTrend() // A144
                ]);
            } finally {
                this.loading = false;
                // 數據載入後更新圖表
                this.$nextTick(() => this.updateCharts());
            }
        },

        // 暴露給熱鍵系統使用
        loadAll: function() { this.loadAll(); },

        // A62 小工具：計算「較上期」文字與顏色
        getChangeText(curr, prev) {
            if (!prev || prev === 0) return '';
            const diff = ((curr - prev) / prev) * 100;
            const sign = diff >= 0 ? '+' : '';
            return `${sign}${diff.toFixed(1)}%`;
        },
        getChangeClass(curr, prev) {
            if (!prev || prev === 0) return 'text-muted';
            return (curr - prev) >= 0 ? 'text-success' : 'text-danger';
        },

        async loadSummary() {
            try {
                const res = await SalonEase.fetch(`/api/reports.php?action=summary&from=${this.from}&to=${this.to}`);
                this.summary = res.data;
            } catch (e) {
                console.error(e);
            }
        },

        async loadPaymentBreakdown() {
            try {
                const res = await SalonEase.fetch(`/api/reports.php?action=payment_breakdown&from=${this.from}&to=${this.to}`);
                this.paymentBreakdown = res.data || [];
            } catch (e) {}
        },

        async loadTopProducts() {
            try {
                const res = await SalonEase.fetch(`/api/reports.php?action=top_products&from=${this.from}&to=${this.to}&limit=5`);
                this.topProducts = res.data || [];
            } catch (e) {}
        },

        async loadTopServices() {
            try {
                const res = await SalonEase.fetch(`/api/reports.php?action=top_services&from=${this.from}&to=${this.to}&limit=5`);
                this.topServices = res.data || [];
            } catch (e) {}
        },

        async loadPackageRedemptions() {
            try {
                const res = await SalonEase.fetch(`/api/reports.php?action=package_redemptions&from=${this.from}&to=${this.to}`);
                this.packageRedemptions = res.data || [];
            } catch (e) {}
        },

        // A62：載入上期數據以計算「較上期」百分比
        async loadPrevSummary() {
            const duration = new Date(this.to) - new Date(this.from);
            const prevTo = new Date(new Date(this.from).getTime() - 86400000); // 前一天
            const prevFrom = new Date(prevTo.getTime() - duration);
            const pFrom = prevFrom.toISOString().slice(0,10);
            const pTo = prevTo.toISOString().slice(0,10);

            try {
                const res = await SalonEase.fetch(`/api/reports.php?action=summary&from=${pFrom}&to=${pTo}`);
                this.prevSummary = res.data;
            } catch (e) {
                this.prevSummary = { total_sales: 0, total_transactions: 0, avg_ticket: 0, total_discount: 0, package_sessions: 0 };
            }
        },

        // 載入員工銷售排行
        async loadStaffRanking() {
            try {
                let url = `/api/reports.php?action=staff_sales_ranking&from=${this.from}&to=${this.to}`;
                if (this.selectedStaffId) {
                    url += `&staff_id=${this.selectedStaffId}`;
                }
                const res = await SalonEase.fetch(url);
                this.staffRanking = res.data || [];
            } catch (e) {
                this.staffRanking = [];
            }
        },

        // A141/A142：載入真實每日銷售數據（支援 staff 篩選 + loading 狀態）
        async loadDailySales() {
            this.dailySalesLoading = true;
            try {
                let url = `/api/reports.php?action=daily_sales&from=${this.from}&to=${this.to}`;
                if (this.selectedStaffId) {
                    url += `&staff_id=${this.selectedStaffId}`;
                }
                const res = await SalonEase.fetch(url);
                this.dailySalesData = res.data || [];
            } catch (e) {
                console.warn('載入每日銷售數據失敗', e);
                this.dailySalesData = [];
            } finally {
                this.dailySalesLoading = false;
            }
        },

        // A143：載入庫存周轉率
        async loadInventoryTurnover() {
            this.inventoryLoading = true;
            try {
                const res = await SalonEase.fetch(`/api/reports.php?action=inventory_turnover&from=${this.from}&to=${this.to}`);
                this.inventoryTurnover = res.data || [];
            } catch (e) {
                console.warn('載入庫存周轉率失敗', e);
                this.inventoryTurnover = [];
            }
        },

        // A143：載入缺貨趨勢
        async loadStockoutTrend() {
            try {
                const res = await SalonEase.fetch(`/api/reports.php?action=stockout_trend&from=${this.from}&to=${this.to}`);
                this.stockoutTrend = res.data || [];
            } catch (e) {
                console.warn('載入缺貨趨勢失敗', e);
                this.stockoutTrend = [];
            } finally {
                this.inventoryLoading = false;
            }
        },

        // A144：載入員工表現趨勢
        async loadStaffPerformanceTrend() {
            this.staffPerformanceLoading = true;
            try {
                let url = `/api/reports.php?action=staff_performance_trend&from=${this.from}&to=${this.to}`;
                if (this.selectedStaffId) {
                    url += `&staff_id=${this.selectedStaffId}`;
                }
                const res = await SalonEase.fetch(url);
                this.staffPerformanceTrend = res.data || [];
            } catch (e) {
                console.warn('載入員工表現趨勢失敗', e);
                this.staffPerformanceTrend = [];
            } finally {
                this.staffPerformanceLoading = false;
            }
        },

        formatDate(d) {
            return d.toISOString().split('T')[0];
        },

        formatMoney(amount) {
            return 'HK$ ' + parseFloat(amount || 0).toLocaleString('zh-HK', { minimumFractionDigits: 0 });
        },

        // A142 補充：報表頁 badge 變化文字與顏色（與 dashboard 一致）
        getChangeText(curr, prev) {
            const c = parseFloat(curr || 0);
            const p = parseFloat(prev || 0);
            const diff = c - p;
            const pct = p > 0 ? ((diff / p) * 100) : (c > 0 ? 100 : 0);
            const sign = diff >= 0 ? '+' : '';
            return `${sign}${pct.toFixed(1)}%`;
        },
        getChangeClass(curr, prev) {
            const diff = parseFloat(curr || 0) - parseFloat(prev || 0);
            return diff > 0 ? 'text-success' : (diff < 0 ? 'text-danger' : 'text-muted');
        },

        getPaymentLabel(method) {
            // Phase 2: API 已回傳 payment_methods 表的顯示名稱，直接使用
            return method || '未知';
        },

        // 載入員工清單（供篩選使用）
        async loadStaffList() {
            try {
                const res = await SalonEase.fetch('/api/staff.php?action=list&is_active=1');
                this.staffList = res.data || [];
            } catch (e) {
                console.warn('載入員工清單失敗', e);
            }
        },

        // 匯出員工排行 CSV
        exportStaffRankingCSV() {
            if (!this.staffRanking.length) {
                SalonEase.toast('目前沒有可匯出的排行資料', 'error');
                return;
            }

            let csv = '\uFEFF員工,單數,營業額,平均每單,套票扣減次數\n';
            this.staffRanking.forEach(r => {
                csv += `${r.staff_name},${r.transaction_count},${r.total_sales},${r.avg_ticket},${r.package_sessions}\n`;
            });

            const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            const url = URL.createObjectURL(blob);
            link.href = url;
            link.download = `員工銷售排行_${this.from}_${this.to}.csv`;
            link.click();
            URL.revokeObjectURL(url);
        },

        // 初始化 / 更新圖表
        updateCharts() {
            // 付款方式圓餅圖
            const paymentCtx = document.getElementById('paymentChart');
            if (paymentCtx) {
                if (this.paymentChart) this.paymentChart.destroy();
                const labels = this.paymentBreakdown.map(p => this.getPaymentLabel(p.method));
                const data = this.paymentBreakdown.map(p => p.amount);
                this.paymentChart = new Chart(paymentCtx, {
                    type: 'doughnut',
                    data: {
                        labels: labels,
                        datasets: [{
                            data: data,
                            backgroundColor: ['#2C2C2E', '#8FA68F', '#C97C7C', '#F3EDE6', '#A8A29E']
                        }]
                    },
                    options: { plugins: { legend: { position: 'bottom' } } }
                });
            }

            // 熱門服務長條圖
            const serviceCtx = document.getElementById('servicesChart');
            if (serviceCtx) {
                if (this.servicesChart) this.servicesChart.destroy();
                const labels = this.topServices.map(s => s.name.length > 12 ? s.name.substring(0,12)+'...' : s.name);
                const data = this.topServices.map(s => s.revenue);
                this.servicesChart = new Chart(serviceCtx, {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: '收入',
                            data: data,
                            backgroundColor: '#8FA68F'
                        }]
                    },
                    options: {
                        plugins: { legend: { display: false } },
                        scales: { y: { beginAtZero: true } }
                    }
                });
            }

            // 銷售趨勢圖表（A141：真實每日數據 + 完整保留 A125-A139 所有視覺元素）
            const trendCtx = document.getElementById('salesTrendChart');
            if (trendCtx) {
                if (this.salesTrendChart) this.salesTrendChart.destroy();

                let labels = [];
                let salesData = [];
                let dailyAvg = 0;
                let prevDaily = 0;

                if (this.dailySalesData && this.dailySalesData.length > 0) {
                    // 真實路徑（A141 核心）
                    labels = this.dailySalesData.map(d => d.date.substring(5)); // MM-DD 簡潔
                    salesData = this.dailySalesData.map(d => d.total_sales);
                    const sum = salesData.reduce((a, b) => a + b, 0);
                    dailyAvg = salesData.length > 0 ? (sum / salesData.length) : 0;
                    // 上期水平參考值（用 prevSummary 總額 / 當前點數 作為每日平均 proxy，保留對比意義）
                    const days = Math.max(1, salesData.length);
                    prevDaily = (this.prevSummary.total_sales || 0) / days;
                } else {
                    // 無資料 fallback（保留原有行為）
                    const prev = this.prevSummary.total_sales || 0;
                    const curr = this.summary.total_sales || 0;
                    const fromDate = this.from;
                    const toDate = this.to;
                    const days = Math.max(1, Math.ceil( (new Date(toDate) - new Date(fromDate)) / (1000*60*60*24) ));
                    dailyAvg = curr / days;
                    prevDaily = prev / days;
                    for (let i = 0; i < 5; i++) {
                        const ratio = i / 4;
                        const labelDate = new Date( new Date(fromDate).getTime() + (new Date(toDate).getTime() - new Date(fromDate).getTime()) * ratio ).toISOString().slice(5,10);
                        labels.push(labelDate);
                        salesData.push( prev + (curr - prev) * ratio );
                    }
                }

                const avgLine = new Array(labels.length).fill(dailyAvg);
                const prevLine = new Array(labels.length).fill(prevDaily);

                this.salesTrendChart = new Chart(trendCtx, {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: '每日銷售',
                            data: salesData,
                            borderColor: '#8FA68F',
                            backgroundColor: 'rgba(143, 166, 143, 0.15)',
                            tension: 0.25,
                            fill: true
                        }, {
                            label: '上期水平',
                            data: prevLine,
                            borderColor: '#C97C7C',
                            borderDash: [5, 5],
                            borderWidth: 1.5,
                            pointRadius: 0,
                            fill: false
                        }, {
                            label: '每日平均',
                            data: avgLine,
                            borderColor: '#6B7280',
                            borderDash: [2, 2],
                            borderWidth: 1.5,
                            pointRadius: 0,
                            fill: false
                        }]
                    },
                    options: {
                        plugins: {
                            legend: { display: false },
                            // A142：強化 tooltip，清楚顯示每日數據
                            tooltip: {
                                callbacks: {
                                    title: (ctx) => {
                                        const idx = ctx[0].dataIndex;
                                        const d = this.dailySalesData[idx];
                                        return d ? d.date : labels[idx];
                                    },
                                    label: (ctx) => {
                                        const idx = ctx.dataIndex;
                                        const d = this.dailySalesData[idx];
                                        if (d) {
                                            return `銷售：${this.formatMoney(d.total_sales)} ｜ ${d.total_transactions} 單 ｜ 均 ${this.formatMoney(d.avg_ticket)}`;
                                        }
                                        return `銷售：${this.formatMoney(ctx.raw)}`;
                                    }
                                }
                            }
                        },
                        scales: {
                            y: { beginAtZero: true },
                            x: {
                                // A142：天數多時自動旋轉標籤，避免重疊
                                ticks: {
                                    maxRotation: 45,
                                    autoSkip: true,
                                    maxTicksLimit: 12
                                }
                            }
                        }
                    }
                });
            }

            // A143：庫存周轉率長條圖
            const invCtx = document.getElementById('inventoryTurnoverChart');
            if (invCtx) {
                if (this.inventoryTurnoverChart) this.inventoryTurnoverChart.destroy();
                const labels = this.inventoryTurnover.slice(0, 8).map(p => p.name.length > 10 ? p.name.substring(0,10)+'...' : p.name);
                const data = this.inventoryTurnover.slice(0, 8).map(p => p.turnover);
                this.inventoryTurnoverChart = new Chart(invCtx, {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: '周轉率',
                            data: data,
                            backgroundColor: '#8FA68F'
                        }]
                    },
                    options: {
                        plugins: { legend: { display: false } },
                        scales: { y: { beginAtZero: true } }
                    }
                });
            }

            // A143：缺貨趨勢線圖
            const stockoutCtx = document.getElementById('stockoutTrendChart');
            if (stockoutCtx) {
                if (this.stockoutTrendChart) this.stockoutTrendChart.destroy();
                const labels = this.stockoutTrend.map(d => d.date.substring(5));
                const data = this.stockoutTrend.map(d => d.low_stock_count);
                this.stockoutTrendChart = new Chart(stockoutCtx, {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: '低庫存產品數',
                            data: data,
                            borderColor: '#C97C7C',
                            backgroundColor: 'rgba(201, 124, 124, 0.15)',
                            tension: 0.3,
                            fill: true
                        }]
                    },
                    options: {
                        plugins: { legend: { display: false } },
                        scales: { y: { beginAtZero: true } }
                    }
                });
            }

            // A144：員工表現趨勢圖表（多員工比較）
            const perfCtx = document.getElementById('staffPerformanceChart');
            if (perfCtx) {
                if (this.staffPerformanceChart) this.staffPerformanceChart.destroy();

                if (this.staffPerformanceTrend.length > 0) {
                    // 按員工分組
                    const byStaff = {};
                    this.staffPerformanceTrend.forEach(d => {
                        if (!byStaff[d.staff_id]) {
                            byStaff[d.staff_id] = { name: d.staff_name, dates: [], sales: [] };
                        }
                        byStaff[d.staff_id].dates.push(d.date.substring(5));
                        byStaff[d.staff_id].sales.push(d.total_sales);
                    });

                    const staffIds = Object.keys(byStaff);
                    const colors = ['#8FA68F', '#C97C7C', '#6B7280', '#A78BFA', '#F59E0B'];

                    const datasets = staffIds.slice(0, 5).map((id, idx) => ({
                        label: byStaff[id].name,
                        data: byStaff[id].sales,
                        borderColor: colors[idx % colors.length],
                        backgroundColor: 'transparent',
                        tension: 0.3,
                        borderWidth: 2
                    }));

                    // 取第一個員工的日期作為 labels（假設日期對齊）
                    const labels = byStaff[staffIds[0]] ? byStaff[staffIds[0]].dates : [];

                    this.staffPerformanceChart = new Chart(perfCtx, {
                        type: 'line',
                        data: { labels, datasets },
                        options: {
                            plugins: { legend: { position: 'bottom', labels: { boxWidth: 12 } } },
                            scales: { y: { beginAtZero: true } }
                        }
                    });
                }
            }
        },

        setQuickRange(type) {
            this.activeRange = type;
            const today = new Date();
            let fromDate = new Date(today);

            if (type === 'today') {
                this.from = this.formatDate(today);
                this.to = this.formatDate(today);
            } else if (type === 'week') {
                const day = today.getDay();
                const diff = today.getDate() - day + (day === 0 ? -6 : 1); // Monday
                fromDate = new Date(today.setDate(diff));
                this.from = this.formatDate(fromDate);
                this.to = this.formatDate(new Date());
            } else if (type === 'month') {
                fromDate = new Date(today.getFullYear(), today.getMonth(), 1);
                this.from = this.formatDate(fromDate);
                this.to = this.formatDate(new Date());
            }
            this.loadAll();
        }
    }
}

// 註冊報表頁熱鍵
if (window.SalonEase && window.SalonEase.Hotkeys && window.SalonEase.Hotkeys.registerPage) {
    window.SalonEase.Hotkeys.registerPage([
        { key: 'T', desc: '切換至今日' },
        { key: 'W', desc: '切換至本週' },
        { key: 'M', desc: '切換至本月' },
        { key: 'R', desc: '重新載入報表' },
        { key: 'F5', desc: '重新載入資料（不刷新頁面）' },
    ]);
}

document.addEventListener('keydown', function(e) {
    if (!document.getElementById('reports-app') && !document.querySelector('[x-data="reportsApp()"]')) return;
    if (['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement.tagName)) return;

    const app = document.querySelector('[x-data="reportsApp()"]');
    if (!app || !app.__x) return;

    const data = app.__x.$data;
    if (!data) return;

    if (e.key.toUpperCase() === 'T') { e.preventDefault(); data.setQuickRange('today'); }
    if (e.key.toUpperCase() === 'W') { e.preventDefault(); data.setQuickRange('week'); }
    if (e.key.toUpperCase() === 'M') { e.preventDefault(); data.setQuickRange('month'); }
    if (e.key.toUpperCase() === 'R') { e.preventDefault(); data.loadAll(); }
    if (e.key === 'F5') { e.preventDefault(); data.loadAll(); }
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>