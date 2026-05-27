<?php
/**
 * SalonEase - 房間管理
 */
require_once __DIR__ . '/includes/auth.php';
require_login();

require_once __DIR__ . '/includes/functions.php';

$pageTitle = '房間管理';
$pageSubtitle = '管理美容院房間容量，用於預約衝突檢查';
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
        <span>+ 新增房間</span>
        <span class="text-xs opacity-75">[N]</span>
    </button>
</div>

<!-- 列表 -->
<div class="bg-white rounded-2xl border border-gray-100 overflow-hidden">
    <table class="salon-table w-full">
        <thead>
            <tr>
                <th>房間名稱</th>
                <th>容量</th>
                <th>狀態</th>
                <th class="text-right">操作</th>
            </tr>
        </thead>
        <tbody id="rooms-list">
            <tr><td colspan="4" class="py-8 text-center text-[#8A8A8C]">載入中...</td></tr>
        </tbody>
    </table>
</div>

<!-- 新增 / 編輯 Modal -->
<div id="room-modal" class="hidden fixed inset-0 bg-black/40 z-[70] flex items-center justify-center" onclick="hideRoomModal()">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-md mx-4" onclick="event.stopImmediatePropagation()">
        <div class="px-5 py-4 border-b flex items-center justify-between">
            <div class="font-semibold text-lg" id="modal-title">新增房間</div>
            <button onclick="hideRoomModal()" class="text-2xl leading-none text-gray-400 hover:text-gray-600">×</button>
        </div>

        <div class="p-5 space-y-4">
            <input type="hidden" id="room-id">

            <div>
                <label class="block text-sm font-medium mb-1">房間名稱 <span class="text-red-500">*</span></label>
                <input type="text" id="room-name" class="salon-input" placeholder="1 號房 / VIP 房">
            </div>

            <div>
                <label class="block text-sm font-medium mb-1">容量（人數）</label>
                <input type="number" id="room-capacity" class="salon-input" value="1" min="1" max="10">
                <p class="text-xs text-[#8A8A8C] mt-1">通常美容院單人房間填 1，雙人房填 2</p>
            </div>
        </div>

        <div class="px-5 py-4 bg-gray-50 flex justify-end gap-3 rounded-b-2xl">
            <button onclick="hideRoomModal()" class="salon-btn salon-btn-secondary">取消</button>
            <button onclick="saveRoom()" class="salon-btn salon-btn-primary" id="save-btn">新增房間</button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    loadRooms();

    if (window.SalonEase && window.SalonEase.Hotkeys) {
        window.SalonEase.Hotkeys.registerPage([
            { key: 'N', desc: '新增房間' },
            { key: 'Esc', desc: '關閉彈窗' }
        ]);
    }
});

async function loadRooms() {
    const tbody = document.getElementById('rooms-list');
    tbody.innerHTML = `<tr><td colspan="4" class="py-8 text-center text-[#8A8A8C]">載入中...</td></tr>`;

    try {
        const res = await SalonEase.fetch('/api/rooms.php?action=list');
        renderRoomsTable(res.data);
    } catch (err) {
        tbody.innerHTML = `<tr><td colspan="4" class="py-6 text-center text-red-500">${err.message}</td></tr>`;
    }
}

function renderRoomsTable(list) {
    const tbody = document.getElementById('rooms-list');
    if (!list || list.length === 0) {
        tbody.innerHTML = `<tr><td colspan="4" class="py-8 text-center text-[#8A8A8C]">尚未新增任何房間</td></tr>`;
        return;
    }

    let html = '';
    list.forEach(r => {
        const statusBadge = r.is_active == 1 
            ? `<span class="px-2.5 py-0.5 text-xs rounded-full bg-green-100 text-green-700">啟用中</span>`
            : `<span class="px-2.5 py-0.5 text-xs rounded-full bg-gray-200 text-gray-600">已停用</span>`;

        html += `
            <tr>
                <td class="font-medium">${e(r.name)}</td>
                <td>${r.capacity} 人</td>
                <td>${statusBadge}</td>
                <td class="text-right">
                    <button onclick="editRoom(${r.id})" class="text-[#8FA68F] hover:underline text-sm mr-3">編輯</button>
                    <button onclick="toggleRoom(${r.id}, ${r.is_active})" class="text-sm ${r.is_active == 1 ? 'text-red-500' : 'text-green-600'} hover:underline">
                        ${r.is_active == 1 ? '停用' : '啟用'}
                    </button>
                </td>
            </tr>
        `;
    });
    tbody.innerHTML = html;
}

function showAddModal() {
    document.getElementById('modal-title').textContent = '新增房間';
    document.getElementById('room-id').value = '';
    document.getElementById('room-name').value = '';
    document.getElementById('room-capacity').value = '1';

    document.getElementById('save-btn').textContent = '新增房間';

    document.getElementById('room-modal').classList.remove('hidden');
    document.getElementById('room-modal').classList.add('flex');
    setTimeout(() => document.getElementById('room-name').focus(), 100);
}

async function editRoom(id) {
    try {
        const res = await SalonEase.fetch(`/api/rooms.php?action=list`);
        const room = res.data.find(r => r.id == id);
        if (!room) return;

        document.getElementById('modal-title').textContent = '編輯房間';
        document.getElementById('room-id').value = room.id;
        document.getElementById('room-name').value = room.name;
        document.getElementById('room-capacity').value = room.capacity;

        document.getElementById('save-btn').textContent = '儲存變更';

        document.getElementById('room-modal').classList.remove('hidden');
        document.getElementById('room-modal').classList.add('flex');
    } catch (err) {
        SalonEase.toast(err.message, 'error');
    }
}

function hideRoomModal() {
    const modal = document.getElementById('room-modal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}

async function saveRoom() {
    const id = document.getElementById('room-id').value;
    const isEdit = !!id;

    const payload = {
        name: document.getElementById('room-name').value.trim(),
        capacity: document.getElementById('room-capacity').value
    };

    try {
        const action = isEdit ? 'update' : 'create';
        if (isEdit) payload.id = id;

        await SalonEase.fetch(`/api/rooms.php?action=${action}`, {
            method: 'POST',
            body: payload
        });

        hideRoomModal();
        SalonEase.toast(isEdit ? '房間已更新' : '房間新增成功');
        loadRooms();
    } catch (err) {
        SalonEase.toast(err.message, 'error');
    }
}

async function toggleRoom(id, currentStatus) {
    const newStatus = currentStatus == 1 ? 0 : 1;
    const actionText = newStatus === 1 ? '啟用' : '停用';

    if (!confirm(`確定要${actionText}此房間嗎？`)) return;

    try {
        await SalonEase.fetch('/api/rooms.php?action=toggle', {
            method: 'POST',
            body: { id, status: newStatus }
        });
        SalonEase.toast(`房間已${actionText}`);
        loadRooms();
    } catch (err) {
        SalonEase.toast(err.message, 'error');
    }
}

// 熱鍵
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
