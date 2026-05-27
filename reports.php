<?php
/**
 * SalonEase - 營業報表
 */
require_once __DIR__ . '/includes/auth.php';
require_login();

$pageTitle = '報表';
$pageSubtitle = '營業數據與分析';
include __DIR__ . '/includes/header.php';
?>
<div class="max-w-6xl mx-auto" x-data="reportsApp()">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
        <div>
            <h2 class="text-2xl font-semibold">營業報表</h2>
            <p class="text-sm text-[#5A5A5C]">查看銷售、付款及套票使用情況</p>
        </div>

        <!-- 快速日期選擇 -->
        <div class="flex flex-wrap gap-2">
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
        </div>
    </div>

    <!-- 四大統計卡片 -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
        <div class="bg-white rounded-2xl p-5 border border-gray-100">
            <div class="text-xs uppercase tracking-wider text-[#8A8A8C] mb-1">總營業額</div>
            <div class="text-3xl font-semibold text-[#2C2C2E]" x-text="formatMoney(summary.total_sales)">HK$ 0</div>
            <div class="text-xs mt-2 text-[#8FA68F]" x-text="summary.total_transactions + ' 單'"></div>
        </div>
        <div class="bg-white rounded-2xl p-5 border border-gray-100">
            <div class="text-xs uppercase tracking-wider text-[#8A8A8C] mb-1">平均每單</div>
            <div class="text-3xl font-semibold" x-text="formatMoney(summary.avg_ticket)">HK$ 0</div>
            <div class="text-xs mt-2 text-[#8A8A8C]">折扣總額 <span x-text="formatMoney(summary.total_discount)"></span></div>
        </div>
        <div class="bg-white rounded-2xl p-5 border border-gray-100">
            <div class="text-xs uppercase tracking-wider text-[#8A8A8C] mb-1">套票扣減次數</div>
            <div class="text-3xl font-semibold text-purple-600" x-text="summary.package_sessions"></div>
            <div class="text-xs mt-2 text-[#8A8A8C]">客戶使用療程卡</div>
        </div>
        <div class="bg-white rounded-2xl p-5 border border-gray-100">
            <div class="text-xs uppercase tracking-wider text-[#8A8A8C] mb-1">查詢期間</div>
            <div class="text-xl font-medium text-[#2C2C2E]" x-text="from + ' ~ ' + to"></div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        
        <!-- 付款方式分佈 -->
        <div class="bg-white rounded-2xl border border-gray-100 p-6">
            <div class="font-semibold mb-4 flex items-center gap-2">
                <span>付款方式分佈</span>
            </div>
            <div class="space-y-3" x-show="paymentBreakdown.length > 0">
                <template x-for="item in paymentBreakdown" :key="item.method">
                    <div class="flex justify-between items-center text-sm">
                        <div class="flex items-center gap-3">
                            <span class="font-medium w-20" x-text="getPaymentLabel(item.method)"></span>
                            <span class="text-[#8A8A8C]" x-text="item.count + ' 單'"></span>
                        </div>
                        <div class="font-semibold text-right" x-text="formatMoney(item.amount)"></div>
                    </div>
                </template>
            </div>
            <div x-show="paymentBreakdown.length === 0" class="text-[#8A8A8C] text-sm py-8 text-center">
                查詢期間暫無銷售記錄
            </div>
        </div>

        <!-- 套票扣減明細 -->
        <div class="bg-white rounded-2xl border border-gray-100 p-6">
            <div class="font-semibold mb-4">套票扣減統計</div>
            <div class="space-y-2 text-sm" x-show="packageRedemptions.length > 0">
                <template x-for="p in packageRedemptions" :key="p.package_name">
                    <div class="flex justify-between border-b pb-2 last:border-0 last:pb-0">
                        <div x-text="p.package_name"></div>
                        <div class="text-right">
                            <span class="font-medium" x-text="p.total_sessions + ' 次'"></span>
                            <span class="text-xs text-[#8A8A8C] ml-1" x-text="'(' + p.times + ' 單)'"></span>
                        </div>
                    </div>
                </template>
            </div>
            <div x-show="packageRedemptions.length === 0" class="text-[#8A8A8C] text-sm py-8 text-center">
                查詢期間無套票扣減
            </div>
        </div>

        <!-- 熱門服務 -->
        <div class="bg-white rounded-2xl border border-gray-100 p-6">
            <div class="font-semibold mb-4">熱門服務 Top 5</div>
            <div x-show="topServices.length > 0">
                <table class="w-full text-sm">
                    <thead class="text-[#8A8A8C]">
                        <tr class="border-b">
                            <th class="text-left py-2">服務名稱</th>
                            <th class="text-right py-2">次數</th>
                            <th class="text-right py-2">收入</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="s in topServices" :key="s.name">
                            <tr class="border-b last:border-0">
                                <td class="py-2" x-text="s.name"></td>
                                <td class="text-right py-2" x-text="s.qty"></td>
                                <td class="text-right py-2 font-medium" x-text="formatMoney(s.revenue)"></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
            <div x-show="topServices.length === 0" class="text-[#8A8A8C] text-sm py-8 text-center">暫無數據</div>
        </div>

        <!-- 熱門產品 -->
        <div class="bg-white rounded-2xl border border-gray-100 p-6">
            <div class="font-semibold mb-4">熱門產品 Top 5</div>
            <div x-show="topProducts.length > 0">
                <table class="w-full text-sm">
                    <thead class="text-[#8A8A8C]">
                        <tr class="border-b">
                            <th class="text-left py-2">產品名稱</th>
                            <th class="text-right py-2">售出</th>
                            <th class="text-right py-2">收入</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="p in topProducts" :key="p.name">
                            <tr class="border-b last:border-0">
                                <td class="py-2" x-text="p.name"></td>
                                <td class="text-right py-2" x-text="p.qty"></td>
                                <td class="text-right py-2 font-medium" x-text="formatMoney(p.revenue)"></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
            <div x-show="topProducts.length === 0" class="text-[#8A8A8C] text-sm py-8 text-center">暫無數據</div>
        </div>

    </div>

    <div class="mt-6 text-xs text-[#8A8A8C] text-center">
        數據以結帳日期（sale_date）為準 · 按 F5 刷新頁面
    </div>
</div>

<script>
function reportsApp() {
    return {
        from: '<?= date('Y-m-d') ?>',
        to: '<?= date('Y-m-d') ?>',
        activeRange: 'today',
        
        summary: {
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
            this.setQuickRange('today');
        },

        async loadAll() {
            this.loadSummary();
            this.loadPaymentBreakdown();
            this.loadTopServices();
            this.loadTopProducts();
            this.loadPackageRedemptions();
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

        async loadTopServices() {
            try {
                const res = await SalonEase.fetch(`/api/reports.php?action=top_services&from=${this.from}&to=${this.to}&limit=5`);
                this.topServices = res.data || [];
            } catch (e) {}
        },

        async loadTopProducts() {
            try {
                const res = await SalonEase.fetch(`/api/reports.php?action=top_products&from=${this.from}&to=${this.to}&limit=5`);
                this.topProducts = res.data || [];
            } catch (e) {}
        },

        async loadPackageRedemptions() {
            try {
                const res = await SalonEase.fetch(`/api/reports.php?action=package_redemptions&from=${this.from}&to=${this.to}`);
                this.packageRedemptions = res.data || [];
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
        }
    }
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>