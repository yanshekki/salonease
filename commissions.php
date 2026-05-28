<?php
/**
 * SalonEase - 員工佣金查詢
 */
require_once __DIR__ . '/includes/auth.php';
require_role(['admin', 'manager']);

$pageTitle = '佣金查詢';
$pageSubtitle = '按員工查看累計佣金';
include __DIR__ . '/includes/header.php';
?>
<div class="max-w-6xl mx-auto" x-data="commissionsApp()">
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
        <div>
            <h2 class="h4 fw-semibold mb-1">員工佣金查詢</h2>
            <p class="text-muted small mb-0">查看服務、零售、開單佣金（依當時設定計算）</p>
        </div>

        <!-- 日期 + 員工篩選 -->
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

            <select x-model="selectedStaffId" @change="loadAll()" class="form-select form-select-sm" style="width: auto;">
                <option value="">全部員工</option>
                <template x-for="s in staffList" :key="s.id">
                    <option :value="s.id" x-text="s.name"></option>
                </template>
            </select>

            <button @click="exportCSV()" class="btn btn-sm btn-outline-secondary">
                📄 匯出 CSV
            </button>
        </div>
    </div>

    <!-- 四大統計卡片 -->
    <div class="row g-3 mb-4">
        <div class="col-12 col-sm-6 col-md-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-uppercase text-muted small mb-1">總佣金</div>
                    <div class="fs-4 fw-semibold" x-text="formatMoney(summary.total_commission)">HK$ 0</div>
                    <div class="small text-success mt-2" x-text="summary.sale_count + ' 單有佣金'"></div>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-md-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-uppercase text-muted small mb-1">服務佣金</div>
                    <div class="fs-4 fw-semibold text-success" x-text="formatMoney(summary.service_commission)">HK$ 0</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-md-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-uppercase text-muted small mb-1">零售佣金</div>
                    <div class="fs-4 fw-semibold" x-text="formatMoney(summary.retail_commission)">HK$ 0</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-md-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-uppercase text-muted small mb-1">開單佣金</div>
                    <div class="fs-4 fw-semibold" x-text="formatMoney(summary.open_commission)">HK$ 0</div>
                </div>
            </div>
        </div>
    </div>

    <!-- 員工佣金明細 -->
    <div class="card">
        <div class="card-body">
            <div class="fw-semibold mb-3">員工佣金明細</div>

            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light small text-muted">
                        <tr>
                            <th>員工</th>
                            <th class="text-end">服務佣金</th>
                            <th class="text-end">零售佣金</th>
                            <th class="text-end">開單佣金</th>
                            <th class="text-end">總計</th>
                            <th class="text-end">相關單數</th>
                        </tr>
                    </thead>
                    <tbody class="small">
                        <template x-for="row in staffCommissions" :key="row.staff_id">
                            <tr>
                                <td class="fw-medium">
                                    <a href="#" @click.prevent="showStaffDetails(row)" class="text-decoration-none" x-text="row.staff_name"></a>
                                </td>
                                <td class="text-end text-success" x-text="formatMoney(row.service_commission)"></td>
                                <td class="text-end" x-text="formatMoney(row.retail_commission)"></td>
                                <td class="text-end" x-text="formatMoney(row.open_commission)"></td>
                                <td class="text-end fw-semibold" x-text="formatMoney(row.total_commission)"></td>
                                <td class="text-end text-muted" x-text="row.sale_count"></td>
                            </tr>
                        </template>
                        <tr x-show="staffCommissions.length === 0">
                            <td colspan="6" class="py-4 text-center text-muted">查詢期間暫無佣金記錄</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="mt-3 small text-muted text-center">
        佣金以結帳當時的設定比率計算（已快照）· 按 F5 刷新
    </div>

    <!-- 員工明細 Modal (Bootstrap) -->
    <div class="modal fade" id="commissionDetailsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title" x-text="selectedStaffForDetails ? selectedStaffForDetails.staff_name + ' 的佣金明細' : ''"></h5>
                        <div class="small text-muted" x-text="from + ' ~ ' + to"></div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <div x-show="loadingDetails" class="text-center py-4 text-muted">載入中...</div>

                    <div x-show="!loadingDetails && staffDetails.length > 0" class="table-responsive">
                        <table class="table table-sm">
                            <thead class="table-light small text-muted">
                                <tr>
                                    <th>日期</th>
                                    <th>客戶</th>
                                    <th>銷售單</th>
                                    <th class="text-end">銷售額</th>
                                    <th>類型</th>
                                    <th class="text-end">比率</th>
                                    <th class="text-end">佣金</th>
                                </tr>
                            </thead>
                            <tbody class="small">
                                <template x-for="d in staffDetails" :key="d.id">
                                    <tr>
                                        <td x-text="d.sale_date"></td>
                                        <td x-text="d.customer_name"></td>
                                        <td class="text-muted small">#<span x-text="d.sale_id"></span></td>
                                        <td class="text-end" x-text="formatMoney(d.sale_total)"></td>
                                        <td><span class="badge bg-secondary-subtle text-dark small" x-text="formatType(d.type)"></span></td>
                                        <td class="text-end small" x-text="d.rate + '%'"></td>
                                        <td class="text-end fw-semibold text-success" x-text="formatMoney(d.amount)"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    <div x-show="!loadingDetails && staffDetails.length === 0" class="text-center py-4 text-muted">
                        此期間沒有找到佣金明細
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">關閉</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function commissionsApp() {
    return {
        from: '<?= date('Y-m-d') ?>',
        to: '<?= date('Y-m-d') ?>',
        activeRange: 'today',
        selectedStaffId: '',
        staffList: [],
        staffCommissions: [],

        // 明細彈窗（Bootstrap）
        selectedStaffForDetails: null,
        staffDetails: [],
        loadingDetails: false,

        summary: {
            total_commission: 0,
            service_commission: 0,
            retail_commission: 0,
            open_commission: 0,
            sale_count: 0
        },

        init() {
            this.loadStaffList();
            this.setQuickRange('today');
        },

        async loadAll() {
            this.loadSummary();
            this.loadByStaff();
        },

        async loadSummary() {
            try {
                let url = `/api/commissions.php?action=summary&from=${this.from}&to=${this.to}`;
                if (this.selectedStaffId) url += `&staff_id=${this.selectedStaffId}`;
                const res = await SalonEase.fetch(url);
                this.summary = res.data;
            } catch (e) {
                console.error(e);
            }
        },

        async loadByStaff() {
            try {
                let url = `/api/commissions.php?action=by_staff&from=${this.from}&to=${this.to}`;
                if (this.selectedStaffId) url += `&staff_id=${this.selectedStaffId}`;
                const res = await SalonEase.fetch(url);
                this.staffCommissions = res.data || [];
            } catch (e) {
                this.staffCommissions = [];
            }
        },

        async loadStaffList() {
            try {
                const res = await SalonEase.fetch('/api/staff.php?action=list&is_active=1');
                this.staffList = res.data || [];
            } catch (e) {}
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
                const diff = today.getDate() - day + (day === 0 ? -6 : 1);
                fromDate = new Date(today.setDate(diff));
                this.from = this.formatDate(fromDate);
                this.to = this.formatDate(new Date());
            } else if (type === 'month') {
                fromDate = new Date(today.getFullYear(), today.getMonth(), 1);
                this.from = this.formatDate(fromDate);
                this.to = this.formatDate(new Date());
            }
            this.loadAll();
        },

        formatDate(d) {
            return d.toISOString().split('T')[0];
        },

        formatMoney(amount) {
            return 'HK$ ' + parseFloat(amount || 0).toLocaleString('zh-HK', { minimumFractionDigits: 0 });
        },

        exportCSV() {
            if (!this.staffCommissions.length) {
                SalonEase.toast('目前沒有可匯出的資料', 'error');
                return;
            }

            let csv = '\uFEFF員工,服務佣金,零售佣金,開單佣金,總計,相關單數\n';
            this.staffCommissions.forEach(r => {
                csv += `${r.staff_name},${r.service_commission},${r.retail_commission},${r.open_commission},${r.total_commission},${r.sale_count}\n`;
            });

            const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            const url = URL.createObjectURL(blob);
            link.href = url;
            link.download = `佣金明細_${this.from}_${this.to}.csv`;
            link.click();
            URL.revokeObjectURL(url);
        },

        // 顯示員工明細
        async showStaffDetails(staff) {
            this.selectedStaffForDetails = staff;
            this.staffDetails = [];
            this.loadingDetails = true;

            const modalEl = document.getElementById('commissionDetailsModal');
            const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
            modal.show();

            try {
                const res = await SalonEase.fetch(
                    `/api/commissions.php?action=staff_details&from=${this.from}&to=${this.to}&staff_id=${staff.staff_id}`
                );
                this.staffDetails = res.data || [];
            } catch (e) {
                this.staffDetails = [];
            } finally {
                this.loadingDetails = false;
            }
        },

        closeDetailsModal() {
            const modalEl = document.getElementById('commissionDetailsModal');
            const modal = bootstrap.Modal.getInstance(modalEl);
            if (modal) modal.hide();

            this.selectedStaffForDetails = null;
            this.staffDetails = [];
        },

        formatType(type) {
            const map = { 'service': '服務', 'retail': '零售', 'open': '開單' };
            return map[type] || type;
        }
    }
}

// 註冊佣金頁熱鍵
if (window.SalonEase && window.SalonEase.Hotkeys && window.SalonEase.Hotkeys.registerPage) {
    window.SalonEase.Hotkeys.registerPage([
        { key: 'T', desc: '切換至今日' },
        { key: 'W', desc: '切換至本週' },
        { key: 'M', desc: '切換至本月' },
        { key: 'R', desc: '重新載入佣金報表' },
        { key: 'F5', desc: '重新載入資料（不刷新頁面）' },
    ]);
}

document.addEventListener('keydown', function(e) {
    if (!document.querySelector('[x-data="commissionsApp()"]')) return;
    if (['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement.tagName)) return;

    const app = document.querySelector('[x-data="commissionsApp()"]');
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