/**
 * SalonEase - POS 前端邏輯
 */

let cart = [];
let currentCustomer = null;
let itemsCache = [];
let customerPackages = [];   // 客戶持有的有效套票
let staffList = [];          // 員工清單（供指派使用）
let currentStaffId = null;   // 目前登入員工 ID
let lastAssignedStaffId = null;  // 記住上次指派的員工（方便連續指派）

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
            // 支援自訂扣減次數（1 ~ 剩餘次數）
            const maxSessions = cp.remaining_sessions;
            html += `
                <div class="p-3 border-b hover:bg-[#F8F5F0]">
                    <div class="flex justify-between items-center">
                        <div>
                            <div class="font-medium">${e(cp.name)}</div>
                            <div class="text-xs text-[#8A8A8C]">剩餘 ${maxSessions} 次 ｜ 到期 ${cp.expiry_date}</div>
                        </div>
                        <div class="text-right">
                            <div class="font-semibold text-purple-600">扣減套票</div>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 mt-2">
                        <span class="text-sm">扣</span>
                        <input type="number" id="sessions-${cp.id}" 
                               class="w-16 border rounded px-2 py-1 text-sm" 
                               value="1" min="1" max="${maxSessions}">
                        <span class="text-sm">次</span>
                        <button onclick="addCustomPackageRedemption(${cp.id}, '${cp.name.replace(/'/g, "\\'")}', ${maxSessions})" 
                                class="ml-2 px-3 py-1 text-sm bg-purple-600 text-white rounded hover:bg-purple-700">
                            加入購物車
                        </button>
                    </div>
                </div>
            `;
        }
    });

    container.innerHTML = html;
}

// 加入自訂次數的套票扣減
function addCustomPackageRedemption(customerPackageId, packageName, maxSessions) {
    const input = document.getElementById(`sessions-${customerPackageId}`);
    let sessions = parseInt(input.value) || 1;

    if (sessions < 1) sessions = 1;
    if (sessions > maxSessions) sessions = maxSessions;

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
        name: packageName,
        subtitle: `扣減 ${sessions} 次`,
        unit_price: 0,
        qty: sessions,
        max_sessions: maxSessions
    });

    renderCart();
    updateCartTotals();
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
    // 防呆 1：必須選擇客戶才能使用套票扣減
    if (!currentCustomer) {
        SalonEase.toast('請先選擇客戶，才能使用套票扣減', 'error');
        return;
    }

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
        name: packageName,
        subtitle: `扣減 1 次`,
        unit_price: 0,
        qty: 1,
        max_sessions: remaining
    });

    renderCart();
    updateCartTotals();
}

// 加入購物車
function addToCart(type, id, name, price) {
    // 優先使用上次指派的員工，其次目前登入員工
    const defaultStaff = lastAssignedStaffId || currentStaffId || null;

    cart.push({
        id: Date.now(), // 購物車內部 ID
        ref_id: id,
        type: type,
        name: name,
        unit_price: parseFloat(price),
        qty: 1,
        assigned_staff_id: defaultStaff
    });

    // 低庫存警示（僅產品）
    if (type === 'product') {
        const product = itemsCache.find(p => p.id == id && p.type === 'product');
        if (product) {
            const threshold = product.effective_low_stock_threshold || 5;
            if (product.stock_qty <= threshold) {
                SalonEase.toast(`⚠ 低庫存：${name}（剩餘 ${product.stock_qty} 件，門檻 ${threshold}）`, 'error');
            }
        }
    }

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

        if (isPackage) {
            // 套票扣減項目 - 清晰紫色區分 + 扣 X 次 + 可調整次數 + 移除
            const deducted = item.qty || 1;
            const maxS = item.max_sessions || 99;
            html += `
                <div class="flex justify-between items-center p-2 border-b bg-purple-50 border-l-4 border-purple-400">
                    <div class="flex-1 min-w-0">
                        <div class="font-medium text-sm text-purple-700">${e(item.name)}</div>
                        <div class="text-[11px] text-purple-500">套票扣減</div>
                        <div class="mt-0.5 text-sm font-semibold text-purple-700">
                            扣 <span class="text-base">${deducted}</span> 次
                            <span class="font-normal text-purple-500">（不計費）</span>
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="flex items-center gap-1 justify-end">
                            <button onclick="changeQty(${index}, -1)" 
                                    class="w-6 h-6 text-sm border border-purple-300 rounded bg-white hover:bg-purple-100 active:scale-95">-</button>
                            <span class="inline-block w-6 text-center text-sm font-semibold text-purple-700">${deducted}</span>
                            <button onclick="changeQty(${index}, 1)" 
                                    class="w-6 h-6 text-sm border border-purple-300 rounded bg-white hover:bg-purple-100 active:scale-95">+</button>
                            <button onclick="removeFromCart(${index})" 
                                    class="ml-1 w-6 h-6 text-sm text-red-500 hover:bg-red-50 rounded border border-transparent hover:border-red-200">×</button>
                        </div>
                        <div class="text-[10px] text-purple-400 mt-0.5">最多 ${maxS} 次</div>
                    </div>
                </div>
            `;
        } else {
            // 一般收費項目 + 指派員工功能 + 低庫存警示
            const assignedName = getStaffName(item.assigned_staff_id) || '未指派';
            const product = itemsCache.find(p => p.id == item.ref_id && p.type === 'product');
            const isLow = product && product.stock_qty <= (product.effective_low_stock_threshold || 5);
            const lowStockBadge = isLow 
                ? `<span class="ml-1 text-[9px] px-1 py-0 bg-red-100 text-red-600 rounded">低庫存</span>` 
                : '';

            html += `
                <div class="flex justify-between items-center p-2 border-b">
                    <div class="flex-1">
                        <div class="font-medium text-sm">${e(item.name)}${lowStockBadge}</div>
                        <div class="text-xs text-[#8A8A8C]">HK$ ${item.unit_price.toFixed(0)} × ${item.qty}</div>
                        <div class="text-[10px] mt-0.5">
                            <span class="text-[#8A8A8C]">指派：</span>
                            <select onchange="changeAssignedStaff(${index}, this.value)" 
                                    class="text-xs border rounded px-1 py-0 bg-white"
                                    title="指派負責此項目的員工（影響佣金計算）"
                                    aria-label="指派員工">
                                <option value="">開單人</option>
                                ${staffList.map(s => 
                                    `<option value="${s.id}" ${item.assigned_staff_id == s.id ? 'selected' : ''}>${e(s.name)}</option>`
                                ).join('')}
                            </select>
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="font-semibold">HK$ ${lineTotal.toFixed(0)}</div>
                        <div class="flex gap-1 mt-1 justify-end">
                            <button onclick="changeQty(${index}, -1)" class="w-5 h-5 text-xs border rounded">-</button>
                            <button onclick="changeQty(${index}, 1)" class="w-5 h-5 text-xs border rounded">+</button>
                            <button onclick="removeFromCart(${index})" class="w-5 h-5 text-xs text-red-500">×</button>
                        </div>
                    </div>
                </div>
            `;
        }
    });

    container.innerHTML = html;
}

// 改變數量（一般項目 + 套票扣減次數支援）
function changeQty(index, delta) {
    const item = cart[index];
    if (item.type === 'package') {
        const max = item.max_sessions || 99;
        item.qty = Math.max(1, Math.min(max, item.qty + delta));
    } else {
        item.qty = Math.max(1, item.qty + delta);
    }
    renderCart();
    updateCartTotals();
}

// 取得員工名稱
function getStaffName(staffId) {
    if (!staffId) return null;
    const s = staffList.find(x => x.id == staffId);
    return s ? s.name : null;
}

// 改變項目指派的員工（同時記住選擇，方便下次加入項目）
function changeAssignedStaff(index, staffId) {
    const val = staffId ? parseInt(staffId) : null;
    cart[index].assigned_staff_id = val;
    if (val) {
        lastAssignedStaffId = val;
    }
}

// 批量指派員工給所有非套票項目
function bulkAssignStaff() {
    if (staffList.length === 0) {
        SalonEase.toast('尚未載入員工清單', 'error');
        return;
    }

    // 簡單用 prompt 選擇員工（輕量做法）
    let options = staffList.map((s, i) => `${i+1}. ${s.name}`).join('\n');
    let choice = prompt(
        '請輸入數字選擇要批量指派的員工：\n' + options + '\n\n或直接輸入 0 取消指派'
    );

    if (choice === null) return;

    let staffId = null;
    let idx = parseInt(choice) - 1;

    if (choice === '0') {
        staffId = null;
    } else if (!isNaN(idx) && staffList[idx]) {
        staffId = staffList[idx].id;
        lastAssignedStaffId = staffId;
    } else {
        SalonEase.toast('輸入無效', 'error');
        return;
    }

    let changed = 0;
    cart.forEach(item => {
        if (item.type !== 'package') {
            item.assigned_staff_id = staffId;
            changed++;
        }
    });

    if (changed > 0) {
        renderCart();
        SalonEase.toast(`已為 ${changed} 項指派員工`);
    }
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
            // A1-1h 防呆：清除客戶時，如果購物車有套票扣減，自動移除並提示
            const hadPackageRedemption = cart.some(item => item.type === 'package');
            if (hadPackageRedemption) {
                cart = cart.filter(item => item.type !== 'package');
                renderCart();
                updateCartTotals();
                SalonEase.toast('已清除購物車中的套票扣減項目（客戶已清除）', 'info');
            }

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
                    // A1-1h 防呆：切換客戶時，如果購物車有套票扣減，清除它們
                    const hadPackageRedemption = cart.some(item => item.type === 'package');
                    if (hadPackageRedemption) {
                        cart = cart.filter(item => item.type !== 'package');
                        renderCart();
                        updateCartTotals();
                        SalonEase.toast('已清除之前客戶的套票扣減項目', 'info');
                    }

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
        SalonEase.toast('購物車是空的', 'error');
        return;
    }

    // A1-1h 防呆：如果有套票扣減項目，必須選擇客戶
    const hasPackageRedemption = cart.some(item => item.type === 'package');
    if (hasPackageRedemption && !currentCustomer) {
        SalonEase.toast('使用套票扣減必須先選擇客戶', 'error');
        return;
    }

    const total = parseFloat(document.getElementById('cart-total').textContent.replace('HK$ ', '')) || 0;
    const received = parseFloat(document.getElementById('amount-received').value) || 0;
    const method = document.getElementById('payment-method').value;
    const notes = document.getElementById('sale-notes').value;

    if (received < total) {
        SalonEase.toast('實收金額不足', 'error');
        return;
    }

    // 結帳前低庫存檢查
    const lowStockItems = cart.filter(item => {
        if (item.type !== 'product') return false;
        const product = itemsCache.find(p => p.id == item.ref_id && p.type === 'product');
        if (!product) return false;
        const threshold = product.effective_low_stock_threshold || 5;
        return product.stock_qty <= threshold;
    });

    if (lowStockItems.length > 0) {
        const names = lowStockItems.map(i => i.name).join('、');
        if (!confirm(`以下產品庫存偏低：${names}\n\n確定要繼續結帳嗎？`)) {
            return;
        }
    }

    const payload = {
        customer_id: currentCustomer ? currentCustomer.id : '',
        items: cart.map(item => ({
            type: item.type,
            ref_id: item.ref_id,
            name: item.name,
            qty: item.qty,
            unit_price: item.unit_price,
            staff_id: item.assigned_staff_id || null   // 指派員工
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

        // A1-1k：結帳成功後，如果仍有選擇客戶，重新載入其套票剩餘次數
        if (currentCustomer) {
            await loadCustomerPackages(currentCustomer.id);

            // 如果目前正在顯示客戶的套票列表，刷新它
            const packageBtn = document.getElementById('filter-package');
            if (packageBtn && packageBtn.classList.contains('bg-[#2C2C2E]')) {
                renderCustomerPackages();
            }
        }

        // 取得系統預設打印寬度（來自設定頁）
        let defaultWidth = '58';
        try {
            const cfg = await SalonEase.fetch('/api/settings.php?action=get');
            if (cfg.data && cfg.data.printer_width) {
                defaultWidth = cfg.data.printer_width;
            }
        } catch (e) {}

        // A 改進：結帳後提供清晰打印選擇（預設使用設定頁的打印機寬度）
        const defaultLabel = defaultWidth === '80' ? '80mm' : '58mm';
        setTimeout(() => {
            if (confirm(`結帳完成！是否立即打印熱感紙收據（${defaultLabel}）？\n\n按「取消」可稍後選擇其他格式。`)) {
                printReceipt(res.data.id, defaultWidth);
            } else {
                setTimeout(() => {
                    if (confirm('要打印其他格式嗎？\n是 = 選擇 58mm / 80mm / A4')) {
                        showPrintFormatChoice(res.data.id);
                    }
                }, 300);
            }
        }, 650);

    } catch (err) {
        let msg = err.message || '結帳失敗';

        // A1-1i：改善套票扣減相關錯誤提示（更友善、具體）
        if (msg.includes('剩餘次數不足')) {
            msg = '套票剩餘次數不足，無法扣減。請檢查客戶套票餘額。';
        } else if (msg.includes('已過期')) {
            msg = '所選套票已過期，無法使用。請選擇其他套票或改為收費。';
        } else if (msg.includes('不屬於目前選擇的客戶')) {
            msg = '套票不屬於目前客戶。請重新選擇正確客戶或移除套票扣減。';
        } else if (msg.includes('找不到該套票記錄')) {
            msg = '找不到該套票記錄，可能已被刪除或失效。';
        } else if (msg.includes('套票扣減失敗')) {
            msg = '套票扣減失敗，請確認套票狀態後重試。';
        } else if (msg.includes('套票')) {
            // 捕捉其他套票相關通用錯誤
            msg = '套票處理發生問題：' + msg;
        }

        SalonEase.toast(msg, 'error');
    }
}

// 打印收據（支援格式：58 / 80 / a4）
function printReceipt(saleId, format = '58') {
    if (!saleId && window.lastSaleId) {
        saleId = window.lastSaleId;
    }
    if (!saleId) {
        SalonEase.toast('沒有可打印的收據', 'error');
        return;
    }

    const url = `/api/sales.php?action=print_receipt&id=${saleId}&format=${format}`;
    const win = window.open(url, '_blank');

    // 熱感紙默認會自動打印，A4 版由用戶手動觸發
    if (format === 'a4' && win) {
        SalonEase.toast('A4 收據已開啟，請按 Ctrl+P 打印', 'info');
    }
}

function printLastReceipt(format = '58') {
    if (window.lastSaleId) {
        printReceipt(window.lastSaleId, format);
    } else {
        SalonEase.toast('目前沒有上一張收據', 'error');
    }
}

// 快速選擇打印格式（結帳後或手動使用）
function showPrintFormatChoice(saleId) {
    if (!saleId && window.lastSaleId) saleId = window.lastSaleId;
    if (!saleId) {
        SalonEase.toast('沒有可打印的收據', 'error');
        return;
    }

    const choice = prompt(
        '請選擇打印格式：\n' +
        '1 = 熱感紙 58mm（最常用）\n' +
        '2 = 熱感紙 80mm\n' +
        '3 = A4 正式收據 / 合約（含簽名欄）\n\n' +
        '直接輸入 1 / 2 / 3'
    );

    let format = '58';
    if (choice === '2') format = '80';
    if (choice === '3') format = 'a4';

    printReceipt(saleId, format);
}

// 工具函數
function e(str) {
    return str ? str.replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m])) : '';
}

// 載入員工清單（供 POS 指派員工使用）
async function loadStaffForAssignment() {
    try {
        const res = await SalonEase.fetch('/api/staff.php?action=list&is_active=1');
        staffList = res.data || [];

        // 同時取得目前登入員工
        try {
            const me = await SalonEase.fetch('/api/auth.php?action=me');
            currentStaffId = me.data?.id || null;
        } catch (e) {}
    } catch (e) {
        console.warn('載入員工清單失敗', e);
    }
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
window.showPrintFormatChoice = showPrintFormatChoice;
window.bulkAssignStaff = bulkAssignStaff;