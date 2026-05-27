<?php 
require_once __DIR__ . '/includes/auth.php'; 
require_login(); 
$pageTitle = '系統設定'; 
$extraJs = 'settings.js';   // 稍後可獨立抽離，目前先用內聯 Alpine
include __DIR__ . '/includes/header.php'; 
?>
<div class="container" style="max-width: 800px;" x-data="shopSettings()">
    <div class="alert alert-light border mb-4 small">
        目前系統已進入 <strong>維護階段</strong>。
        核心功能已完成，未來會以穩定性及小優化為主。如有新需求，歡迎提出。
    </div>

    <div class="d-flex align-items-center justify-content-between mb-3">
        <h2 class="h5 mb-0">系統設定</h2>
        <div class="badge bg-light text-dark small d-flex align-items-center gap-1">
            <span class="fw-medium">SalonEase</span>
            <span class="font-mono text-success">v1.0.0</span>
            <span class="text-muted">· 2025 年 5 月</span>
            <span class="text-success ms-1">(維護階段)</span>
        </div>
    </div>

    <!-- 店舖基本資訊 -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <div>
                    <div class="fw-semibold">店舖基本資訊</div>
                    <div class="small text-muted">收據、打印會自動使用以下資料</div>
                </div>
                <div class="badge bg-light text-dark small">即時生效</div>
            </div>

            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label small">店舖名稱</label>
                    <input type="text" x-model="form.salon_name" class="form-control" placeholder="SalonEase 美容中心">
                </div>
                <div class="col-12">
                    <label class="form-label small">地址</label>
                    <input type="text" x-model="form.address" class="form-control" placeholder="香港九龍尖沙咀彌敦道 100 號 8 樓">
                </div>
                <div class="col-md-6">
                    <label class="form-label small">電話</label>
                    <input type="text" x-model="form.phone" class="form-control" placeholder="2123 4567">
                </div>
                <div class="col-md-6 d-flex align-items-end">
                    <button @click="saveShop()" 
                            :disabled="saving"
                            class="btn btn-dark w-100">
                        <span x-text="saving ? '儲存中...' : '儲存店舖資訊'"></span>
                    </button>
                    <span x-show="saved" x-cloak class="ms-3 small text-success fw-medium">✓ 已儲存</span>
                </div>
            </div>

            <div class="mt-3 small text-muted">
                修改後，所有新打印的收據（熱感紙及 A4）都會即時顯示最新資料。
            </div>
        </div>
    </div>

    <!-- 常用熱鍵一覽（A 收尾） -->
    <div class="bg-white rounded-2xl border border-gray-100 p-6 mb-8">
        <div class="font-semibold text-lg mb-4" title="按 ? 鍵可隨時查看目前頁面的完整熱鍵清單">常用熱鍵一覽</div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-4 text-sm">
            <div>
                <div class="font-medium mb-2 text-[#2C2C2E]">全域熱鍵</div>
                <div class="space-y-1 text-[#5A5A5C]">
                    <div><span class="font-mono text-[#8FA68F]">?</span>　顯示目前頁面熱鍵說明</div>
                    <div><span class="font-mono text-[#8FA68F]">Ctrl + K</span>　開啟命令面板</div>
                    <div><span class="font-mono text-[#8FA68F]">Alt + H</span>　返回概覽</div>
                    <div><span class="font-mono text-[#8FA68F]">Alt + P</span>　前往 POS</div>
                    <div><span class="font-mono text-[#8FA68F]">Alt + A</span>　前往預約</div>
                    <div><span class="font-mono text-[#8FA68F]">Alt + C</span>　前往客戶</div>
                    <div><span class="font-mono text-[#8FA68F]">Alt + R</span>　前往報表</div>
                    <div><span class="font-mono text-[#8FA68F]">Alt + M</span>　前往佣金查詢</div>
                    <div><span class="font-mono text-[#8FA68F]">Alt + S</span>　前往設定</div>
                    <div><span class="font-mono text-[#8FA68F]">F9</span>　打印上一張收據（58mm）</div>
                </div>
            </div>
            <div>
                <div class="font-medium mb-2 text-[#2C2C2E]">報表 / 佣金頁</div>
                <div class="space-y-1 text-[#5A5A5C]">
                    <div><span class="font-mono text-[#8FA68F]">T</span>　切換至今日</div>
                    <div><span class="font-mono text-[#8FA68F]">W</span>　切換至本週</div>
                    <div><span class="font-mono text-[#8FA68F]">M</span>　切換至本月</div>
                    <div><span class="font-mono text-[#8FA68F]">R</span>　重新載入資料</div>
                    <div><span class="font-mono text-[#8FA68F]">F5</span>　重新載入資料（不刷新頁面）</div>
                </div>
                
                <div class="font-medium mt-4 mb-2 text-[#2C2C2E]">POS 頁</div>
                <div class="space-y-1 text-[#5A5A5C]">
                    <div><span class="font-mono text-[#8FA68F]">S</span>　批量指派員工</div>
                    <div><span class="font-mono text-[#8FA68F]">F9</span>　打印上一張收據</div>
                </div>
            </div>
        </div>
        
        <div class="mt-4 text-xs text-[#8A8A8C]">
            提示：按 <span class="font-mono">?</span> 鍵可隨時查看目前頁面的完整熱鍵說明。
        </div>
    </div>

    <!-- 打印與佣金預設（本輪 A 選擇） -->
    <div class="bg-white rounded-2xl border border-gray-100 p-6 mb-8">
        <div class="flex items-center justify-between mb-4">
            <div>
                <div class="font-semibold text-lg">打印與佣金預設</div>
                <div class="text-sm text-[#5A5A5C]">這些數值會作為新單據的預設值</div>
            </div>
        </div>

        <div class="space-y-5">
            <!-- 打印機寬度 -->
            <div>
                <label class="block text-sm font-medium mb-2">熱感紙打印機預設寬度</label>
                <div class="flex gap-4">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="radio" x-model="form.printer_width" value="58" class="accent-[#2C2C2E]">
                        <span>58mm（最常用）</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="radio" x-model="form.printer_width" value="80" class="accent-[#2C2C2E]">
                        <span>80mm</span>
                    </label>
                </div>
                <div class="text-xs text-[#8A8A8C] mt-1">結帳後預設打印格式會參考此設定</div>
            </div>

            <!-- 佣金預設比率 -->
            <div>
                <div class="text-sm font-medium mb-2">佣金預設比率（%）</div>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs text-[#5A5A5C] mb-1">服務項目</label>
                        <div class="flex items-center">
                            <input type="number" x-model.number="form.default_commission_service" 
                                   step="0.5" min="0" max="100"
                                   class="salon-input w-full text-right">
                            <span class="ml-2 text-sm text-[#8A8A8C]">%</span>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs text-[#5A5A5C] mb-1">零售產品</label>
                        <div class="flex items-center">
                            <input type="number" x-model.number="form.default_commission_retail" 
                                   step="0.5" min="0" max="100"
                                   class="salon-input w-full text-right">
                            <span class="ml-2 text-sm text-[#8A8A8C]">%</span>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs text-[#5A5A5C] mb-1">開單佣金</label>
                        <div class="flex items-center">
                            <input type="number" x-model.number="form.default_commission_open" 
                                   step="0.5" min="0" max="100"
                                   class="salon-input w-full text-right">
                            <span class="ml-2 text-sm text-[#8A8A8C]">%</span>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs text-[#5A5A5C] mb-1" title="產品庫存低於此數量時會顯示警示（可被個別產品的低庫存門檻覆蓋）。建議設定為日常平均每日銷量的 3-7 天份。">低庫存預設門檻</label>
                        <div class="text-[10px] text-[#8A8A8C]">低於此數量會在產品列表及 POS 顯示警示</div>
                        <div class="flex items-center">
                            <input type="number" x-model.number="form.default_low_stock_threshold" 
                                   step="1" min="0" 
                                   class="salon-input w-full text-right" title="產品庫存低於此數量時會顯示警示（建議 3-10）">
                            <span class="ml-2 text-sm text-[#8A8A8C]">件</span>
                        </div>
                    </div>
                </div>
                <div class="text-xs text-[#8A8A8C] mt-1">此為全域預設，個別員工可另行覆蓋</div>
            </div>

            <div>
                <button @click="saveShop()" 
                        :disabled="saving"
                        class="salon-btn px-8 disabled:opacity-60">
                    <span x-text="saving ? '儲存中...' : '儲存打印與佣金設定'"></span>
                </button>
                <span x-show="saved" x-cloak class="ml-3 text-sm text-[#8FA68F]">✓ 已儲存</span>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <a href="/customers.php" class="block p-5 bg-white border border-gray-100 rounded-2xl hover:border-[#8FA68F] transition group">
            <div class="text-2xl mb-2">👥</div>
            <div class="font-semibold group-hover:text-[#8FA68F]">客戶管理</div>
            <div class="text-sm text-[#5A5A5C] mt-1">客戶資料、新增與編輯</div>
        </a>

        <a href="/staff.php" class="block p-5 bg-white border border-gray-100 rounded-2xl hover:border-[#8FA68F] transition group">
            <div class="text-2xl mb-2">🧑‍💼</div>
            <div class="font-semibold group-hover:text-[#8FA68F]">員工管理</div>
            <div class="text-sm text-[#5A5A5C] mt-1">新增、編輯、啟用/停用員工帳號及角色</div>
        </a>

        <a href="/rooms.php" class="block p-5 bg-white border border-gray-100 rounded-2xl hover:border-[#8FA68F] transition group">
            <div class="text-2xl mb-2">🏠</div>
            <div class="font-semibold group-hover:text-[#8FA68F]">房間管理</div>
            <div class="text-sm text-[#5A5A5C] mt-1">管理房間名稱與容量（用於預約）</div>
        </a>

        <a href="/services.php" class="block p-5 bg-white border border-gray-100 rounded-2xl hover:border-[#8FA68F] transition group">
            <div class="text-2xl mb-2">💆</div>
            <div class="font-semibold group-hover:text-[#8FA68F]">服務項目管理</div>
            <div class="text-sm text-[#5A5A5C] mt-1">管理療程名稱、時長與價格</div>
        </a>

        <a href="/products.php" class="block p-5 bg-white border border-gray-100 rounded-2xl hover:border-[#8FA68F] transition group">
            <div class="text-2xl mb-2">🛍️</div>
            <div class="font-semibold group-hover:text-[#8FA68F]">零售產品管理</div>
            <div class="text-sm text-[#5A5A5C] mt-1">管理產品、售價與庫存</div>
        </a>

        <a href="/packages.php" class="block p-5 bg-white border border-gray-100 rounded-2xl hover:border-[#8FA68F] transition group">
            <div class="text-2xl mb-2">🎫</div>
            <div class="font-semibold group-hover:text-[#8FA68F]">套票管理</div>
            <div class="text-sm text-[#5A5A5C] mt-1">療程卡（套票）定義與定價</div>
        </a>
    </div>
</div>

<script>
function shopSettings() {
    return {
        form: {
            salon_name: 'SalonEase 美容中心',
            address: '',
            phone: '',
            printer_width: '58',
            default_commission_service: 40,
            default_commission_retail: 15,
            default_commission_open: 5,
            default_low_stock_threshold: 5
        },
        saving: false,
        saved: false,

        init() {
            this.loadShop();
        },

        async loadShop() {
            try {
                const res = await SalonEase.fetch('/api/settings.php?action=get');
                if (res.data) {
                    const d = res.data;
                    this.form.salon_name = d.salon_name || 'SalonEase 美容中心';
                    this.form.address = d.address || '';
                    this.form.phone = d.phone || '';
                    this.form.printer_width = d.printer_width || '58';
                    this.form.default_commission_service = parseFloat(d.default_commission_service) || 40;
                    this.form.default_commission_retail  = parseFloat(d.default_commission_retail) || 15;
                    this.form.default_commission_open    = parseFloat(d.default_commission_open) || 5;
                    this.form.default_low_stock_threshold = parseInt(d.default_low_stock_threshold) || 5;
                }
            } catch (e) {
                console.warn('載入設定失敗', e);
            }
        },

        async saveShop() {
            this.saving = true;
            this.saved = false;

            try {
                await SalonEase.fetch('/api/settings.php?action=save_shop', {
                    method: 'POST',
                    body: {
                        salon_name: this.form.salon_name,
                        address: this.form.address,
                        phone: this.form.phone,
                        printer_width: this.form.printer_width,
                        default_commission_service: this.form.default_commission_service,
                        default_commission_retail: this.form.default_commission_retail,
                        default_commission_open: this.form.default_commission_open,
                        default_low_stock_threshold: this.form.default_low_stock_threshold
                    }
                });

                SalonEase.toast('設定已成功儲存');
                this.saved = true;
                setTimeout(() => { this.saved = false; }, 2800);
            } catch (err) {
                SalonEase.toast(err.message || '儲存失敗', 'error');
            } finally {
                this.saving = false;
            }
        }
    }
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
