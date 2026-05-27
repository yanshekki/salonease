<?php
/**
 * SalonEase - 員工佣金查詢
 */
require_once __DIR__ . '/includes/auth.php';
require_login();

$pageTitle = '佣金查詢';
$pageSubtitle = '按員工查看累計佣金';
include __DIR__ . '/includes/header.php';
?>
<div class="max-w-6xl mx-auto" x-data="commissionsApp()">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
        <div>
            <h2 class="text-2xl font-semibold">員工佣金查詢</h2>
            <p class="text-sm text-[#5A5A5C]">查看服務、零售、開單佣金（依當時設定計算）</p>
        </div>

        <!-- 日期 + 員工篩選 -->
        <div class="flex flex-wrap items-center gap-2">
            <button @click="setQuickRange('today')" 
                    :class="activeRange === 'today' ? 'bg-[#2C2C2E] text-white' : 'border hover:bg-gray-50'"
                    class="px-4 py-1.5 text-sm rounded-xl transition">今日</button>
            <button @click="setQuickRange('week')" 
                    :class="activeRange === 'week' ? 'bg-[#2C2C2E] text-white' : 'border hover:bg-gray-50'"
                    class="px-4 py-1.5 text-sm rounded-xl transition">本週</button>
            <button @click="setQuickRange('month')" 
                    :class="activeRange === 'month' ? 'bg-[#2C2C2E] text-white' : 'border hover:bg-gray-50'"
                    class="px-4 py-1.5 text-sm rounded-xl transition">本月</button>
            
            <div class="flex items-center gap-2 border rounded-xl px-3 py-1 text-sm">
                <input type="date" x-model="from" @change="activeRange='custom'; loadAll()" class="border-0 text-sm focus:ring-0">
                <span class="text-[#8A8A8C]">至</span>
                <input type="date" x-model="to" @change="activeRange='custom'; loadAll()" class="border-0 text-sm focus:ring-0">
            </div>

            <select x-model="selectedStaffId" @change="loadAll()" class="border rounded-xl px-3 py-1.5 text-sm bg-white">
                <option value="">全部員工</option>
                <template x-for="s in staffList" :key="s.id">
                    <option :value="s.id" x-text="s.name"></option>
                </template>
            </select>

            <button @click="exportCSV()" 
                    class="px-3 py-1.5 text-sm border rounded-xl hover:bg-gray-50 flex items-center gap-1">
                📄 匯出 CSV
            </button>
        </div>
    </div>

    <!-- 四大統計卡片 -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
        <div class="bg-white rounded-2xl p-5 border border-gray-100">
            <div class="text-xs uppercase tracking-wider text-[#8A8A8C] mb-1">總佣金</div>
            <div class="text-3xl font-semibold text-[#2C2C2E]" x-text="formatMoney(summary.total_commission)">HK$ 0</div>
            <div class="text-xs mt-2 text-[#8FA68F]" x-text="summary.sale_count + ' 單有佣金'"></div>
        </div>
        <div class="bg-white rounded-2xl p-5 border border-gray-100">
            <div class="text-xs uppercase tracking-wider text-[#8A8A8C] mb-1">服務佣金</div>
            <div class="text-3xl font-semibold text-[#8FA68F]" x-text="formatMoney(summary.service_commission)">HK$ 0</div>
        </div>
        <div class="bg-white rounded-2xl p-5 border border-gray-100">
            <div class="text-xs uppercase tracking-wider text-[#8A8A8C] mb-1">零售佣金</div>
            <div class="text-3xl font-semibold" x-text="formatMoney(summary.retail_commission)">HK$ 0</div>
        </div>
        <div class="bg-white rounded-2xl p-5 border border-gray-100">
            <div class="text-xs uppercase tracking-wider text-[#8A8A8C] mb-1">開單佣金</div>
            <div class="text-3xl font-semibold" x-text="formatMoney(summary.open_commission)">HK$ 0</div>
        </div>
    </div>

    <!-- 員工佣金明細 -->
    <div class="bg-white rounded-2xl border border-gray-100 p-6">
        <div class="font-semibold mb-4">員工佣金明細</div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="text-[#8A8A8C]">
                    <tr class="border-b">
                        <th class="text-left py-2 pr-4">員工</th>
                        <th class="text-right py-2 px-3">服務佣金</th>
                        <th class="text-right py-2 px-3">零售佣金</th>
                        <th class="text-right py-2 px-3">開單佣金</th>
                        <th class="text-right py-2">總計</th>
                        <th class="text-right py-2">相關單數</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="row in staffCommissions" :key="row.staff_id">
                        <tr class="border-b last:border-0">
                            <td class="py-2 pr-4 font-medium text-[#2C2C2E] hover:text-[#8FA68F] cursor-pointer underline-offset-2 hover:underline" 
                                @click="showStaffDetails(row)" x-text="row.staff_name"></td>
                            <td class="text-right py-2 px-3 text-[#8FA68F]" x-text="formatMoney(row.service_commission)"></td>
                            <td class="text-right py-2 px-3" x-text="formatMoney(row.retail_commission)"></td>
                            <td class="text-right py-2 px-3" x-text="formatMoney(row.open_commission)"></td>
                            <td class="text-right py-2 font-semibold" x-text="formatMoney(row.total_commission)"></td>
                            <td class="text-right py-2 text-[#8A8A8C]" x-text="row.sale_count"></td>
                        </tr>
                    </template>
                    <tr x-show="staffCommissions.length === 0">
                        <td colspan="6" class="py-8 text-center text-[#8A8A8C]">查詢期間暫無佣金記錄</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-6 text-xs text-[#8A8A8C] text-center">
        佣金以結帳當時的設定比率計算（已快照）· 按 F5 刷新
    </div>

    <!-- 員工明細彈窗 -->
    <div x-show="showDetailsModal" 
         class="fixed inset-0 bg-black/40 flex items-center justify-center z-50"
         @click="closeDetailsModal()">
        <div @click.stop class="bg-white rounded-2xl w-full max-w-3xl mx-4 max-h-[85vh] overflow-hidden shadow-xl">
            <div class="flex items-center justify-between px-6 py-4 border-b">
                <div>
                    <div class="font-semibold text-lg" x-text="selectedStaffForDetails ? selectedStaffForDetails.staff_name + ' 的佣金明細' : ''"></div>
                    <div class="text-xs text-[#8A8A8C]" x-text="from + ' ~ ' + to"></div>
                </div>
                <button @click="closeDetailsModal()" class="text-2xl leading-none text-[#8A8A8C] hover:text-black">&times;</button>
            </div>

            <div class="p-6 overflow-auto max-h-[60vh]">
                <div x-show="loadingDetails" class="text-center py-8 text-[#8A8A8C]">載入中...</div>

                <table x-show="!loadingDetails && staffDetails.length > 0" class="w-full text-sm">
                    <thead class="text-[#8A8A8C]">
                        <tr class="border-b">
                            <th class="text-left py-2">日期</th>
                            <th class="text-left py-2">客戶</th>
                            <th class="text-left py-2">銷售單</th>
                            <th class="text-right py-2">銷售額</th>
                            <th class="text-left py-2">類型</th>
                            <th class="text-right py-2">比率</th>
                            <th class="text-right py-2">佣金</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="d in staffDetails" :key="d.id">
                            <tr class="border-b">
                                <td class="py-2" x-text="d.sale_date"></td>
                                <td class="py-2" x-text="d.customer_name"></td>
                                <td class="py-2 text-xs text-[#8A8A8C]">#<span x-text="d.sale_id"></span></td>
                                <td class="py-2 text-right" x-text="formatMoney(d.sale_total)"></td>
                                <td class="py-2"><span class="px-2 py-0.5 text-xs rounded bg-gray-100" x-text="formatType(d.type)"></span></td>
                                <td class="py-2 text-right text-xs" x-text="d.rate + '%'"></td>
                                <td class="py-2 text-right font-semibold text-[#8FA68F]" x-text="formatMoney(d.amount)"></td>
                            </tr>
                        </template>
                    </tbody>
                </table>

                <div x-show="!loadingDetails && staffDetails.length === 0" class="text-center py-8 text-[#8A8A8C]">
                    此期間沒有找到佣金明細
                </div>
            </div>

            <div class="px-6 py-3 border-t text-right">
                <button @click="closeDetailsModal()" class="px-4 py-1.5 text-sm border rounded-xl hover:bg-gray-50">關閉</button>
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

        // 明細彈窗
        showDetailsModal: false,
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
            this.showDetailsModal = true;

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
            this.showDetailsModal = false;
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