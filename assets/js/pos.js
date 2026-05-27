/**
 * SalonEase - POS 前端邏輯
 */

let cart = [];
let currentCustomer = null;
let itemsCache = [];
let customerPackages = [];   // 客戶持有的有效套票

// 載入所有可銷售項目
async function loadItems() {
    const container = document.getElementById('items-list');
    container.innerHTML = `<div class="p-4 text-center text-[#8A8A8C]">載入中...</div>`;

    try {
        const [servicesRes, productsRes, packagesRes] = await Promise.all([
            SalonEase.fetch('/api/services.php?action=list&status=1'),
            SalonEase.fetch('/api/products.php?action=list&status=1'),
            SalonEase.fetch('/api/packages.php?action=list&status=1')
        ]);

        itemsCache = [
            ...servicesRes.data.map(s => ({ ...s, type: 'service' })),
            ...productsRes.data.map(p => ({ ...p, type: 'product' })),
            ...packagesRes.data.map(pk => ({ ...pk, type: 'package' }))
        ];

        renderItems(itemsCache);
    } catch (err) {
        container.innerHTML = `<div class="p-4 text-center text-red-500">載入失敗</div>`;
        console.error(err);
    }
}

// 渲染項目列表
function renderItems(items) {
    const container = document.getElementById('items-list');
    
    if (!items || items.length === 0) {
        container.innerHTML = `<div class="p-4 text-center text-[#8A8A8C]">沒有項目</div>`;
        return;
    }

    let html = '';
    items.forEach(item => {
        let price = 0;
        let subtitle = '';

        if (item.type === 'service') {
            price = item.price;
            subtitle = `${item.duration_min} 分鐘`;
        } else if (item.type === 'product') {
            price = item.price;
            subtitle = `庫存: ${item.stock_qty}`;
        } else if (item.type === 'package') {
            price = item.price;
            subtitle = `${item.total_sessions} 次`;
        }

        html += `
            <div onclick="addToCart('${item.type}', ${item.id}, '${item.name.replace(/'/g, "\\'")}', ${price})" 
                 class="flex justify-between items-center p-3 border-b hover:bg-[#F8F5F0] cursor-pointer">
                <div>
                    <div class="font-medium">${e(item.name)}</div>
                    <div class="text-xs text-[#8A8A8C]">${subtitle}</div>
                </div>
                <div class="text-right">
                    <div class="font-semibold">HK$ ${parseFloat(price).toFixed(0)}</div>
                    <div class="text-[10px] text-[#8FA68F]">${item.type === 'service' ? '服務' : (item.type === 'product' ? '產品' : '套票')}</div>
                </div>
            </div>
        `;
    });

    container.innerHTML = html;
}

// 篩選項目
function filterItems(type) {
    // 更新按鈕樣式
    document.querySelectorAll('[id^="filter-"]').forEach(btn => btn.classList.remove('bg-[#2C2C2E]', 'text-white'));
    document.querySelectorAll('[id^="filter-"]').forEach(btn => btn.classList.add('border', 'hover:bg-gray-100'));

    let targetBtn = document.getElementById('filter-all');
    if (type === 'service') targetBtn = document.getElementById('filter-service');
    if (type === 'product') targetBtn = document.getElementById('filter-product');
    if (type === 'package') targetBtn = document.getElementById('filter-package');

    if (targetBtn) {
        targetBtn.classList.remove('border', 'hover:bg-gray-100');
        targetBtn.classList.add('bg-[#2C2C2E]', 'text-white');
    }

    if (type === 'package' && currentCustomer && customerPackages.length > 0) {
        // 特殊處理：顯示客戶持有的套票（而非套票模板）
        renderCustomerPackages();
    } else {
        let filtered = itemsCache;
        if (type !== 'all') {
            filtered = itemsCache.filter(i => i.type === type);
        }
        renderItems(filtered);
    }
}

// 渲染客戶持有的套票（供扣減使用）
function renderCustomerPackages() {
    const container = document.getElementById('items-list');

    if (!customerPackages || customerPackages.length === 0) {
        container.innerHTML = `<div class="p-4 text-center text-[#8A8A8C]">此客戶目前沒有可用套票</div>`;
        return;
    }

    let html = '';
    customerPackages.forEach(cp => {
        const alreadyAdded = cart.some(item => item.type === 'package' && item.ref_id === cp.id);

        if (alreadyAdded) {
            html += `
                <div class="flex justify-between items-center p-3 border-b bg-gray-100 opacity-60">
                    <div>
                        <div class="font-medium">${e(cp.name)}</div>
                        <div class="text-xs text-[#8A8A8C]">剩餘 ${cp.remaining_sessions} 次 ｜ 已加入購物車</div>
                    </div>
                    <div class="text-right text-xs text-[#8A8A8C]">已加入</div>
                </div>
            `;
        } else {
            html += `
                <div onclick="addPackageRedemption(${cp.id}, '${cp.name.replace(/'/g, "\\'")}', ${cp.remaining_sessions})" 
                     class="flex justify-between items-center p-3 border-b hover:bg-[#F8F5F0] cursor-pointer">
                    <div>
                        <div class="font-medium">${e(cp.name)}</div>
                        <div class="text-xs text-[#8A8A8C]">剩餘 ${cp.remaining_sessions} 次 ｜ 到期 ${cp.expiry_date}</div>
                    </div>
                    <div class="text-right">
                        <div class="font-semibold text-[#8FA68F]">使用套票</div>
                        <div class="text-[10px] text-[#8FA68F]">扣 1 次</div>
                    </div>
                </div>
            `;
        }
    });

    container.innerHTML = html;
}

// 快速顯示客戶可用套票（從客戶資訊點擊觸發）
function showCustomerPackagesQuick() {
    // 切換到套票分類
    filterItems('package');

    // 確保顯示的是客戶的套票（而非模板）
    setTimeout(() => {
        if (currentCustomer && customerPackages.length > 0) {
            renderCustomerPackages();
        }
    }, 50);
}

// 加入套票扣減到購物車
function addPackageRedemption(customerPackageId, packageName, remaining) {
    // 檢查是否已經加入同一張套票
    const alreadyAdded = cart.some(item => item.type === 'package' && item.ref_id === customerPackageId);
    if (alreadyAdded) {
        SalonEase.toast('此套票已經在購物車中', 'error');
        return;
    }

    cart.push({
        id: Date.now(),
        ref_id: customerPackageId,
        type: 'package',
        name: `使用套票：${packageName}`,
        subtitle: `扣減 1 次（剩 ${remaining} 次）`,
        unit_price: 0,
        qty: 1
    });

    renderCart();
    updateCartTotals();
}

// 加入購物車
function addToCart(type, id, name, price) {
    cart.push({
        id: Date.now(), // 購物車內部 ID
        ref_id: id,
        type: type,
        name: name,
        unit_price: parseFloat(price),
        qty: 1
    });

    renderCart();
    updateCartTotals();
}

// 渲染購物車
function renderCart() {
    const container = document.getElementById('cart-items');

    if (cart.length === 0) {
        container.innerHTML = `<div class="h-full flex items-center justify-center text-[#8A8A8C]">購物車是空的</div>`;
        return;
    }

    let html = '';
    cart.forEach((item, index) => {
        const lineTotal = item.unit_price * item.qty;
        const isPackage = item.type === 'package';

        let nameHtml = `<div class="font-medium text-sm">${e(item.name)}</div>`;
        let subtitleHtml = '';

        if (isPackage) {
            subtitleHtml = `<div class="text-xs text-purple-600">${e(item.subtitle || '套票扣減')}</div>`;
            nameHtml = `<div class="font-medium text-sm text-purple-700">${e(item.name)}</div>`;
        } else {
            subtitleHtml = `<div class="text-xs text-[#8A8A8C]">HK$ ${item.unit_price.toFixed(0)} × ${item.qty}</div>`;
        }

        html += `
            <div class="flex justify-between items-center p-2 border-b ${isPackage ? 'bg-purple-50' : ''}">
                <div class="flex-1">
                    ${nameHtml}
                    ${subtitleHtml}
                </div>
                <div class="text-right">
                    <div class="font-semibold ${isPackage ? 'text-purple-700' : ''}">
                        ${isPackage ? '扣減' : 'HK$ ' + lineTotal.toFixed(0)}
                    </div>
                    <div class="flex gap-1 mt-1 justify-end">
                        ${!isPackage ? `
                            <button onclick="changeQty(${index}, -1)" class="w-5 h-5 text-xs border rounded">-</button>
                            <button onclick="changeQty(${index}, 1)" class="w-5 h-5 text-xs border rounded">+</button>
                        ` : ''}
                        <button onclick="removeFromCart(${index})" class="w-5 h-5 text-xs text-red-500">×</button>
                    </div>
                </div>
            </div>
        `;
    });

    container.innerHTML = html;
}

// 改變數量
function changeQty(index, delta) {
    cart[index].qty = Math.max(1, cart[index].qty + delta);
    renderCart();
    updateCartTotals();
}

// 移除項目
function removeFromCart(index) {
    const removedItem = cart[index];
    cart.splice(index, 1);
    renderCart();
    updateCartTotals();

    // 如果目前正在顯示客戶套票列表，且移除的是套票，刷新列表
    const packageBtn = document.getElementById('filter-package');
    if (packageBtn && packageBtn.classList.contains('bg-[#2C2C2E]') && removedItem.type === 'package') {
        renderCustomerPackages();
    }
}

// 清空購物車
function clearCart() {
    if (!confirm('確定要清空購物車嗎？')) return;

    const hadPackages = cart.some(item => item.type === 'package');

    cart = [];
    currentCustomer = null;
    renderCart();
    updateCartTotals();
    document.getElementById('pos-customer-info').innerHTML = '';

    // 如果正在看套票列表且之前有套票，刷新列表
    const packageBtn = document.getElementById('filter-package');
    if (packageBtn && packageBtn.classList.contains('bg-[#2C2C2E]') && hadPackages) {
        renderCustomerPackages();
    }
}

// 更新總計
function updateCartTotals() {
    let subtotal = 0;
    cart.forEach(item => {
        subtotal += item.unit_price * item.qty;
    });

    const discount = parseFloat(document.getElementById('cart-discount').value) || 0;
    const total = Math.max(0, subtotal - discount);

    document.getElementById('cart-subtotal').textContent = `HK$ ${subtotal.toFixed(2)}`;
    document.getElementById('cart-total').textContent = `HK$ ${total.toFixed(2)}`;

    // 更新找零
    calculateChange();
}

// 計算找零
function calculateChange() {
    const total = parseFloat(document.getElementById('cart-total').textContent.replace('HK$ ', '')) || 0;
    const received = parseFloat(document.getElementById('amount-received').value) || 0;
    const change = received - total;

    const info = document.getElementById('change-info');
    if (received > 0) {
        if (change >= 0) {
            info.innerHTML = `<span class="text-green-600">應找：HK$ ${change.toFixed(2)}</span>`;
        } else {
            info.innerHTML = `<span class="text-red-600">尚差：HK$ ${Math.abs(change).toFixed(2)}</span>`;
        }
    } else {
        info.innerHTML = '';
    }
}

// 客戶搜尋
function setupCustomerSearch() {
    const input = document.getElementById('pos-customer-search');
    let timer;

    input.addEventListener('input', function() {
        clearTimeout(timer);
        const keyword = this.value.trim();

        if (!keyword) {
            currentCustomer = null;
            customerPackages = [];
            document.getElementById('pos-customer-info').innerHTML = '';

            // 清除客戶時，如果目前在「套票」分類，切回「全部」
            const packageBtn = document.getElementById('filter-package');
            if (packageBtn && packageBtn.classList.contains('bg-[#2C2C2E]')) {
                filterItems('all');
            }
            return;
        }

        timer = setTimeout(async () => {
            try {
                const res = await SalonEase.fetch(`/api/customers.php?action=list&search=${encodeURIComponent(keyword)}`);
                if (res.data && res.data.length > 0) {
                    const c = res.data[0];
                    currentCustomer = c;
                    let infoHtml = `<span class="text-[#8FA68F]">✓ ${e(c.name)} (${e(c.phone)})</span>`;

                    // 載入客戶持有的有效套票
                    await loadCustomerPackages(c.id);

                    if (customerPackages.length > 0) {
                        infoHtml += ` <span class="text-xs text-purple-600 cursor-pointer" onclick="showCustomerPackagesQuick()">(有 ${customerPackages.length} 張可用套票)</span>`;
                    }

                    document.getElementById('pos-customer-info').innerHTML = infoHtml;

                    // === A1-1f 改進：如果客戶有可用套票，自動切換到「套票」分類 ===
                    if (customerPackages.length > 0) {
                        // 只有在目前不是套票分類時才自動切換，避免干擾用戶
                        const packageBtn = document.getElementById('filter-package');
                        if (!packageBtn || !packageBtn.classList.contains('bg-[#2C2C2E]')) {
                            filterItems('package');
                        } else {
                            // 如果已經在套票分類，直接刷新顯示客戶的套票
                            renderCustomerPackages();
                        }
                    } else {
                        // 如果客戶沒有套票，但目前在套票分類，切回「全部」
                        const packageBtn = document.getElementById('filter-package');
                        if (packageBtn && packageBtn.classList.contains('bg-[#2C2C2E]')) {
                            filterItems('all');
                        }
                    }
                } else {
                    currentCustomer = null;
                    customerPackages = [];
                    document.getElementById('pos-customer-info').innerHTML = 
                        `<span class="text-[#8A8A8C]">找不到客戶</span>`;
                }
            } catch (e) {
                console.error(e);
            }
        }, 300);
    });
}

// 載入客戶持有的有效套票
async function loadCustomerPackages(customerId) {
    customerPackages = [];
    try {
        const res = await SalonEase.fetch(`/api/packages.php?action=customer_packages&customer_id=${customerId}`);
        if (res.data) {
            // 只保留還有剩餘次數且未過期的
            const today = new Date().toISOString().split('T')[0];
            customerPackages = res.data.filter(p => 
                p.remaining_sessions > 0 && p.expiry_date >= today
            );
        }
    } catch (err) {
        console.error('載入客戶套票失敗', err);
        customerPackages = [];
    }
}

// 快速建立新客戶
async function quickCreateCustomer() {
    const name = prompt('請輸入客戶姓名：');
    if (!name) return;

    const phone = prompt('請輸入客戶電話：');
    if (!phone) return;

    try {
        const res = await SalonEase.fetch('/api/customers.php?action=create', {
            method: 'POST',
            body: {
                name: name,
                phone: phone
            }
        });

        currentCustomer = { id: res.data.id, name: name, phone: phone };
        document.getElementById('pos-customer-search').value = name;
        document.getElementById('pos-customer-info').innerHTML = 
            `<span class="text-[#8FA68F]">✓ ${e(name)} (${e(phone)})</span>`;

        SalonEase.toast('客戶已建立');
    } catch (err) {
        SalonEase.toast(err.message, 'error');
    }
}

// 結帳
async function checkout() {
    if (cart.length === 0) {
        alert('購物車是空的');
        return;
    }

    const total = parseFloat(document.getElementById('cart-total').textContent.replace('HK$ ', '')) || 0;
    const received = parseFloat(document.getElementById('amount-received').value) || 0;
    const method = document.getElementById('payment-method').value;
    const notes = document.getElementById('sale-notes').value;

    if (received < total) {
        alert('實收金額不足');
        return;
    }

    const payload = {
        customer_id: currentCustomer ? currentCustomer.id : '',
        items: cart.map(item => ({
            type: item.type,
            ref_id: item.ref_id,
            name: item.name,
            qty: item.qty,
            unit_price: item.unit_price
        })),
        discount: parseFloat(document.getElementById('cart-discount').value) || 0,
        payment_method: method,
        amount_received: received,
        notes: notes
    };

    try {
        const res = await SalonEase.fetch('/api/sales.php?action=checkout', {
            method: 'POST',
            body: payload
        });

        SalonEase.toast('結帳成功！');

        // 清空購物車
        cart = [];
        currentCustomer = null;
        renderCart();
        updateCartTotals();
        document.getElementById('amount-received').value = '';
        document.getElementById('change-info').innerHTML = '';
        document.getElementById('pos-customer-info').innerHTML = '';
        document.getElementById('sale-notes').value = '';

        // 儲存最後一張收據 ID，方便打印
        window.lastSaleId = res.data.id;

        // 自動跳出打印選擇
        setTimeout(() => {
            if (confirm('是否立即打印收據？')) {
                printReceipt(res.data.id);
            }
        }, 800);

    } catch (err) {
        SalonEase.toast(err.message, 'error');
    }
}

// 打印收據
function printReceipt(saleId) {
    if (!saleId && window.lastSaleId) {
        saleId = window.lastSaleId;
    }
    if (!saleId) {
        alert('沒有可打印的收據');
        return;
    }

    // 開新視窗打印（之後可改為更完整的打印模板）
    const url = `/api/sales.php?action=print_receipt&id=${saleId}`;
    window.open(url, '_blank');
}

function printLastReceipt() {
    if (window.lastSaleId) {
        printReceipt(window.lastSaleId);
    } else {
        alert('目前沒有上一張收據');
    }
}

// 工具函數
function e(str) {
    return str ? str.replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m])) : '';
}

// 暴露給全域（給 pos.php 內聯 script 呼叫）
window.loadItems = loadItems;
window.filterItems = filterItems;
window.addToCart = addToCart;
window.clearCart = clearCart;
window.updateCartTotals = updateCartTotals;
window.calculateChange = calculateChange;
window.checkout = checkout;
window.printLastReceipt = printLastReceipt;