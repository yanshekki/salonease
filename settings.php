<?php 
require_once __DIR__ . '/includes/auth.php'; 
require_once __DIR__ . '/includes/csrf.php';
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

    <!-- 常用熱鍵一覽 -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="fw-semibold mb-3" title="按 ? 鍵可隨時查看目前頁面的完整熱鍵清單">常用熱鍵一覽</div>
            
            <div class="row g-4 small">
                <div class="col-md-6">
                    <div class="fw-medium mb-2 text-dark">全域熱鍵</div>
                    <div class="text-muted">
                        <div><span class="font-mono text-success">?</span>　顯示目前頁面熱鍵說明</div>
                        <div><span class="font-mono text-success">Ctrl + K</span>　開啟命令面板</div>
                        <div><span class="font-mono text-success">Alt + H</span>　返回概覽</div>
                        <div><span class="font-mono text-success">Alt + P</span>　前往 POS</div>
                        <div><span class="font-mono text-success">Alt + A</span>　前往預約</div>
                        <div><span class="font-mono text-success">Alt + C</span>　前往客戶</div>
                        <div><span class="font-mono text-success">Alt + R</span>　前往報表</div>
                        <div><span class="font-mono text-success">Alt + M</span>　前往佣金查詢</div>
                        <div><span class="font-mono text-success">Alt + S</span>　前往設定</div>
                        <div><span class="font-mono text-success">F9</span>　打印上一張收據（58mm）</div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="fw-medium mb-2 text-dark">報表 / 佣金頁</div>
                    <div class="text-muted">
                        <div><span class="font-mono text-success">T</span>　切換至今日</div>
                        <div><span class="font-mono text-success">W</span>　切換至本週</div>
                        <div><span class="font-mono text-success">M</span>　切換至本月</div>
                        <div><span class="font-mono text-success">R</span>　重新載入資料</div>
                        <div><span class="font-mono text-success">F5</span>　重新載入資料（不刷新頁面）</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 打印與佣金預設 -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <div>
                    <div class="fw-semibold">打印與佣金預設</div>
                    <div class="small text-muted">這些數值會作為新單據的預設值</div>
                </div>
            </div>

            <div class="mb-4">
                <label class="form-label small">熱感紙打印機預設寬度</label>
                <div class="d-flex gap-4">
                    <div class="form-check">
                        <input class="form-check-input" type="radio" x-model="form.printer_width" value="58" id="print58">
                        <label class="form-check-label small" for="print58">58mm（最常用）</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" x-model="form.printer_width" value="80" id="print80">
                        <label class="form-check-label small" for="print80">80mm</label>
                    </div>
                </div>
                <div class="small text-muted mt-1">結帳後預設打印格式會參考此設定</div>
            </div>

            <div>
                <div class="fw-medium small mb-2">佣金預設比率（%）</div>
                <div class="row g-3">
                    <div class="col-sm-4">
                        <label class="form-label small">服務項目</label>
                        <div class="input-group input-group-sm">
                            <input type="number" x-model.number="form.default_commission_service" 
                                   step="0.5" min="0" max="100" class="form-control">
                            <span class="input-group-text">%</span>
                        </div>
                    </div>
                    <div class="col-sm-4">
                        <label class="form-label small">零售產品</label>
                        <div class="input-group input-group-sm">
                            <input type="number" x-model.number="form.default_commission_retail" 
                                   step="0.5" min="0" max="100" class="form-control">
                            <span class="input-group-text">%</span>
                        </div>
                    </div>
                    <div class="col-sm-4">
                        <label class="form-label small">開單</label>
                        <div class="input-group input-group-sm">
                            <input type="number" x-model.number="form.default_commission_open" 
                                   step="0.5" min="0" max="100" class="form-control">
                            <span class="input-group-text">%</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 忠誠度積分設定（Phase 2 A18 補完） -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <div>
                    <div class="fw-semibold">忠誠度積分設定</div>
                    <div class="small text-muted">調整客戶積分累積與兌換規則，即時生效</div>
                </div>
                <div class="badge bg-light text-dark small">即時生效</div>
            </div>

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label small">累積率（每消費多少元 = 1 點）</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text">$</span>
                        <input type="number" x-model.number="form.points_earn_rate" 
                               step="1" min="1" max="100" class="form-control">
                        <span class="input-group-text">= 1 點</span>
                    </div>
                    <div class="small text-muted mt-1">例如：10 表示每 $10 消費累積 1 點</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label small">兌換率（多少點 = $1 折扣）</label>
                    <div class="input-group input-group-sm">
                        <input type="number" x-model.number="form.points_redemption_rate" 
                               step="1" min="1" max="100" class="form-control">
                        <span class="input-group-text">點 = $1</span>
                    </div>
                    <div class="small text-muted mt-1">例如：10 表示 10 點可兌 $1 折扣</div>
                </div>
            </div>
        </div>
    </div>

    <!-- 快速補貨預設（A38） -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <div>
                    <div class="fw-semibold">快速補貨預設數量</div>
                    <div class="small text-muted">設定產品列表「快速入庫」按鈕的預設值</div>
                </div>
                <div class="badge bg-light text-dark small">即時生效</div>
            </div>

            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label small">快速 +5 入庫</label>
                    <div class="input-group input-group-sm">
                        <input type="number" x-model.number="form.quick_restock_5" 
                               step="1" min="1" max="100" class="form-control">
                        <span class="input-group-text">件</span>
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label small">快速 +10 入庫</label>
                    <div class="input-group input-group-sm">
                        <input type="number" x-model.number="form.quick_restock_10" 
                               step="1" min="1" max="100" class="form-control">
                        <span class="input-group-text">件</span>
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label small">快速 +20 入庫</label>
                    <div class="input-group input-group-sm">
                        <input type="number" x-model.number="form.quick_restock_20" 
                               step="1" min="1" max="100" class="form-control">
                        <span class="input-group-text">件</span>
                    </div>
                </div>
            </div>
            <div class="small text-muted mt-2">修改後，產品列表的快速入庫按鈕會使用這些預設值</div>
        </div>
    </div>

    <!-- 管理功能快速入口 -->
    <div class="row g-3">
        <div class="col-md-6 col-lg-4">
            <a href="/customers.php" class="card h-100 text-decoration-none text-dark">
                <div class="card-body">
                    <div class="fs-3 mb-2">👥</div>
                    <div class="fw-semibold">客戶管理</div>
                    <div class="small text-muted">客戶資料、新增與編輯</div>
                </div>
            </a>
        </div>
        <div class="col-md-6 col-lg-4">
            <a href="/staff.php" class="card h-100 text-decoration-none text-dark">
                <div class="card-body">
                    <div class="fs-3 mb-2">🧑‍💼</div>
                    <div class="fw-semibold">員工管理</div>
                    <div class="small text-muted">新增、編輯、啟用/停用員工帳號及角色</div>
                </div>
            </a>
        </div>
        <div class="col-md-6 col-lg-4">
            <a href="/rooms.php" class="card h-100 text-decoration-none text-dark">
                <div class="card-body">
                    <div class="fs-3 mb-2">🏠</div>
                    <div class="fw-semibold">房間管理</div>
                    <div class="small text-muted">管理房間名稱與容量（用於預約）</div>
                </div>
            </a>
        </div>
        <div class="col-md-6 col-lg-4">
            <a href="/services.php" class="card h-100 text-decoration-none text-dark">
                <div class="card-body">
                    <div class="fs-3 mb-2">💆</div>
                    <div class="fw-semibold">服務項目管理</div>
                    <div class="small text-muted">管理療程名稱、時長與價格</div>
                </div>
            </a>
        </div>
        <div class="col-md-6 col-lg-4">
            <a href="/products.php" class="card h-100 text-decoration-none text-dark">
                <div class="card-body">
                    <div class="fs-3 mb-2">🛍️</div>
                    <div class="fw-semibold">零售產品管理</div>
                    <div class="small text-muted">管理產品、售價與庫存</div>
                </div>
            </a>
        </div>
        <div class="col-md-6 col-lg-4">
            <a href="/packages.php" class="card h-100 text-decoration-none text-dark">
                <div class="card-body">
                    <div class="fs-3 mb-2">🎫</div>
                    <div class="fw-semibold">套票管理</div>
                    <div class="small text-muted">療程卡（套票）定義與定價</div>
                </div>
            </a>
        </div>
    </div>

    <!-- A145：運維工具 - 資料庫備份 -->
    <div class="card mb-4 border-warning">
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <div>
                    <div class="fw-semibold text-warning">資料庫備份</div>
                    <div class="small text-muted">手動產生完整 SQL 備份（含結構與資料）</div>
                </div>
                <div class="badge bg-warning text-dark small">運維工具</div>
            </div>

            <div class="small text-muted mb-3">
                建議定期手動備份，尤其在進行重要操作前。<br>
                備份檔案會以 .sql.gz 格式下載，可直接用於還原。
            </div>

            <button @click="manualBackup()" class="btn btn-warning">
                📦 手動備份資料庫
            </button>
        </div>
    </div>

    <!-- A148：資料匯出中心（統一入口） -->
    <div class="card mb-4 border-secondary">
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <div>
                    <div class="fw-semibold">資料匯出中心</div>
                    <div class="small text-muted">快速找到所有匯出功能</div>
                </div>
                <div class="badge bg-secondary text-light small">收尾工具</div>
            </div>

            <div class="row g-2 small">
                <div class="col-12 col-md-6">
                    <a href="/loyalty.php" class="d-block border rounded p-2 text-decoration-none hover-bg-light">
                        📋 忠誠度記錄 CSV <span class="text-muted">（loyalty.php）</span>
                    </a>
                </div>
                <div class="col-12 col-md-6">
                    <a href="/reports.php" class="d-block border rounded p-2 text-decoration-none hover-bg-light">
                        📊 員工銷售排行 CSV <span class="text-muted">（reports.php）</span>
                    </a>
                </div>
                <div class="col-12 col-md-6">
                    <a href="/audit_logs.php" class="d-block border rounded p-2 text-decoration-none hover-bg-light">
                        📝 操作審計日誌 CSV <span class="text-muted">（audit_logs.php）</span>
                    </a>
                </div>
                <div class="col-12 col-md-6">
                    <button @click="manualBackup()" class="d-block w-100 border rounded p-2 text-start btn btn-link text-decoration-none hover-bg-light">
                        💾 完整資料庫備份 <span class="text-muted">（.sql.gz）</span>
                    </button>
                </div>
            </div>

            <div class="small text-muted mt-2">
                更多報表數據可直接在報表頁使用日期篩選後匯出。
            </div>
        </div>
    </div>

    <!-- A146：系統健康檢查 -->
    <div class="card mb-4 border-info">
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <div>
                    <div class="fw-semibold text-info">系統健康檢查</div>
                    <div class="small text-muted">快速查看核心服務狀態</div>
                </div>
                <div class="badge bg-info text-dark small">運維工具</div>
            </div>

            <div x-show="healthLoading" class="text-center py-3 text-muted small">
                <span class="spinner-border spinner-border-sm me-2"></span> 檢查中...
            </div>

            <div x-show="!healthLoading && health" class="row g-2">
                <template x-for="(item, key) in (health.checks || {})" :key="key">
                    <div class="col-6">
                        <div class="border rounded p-2 small d-flex align-items-center gap-2" 
                             :class="{
                                 'border-success bg-success-subtle': item.status === 'ok',
                                 'border-warning bg-warning-subtle': item.status === 'warning',
                                 'border-danger bg-danger-subtle': item.status === 'error'
                             }">
                            <span x-text="item.label"></span>
                            <span class="ms-auto" x-text="item.status === 'ok' ? '✅' : (item.status === 'warning' ? '⚠️' : '❌')"></span>
                        </div>
                        <div class="small text-muted mt-1" x-text="item.detail"></div>
                    </div>
                </template>
            </div>

            <div class="mt-3 d-flex gap-2">
                <button @click="loadHealth()" class="btn btn-sm btn-outline-info">
                    🔄 刷新健康狀態
                </button>
                <span class="small text-muted align-self-center" x-show="health" x-text="'最後檢查：' + (health.checked_at || '')"></span>
            </div>
        </div>
    </div>

    <!-- A152：系統資訊 -->
    <div class="card mb-4 border-light">
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <div>
                    <div class="fw-semibold">系統資訊</div>
                    <div class="small text-muted">基本運行環境</div>
                </div>
                <div class="badge bg-light text-dark small">收尾</div>
            </div>

            <div class="row small g-2">
                <div class="col-6">
                    <strong>版本</strong><br>
                    <span class="text-muted">v1.5+ (Phase 3 完成)</span>
                </div>
                <div class="col-6">
                    <strong>PHP</strong><br>
                    <span class="text-muted"><?= PHP_VERSION ?></span>
                </div>
                <div class="col-6">
                    <strong>資料庫</strong><br>
                    <span class="text-muted">MySQL (PDO)</span>
                </div>
                <div class="col-6">
                    <strong>最近備份</strong><br>
                    <span class="text-muted">請參考上方「資料庫備份」</span>
                </div>
            </div>
        </div>
    </div>

    <!-- A153：關於 SalonEase -->
    <div class="card mb-4 border-light">
        <div class="card-body text-center">
            <div class="fw-semibold mb-2">關於 SalonEase</div>
            <div class="small text-muted mb-3">
                香港小型美容院管理系統<br>
                v1.5+（Phase 3 完成）<br>
                純 PHP + API-First
            </div>
            <div class="small">
                <a href="https://github.com/yanshekki/salonease" target="_blank" class="text-decoration-none">GitHub</a>
                <span class="mx-2">·</span>
                <span class="text-muted">感謝您的使用</span>
            </div>
        </div>
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
            default_low_stock_threshold: 5,
            points_earn_rate: 10,
            points_redemption_rate: 10,
            quick_restock_5: 5,
            quick_restock_10: 10,
            quick_restock_20: 20
        },
        saving: false,
        saved: false,

        init() {
            this.loadShop();
            this.loadHealth();   // A146 自動載入健康狀態
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
                    this.form.points_earn_rate = parseInt(d.points_earn_rate) || 10;
                    this.form.points_redemption_rate = parseInt(d.points_redemption_rate) || 10;
                    this.form.quick_restock_5  = parseInt(d.quick_restock_5) || 5;
                    this.form.quick_restock_10 = parseInt(d.quick_restock_10) || 10;
                    this.form.quick_restock_20 = parseInt(d.quick_restock_20) || 20;
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
                        csrf_token: '<?= csrf_token() ?>',
                        salon_name: this.form.salon_name,
                        address: this.form.address,
                        phone: this.form.phone,
                        printer_width: this.form.printer_width,
                        default_commission_service: this.form.default_commission_service,
                        default_commission_retail: this.form.default_commission_retail,
                        default_commission_open: this.form.default_commission_open,
                        default_low_stock_threshold: this.form.default_low_stock_threshold,
                        points_earn_rate: this.form.points_earn_rate,
                        points_redemption_rate: this.form.points_redemption_rate,
                        quick_restock_5: this.form.quick_restock_5,
                        quick_restock_10: this.form.quick_restock_10,
                        quick_restock_20: this.form.quick_restock_20
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
        },

        // A145：手動備份資料庫
        async manualBackup() {
            if (!confirm('確定要產生資料庫備份嗎？\n備份過程可能需要幾秒鐘。')) return;

            try {
                // 直接導向下載
                window.location.href = '/api/backup.php?action=manual';
                SalonEase.toast('備份已開始下載');
            } catch (err) {
                SalonEase.toast('備份失敗', 'error');
            }
        },

        // A146：系統健康檢查
        health: null,
        healthLoading: false,

        async loadHealth() {
            this.healthLoading = true;
            try {
                const res = await SalonEase.fetch('/api/health.php');
                this.health = res;
            } catch (e) {
                console.warn('健康檢查失敗', e);
                this.health = { overall: 'error', checks: {} };
            } finally {
                this.healthLoading = false;
            }
        }
    }
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
