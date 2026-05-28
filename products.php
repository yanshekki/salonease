<?php
/**
 * SalonEase - 零售產品管理
 */
require_once __DIR__ . '/includes/auth.php';
require_login();

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/csrf.php';

$canAdjustStock = in_array($_SESSION['staff_role'] ?? '', ['admin', 'manager']);

$pageTitle = '零售產品管理';
$pageSubtitle = '管理美容院零售產品及庫存';
$extraJs = 'hotkeys.js';
?>
<?php include __DIR__ . '/includes/header.php'; ?>

<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between mb-4">
    <div class="mb-3 mb-md-0">
        <h1 class="h4 fw-semibold mb-1"><?= e($pageTitle) ?></h1>
        <p class="text-muted small mb-0"><?= e($pageSubtitle) ?></p>
    </div>
    <button onclick="showAddModal()" class="btn btn-primary d-flex align-items-center gap-2">
        <span>+ 新增產品</span>
        <span class="small opacity-75">[N]</span>
    </button>
</div>

<div class="card mb-3">
    <div class="card-body">
        <div class="row g-3 align-items-end">
            <div class="col-12 col-md-3">
                <label class="form-label small text-muted mb-1">搜尋（名稱 / SKU）</label>
                <input type="text" id="search" class="form-control" oninput="debounceLoadProducts()">
            </div>
            <div class="col-12 col-sm-6 col-md-2">
                <label class="form-label small text-muted mb-1">類別</label>
                <select id="category-filter" class="form-select" onchange="loadProducts()">
                    <option value="">全部</option>
                    <option value="護膚品">護膚品</option>
                    <option value="身體護理">身體護理</option>
                    <option value="其他">其他</option>
                </select>
            </div>
            <div class="col-12 col-sm-6 col-md-2">
                <label class="form-label small text-muted mb-1">狀態</label>
                <select id="status-filter" class="form-select" onchange="loadProducts()">
                    <option value="">全部</option>
                    <option value="1">已啟用</option>
                    <option value="0">已停用</option>
                </select>
            </div>
            <div class="col-12 col-md-auto d-flex align-items-end">
                <div class="form-check me-3">
                    <input class="form-check-input" type="checkbox" id="low-stock-only" onchange="loadProducts()">
                    <label class="form-check-label small text-muted" for="low-stock-only" title="只顯示庫存低於門檻的產品">
                        只顯示低庫存
                    </label>
                </div>
                <button onclick="loadProducts()" class="btn btn-outline-secondary">重新載入</button>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>產品名稱</th>
                    <th>SKU</th>
                    <th>售價</th>
                    <th>庫存</th>
                    <th>類別</th>
                    <th>狀態</th>
                    <th class="text-end" style="width: 120px;">操作</th>
                </tr>
            </thead>
            <tbody id="products-list">
                <tr><td colspan="7" class="py-5 text-center text-muted">載入中...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal (Bootstrap) -->
<div class="modal fade" id="productModal" tabindex="-1" aria-labelledby="productModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modal-title">新增產品</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <input type="hidden" id="product-id">

                <div class="mb-3">
                    <label class="form-label">產品名稱 <span class="text-danger">*</span></label>
                    <input type="text" id="product-name" class="form-control">
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">SKU</label>
                        <input type="text" id="product-sku" class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">類別</label>
                        <select id="product-category" class="form-select">
                            <option value="">未分類</option>
                            <option value="護膚品">護膚品</option>
                            <option value="身體護理">身體護理</option>
                            <option value="其他">其他</option>
                        </select>
                    </div>
                </div>

                <div class="row g-3 mt-3">
                    <div class="col-md-6">
                        <label class="form-label">售價（HK$） <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" id="product-price" class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">成本價</label>
                        <input type="number" step="0.01" id="product-cost" class="form-control" value="0">
                    </div>
                </div>

                <div class="row g-3 mt-3">
                    <div class="col-md-6">
                        <label class="form-label">初始庫存數量</label>
                        <input type="number" id="product-stock" class="form-control" value="0">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" title="留空則使用設定頁的全域預設門檻">低庫存門檻（留空則用全域預設）</label>
                        <input type="number" id="product-low-stock" class="form-control" value="" placeholder="例如 5">
                    </div>
                </div>

                <!-- A21：最近庫存異動（僅編輯時顯示） -->
                <div id="stock-history-section" class="mt-4 d-none">
                    <div class="fw-semibold small mb-2 text-muted">最近庫存異動（最多 8 筆）</div>
                    <div id="stock-history-list" class="small border rounded p-2 bg-light" style="max-height: 140px; overflow-y: auto; font-size: 0.8rem;">
                        <!-- 由 JS 動態填入 -->
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">取消</button>
                <button type="button" onclick="saveProduct()" class="btn btn-primary" id="save-btn">新增產品</button>
            </div>
        </div>
    </div>
</div>

<!-- 庫存調整 Modal（A19） -->
<div class="modal fade" id="stockAdjustModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">調整庫存 <span id="stock-product-name" class="text-muted small"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="stock-product-id">
                <div class="mb-3">
                    <label class="form-label small">目前庫存</label>
                    <div id="stock-current" class="fs-5 fw-semibold"></div>
                </div>
                <div class="mb-3">
                    <label class="form-label small">調整數量 <span class="text-muted">（正數入庫，負數出庫/損耗）</span></label>
                    <input type="number" id="stock-adjustment" class="form-control" placeholder="例如 +10 或 -3" step="1">
                    <!-- A23：快速入庫按鈕 -->
                    <div class="mt-2 d-flex flex-wrap gap-1">
                        <button type="button" onclick="setQuickAdjustment(5)" class="btn btn-sm btn-outline-success">+5 入庫</button>
                        <button type="button" onclick="setQuickAdjustment(10)" class="btn btn-sm btn-outline-success">+10 入庫</button>
                        <button type="button" onclick="setQuickAdjustment(20)" class="btn btn-sm btn-outline-success">+20 入庫</button>
                        <button type="button" onclick="setQuickAdjustment(-5)" class="btn btn-sm btn-outline-danger">-5 出庫</button>
                    </div>
                </div>
                <div>
                    <label class="form-label small">原因 / 備註 <span class="text-danger">*</span></label>
                    <textarea id="stock-reason" class="form-control" rows="2" placeholder="例如：供應商補貨 / 盤點調整 / 損壞報廢"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">取消</button>
                <button type="button" onclick="submitStockAdjustment()" class="btn btn-dark">確認調整</button>
            </div>
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
    tbody.innerHTML = `<tr><td colspan="7" class="py-8 text-center text-muted">載入中...</td></tr>`;

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
        tbody.innerHTML = `<tr><td colspan="7" class="py-8 text-center text-muted">沒有符合的產品</td></tr>`;
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
            ? `<span onclick="event.stopImmediatePropagation(); editProduct(${p.id});" class="badge bg-danger-subtle text-danger ms-1 small" style="cursor:pointer;" title="點擊快速編輯低庫存門檻">低庫存</span>` 
            : '';

        html += `
            <tr>
                <td class="font-medium">${e(p.name)}</td>
                <td class="text-sm text-[#5A5A5C]">${e(p.sku || '-')}</td>
                <td class="font-medium">${parseFloat(p.price).toFixed(2)}</td>
                <td class="${stockColor}">
                    <?php if ($canAdjustStock): ?>
                    <span onclick="event.stopImmediatePropagation(); adjustProductStock(${p.id}, ${p.stock_qty}, '${e(p.name).replace(/'/g, "\\'")}')" 
                          style="cursor: pointer; text-decoration: underline; text-decoration-style: dotted;" 
                          title="點擊快速調整庫存">
                        ${p.stock_qty}
                    </span>
                    <?php else: ?>
                    ${p.stock_qty}
                    <?php endif; ?>
                    ${stockBadge}
                </td>
                <td><span class="text-xs px-2 py-0.5 bg-gray-100 rounded">${e(p.category || '-')}</span></td>
                <td>${statusBadge}</td>
                <td class="text-right">
                    <button onclick="editProduct(${p.id})" class="btn btn-link btn-sm text-success p-0 me-2">編輯</button>
                    <?php if ($canAdjustStock): ?>
                    <button onclick="adjustProductStock(${p.id}, ${p.stock_qty}, '${e(p.name).replace(/'/g, "\\'")}')" class="btn btn-link btn-sm text-primary p-0 me-2">調整庫存</button>
                    <?php endif; ?>
                    <button onclick="toggleProduct(${p.id}, ${p.is_active})" class="btn btn-link btn-sm ${p.is_active == 1 ? 'text-danger' : 'text-success'} p-0">
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

    // A21：新增模式隱藏異動歷史
    document.getElementById('stock-history-section').classList.add('d-none');
    document.getElementById('stock-history-list').innerHTML = '';

    const modalEl = document.getElementById('productModal');
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    modal.show();

    setTimeout(() => document.getElementById('product-name').focus(), 400);
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

        // A21：載入最近庫存異動
        document.getElementById('stock-history-section').classList.remove('d-none');
        loadStockHistory(id);

        const modalEl = document.getElementById('productModal');
        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        modal.show();
    } catch (err) {
        SalonEase.toast(err.message, 'error');
    }
}

function hideProductModal() {
    const modalEl = document.getElementById('productModal');
    const modal = bootstrap.Modal.getInstance(modalEl);
    if (modal) modal.hide();
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
            body: {
                ...payload,
                csrf_token: '<?= csrf_token() ?>'
            }
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
            body: { 
                id, 
                status: newStatus,
                csrf_token: '<?= csrf_token() ?>'
            }
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

/* Phase 2 A19：產品庫存調整 UI */
let stockAdjustModalInstance = null;

function adjustProductStock(id, currentQty, name) {
    document.getElementById('stock-product-id').value = id;
    document.getElementById('stock-product-name').textContent = `— ${name}`;
    document.getElementById('stock-current').textContent = currentQty;
    document.getElementById('stock-adjustment').value = '';
    document.getElementById('stock-reason').value = '';

    const modalEl = document.getElementById('stockAdjustModal');
    stockAdjustModalInstance = bootstrap.Modal.getOrCreateInstance(modalEl);
    stockAdjustModalInstance.show();

    setTimeout(() => {
        document.getElementById('stock-adjustment').focus();
    }, 350);
}

async function submitStockAdjustment() {
    const id = parseInt(document.getElementById('stock-product-id').value);
    const adjustment = parseInt(document.getElementById('stock-adjustment').value || '0');
    const reason = document.getElementById('stock-reason').value.trim();

    if (!adjustment || adjustment === 0) {
        SalonEase.toast('調整數量不能為 0', 'error');
        return;
    }
    if (!reason) {
        SalonEase.toast('請填寫調整原因', 'error');
        return;
    }

    try {
        await SalonEase.fetch('/api/products.php?action=adjust_stock', {
            method: 'POST',
            body: {
                id: id,
                adjustment: adjustment,
                reason: reason,
                csrf_token: '<?= csrf_token() ?>'
            }
        });

        if (stockAdjustModalInstance) stockAdjustModalInstance.hide();
        SalonEase.toast('庫存已成功調整');
        loadProducts();
    } catch (err) {
        SalonEase.toast(err.message || '調整失敗', 'error');
    }
}

/* A23：快速設定調整數量 */
function setQuickAdjustment(qty) {
    const input = document.getElementById('stock-adjustment');
    if (input) {
        input.value = qty;
        input.focus();
    }
}

/* A21：載入產品最近庫存異動 */
async function loadStockHistory(productId) {
    const container = document.getElementById('stock-history-list');
    container.innerHTML = '<div class="text-muted">載入中...</div>';

    try {
        const res = await SalonEase.fetch(`/api/products.php?action=stock_history&id=${productId}`);
        const list = res.data || [];

        if (list.length === 0) {
            container.innerHTML = '<div class="text-muted">尚無庫存異動記錄</div>';
            return;
        }

        let html = '<table class="table table-sm mb-0" style="font-size:0.75rem;"><tbody>';
        list.forEach(item => {
            const adj = item.adjustment != null ? (item.adjustment > 0 ? '+' + item.adjustment : item.adjustment) : '';
            const change = (item.old != null && item.new != null) ? `${item.old} → ${item.new}` : (adj || '');
            html += `
                <tr>
                    <td class="text-nowrap">${item.time.substring(0,16).replace('T',' ')}</td>
                    <td>${item.staff}</td>
                    <td><span class="badge bg-secondary-subtle text-dark">${item.type}</span></td>
                    <td>${change}</td>
                    <td class="text-muted">${item.reason || ''}</td>
                </tr>`;
        });
        html += '</tbody></table>';
        container.innerHTML = html;
    } catch (e) {
        container.innerHTML = '<div class="text-danger small">無法載入異動記錄</div>';
    }
}

function e(str) {
    return str ? str.replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m])) : '';
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
