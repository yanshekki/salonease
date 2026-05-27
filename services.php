<?php
/**
 * SalonEase - 服務項目管理
 */
require_once __DIR__ . '/includes/auth.php';
require_login();

require_once __DIR__ . '/includes/functions.php';

$pageTitle = '服務項目管理';
$pageSubtitle = '管理美容院提供的療程服務';
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
        <span>+ 新增服務</span>
        <span class="text-xs opacity-75">[N]</span>
    </button>
</div>

<!-- 搜尋與過濾 -->
<div class="bg-white rounded-2xl border border-gray-100 p-4 mb-4 flex flex-wrap gap-3 items-end">
    <div class="flex-1 min-w-[240px]">
        <label class="block text-xs text-[#5A5A5C] mb-1">搜尋服務名稱</label>
        <input type="text" id="search" class="salon-input" oninput="debounceLoadServices()">
    </div>
    <div>
        <label class="block text-xs text-[#5A5A5C] mb-1">類別</label>
        <select id="category-filter" class="salon-input" onchange="loadServices()">
            <option value="">全部類別</option>
            <option value="面部護理">面部護理</option>
            <option value="身體護理">身體護理</option>
            <option value="醫美">醫美</option>
            <option value="其他">其他</option>
        </select>
    </div>
    <div>
        <label class="block text-xs text-[#5A5A5C] mb-1">狀態</label>
        <select id="status-filter" class="salon-input" onchange="loadServices()">
            <option value="">全部</option>
            <option value="1">已啟用</option>
            <option value="0">已停用</option>
        </select>
    </div>
    <button onclick="loadServices()" class="salon-btn salon-btn-secondary h-[42px]">重新載入</button>
</div>

<div class="bg-white rounded-2xl border border-gray-100 overflow-hidden">
    <table class="salon-table w-full">
        <thead>
            <tr>
                <th>服務名稱</th>
                <th>時長</th>
                <th>價格</th>
                <th>類別</th>
                <th>狀態</th>
                <th class="text-right">操作</th>
            </tr>
        </thead>
        <tbody id="services-list">
            <tr><td colspan="6" class="py-8 text-center text-[#8A8A8C]">載入中...</td></tr>
        </tbody>
    </table>
</div>

<!-- Modal -->
<div id="service-modal" class="hidden fixed inset-0 bg-black/40 z-[70] flex items-center justify-center" onclick="hideServiceModal()">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-md mx-4" onclick="event.stopImmediatePropagation()">
        <div class="px-5 py-4 border-b flex items-center justify-between">
            <div class="font-semibold text-lg" id="modal-title">新增服務</div>
            <button onclick="hideServiceModal()" class="text-2xl leading-none text-gray-400 hover:text-gray-600">×</button>
        </div>

        <div class="p-5 space-y-4">
            <input type="hidden" id="service-id">

            <div>
                <label class="block text-sm font-medium mb-1">服務名稱 <span class="text-red-500">*</span></label>
                <input type="text" id="service-name" class="salon-input" placeholder="經典面部護理 60 分鐘">
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-1">時長（分鐘）</label>
                    <input type="number" id="service-duration" class="salon-input" value="60">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">價格（HK$） <span class="text-red-500">*</span></label>
                    <input type="number" step="0.01" id="service-price" class="salon-input" placeholder="680">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium mb-1">類別</label>
                <select id="service-category" class="salon-input">
                    <option value="">未分類</option>
                    <option value="面部護理">面部護理</option>
                    <option value="身體護理">身體護理</option>
                    <option value="醫美">醫美</option>
                    <option value="其他">其他</option>
                </select>
            </div>
        </div>

        <div class="px-5 py-4 bg-gray-50 flex justify-end gap-3 rounded-b-2xl">
            <button onclick="hideServiceModal()" class="salon-btn salon-btn-secondary">取消</button>
            <button onclick="saveService()" class="salon-btn salon-btn-primary" id="save-btn">新增服務</button>
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
    tbody.innerHTML = `<tr><td colspan="6" class="py-8 text-center text-[#8A8A8C]">載入中...</td></tr>`;

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
        tbody.innerHTML = `<tr><td colspan="6" class="py-8 text-center text-[#8A8A8C]">沒有符合的服務項目</td></tr>`;
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
                    <button onclick="editService(${s.id})" class="text-[#8FA68F] hover:underline text-sm mr-3">編輯</button>
                    <button onclick="toggleService(${s.id}, ${s.is_active})" class="text-sm ${s.is_active == 1 ? 'text-red-500' : 'text-green-600'} hover:underline">
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

    document.getElementById('service-modal').classList.remove('hidden');
    document.getElementById('service-modal').classList.add('flex');
    setTimeout(() => document.getElementById('service-name').focus(), 100);
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

        document.getElementById('service-modal').classList.remove('hidden');
        document.getElementById('service-modal').classList.add('flex');
    } catch (err) {
        SalonEase.toast(err.message, 'error');
    }
}

function hideServiceModal() {
    const modal = document.getElementById('service-modal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
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
            body: payload
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
            body: { id, status: newStatus }
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
