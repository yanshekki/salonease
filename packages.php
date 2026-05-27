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

<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-semibold"><?= e($pageTitle) ?></h1>
        <p class="text-[#5A5A5C] text-sm mt-1"><?= e($pageSubtitle) ?></p>
    </div>
    <button onclick="showAddModal()"
            class="salon-btn salon-btn-primary flex items-center gap-x-2">
        <span>+ 新增套票</span>
        <span class="text-xs opacity-75">[N]</span>
    </button>
</div>

<div class="bg-white rounded-2xl border border-gray-100 overflow-hidden">
    <table class="salon-table w-full">
        <thead>
            <tr>
                <th>套票名稱</th>
                <th>總次數</th>
                <th>售價</th>
                <th>有效期（天）</th>
                <th>狀態</th>
                <th class="text-right">操作</th>
            </tr>
        </thead>
        <tbody id="packages-list">
            <tr><td colspan="6" class="py-8 text-center text-[#8A8A8C]">載入中...</td></tr>
        </tbody>
    </table>
</div>

<!-- Modal -->
<div id="package-modal" class="hidden fixed inset-0 bg-black/40 z-[70] flex items-center justify-center" onclick="hidePackageModal()">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-md mx-4" onclick="event.stopImmediatePropagation()">
        <div class="px-5 py-4 border-b flex items-center justify-between">
            <div class="font-semibold text-lg" id="modal-title">新增套票</div>
            <button onclick="hidePackageModal()" class="text-2xl leading-none text-gray-400 hover:text-gray-600">×</button>
        </div>

        <div class="p-5 space-y-4">
            <input type="hidden" id="package-id">

            <div>
                <label class="block text-sm font-medium mb-1">套票名稱 <span class="text-red-500">*</span></label>
                <input type="text" id="package-name" class="salon-input" placeholder="經典面部護理 10 次卡">
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-1">總次數 <span class="text-red-500">*</span></label>
                    <input type="number" id="package-sessions" class="salon-input" value="10">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">售價（HK$） <span class="text-red-500">*</span></label>
                    <input type="number" step="0.01" id="package-price" class="salon-input">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium mb-1">有效期（天）</label>
                <input type="number" id="package-validity" class="salon-input" value="365">
                <p class="text-xs text-[#8A8A8C] mt-1">通常建議 365 天（一年）</p>
            </div>
        </div>

        <div class="px-5 py-4 bg-gray-50 flex justify-end gap-3 rounded-b-2xl">
            <button onclick="hidePackageModal()" class="salon-btn salon-btn-secondary">取消</button>
            <button onclick="savePackage()" class="salon-btn salon-btn-primary" id="save-btn">新增套票</button>
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
    tbody.innerHTML = `<tr><td colspan="6" class="py-8 text-center text-[#8A8A8C]">載入中...</td></tr>`;

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
        tbody.innerHTML = `<tr><td colspan="6" class="py-8 text-center text-[#8A8A8C]">尚未新增任何套票</td></tr>`;
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
                    <button onclick="editPackage(${p.id})" class="text-[#8FA68F] hover:underline text-sm mr-3">編輯</button>
                    <button onclick="togglePackage(${p.id}, ${p.is_active})" class="text-sm ${p.is_active == 1 ? 'text-red-500' : 'text-green-600'} hover:underline">
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

    document.getElementById('package-modal').classList.remove('hidden');
    document.getElementById('package-modal').classList.add('flex');
    setTimeout(() => document.getElementById('package-name').focus(), 100);
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

        document.getElementById('package-modal').classList.remove('hidden');
        document.getElementById('package-modal').classList.add('flex');
    } catch (err) {
        SalonEase.toast(err.message, 'error');
    }
}

function hidePackageModal() {
    const modal = document.getElementById('package-modal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
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
