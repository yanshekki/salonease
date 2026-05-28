<?php
/**
 * SalonEase - POS 銷售系統
 */
require_once __DIR__ . '/includes/auth.php';
require_login();

require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'POS 銷售';
$pageSubtitle = '快速開單、結帳、熱感紙 / A4 收據打印';
$extraJs = 'pos.js';
?>
<?php include __DIR__ . '/includes/header.php'; ?>

<div class="row g-3">

    <!-- 左側：商品/服務選擇區 -->
    <div class="col-12 col-lg-5 order-1">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between mb-3 gap-1">
                    <div class="fw-semibold fs-5">加入項目</div>
                    <div class="small text-muted">搜尋服務或產品</div>
                </div>

                <input type="text" id="item-search" 
                       class="form-control mb-3" 
                       placeholder="輸入服務或產品名稱搜尋...">

                <!-- 分類標籤 -->
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <button onclick="filterItems('all')" id="filter-all"
                            class="btn btn-sm btn-dark">全部</button>
                    <button onclick="filterItems('service')" id="filter-service"
                            class="btn btn-sm btn-outline-secondary">服務</button>
                    <button onclick="filterItems('product')" id="filter-product"
                            class="btn btn-sm btn-outline-secondary">產品</button>
                    <button onclick="filterItems('package')" id="filter-package"
                            class="btn btn-sm btn-outline-secondary">套票</button>
                </div>

                <div id="items-list" class="border rounded overflow-auto" style="max-height: 420px;">
                    <!-- 由 JS 動態載入項目 -->
                    <div class="p-4 text-center text-muted">載入中...</div>
                </div>
            </div>
        </div>
    </div>

    <!-- 中間：購物車 -->
    <div class="col-12 col-lg-4 order-2">
        <div class="card h-100 d-flex flex-column">
            <div class="card-body d-flex flex-column">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div class="fw-semibold fs-5">購物車</div>
                    <div class="d-flex gap-3 small">
                        <button onclick="bulkAssignStaff()" class="btn btn-link btn-sm p-0 text-success" title="S 鍵也可快速觸發">批量指派</button>
                        <button onclick="clearCart()" class="btn btn-link btn-sm p-0 text-danger">清空</button>
                    </div>
                </div>

                <div id="cart-items" class="flex-fill overflow-auto border rounded p-2 mb-3" style="min-height: 220px;">
                    <!-- 購物車項目由 JS 渲染 -->
                    <div class="h-100 d-flex align-items-center justify-content-center text-muted">
                        購物車是空的
                    </div>
                </div>

                <!-- 客戶選擇 -->
                <div class="mb-3">
                    <label class="form-label small">客戶（可選）</label>
                    <div class="d-flex gap-2">
                        <input type="text" id="pos-customer-search" class="form-control flex-fill" placeholder="搜尋客戶姓名或電話">
                        <button onclick="quickCreateCustomer()" class="btn btn-outline-secondary btn-sm text-nowrap" title="快速建立新客戶（無需離開 POS）">新客戶</button>
                    </div>
                    <div id="pos-customer-info" class="mt-1 small text-muted"></div>
                </div>

                <div class="border-top pt-3 mt-auto">
                    <div class="d-flex justify-content-between small mb-1">
                        <span>小計</span>
                        <span id="cart-subtotal">HK$ 0.00</span>
                    </div>
                    <div class="d-flex justify-content-between small mb-1 align-items-center">
                        <span>折扣</span>
                        <input type="number" id="cart-discount" value="0" step="0.01" 
                               class="form-control form-control-sm text-end" style="width: 90px;" onchange="updateCartTotals()">
                    </div>
                    <div class="d-flex justify-content-between fw-semibold fs-5">
                        <span>總計</span>
                        <span id="cart-total">HK$ 0.00</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 右側：結帳區 -->
    <div class="col-12 col-lg-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="fw-semibold fs-5 mb-3">結帳</div>

                <div class="mb-3">
                    <label class="form-label small">付款方式</label>
                    <select id="payment-method" class="form-select">
                        <option value="cash">現金</option>
                        <option value="fps">轉數快 (FPS)</option>
                        <option value="card">信用卡 / 八達通</option>
                        <option value="wechat">WeChat Pay</option>
                        <option value="alipay">Alipay</option>
                        <option value="other">其他</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label small">實收金額</label>
                    <input type="number" id="amount-received" step="0.01" 
                           class="form-control form-control-lg" placeholder="0.00" oninput="calculateChange()">
                    <div id="change-info" class="mt-1 small text-muted"></div>
                </div>

                <div class="mb-3">
                    <label class="form-label small">備註</label>
                    <textarea id="sale-notes" rows="2" class="form-control" placeholder="可選"></textarea>
                </div>

                <button onclick="checkout()" 
                        class="btn btn-dark w-100 py-2 fs-5 fw-semibold mb-2">
                    確認結帳
                </button>

                <button onclick="printLastReceipt()" 
                        class="btn btn-outline-secondary w-100 btn-sm mb-1">
                    打印上一張收據（58mm） <span class="small text-success">F9</span>
                </button>
                <button onclick="showPrintFormatChoice()" 
                        class="btn btn-link btn-sm w-100 text-muted text-decoration-none p-0 mt-1">
                    選擇其他格式打印（80mm / A4 合約）
                </button>
            </div>
        </div>
    </div>

</div>

<script>
// 初始載入 + 註冊本頁專屬熱鍵
document.addEventListener('DOMContentLoaded', () => {
    loadItems();
    setupCustomerSearch();
    loadStaffForAssignment();   // 載入員工清單供指派使用

    // 註冊 POS 頁專屬熱鍵
    if (window.SalonEase && window.SalonEase.Hotkeys && window.SalonEase.Hotkeys.registerPage) {
        window.SalonEase.Hotkeys.registerPage([
            { key: 'F9', desc: '快速打印上一張收據（58mm 熱感紙）' },
            { key: 'Ctrl+P', desc: '選擇格式打印收據' },
            { key: 'S', desc: '批量指派員工' },
        ]);
    }

    // POS 專屬快速鍵：S = 批量指派
    document.addEventListener('keydown', function(e) {
        if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || e.target.tagName === 'SELECT') return;
        if (e.key.toUpperCase() === 'S') {
            e.preventDefault();
            if (window.bulkAssignStaff) window.bulkAssignStaff();
        }
    });
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
