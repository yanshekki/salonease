<?php
/**
 * SalonEase - 零售產品管理
 */
require_once __DIR__ . '/includes/auth.php';
require_login();

require_once __DIR__ . '/includes/functions.php';

$pageTitle = '零售產品管理';
$pageSubtitle = '管理美容院零售產品及庫存';
$extraJs = 'hotkeys.js';
?>
<?php include __DIR__ . '/includes/header.php'; ?>

<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-semibold"><?= e($pageTitle) ?></h1>
        <p class="text-[#5A5A5C] text-sm mt-1"><?= e($pageSubtitle) ?></p>
    </div>
    <button onclick="showAddModal()"
            class="salon-btn salon-btn-primary flex items-center gap-x-2">
        <span>+ 新增產品</span>
        <span class="text-xs opacity-75">[N]</span>
    </button>
</div>

<div class="bg-white rounded-2xl border border-gray-100 p-4 mb-4 flex flex-wrap gap-3 items-end">
    <div class="flex-1 min-w-[240px]">
        <label class="block text-xs text-[#5A5A5C] mb-1">搜尋（名稱 / SKU）</label>
        <input type="text" id="search" class="salon-input" oninput="debounceLoadProducts()">
    </div>
    <div>
        <label class="block text-xs text-[#5A5A5C] mb-1">類別</label>
        <select id="category-filter" class="salon-input" onchange="loadProducts()">
            <option value="">全部</option>
            <option value="護膚品">護膚品</option>
            <option value="身體護理">身體護理</option>
            <option value="其他">其他</option>
        </select>
    </div>
    <div>
        <label class="block text-xs text-[#5A5A5C] mb-1">狀態</label>
        <select id="status-filter" class="salon-input" onchange="loadProducts()">
            <option value="">全部</option>
            <option value="1">已啟用</option>
            <option value="0">已停用</option>
        </select>
    </div>
    <div class="flex items-center pt-5">
        <label class="flex items-center gap-2 text-sm cursor-pointer" title="只顯示庫存低於門檻的產品">
            <input type="checkbox" id="low-stock-only" onchange="loadProducts()" class="accent-[#2C2C2E]">
            <span>只顯示低庫存</span>
        </label>
    </div>
    <button onclick="loadProducts()" class="salon-btn salon-btn-secondary h-[42px]">重新載入</button>
</div>

<div class="bg-white rounded-2xl border border-gray-100 overflow-hidden">
    <table class="salon-table w-full">
        <thead>
            <tr>
                <th>產品名稱</th>
                <th>SKU</th>
                <th>售價</th>
                <th>庫存</th>
                <th>類別</th>
                <th>狀態</th>
                <th class="text-right">操作</th>
            </tr>
        </thead>
        <tbody id="products-list">
            <tr><td colspan="7" class="py-8 text-center text-[#8A8A8C]">載入中...</td></tr>
        </tbody>
    </table>
</div>

<!-- Modal -->
<div id="product-modal" class="hidden fixed inset-0 bg-black/40 z-[70] flex items-center justify-center" onclick="hideProductModal()">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-md mx-4" onclick="event.stopImmediatePropagation()">
        <div class="px-5 py-4 border-b flex items-center justify-between">
            <div class="font-semibold text-lg" id="modal-title">新增產品</div>
            <button onclick="hideProductModal()" class="text-2xl leading-none text-gray-400 hover:text-gray-600">×</button>
        </div>

        <div class="p-5 space-y-4">
            <input type="hidden" id="product-id">

            <div>
                <label class="block text-sm font-medium mb-1">產品名稱 <span class="text-red-500">*</span></label>
                <input type="text" id="product-name" class="salon-input">
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-1">SKU</label>
                    <input type="text" id="product-sku" class="salon-input">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">類別</label>
                    <select id="product-category" class="salon-input">
                        <option value="">未分類</option>
                        <option value="護膚品">護膚品</option>
                        <option value="身體護理">身體護理</option>
                        <option value="其他">其他</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-1">售價（HK$） <span class="text-red-500">*</span></label>
                    <input type="number" step="0.01" id="product-price" class="salon-input">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">成本價</label>
                    <input type="number" step="0.01" id="product-cost" class="salon-input" value="0">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium mb-1">初始庫存數量</label>
                <input type="number" id="product-stock" class="salon-input" value="0">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1" title="留空則使用設定頁的全域預設門檻">低庫存門檻（留空則用全域預設）</label>
                <input type="number" id="product-low-stock" class="salon-input" value="" placeholder="例如 5" title="留空則使用設定頁的全域預設門檻">
            </div>
        </div>

        <div class="px-5 py-4 bg-gray-50 flex justify-end gap-3 rounded-b-2xl">
            <button onclick="hideProductModal()" class="salon-btn salon-btn-secondary">取消</button>
            <button onclick="saveProduct()" class="salon-btn salon-btn-primary" id="save-btn">新增產品</button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    loadProducts();

    if (window.SalonEase && window.SalonEase.Hotkeys) {
        window.SalonEase.Hotkeys.registerPage([
            { key: 'N', desc: '新增零售產品' },
            { key: 'Esc', desc: '關閉彈窗' }
        ]);
    }
});

let debounceTimer;
function debounceLoadProducts() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(loadProducts, 280);
}

async function loadProducts() {
    const tbody = document.getElementById('products-list');
    tbody.innerHTML = `<tr><td colspan="7" class="py-8 text-center text-[#8A8A8C]">載入中...</td></tr>`;

    const search = document.getElementById('search').value;
    const category = document.getElementById('category-filter').value;
    const status = document.getElementById('status-filter').value;
    const lowStockOnly = document.getElementById('low-stock-only').checked;

    try {
        const res = await SalonEase.fetch(`/api/products.php?action=list&search=${encodeURIComponent(search)}&category=${encodeURIComponent(category)}&status=${status}`);
        let data = res.data;

        if (lowStockOnly) {
            data = data.filter(p => p.stock_qty <= (p.effective_low_stock_threshold || 5));
        }

        renderProductsTable(data);
    } catch (err) {
        tbody.innerHTML = `<tr><td colspan="7" class="py-6 text-center text-red-500">${err.message}</td></tr>`;
    }
}

function renderProductsTable(list) {
    const tbody = document.getElementById('products-list');
    if (!list || list.length === 0) {
        tbody.innerHTML = `<tr><td colspan="7" class="py-8 text-center text-[#8A8A8C]">沒有符合的產品</td></tr>`;
        return;
    }

    let html = '';
    list.forEach(p => {
        const statusBadge = p.is_active == 1 
            ? `<span class="px-2.5 py-0.5 text-xs rounded-full bg-green-100 text-green-700">已啟用</span>`
            : `<span class="px-2.5 py-0.5 text-xs rounded-full bg-gray-200 text-gray-600">已停用</span>`;

        const threshold = p.effective_low_stock_threshold || 5;
        const isLowStock = p.stock_qty <= threshold;
        const stockColor = isLowStock ? 'text-red-600 font-medium' : '';
        const stockBadge = isLowStock 
            ? `<span onclick="event.stopImmediatePropagation(); editProduct(${p.id});" class="ml-1 text-xs px-1.5 py-0.5 bg-red-100 text-red-600 rounded cursor-pointer hover:bg-red-200" title="點擊快速編輯低庫存門檻">低庫存</span>` 
            : '';

        html += `
            <tr>
                <td class="font-medium">${e(p.name)}</td>
                <td class="text-sm text-[#5A5A5C]">${e(p.sku || '-')}</td>
                <td class="font-medium">${parseFloat(p.price).toFixed(2)}</td>
                <td class="${stockColor}">${p.stock_qty}${stockBadge}</td>
                <td><span class="text-xs px-2 py-0.5 bg-gray-100 rounded">${e(p.category || '-')}</span></td>
                <td>${statusBadge}</td>
                <td class="text-right">
                    <button onclick="editProduct(${p.id})" class="text-[#8FA68F] hover:underline text-sm mr-3">編輯</button>
                    <button onclick="toggleProduct(${p.id}, ${p.is_active})" class="text-sm ${p.is_active == 1 ? 'text-red-500' : 'text-green-600'} hover:underline">
                        ${p.is_active == 1 ? '停用' : '啟用'}
                    </button>
                </td>
            </tr>
        `;
    });
    tbody.innerHTML = html;
}

function showAddModal() {
    document.getElementById('modal-title').textContent = '新增產品';
    document.getElementById('product-id').value = '';
    document.getElementById('product-name').value = '';
    document.getElementById('product-sku').value = '';
    document.getElementById('product-price').value = '';
    document.getElementById('product-cost').value = '0';
    document.getElementById('product-stock').value = '0';
    document.getElementById('product-low-stock').value = '';
    document.getElementById('product-category').value = '';

    document.getElementById('save-btn').textContent = '新增產品';

    document.getElementById('product-modal').classList.remove('hidden');
    document.getElementById('product-modal').classList.add('flex');
    setTimeout(() => document.getElementById('product-name').focus(), 100);
}

async function editProduct(id) {
    try {
        const res = await SalonEase.fetch(`/api/products.php?action=list`);
        const p = res.data.find(x => x.id == id);
        if (!p) return;

        document.getElementById('modal-title').textContent = '編輯產品';
        document.getElementById('product-id').value = p.id;
        document.getElementById('product-name').value = p.name;
        document.getElementById('product-sku').value = p.sku || '';
        document.getElementById('product-price').value = p.price;
        document.getElementById('product-cost').value = p.cost || 0;
        document.getElementById('product-stock').value = p.stock_qty;
        document.getElementById('product-low-stock').value = p.low_stock_threshold || '';
        document.getElementById('product-category').value = p.category || '';

        document.getElementById('save-btn').textContent = '儲存變更';

        document.getElementById('product-modal').classList.remove('hidden');
        document.getElementById('product-modal').classList.add('flex');
    } catch (err) {
        SalonEase.toast(err.message, 'error');
    }
}

function hideProductModal() {
    const modal = document.getElementById('product-modal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}

async function saveProduct() {
    const id = document.getElementById('product-id').value;
    const isEdit = !!id;

    const payload = {
        name: document.getElementById('product-name').value.trim(),
        sku: document.getElementById('product-sku').value.trim(),
        price: document.getElementById('product-price').value,
        cost: document.getElementById('product-cost').value,
        stock_qty: document.getElementById('product-stock').value,
        low_stock_threshold: document.getElementById('product-low-stock').value,
        category: document.getElementById('product-category').value
    };

    try {
        const action = isEdit ? 'update' : 'create';
        if (isEdit) payload.id = id;

        await SalonEase.fetch(`/api/products.php?action=${action}`, {
            method: 'POST',
            body: payload
        });

        hideProductModal();
        SalonEase.toast(isEdit ? '產品已更新' : '產品新增成功');
        loadProducts();
    } catch (err) {
        SalonEase.toast(err.message, 'error');
    }
}

async function toggleProduct(id, currentStatus) {
    const newStatus = currentStatus == 1 ? 0 : 1;
    const actionText = newStatus === 1 ? '啟用' : '停用';

    if (!confirm(`確定要${actionText}此產品嗎？`)) return;

    try {
        await SalonEase.fetch('/api/products.php?action=toggle', {
            method: 'POST',
            body: { id, status: newStatus }
        });
        SalonEase.toast(`產品已${actionText}`);
        loadProducts();
    } catch (err) {
        SalonEase.toast(err.message, 'error');
    }
}

document.addEventListener('keydown', function(e) {
    if (e.key.toLowerCase() === 'n' && !['INPUT','TEXTAREA'].includes(document.activeElement.tagName)) {
        e.preventDefault();
        showAddModal();
    }
});

function e(str) {
    return str ? str.replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m])) : '';
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
