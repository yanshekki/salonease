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

<div class="grid grid-cols-1 lg:grid-cols-12 gap-4">

    <!-- 左側：商品/服務選擇區 -->
    <div class="lg:col-span-5">
        <div class="bg-white rounded-2xl border border-gray-100 p-4">
            <div class="flex items-center justify-between mb-3">
                <div class="font-semibold text-lg">加入項目</div>
                <div class="text-sm text-[#8A8A8C]">搜尋服務或產品</div>
            </div>

            <input type="text" id="item-search" 
                   class="salon-input mb-3" 
                   placeholder="輸入服務或產品名稱搜尋...">

            <!-- 分類標籤 -->
            <div class="flex gap-2 mb-3">
                <button onclick="filterItems('all')" id="filter-all"
                        class="px-3 py-1 text-sm rounded-xl bg-[#2C2C2E] text-white">全部</button>
                <button onclick="filterItems('service')" id="filter-service"
                        class="px-3 py-1 text-sm rounded-xl border hover:bg-gray-100">服務</button>
                <button onclick="filterItems('product')" id="filter-product"
                        class="px-3 py-1 text-sm rounded-xl border hover:bg-gray-100">產品</button>
                <button onclick="filterItems('package')" id="filter-package"
                        class="px-3 py-1 text-sm rounded-xl border hover:bg-gray-100">套票</button>
            </div>

            <div id="items-list" class="max-h-[420px] overflow-auto border rounded-xl">
                <!-- 由 JS 動態載入項目 -->
                <div class="p-4 text-center text-[#8A8A8C]">載入中...</div>
            </div>
        </div>
    </div>

    <!-- 中間：購物車 -->
    <div class="lg:col-span-4">
        <div class="bg-white rounded-2xl border border-gray-100 p-4 h-full flex flex-col">
            <div class="flex items-center justify-between mb-3">
                <div class="font-semibold text-lg">購物車</div>
                <button onclick="clearCart()" class="text-sm text-red-500 hover:underline">清空</button>
            </div>

            <div id="cart-items" class="flex-1 overflow-auto min-h-[280px] border rounded-xl p-2 mb-3">
                <!-- 購物車項目由 JS 渲染 -->
                <div class="h-full flex items-center justify-center text-[#8A8A8C]">
                    購物車是空的
                </div>
            </div>

            <!-- 客戶選擇 -->
            <div class="mb-3">
                <label class="block text-sm font-medium mb-1">客戶（可選）</label>
                <div class="flex gap-2">
                    <input type="text" id="pos-customer-search" class="salon-input flex-1" placeholder="搜尋客戶姓名或電話">
                    <button onclick="quickCreateCustomer()" class="salon-btn salon-btn-secondary text-sm">新客戶</button>
                </div>
                <div id="pos-customer-info" class="mt-1 text-sm text-[#5A5A5C]"></div>
            </div>

            <div class="border-t pt-3">
                <div class="flex justify-between text-sm mb-1">
                    <span>小計</span>
                    <span id="cart-subtotal">HK$ 0.00</span>
                </div>
                <div class="flex justify-between text-sm mb-1">
                    <span>折扣</span>
                    <input type="number" id="cart-discount" value="0" step="0.01" 
                           class="w-24 text-right border rounded px-2 py-0.5 text-sm" onchange="updateCartTotals()">
                </div>
                <div class="flex justify-between font-semibold text-lg">
                    <span>總計</span>
                    <span id="cart-total">HK$ 0.00</span>
                </div>
            </div>
        </div>
    </div>

    <!-- 右側：結帳區 -->
    <div class="lg:col-span-3">
        <div class="bg-white rounded-2xl border border-gray-100 p-4 h-full">
            <div class="font-semibold text-lg mb-3">結帳</div>

            <div class="space-y-3">
                <div>
                    <label class="block text-sm font-medium mb-1">付款方式</label>
                    <select id="payment-method" class="salon-input">
                        <option value="cash">現金</option>
                        <option value="fps">轉數快 (FPS)</option>
                        <option value="card">信用卡 / 八達通</option>
                        <option value="wechat">WeChat Pay</option>
                        <option value="alipay">Alipay</option>
                        <option value="other">其他</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1">實收金額</label>
                    <input type="number" id="amount-received" step="0.01" 
                           class="salon-input text-lg" placeholder="0.00" oninput="calculateChange()">
                    <div id="change-info" class="mt-1 text-sm text-[#5A5A5C]"></div>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1">備註</label>
                    <textarea id="sale-notes" rows="2" class="salon-input" placeholder="可選"></textarea>
                </div>

                <button onclick="checkout()" 
                        class="w-full py-3 bg-[#2C2C2E] hover:bg-black text-white font-semibold rounded-2xl text-lg transition active:scale-[0.985]">
                    確認結帳
                </button>

                <button onclick="printLastReceipt()" 
                        class="w-full py-2 border text-sm rounded-xl hover:bg-gray-50">
                    打印上一張收據（58mm）　<span class="text-[10px] text-[#8FA68F]">F9</span>
                </button>
                <button onclick="showPrintFormatChoice()" 
                        class="w-full py-1.5 mt-1 text-xs text-[#5A5A5C] hover:text-[#2C2C2E] underline">
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

    // 註冊 POS 頁專屬熱鍵（F9 已全域，但這裡再強調）
    if (window.SalonEase && window.SalonEase.Hotkeys && window.SalonEase.Hotkeys.registerPage) {
        window.SalonEase.Hotkeys.registerPage([
            { key: 'F9', desc: '快速打印上一張收據（58mm 熱感紙）' },
            { key: 'Ctrl+P', desc: '選擇格式打印收據' },
        ]);
    }
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
