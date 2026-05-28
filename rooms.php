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

<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between mb-4">
    <div class="mb-3 mb-md-0">
        <h1 class="h4 fw-semibold mb-1"><?= e($pageTitle) ?></h1>
        <p class="text-muted small mb-0"><?= e($pageSubtitle) ?></p>
    </div>
    <button onclick="showAddModal()" class="btn btn-primary d-flex align-items-center gap-2">
        <span>+ 新增房間</span>
        <span class="small opacity-75">[N]</span>
    </button>
</div>

<!-- 列表 -->
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>房間名稱</th>
                    <th>容量</th>
                    <th>狀態</th>
                    <th class="text-end" style="width: 90px;">操作</th>
                </tr>
            </thead>
            <tbody id="rooms-list">
                <tr><td colspan="4" class="py-5 text-center text-muted">載入中...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- 新增 / 編輯 Modal (Bootstrap) -->
<div class="modal fade" id="roomModal" tabindex="-1" aria-labelledby="roomModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modal-title">新增房間</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <input type="hidden" id="room-id">

                <div class="mb-3">
                    <label class="form-label">房間名稱 <span class="text-danger">*</span></label>
                    <input type="text" id="room-name" class="form-control" placeholder="1 號房 / VIP 房">
                </div>

                <div>
                    <label class="form-label">容量（人數）</label>
                    <input type="number" id="room-capacity" class="form-control" value="1" min="1" max="10">
                    <div class="form-text">通常美容院單人房間填 1，雙人房填 2</div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">取消</button>
                <button type="button" onclick="saveRoom()" class="btn btn-primary" id="save-btn">新增房間</button>
            </div>
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
    tbody.innerHTML = `<tr><td colspan="4" class="py-8 text-center text-muted">載入中...</td></tr>`;

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
        tbody.innerHTML = `<tr><td colspan="4" class="py-8 text-center text-muted">尚未新增任何房間</td></tr>`;
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
                    <button onclick="editRoom(${r.id})" class="btn btn-link btn-sm text-success p-0 me-2">編輯</button>
                    <button onclick="toggleRoom(${r.id}, ${r.is_active})" class="btn btn-link btn-sm ${r.is_active == 1 ? 'text-danger' : 'text-success'} p-0">
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

    const modalEl = document.getElementById('roomModal');
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    modal.show();

    setTimeout(() => document.getElementById('room-name').focus(), 400);
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

        const modalEl = document.getElementById('roomModal');
        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        modal.show();
    } catch (err) {
        SalonEase.toast(err.message, 'error');
    }
}

function hideRoomModal() {
    const modalEl = document.getElementById('roomModal');
    const modal = bootstrap.Modal.getInstance(modalEl);
    if (modal) modal.hide();
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
