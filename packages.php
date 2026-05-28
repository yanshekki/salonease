<?php
/**
 * SalonEase - 套票管理
 */
require_once __DIR__ . '/includes/auth.php';
require_login();

require_once __DIR__ . '/includes/functions.php';

$pageTitle = '套票管理';
$pageSubtitle = '管理療程卡（套票）定義';
$extraJs = 'hotkeys.js';
?>
<?php include __DIR__ . '/includes/header.php'; ?>

<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between mb-4">
    <div class="mb-3 mb-md-0">
        <h1 class="h4 fw-semibold mb-1"><?= e($pageTitle) ?></h1>
        <p class="text-muted small mb-0"><?= e($pageSubtitle) ?></p>
    </div>
    <button onclick="showAddModal()" class="btn btn-primary d-flex align-items-center gap-2">
        <span>+ 新增套票</span>
        <span class="small opacity-75">[N]</span>
    </button>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>套票名稱</th>
                    <th>總次數</th>
                    <th>售價</th>
                    <th>有效期（天）</th>
                    <th>狀態</th>
                    <th class="text-end" style="width: 120px;">操作</th>
                </tr>
            </thead>
            <tbody id="packages-list">
                <tr><td colspan="6" class="py-5 text-center text-muted">載入中...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal (Bootstrap) -->
<div class="modal fade" id="packageModal" tabindex="-1" aria-labelledby="packageModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modal-title">新增套票</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <input type="hidden" id="package-id">

                <div class="mb-3">
                    <label class="form-label">套票名稱 <span class="text-danger">*</span></label>
                    <input type="text" id="package-name" class="form-control" placeholder="經典面部護理 10 次卡">
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">總次數 <span class="text-danger">*</span></label>
                        <input type="number" id="package-sessions" class="form-control" value="10">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">售價（HK$） <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" id="package-price" class="form-control">
                    </div>
                </div>

                <div class="mt-3">
                    <label class="form-label">有效期（天）</label>
                    <input type="number" id="package-validity" class="form-control" value="365">
                    <div class="form-text">通常建議 365 天（一年）</div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">取消</button>
                <button type="button" onclick="savePackage()" class="btn btn-primary" id="save-btn">新增套票</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    loadPackages();

    if (window.SalonEase && window.SalonEase.Hotkeys) {
        window.SalonEase.Hotkeys.registerPage([
            { key: 'N', desc: '新增套票' },
            { key: 'Esc', desc: '關閉彈窗' }
        ]);
    }
});

async function loadPackages() {
    const tbody = document.getElementById('packages-list');
    tbody.innerHTML = `<tr><td colspan="6" class="py-8 text-center text-muted">載入中...</td></tr>`;

    try {
        const res = await SalonEase.fetch('/api/packages.php?action=list');
        renderPackagesTable(res.data);
    } catch (err) {
        tbody.innerHTML = `<tr><td colspan="6" class="py-6 text-center text-red-500">${err.message}</td></tr>`;
    }
}

function renderPackagesTable(list) {
    const tbody = document.getElementById('packages-list');
    if (!list || list.length === 0) {
        tbody.innerHTML = `<tr><td colspan="6" class="py-8 text-center text-muted">尚未新增任何套票</td></tr>`;
        return;
    }

    let html = '';
    list.forEach(p => {
        const statusBadge = p.is_active == 1 
            ? `<span class="px-2.5 py-0.5 text-xs rounded-full bg-green-100 text-green-700">已啟用</span>`
            : `<span class="px-2.5 py-0.5 text-xs rounded-full bg-gray-200 text-gray-600">已停用</span>`;

        html += `
            <tr>
                <td class="font-medium">${e(p.name)}</td>
                <td class="text-center">${p.total_sessions} 次</td>
                <td class="font-medium">${parseFloat(p.price).toFixed(2)}</td>
                <td class="text-center">${p.validity_days} 天</td>
                <td>${statusBadge}</td>
                <td class="text-right">
                    <button onclick="editPackage(${p.id})" class="btn btn-link btn-sm text-success p-0 me-2">編輯</button>
                    <button onclick="togglePackage(${p.id}, ${p.is_active})" class="btn btn-link btn-sm ${p.is_active == 1 ? 'text-danger' : 'text-success'} p-0">
                        ${p.is_active == 1 ? '停用' : '啟用'}
                    </button>
                </td>
            </tr>
        `;
    });
    tbody.innerHTML = html;
}

function showAddModal() {
    document.getElementById('modal-title').textContent = '新增套票';
    document.getElementById('package-id').value = '';
    document.getElementById('package-name').value = '';
    document.getElementById('package-sessions').value = '10';
    document.getElementById('package-price').value = '';
    document.getElementById('package-validity').value = '365';

    document.getElementById('save-btn').textContent = '新增套票';

    const modalEl = document.getElementById('packageModal');
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    modal.show();

    setTimeout(() => document.getElementById('package-name').focus(), 400);
}

async function editPackage(id) {
    try {
        const res = await SalonEase.fetch('/api/packages.php?action=list');
        const p = res.data.find(x => x.id == id);
        if (!p) return;

        document.getElementById('modal-title').textContent = '編輯套票';
        document.getElementById('package-id').value = p.id;
        document.getElementById('package-name').value = p.name;
        document.getElementById('package-sessions').value = p.total_sessions;
        document.getElementById('package-price').value = p.price;
        document.getElementById('package-validity').value = p.validity_days;

        document.getElementById('save-btn').textContent = '儲存變更';

        const modalEl = document.getElementById('packageModal');
        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        modal.show();
    } catch (err) {
        SalonEase.toast(err.message, 'error');
    }
}

function hidePackageModal() {
    const modalEl = document.getElementById('packageModal');
    const modal = bootstrap.Modal.getInstance(modalEl);
    if (modal) modal.hide();
}

async function savePackage() {
    const id = document.getElementById('package-id').value;
    const isEdit = !!id;

    const payload = {
        name: document.getElementById('package-name').value.trim(),
        total_sessions: document.getElementById('package-sessions').value,
        price: document.getElementById('package-price').value,
        validity_days: document.getElementById('package-validity').value
    };

    try {
        const action = isEdit ? 'update' : 'create';
        if (isEdit) payload.id = id;

        await SalonEase.fetch(`/api/packages.php?action=${action}`, {
            method: 'POST',
            body: payload
        });

        hidePackageModal();
        SalonEase.toast(isEdit ? '套票已更新' : '套票新增成功');
        loadPackages();
    } catch (err) {
        SalonEase.toast(err.message, 'error');
    }
}

async function togglePackage(id, currentStatus) {
    const newStatus = currentStatus == 1 ? 0 : 1;
    const actionText = newStatus === 1 ? '啟用' : '停用';

    if (!confirm(`確定要${actionText}此套票嗎？`)) return;

    try {
        await SalonEase.fetch('/api/packages.php?action=toggle', {
            method: 'POST',
            body: { id, status: newStatus }
        });
        SalonEase.toast(`套票已${actionText}`);
        loadPackages();
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
