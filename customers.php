<?php
/**
 * SalonEase - 客戶管理
 */
require_once __DIR__ . '/includes/auth.php';
require_login();

require_once __DIR__ . '/includes/functions.php';

$pageTitle = '客戶管理';
$pageSubtitle = '管理客戶資料、查看消費記錄';
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
        <span>+ 新增客戶</span>
        <span class="text-xs opacity-75">[N]</span>
    </button>
</div>

<div class="bg-white rounded-2xl border border-gray-100 p-4 mb-4">
    <div class="flex gap-3 items-end">
        <div class="flex-1">
            <label class="block text-xs text-[#5A5A5C] mb-1">搜尋客戶（姓名 / 電話 / 電郵）</label>
            <input type="text" id="search" class="salon-input" placeholder="輸入電話最快..." oninput="debounceLoadCustomers()">
        </div>
        <button onclick="loadCustomers()" class="salon-btn salon-btn-secondary h-[42px]">搜尋</button>
    </div>
</div>

<div class="bg-white rounded-2xl border border-gray-100 overflow-hidden">
    <table class="salon-table w-full">
        <thead>
            <tr>
                <th>姓名</th>
                <th>電話</th>
                <th>電郵</th>
                <th>最近到訪</th>
                <th class="text-right">累計消費</th>
                <th class="text-center">到訪次數</th>
                <th class="text-right">操作</th>
            </tr>
        </thead>
        <tbody id="customers-list">
            <tr><td colspan="7" class="py-8 text-center text-[#8A8A8C]">載入中...</td></tr>
        </tbody>
    </table>
</div>

<!-- 新增 / 編輯 Modal -->
<div id="customer-modal" class="hidden fixed inset-0 bg-black/40 z-[70] flex items-center justify-center" onclick="hideCustomerModal()">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg mx-4" onclick="event.stopImmediatePropagation()">
        <div class="px-5 py-4 border-b flex items-center justify-between">
            <div class="font-semibold text-lg" id="modal-title">新增客戶</div>
            <button onclick="hideCustomerModal()" class="text-2xl leading-none text-gray-400 hover:text-gray-600">×</button>
        </div>

        <div class="p-5 space-y-4">
            <input type="hidden" id="customer-id">

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-1">姓名 <span class="text-red-500">*</span></label>
                    <input type="text" id="customer-name" class="salon-input">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">電話 <span class="text-red-500">*</span></label>
                    <input type="text" id="customer-phone" class="salon-input" placeholder="91234567">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium mb-1">電郵</label>
                <input type="email" id="customer-email" class="salon-input">
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-1">性別</label>
                    <select id="customer-gender" class="salon-input">
                        <option value="">未填</option>
                        <option value="F">女</option>
                        <option value="M">男</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">生日</label>
                    <input type="date" id="customer-birthday" class="salon-input">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium mb-1">備註</label>
                <textarea id="customer-notes" rows="2" class="salon-input" placeholder="過敏、偏好等..."></textarea>
            </div>
        </div>

        <div class="px-5 py-4 bg-gray-50 flex justify-end gap-3 rounded-b-2xl">
            <button onclick="hideCustomerModal()" class="salon-btn salon-btn-secondary">取消</button>
            <button onclick="saveCustomer()" class="salon-btn salon-btn-primary" id="save-btn">新增客戶</button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    loadCustomers();

    if (window.SalonEase && window.SalonEase.Hotkeys) {
        window.SalonEase.Hotkeys.registerPage([
            { key: 'N', desc: '新增客戶' },
            { key: '/', desc: '聚焦搜尋框' },
            { key: 'Esc', desc: '關閉彈窗' }
        ]);
    }

    // 按 / 聚焦搜尋
    document.addEventListener('keydown', function(e) {
        if (e.key === '/' && document.activeElement.tagName === 'BODY') {
            e.preventDefault();
            document.getElementById('search').focus();
        }
    });
});

let debounceTimer;
function debounceLoadCustomers() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(loadCustomers, 300);
}

async function loadCustomers() {
    const tbody = document.getElementById('customers-list');
    tbody.innerHTML = `<tr><td colspan="7" class="py-8 text-center text-[#8A8A8C]">載入中...</td></tr>`;

    const search = document.getElementById('search').value;

    try {
        const res = await SalonEase.fetch(`/api/customers.php?action=list&search=${encodeURIComponent(search)}`);
        renderCustomersTable(res.data);
    } catch (err) {
        tbody.innerHTML = `<tr><td colspan="7" class="py-6 text-center text-red-500">${err.message}</td></tr>`;
    }
}

function renderCustomersTable(list) {
    const tbody = document.getElementById('customers-list');
    if (!list || list.length === 0) {
        tbody.innerHTML = `<tr><td colspan="7" class="py-8 text-center text-[#8A8A8C]">沒有符合的客戶</td></tr>`;
        return;
    }

    let html = '';
    list.forEach(c => {
        const lastVisit = c.last_visit_at ? formatDate(c.last_visit_at) : '-';
        const spent = parseFloat(c.total_spent || 0).toFixed(0);

        html += `
            <tr>
                <td class="font-medium">${e(c.name)}</td>
                <td class="font-mono text-sm">${e(c.phone)}</td>
                <td class="text-sm text-[#5A5A5C]">${e(c.email || '-')}</td>
                <td class="text-sm">${lastVisit}</td>
                <td class="text-right font-medium">HK$ ${spent}</td>
                <td class="text-center">${c.visit_count || 0}</td>
                <td class="text-right">
                    <button onclick="editCustomer(${c.id})" class="text-[#8FA68F] hover:underline text-sm mr-3">編輯</button>
                </td>
            </tr>
        `;
    });
    tbody.innerHTML = html;
}

function formatDate(dt) {
    const d = new Date(dt);
    return d.toLocaleDateString('zh-HK', { month: '2-digit', day: '2-digit' });
}

function showAddModal() {
    document.getElementById('modal-title').textContent = '新增客戶';
    document.getElementById('customer-id').value = '';
    document.getElementById('customer-name').value = '';
    document.getElementById('customer-phone').value = '';
    document.getElementById('customer-email').value = '';
    document.getElementById('customer-gender').value = '';
    document.getElementById('customer-birthday').value = '';
    document.getElementById('customer-notes').value = '';

    document.getElementById('save-btn').textContent = '新增客戶';

    document.getElementById('customer-modal').classList.remove('hidden');
    document.getElementById('customer-modal').classList.add('flex');
    setTimeout(() => document.getElementById('customer-name').focus(), 100);
}

async function editCustomer(id) {
    try {
        const res = await SalonEase.fetch(`/api/customers.php?action=get&id=${id}`);
        const c = res.data;

        document.getElementById('modal-title').textContent = '編輯客戶';
        document.getElementById('customer-id').value = c.id;
        document.getElementById('customer-name').value = c.name || '';
        document.getElementById('customer-phone').value = c.phone || '';
        document.getElementById('customer-email').value = c.email || '';
        document.getElementById('customer-gender').value = c.gender || '';
        document.getElementById('customer-birthday').value = c.birthday || '';
        document.getElementById('customer-notes').value = c.notes || '';

        document.getElementById('save-btn').textContent = '儲存變更';

        document.getElementById('customer-modal').classList.remove('hidden');
        document.getElementById('customer-modal').classList.add('flex');
    } catch (err) {
        SalonEase.toast(err.message, 'error');
    }
}

function hideCustomerModal() {
    const modal = document.getElementById('customer-modal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}

async function saveCustomer() {
    const id = document.getElementById('customer-id').value;
    const isEdit = !!id;

    const payload = {
        name: document.getElementById('customer-name').value.trim(),
        phone: document.getElementById('customer-phone').value.trim(),
        email: document.getElementById('customer-email').value.trim(),
        gender: document.getElementById('customer-gender').value,
        birthday: document.getElementById('customer-birthday').value,
        notes: document.getElementById('customer-notes').value.trim()
    };

    try {
        const action = isEdit ? 'update' : 'create';
        if (isEdit) payload.id = id;

        await SalonEase.fetch(`/api/customers.php?action=${action}`, {
            method: 'POST',
            body: payload
        });

        hideCustomerModal();
        SalonEase.toast(isEdit ? '客戶資料已更新' : '客戶新增成功');
        loadCustomers();
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
