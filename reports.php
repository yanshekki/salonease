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
            </div>
            <canvas id="salesTrendChart" height="80"></canvas>
            <div class="small text-muted mt-1" style="font-size:0.7rem;">
                每日平均 ({{ Math.max(1, Math.ceil( (new Date(to) - new Date(from)) / (1000*60*60*24) )) }} 日)：{{ formatMoney( summary.total_sales / Math.max(1, Math.ceil( (new Date(to) - new Date(from)) / (1000*60*60*24) )) ) }}
            </div>
            <div class="small text-muted mt-1" style="font-size:0.7rem;">
                總變化：{{ formatMoney(summary.total_sales - prevSummary.total_sales) }}
            </div>
            <div class="small text-muted mt-2" style="font-size:0.65rem;">（隨日期範圍即時更新，後續版本將接入真實每日數據）</div>
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
                    this.loadStaffRanking()
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

        formatDate(d) {
            return d.toISOString().split('T')[0];
        },

        formatMoney(amount) {
            return 'HK$ ' + parseFloat(amount || 0).toLocaleString('zh-HK', { minimumFractionDigits: 0 });
        },

        getPaymentLabel(method) {
            const map = {
                'cash': '現金',
                'fps': '轉數快',
                'card': '信用卡/八達通',
                'wechat': 'WeChat Pay',
                'alipay': 'Alipay',
                'other': '其他'
            };
            return map[method] || method;
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

            // 銷售趨勢圖表（A127 改善：使用實際日期標籤）
            const trendCtx = document.getElementById('salesTrendChart');
            if (trendCtx) {
                if (this.salesTrendChart) this.salesTrendChart.destroy();
                const prev = this.prevSummary.total_sales || 0;
                const curr = this.summary.total_sales || 0;
                const fromDate = this.from;
                const toDate = this.to;
                const midDate = fromDate < toDate 
                    ? new Date( (new Date(fromDate).getTime() + new Date(toDate).getTime()) / 2 ).toISOString().slice(0,10)
                    : fromDate;
                const trendLabels = [fromDate, midDate, toDate];
                const trendData = [
                    prev,
                    prev + (curr - prev) * 0.5,
                    curr
                ];
                this.salesTrendChart = new Chart(trendCtx, {
                    type: 'line',
                    data: {
                        labels: trendLabels,
                        datasets: [{
                            label: '銷售額',
                            data: trendData,
                            borderColor: '#8FA68F',
                            backgroundColor: 'rgba(143, 166, 143, 0.1)',
                            tension: 0.3
                        }, {
                            label: '上期水平',
                            data: [prev, prev, prev],
                            borderColor: '#C97C7C',
                            borderDash: [5, 5],
                            borderWidth: 1,
                            pointRadius: 0,
                            fill: false
                        }]
                    },
                    options: {
                        plugins: { legend: { display: false } },
                        scales: { y: { beginAtZero: true } }
                    }
                });
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

    }
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>