<?php
/**
 * SalonEase - 服務項目管理
 */
require_once __DIR__ . '/includes/auth.php';
require_login();

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/csrf.php';

$pageTitle = '服務項目管理';
$pageSubtitle = '管理美容院提供的療程服務';
$extraJs = 'hotkeys.js';
?>
<?php include __DIR__ . '/includes/header.php'; ?>

<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between mb-4">
    <div class="mb-3 mb-md-0">
        <h1 class="h4 fw-semibold mb-1"><?= e($pageTitle) ?></h1>
        <p class="text-muted small mb-0"><?= e($pageSubtitle) ?></p>
    </div>
    <button onclick="showAddModal()" class="btn btn-primary d-flex align-items-center gap-2">
        <span>+ 新增服務</span>
        <span class="small opacity-75">[N]</span>
    </button>
</div>

<!-- 搜尋與過濾 -->
<div class="card mb-3">
    <div class="card-body">
        <div class="row g-3 align-items-end">
            <div class="col-12 col-md-4">
                <label class="form-label small text-muted mb-1">搜尋服務名稱</label>
                <input type="text" id="search" class="form-control" oninput="debounceLoadServices()">
            </div>
            <div class="col-12 col-sm-6 col-md-2">
                <label class="form-label small text-muted mb-1">類別</label>
                <select id="category-filter" class="form-select" onchange="loadServices()">
                    <option value="">全部類別</option>
                    <option value="面部護理">面部護理</option>
                    <option value="身體護理">身體護理</option>
                    <option value="醫美">醫美</option>
                    <option value="其他">其他</option>
                </select>
            </div>
            <div class="col-12 col-sm-6 col-md-2">
                <label class="form-label small text-muted mb-1">狀態</label>
                <select id="status-filter" class="form-select" onchange="loadServices()">
                    <option value="">全部</option>
                    <option value="1">已啟用</option>
                    <option value="0">已停用</option>
                </select>
            </div>
            <div class="col-12 col-md-auto">
                <button onclick="loadServices()" class="btn btn-outline-secondary w-100">重新載入</button>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>服務名稱</th>
                    <th>時長</th>
                    <th>價格</th>
                    <th>類別</th>
                    <th>狀態</th>
                    <th class="text-end" style="width: 120px;">操作</th>
                </tr>
            </thead>
            <tbody id="services-list">
                <tr><td colspan="6" class="py-5 text-center text-muted">載入中...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal (Bootstrap) -->
<div class="modal fade" id="serviceModal" tabindex="-1" aria-labelledby="serviceModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modal-title">新增服務</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <input type="hidden" id="service-id">

                <div class="mb-3">
                    <label class="form-label">服務名稱 <span class="text-danger">*</span></label>
                    <input type="text" id="service-name" class="form-control" placeholder="經典面部護理 60 分鐘">
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">時長（分鐘）</label>
                        <input type="number" id="service-duration" class="form-control" value="60">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">價格（HK$） <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" id="service-price" class="form-control" placeholder="680">
                    </div>
                </div>

                <div class="mt-3">
                    <label class="form-label">類別</label>
                    <select id="service-category" class="form-select">
                        <option value="">未分類</option>
                        <option value="面部護理">面部護理</option>
                        <option value="身體護理">身體護理</option>
                        <option value="醫美">醫美</option>
                        <option value="其他">其他</option>
                    </select>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">取消</button>
                <button type="button" onclick="saveService()" class="btn btn-primary" id="save-btn">新增服務</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    loadServices();

    if (window.SalonEase && window.SalonEase.Hotkeys) {
        window.SalonEase.Hotkeys.registerPage([
            { key: 'N', desc: '新增服務項目' },
            { key: 'Esc', desc: '關閉彈窗' }
        ]);
    }
});

let debounceTimer;
function debounceLoadServices() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(loadServices, 280);
}

async function loadServices() {
    const tbody = document.getElementById('services-list');
    tbody.innerHTML = `<tr><td colspan="6" class="py-8 text-center text-muted">載入中...</td></tr>`;

    const search = document.getElementById('search').value;
    const category = document.getElementById('category-filter').value;
    const status = document.getElementById('status-filter').value;

    try {
        const res = await SalonEase.fetch(`/api/services.php?action=list&search=${encodeURIComponent(search)}&category=${encodeURIComponent(category)}&status=${status}`);
        renderServicesTable(res.data);
    } catch (err) {
        tbody.innerHTML = `<tr><td colspan="6" class="py-6 text-center text-red-500">${err.message}</td></tr>`;
    }
}

function renderServicesTable(list) {
    const tbody = document.getElementById('services-list');
    if (!list || list.length === 0) {
        tbody.innerHTML = `<tr><td colspan="6" class="py-8 text-center text-muted">沒有符合的服務項目</td></tr>`;
        return;
    }

    let html = '';
    list.forEach(s => {
        const statusBadge = s.is_active == 1 
            ? `<span class="px-2.5 py-0.5 text-xs rounded-full bg-green-100 text-green-700">已啟用</span>`
            : `<span class="px-2.5 py-0.5 text-xs rounded-full bg-gray-200 text-gray-600">已停用</span>`;

        html += `
            <tr>
                <td class="font-medium">${e(s.name)}</td>
                <td>${s.duration_min} 分鐘</td>
                <td class="font-medium">${parseFloat(s.price).toFixed(2)}</td>
                <td><span class="text-xs px-2 py-0.5 bg-gray-100 rounded">${e(s.category || '-')}</span></td>
                <td>${statusBadge}</td>
                <td class="text-right">
                    <button onclick="editService(${s.id})" class="btn btn-link btn-sm text-success p-0 me-2">編輯</button>
                    <button onclick="toggleService(${s.id}, ${s.is_active})" class="btn btn-link btn-sm ${s.is_active == 1 ? 'text-danger' : 'text-success'} p-0">
                        ${s.is_active == 1 ? '停用' : '啟用'}
                    </button>
                </td>
            </tr>
        `;
    });
    tbody.innerHTML = html;
}

function showAddModal() {
    document.getElementById('modal-title').textContent = '新增服務';
    document.getElementById('service-id').value = '';
    document.getElementById('service-name').value = '';
    document.getElementById('service-duration').value = '60';
    document.getElementById('service-price').value = '';
    document.getElementById('service-category').value = '';

    document.getElementById('save-btn').textContent = '新增服務';

    const modalEl = document.getElementById('serviceModal');
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    modal.show();

    setTimeout(() => document.getElementById('service-name').focus(), 400);
}

async function editService(id) {
    try {
        const res = await SalonEase.fetch(`/api/services.php?action=list`);
        const s = res.data.find(x => x.id == id);
        if (!s) return;

        document.getElementById('modal-title').textContent = '編輯服務';
        document.getElementById('service-id').value = s.id;
        document.getElementById('service-name').value = s.name;
        document.getElementById('service-duration').value = s.duration_min;
        document.getElementById('service-price').value = s.price;
        document.getElementById('service-category').value = s.category || '';

        document.getElementById('save-btn').textContent = '儲存變更';

        const modalEl = document.getElementById('serviceModal');
        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        modal.show();
    } catch (err) {
        SalonEase.toast(err.message, 'error');
    }
}

function hideServiceModal() {
    const modalEl = document.getElementById('serviceModal');
    const modal = bootstrap.Modal.getInstance(modalEl);
    if (modal) modal.hide();
}

async function saveService() {
    const id = document.getElementById('service-id').value;
    const isEdit = !!id;

    const payload = {
        name: document.getElementById('service-name').value.trim(),
        duration_min: document.getElementById('service-duration').value,
        price: document.getElementById('service-price').value,
        category: document.getElementById('service-category').value
    };

    try {
        const action = isEdit ? 'update' : 'create';
        if (isEdit) payload.id = id;

        await SalonEase.fetch(`/api/services.php?action=${action}`, {
            method: 'POST',
            body: {
                ...payload,
                csrf_token: '<?= csrf_token() ?>'
            }
        });

        hideServiceModal();
        SalonEase.toast(isEdit ? '服務已更新' : '服務新增成功');
        loadServices();
    } catch (err) {
        SalonEase.toast(err.message, 'error');
    }
}

async function toggleService(id, currentStatus) {
    const newStatus = currentStatus == 1 ? 0 : 1;
    const actionText = newStatus === 1 ? '啟用' : '停用';

    if (!confirm(`確定要${actionText}此服務嗎？`)) return;

    try {
        await SalonEase.fetch('/api/services.php?action=toggle', {
            method: 'POST',
            body: { 
                id, 
                status: newStatus,
                csrf_token: '<?= csrf_token() ?>'
            }
        });
        SalonEase.toast(`服務已${actionText}`);
        loadServices();
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
