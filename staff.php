<?php
/**
 * SalonEase - 員工管理
 */
require_once __DIR__ . '/includes/auth.php';
require_role('admin'); // 只有管理員可以管理員工

require_once __DIR__ . '/includes/functions.php';

$pageTitle = '員工管理';
$pageSubtitle = '新增、編輯員工帳號及佣金設定';
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
        <span>+ 新增員工</span>
        <span class="text-xs opacity-75">[N]</span>
    </button>
</div>

<!-- 搜尋與過濾 -->
<div class="bg-white rounded-2xl border border-gray-100 p-4 mb-4 flex flex-wrap gap-3 items-end">
    <div class="flex-1 min-w-[240px]">
        <label class="block text-xs text-[#5A5A5C] mb-1">搜尋</label>
        <input type="text" id="search" placeholder="姓名 / 電郵 / 電話"
               class="salon-input" oninput="debounceLoadStaff()">
    </div>
    <div>
        <label class="block text-xs text-[#5A5A5C] mb-1">角色</label>
        <select id="role-filter" class="salon-input" onchange="loadStaff()">
            <option value="">全部角色</option>
            <option value="admin">管理員</option>
            <option value="manager">經理</option>
            <option value="therapist">美容師</option>
            <option value="reception">前台</option>
        </select>
    </div>
    <div>
        <label class="block text-xs text-[#5A5A5C] mb-1">狀態</label>
        <select id="status-filter" class="salon-input" onchange="loadStaff()">
            <option value="">全部</option>
            <option value="1">已啟用</option>
            <option value="0">已停用</option>
        </select>
    </div>
    <button onclick="loadStaff()" 
            class="salon-btn salon-btn-secondary h-[42px]">重新載入</button>
</div>

<!-- 員工列表 -->
<div class="bg-white rounded-2xl border border-gray-100 overflow-hidden">
    <table class="salon-table w-full">
        <thead>
            <tr>
                <th>姓名</th>
                <th>電郵</th>
                <th>電話</th>
                <th>角色</th>
                <th>狀態</th>
                <th class="text-right">操作</th>
            </tr>
        </thead>
        <tbody id="staff-list">
            <tr><td colspan="6" class="py-8 text-center text-[#8A8A8C]">載入中...</td></tr>
        </tbody>
    </table>
</div>

<!-- 新增 / 編輯 Modal -->
<div id="staff-modal" class="hidden fixed inset-0 bg-black/40 z-[70] flex items-center justify-center" onclick="hideStaffModal()">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-md mx-4" onclick="event.stopImmediatePropagation()">
        <div class="px-5 py-4 border-b flex items-center justify-between">
            <div class="font-semibold text-lg" id="modal-title">新增員工</div>
            <button onclick="hideStaffModal()" class="text-2xl leading-none text-gray-400 hover:text-gray-600">×</button>
        </div>

        <div class="p-5 space-y-4">
            <input type="hidden" id="staff-id">

            <div>
                <label class="block text-sm font-medium mb-1">姓名 <span class="text-red-500">*</span></label>
                <input type="text" id="staff-name" class="salon-input" placeholder="陳美玲">
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-1">電郵 <span class="text-red-500">*</span></label>
                    <input type="email" id="staff-email" class="salon-input">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">電話</label>
                    <input type="text" id="staff-phone" class="salon-input">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium mb-1">角色</label>
                <select id="staff-role" class="salon-input">
                    <option value="therapist">美容師</option>
                    <option value="manager">經理</option>
                    <option value="reception">前台</option>
                    <option value="admin">管理員</option>
                </select>
            </div>

            <div id="password-section">
                <label class="block text-sm font-medium mb-1">初始密碼 <span class="text-red-500">*</span></label>
                <input type="password" id="staff-password" class="salon-input" placeholder="至少 6 個字元">
                <p class="text-xs text-[#8A8A8C] mt-1">新增時必須設定密碼，之後可由員工自行修改或管理員重設。</p>
            </div>

            <div id="reset-password-section" class="hidden">
                <label class="block text-sm font-medium mb-1">新密碼</label>
                <div class="flex gap-2">
                    <input type="password" id="reset-password" class="salon-input flex-1" placeholder="輸入新密碼">
                    <button onclick="resetPassword()" class="salon-btn salon-btn-secondary">重設密碼</button>
                </div>
            </div>
        </div>

        <div class="px-5 py-4 bg-gray-50 flex justify-end gap-3 rounded-b-2xl">
            <button onclick="hideStaffModal()" class="salon-btn salon-btn-secondary">取消</button>
            <button onclick="saveStaff()" class="salon-btn salon-btn-primary" id="save-btn">儲存</button>
        </div>
    </div>
</div>

<script>
// 員工管理頁面專屬熱鍵
document.addEventListener('DOMContentLoaded', () => {
    loadStaff();

    // 註冊本頁熱鍵
    if (window.SalonEase && window.SalonEase.Hotkeys) {
        window.SalonEase.Hotkeys.registerPage([
            { key: 'N', desc: '新增員工' },
            { key: 'Esc', desc: '關閉彈窗' },
            { key: '/', desc: '聚焦搜尋框' }
        ]);
    }

    // 聚焦搜尋
    document.addEventListener('keydown', function(e) {
        if (e.key === '/' && document.activeElement.tagName === 'BODY') {
            e.preventDefault();
            document.getElementById('search').focus();
        }
    });
});

// 防抖載入
let debounceTimer;
function debounceLoadStaff() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(loadStaff, 280);
}

// 載入員工列表
async function loadStaff() {
    const tbody = document.getElementById('staff-list');
    tbody.innerHTML = `<tr><td colspan="6" class="py-8 text-center text-[#8A8A8C]">載入中...</td></tr>`;

    const search = document.getElementById('search').value;
    const role = document.getElementById('role-filter').value;
    const status = document.getElementById('status-filter').value;

    try {
        const res = await SalonEase.fetch(`/api/staff.php?action=list&search=${encodeURIComponent(search)}&role=${role}&status=${status}`);
        renderStaffTable(res.data);
    } catch (err) {
        tbody.innerHTML = `<tr><td colspan="6" class="py-6 text-center text-red-500">${err.message}</td></tr>`;
    }
}

function renderStaffTable(list) {
    const tbody = document.getElementById('staff-list');
    if (!list || list.length === 0) {
        tbody.innerHTML = `<tr><td colspan="6" class="py-8 text-center text-[#8A8A8C]">沒有符合的員工</td></tr>`;
        return;
    }

    let html = '';
    list.forEach(s => {
        const statusBadge = s.is_active == 1 
            ? `<span class="px-2.5 py-0.5 text-xs rounded-full bg-green-100 text-green-700">已啟用</span>`
            : `<span class="px-2.5 py-0.5 text-xs rounded-full bg-gray-200 text-gray-600">已停用</span>`;

        const roleName = {
            'admin': '管理員',
            'manager': '經理',
            'therapist': '美容師',
            'reception': '前台'
        }[s.role] || s.role;

        html += `
            <tr>
                <td class="font-medium">${e(s.name)}</td>
                <td class="text-sm text-[#5A5A5C]">${e(s.email)}</td>
                <td class="text-sm">${e(s.phone || '-')}</td>
                <td><span class="text-xs px-2 py-0.5 bg-gray-100 rounded">${roleName}</span></td>
                <td>${statusBadge}</td>
                <td class="text-right">
                    <button onclick="editStaff(${s.id})" class="text-[#8FA68F] hover:underline text-sm mr-3">編輯</button>
                    <button onclick="toggleStatus(${s.id}, ${s.is_active})" class="text-sm ${s.is_active == 1 ? 'text-red-500' : 'text-green-600'} hover:underline mr-3">
                        ${s.is_active == 1 ? '停用' : '啟用'}
                    </button>
                </td>
            </tr>
        `;
    });
    tbody.innerHTML = html;
}

// 顯示新增 Modal
function showAddModal() {
    document.getElementById('modal-title').textContent = '新增員工';
    document.getElementById('staff-id').value = '';
    document.getElementById('staff-name').value = '';
    document.getElementById('staff-email').value = '';
    document.getElementById('staff-phone').value = '';
    document.getElementById('staff-role').value = 'therapist';
    document.getElementById('staff-password').value = '';

    document.getElementById('password-section').classList.remove('hidden');
    document.getElementById('reset-password-section').classList.add('hidden');
    document.getElementById('save-btn').textContent = '新增員工';

    document.getElementById('staff-modal').classList.remove('hidden');
    document.getElementById('staff-modal').classList.add('flex');
    setTimeout(() => document.getElementById('staff-name').focus(), 100);
}

// 編輯員工
async function editStaff(id) {
    try {
        const res = await SalonEase.fetch(`/api/staff.php?action=get&id=${id}`);
        const s = res.data;

        document.getElementById('modal-title').textContent = '編輯員工';
        document.getElementById('staff-id').value = s.id;
        document.getElementById('staff-name').value = s.name || '';
        document.getElementById('staff-email').value = s.email || '';
        document.getElementById('staff-phone').value = s.phone || '';
        document.getElementById('staff-role').value = s.role || 'therapist';

        document.getElementById('password-section').classList.add('hidden');
        document.getElementById('reset-password-section').classList.remove('hidden');
        document.getElementById('save-btn').textContent = '儲存變更';

        document.getElementById('staff-modal').classList.remove('hidden');
        document.getElementById('staff-modal').classList.add('flex');
    } catch (err) {
        SalonEase.toast(err.message, 'error');
    }
}

function hideStaffModal() {
    const modal = document.getElementById('staff-modal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}

// 儲存（新增或更新）
async function saveStaff() {
    const id = document.getElementById('staff-id').value;
    const isEdit = !!id;

    const payload = {
        name: document.getElementById('staff-name').value.trim(),
        email: document.getElementById('staff-email').value.trim(),
        phone: document.getElementById('staff-phone').value.trim(),
        role: document.getElementById('staff-role').value,
    };

    if (!isEdit) {
        payload.password = document.getElementById('staff-password').value;
    }

    try {
        const action = isEdit ? 'update' : 'create';
        if (isEdit) payload.id = id;

        await SalonEase.fetch(`/api/staff.php?action=${action}`, {
            method: 'POST',
            body: payload
        });

        hideStaffModal();
        SalonEase.toast(isEdit ? '員工資料已更新' : '員工新增成功');
        loadStaff();
    } catch (err) {
        SalonEase.toast(err.message, 'error');
    }
}

// 啟用 / 停用
async function toggleStatus(id, currentStatus) {
    const newStatus = currentStatus == 1 ? 0 : 1;
    const actionText = newStatus === 1 ? '啟用' : '停用';

    if (!confirm(`確定要${actionText}此員工嗎？`)) return;

    try {
        await SalonEase.fetch('/api/staff.php?action=toggle', {
            method: 'POST',
            body: { id, status: newStatus }
        });
        SalonEase.toast(`員工已${actionText}`);
        loadStaff();
    } catch (err) {
        SalonEase.toast(err.message, 'error');
    }
}

// 重設密碼
async function resetPassword() {
    const id = document.getElementById('staff-id').value;
    const pw = document.getElementById('reset-password').value.trim();

    if (!pw || pw.length < 6) {
        alert('密碼至少需要 6 個字元');
        return;
    }

    try {
        await SalonEase.fetch('/api/staff.php?action=reset_pw', {
            method: 'POST',
            body: { id, password: pw }
        });
        document.getElementById('reset-password').value = '';
        SalonEase.toast('密碼已重設成功');
    } catch (err) {
        SalonEase.toast(err.message, 'error');
    }
}

// 全域熱鍵支援
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
