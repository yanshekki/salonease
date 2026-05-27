<?php
/**
 * SalonEase - 預約管理
 */
require_once __DIR__ . '/includes/auth.php';
require_login();

require_once __DIR__ . '/includes/functions.php';

$pageTitle = '預約管理';
$pageSubtitle = '新增預約、查看時程、避免時間衝突';
$extraJs = 'hotkeys.js';
?>
<?php include __DIR__ . '/includes/header.php'; ?>

<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-semibold"><?= e($pageTitle) ?></h1>
        <p class="text-[#5A5A5C] text-sm mt-1"><?= e($pageSubtitle) ?></p>
    </div>
    <button onclick="showCreateModal()"
            class="salon-btn salon-btn-primary flex items-center gap-x-2">
        <span>+ 新增預約</span>
        <span class="text-xs opacity-75">[N]</span>
    </button>
</div>

<!-- 篩選 -->
<div class="bg-white rounded-2xl border border-gray-100 p-4 mb-4 flex flex-wrap gap-3 items-end">
    <div>
        <label class="block text-xs text-[#5A5A5C] mb-1">開始日期</label>
        <input type="date" id="date-from" class="salon-input" value="<?= date('Y-m-d') ?>">
    </div>
    <div>
        <label class="block text-xs text-[#5A5A5C] mb-1">結束日期</label>
        <input type="date" id="date-to" class="salon-input" value="<?= date('Y-m-d', strtotime('+7 days')) ?>">
    </div>
    <div>
        <label class="block text-xs text-[#5A5A5C] mb-1">美容師</label>
        <select id="staff-filter" class="salon-input">
            <option value="">全部</option>
        </select>
    </div>
    <div>
        <label class="block text-xs text-[#5A5A5C] mb-1">狀態</label>
        <select id="status-filter" class="salon-input">
            <option value="">全部</option>
            <option value="pending">待確認</option>
            <option value="confirmed">已確認</option>
            <option value="completed">已完成</option>
            <option value="cancelled">已取消</option>
            <option value="no_show">未到</option>
        </select>
    </div>
    <button onclick="loadAppointments()" class="salon-btn salon-btn-secondary h-[42px]">查詢</button>
</div>

<!-- 列表 -->
<div class="bg-white rounded-2xl border border-gray-100 overflow-hidden">
    <table class="salon-table w-full">
        <thead>
            <tr>
                <th>時間</th>
                <th>客戶</th>
                <th>美容師</th>
                <th>房間</th>
                <th>狀態</th>
                <th class="text-right">操作</th>
            </tr>
        </thead>
        <tbody id="appointments-list">
            <tr><td colspan="6" class="py-8 text-center text-[#8A8A8C]">載入中...</td></tr>
        </tbody>
    </table>
</div>

<!-- 新增/編輯 Modal -->
<div id="appt-modal" class="hidden fixed inset-0 bg-black/40 z-[70] flex items-center justify-center" onclick="hideApptModal()">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-2xl mx-4" onclick="event.stopImmediatePropagation()">
        <div class="px-5 py-4 border-b flex items-center justify-between">
            <div class="font-semibold text-lg" id="modal-title">新增預約</div>
            <button onclick="hideApptModal()" class="text-2xl leading-none text-gray-400 hover:text-gray-600">×</button>
        </div>

        <div class="p-5 space-y-4">
            <input type="hidden" id="appt-id">

            <!-- 客戶搜尋 -->
            <div>
                <label class="block text-sm font-medium mb-1">客戶 <span class="text-red-500">*</span></label>
                <div class="flex gap-2">
                    <input type="text" id="customer-search" class="salon-input flex-1" placeholder="輸入姓名或電話搜尋" oninput="searchCustomers()">
                    <input type="hidden" id="customer-id">
                </div>
                <div id="customer-results" class="mt-1 text-sm max-h-32 overflow-auto border rounded-xl hidden"></div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-1">美容師 <span class="text-red-500">*</span></label>
                    <select id="staff-select" class="salon-input"></select>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">房間</label>
                    <select id="room-select" class="salon-input">
                        <option value="">不指定</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">開始時間 <span class="text-red-500">*</span></label>
                    <input type="datetime-local" id="start-time" class="salon-input" onchange="estimateEndTime()">
                </div>
            </div>

            <!-- 服務選擇 -->
            <div>
                <label class="block text-sm font-medium mb-1">服務項目（可多選）</label>
                <div id="services-checkboxes" class="grid grid-cols-2 md:grid-cols-3 gap-2 max-h-48 overflow-auto border rounded-xl p-3 text-sm"></div>
            </div>

            <div>
                <label class="block text-sm font-medium mb-1">預估結束時間</label>
                <input type="text" id="end-time-display" class="salon-input bg-gray-50" readonly>
                <input type="hidden" id="end-time">
            </div>

            <div>
                <label class="block text-sm font-medium mb-1">備註</label>
                <textarea id="notes" rows="2" class="salon-input"></textarea>
            </div>
        </div>

        <div class="px-5 py-4 bg-gray-50 flex justify-end gap-3 rounded-b-2xl">
            <button onclick="hideApptModal()" class="salon-btn salon-btn-secondary">取消</button>
            <button onclick="saveAppointment()" class="salon-btn salon-btn-primary" id="save-btn">建立預約</button>
        </div>
    </div>
</div>

<script>
// 頁面熱鍵
document.addEventListener('DOMContentLoaded', () => {
    loadStaffOptions();
    loadRoomOptions();
    loadServicesForCheckbox();
    loadAppointments();

    if (window.SalonEase && window.SalonEase.Hotkeys) {
        window.SalonEase.Hotkeys.registerPage([
            { key: 'N', desc: '新增預約' },
            { key: 'Esc', desc: '關閉彈窗' }
        ]);
    }
});

// 載入美容師選單
async function loadStaffOptions() {
    try {
        const res = await SalonEase.fetch('/api/staff.php?action=list&status=1');
        const sel = document.getElementById('staff-select');
        const filter = document.getElementById('staff-filter');

        let html = '<option value="">請選擇</option>';
        res.data.forEach(s => {
            html += `<option value="${s.id}">${e(s.name)}</option>`;
        });
        sel.innerHTML = html;

        // 同時填 filter
        let fhtml = '<option value="">全部</option>';
        res.data.forEach(s => {
            fhtml += `<option value="${s.id}">${e(s.name)}</option>`;
        });
        filter.innerHTML = fhtml;
    } catch (e) {}
}

// 載入房間
async function loadRoomOptions() {
    try {
        const res = await SalonEase.fetch('/api/rooms.php?action=list&status=1');
        const sel = document.getElementById('room-select');
        let html = '<option value="">不指定</option>';
        res.data.forEach(r => {
            html += `<option value="${r.id}">${e(r.name)}（${r.capacity}人）</option>`;
        });
        sel.innerHTML = html;
    } catch (e) {}
}

// 載入服務多選
async function loadServicesForCheckbox() {
    try {
        const res = await SalonEase.fetch('/api/services.php?action=list&status=1');
        const container = document.getElementById('services-checkboxes');
        let html = '';
        res.data.forEach(s => {
            html += `
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" value="${s.id}" data-duration="${s.duration_min}" data-price="${s.price}">
                    <span>${e(s.name)}（${s.duration_min}分 / $${parseFloat(s.price).toFixed(0)}）</span>
                </label>
            `;
        });
        container.innerHTML = html;
    } catch (e) {}
}

function estimateEndTime() {
    const startInput = document.getElementById('start-time').value;
    if (!startInput) return;

    const checkboxes = document.querySelectorAll('#services-checkboxes input:checked');
    let totalMin = 0;
    checkboxes.forEach(cb => totalMin += parseInt(cb.dataset.duration) || 0);

    if (totalMin === 0) totalMin = 60;

    const startDate = new Date(startInput);
    startDate.setMinutes(startDate.getMinutes() + totalMin);

    const endStr = startDate.toISOString().slice(0,16);
    document.getElementById('end-time').value = endStr.replace('T', ' ') + ':00';
    document.getElementById('end-time-display').value = endStr.replace('T', ' ');
}

// 搜尋客戶
let customerSearchTimer;
function searchCustomers() {
    clearTimeout(customerSearchTimer);
    const keyword = document.getElementById('customer-search').value.trim();
    if (keyword.length < 1) {
        document.getElementById('customer-results').classList.add('hidden');
        return;
    }

    customerSearchTimer = setTimeout(async () => {
        try {
            const res = await SalonEase.fetch(`/api/customers.php?action=list&search=${encodeURIComponent(keyword)}`);
            const container = document.getElementById('customer-results');
            let html = '';
            if (res.data.length === 0) {
                html = '<div class="p-2 text-[#8A8A8C]">找不到客戶</div>';
            } else {
                res.data.slice(0,8).forEach(c => {
                    html += `<div class="p-2 hover:bg-gray-100 cursor-pointer" onclick="selectCustomer(${c.id}, '${e(c.name)}', '${e(c.phone)}')">
                        ${e(c.name)} - ${e(c.phone)}
                    </div>`;
                });
            }
            container.innerHTML = html;
            container.classList.remove('hidden');
        } catch (e) {}
    }, 250);
}

function selectCustomer(id, name, phone) {
    document.getElementById('customer-id').value = id;
    document.getElementById('customer-search').value = `${name} (${phone})`;
    document.getElementById('customer-results').classList.add('hidden');
}

async function loadAppointments() {
    const tbody = document.getElementById('appointments-list');
    tbody.innerHTML = `<tr><td colspan="6" class="py-8 text-center text-[#8A8A8C]">載入中...</td></tr>`;

    const from = document.getElementById('date-from').value;
    const to = document.getElementById('date-to').value;
    const staff = document.getElementById('staff-filter').value;
    const status = document.getElementById('status-filter').value;

    try {
        const res = await SalonEase.fetch(`/api/appointments.php?action=list&date_from=${from}&date_to=${to}&staff_id=${staff}&status=${status}`);
        renderAppointments(res.data);
    } catch (err) {
        tbody.innerHTML = `<tr><td colspan="6" class="py-6 text-center text-red-500">${err.message}</td></tr>`;
    }
}

function renderAppointments(list) {
    const tbody = document.getElementById('appointments-list');
    if (!list || list.length === 0) {
        tbody.innerHTML = `<tr><td colspan="6" class="py-8 text-center text-[#8A8A8C]">此區間沒有預約</td></tr>`;
        return;
    }

    const statusMap = {
        'pending': '待確認',
        'confirmed': '已確認',
        'completed': '已完成',
        'cancelled': '已取消',
        'no_show': '未到'
    };

    let html = '';
    list.forEach(a => {
        const time = new Date(a.start_time).toLocaleString('zh-HK', {month:'2-digit', day:'2-digit', hour:'2-digit', minute:'2-digit'});
        html += `
            <tr>
                <td>${time}</td>
                <td>${e(a.customer_name || '-')} <span class="text-xs text-[#8A8A8C]">(${e(a.customer_phone || '')})</span></td>
                <td>${e(a.staff_name || '-')}</td>
                <td>${e(a.room_name || '不指定')}</td>
                <td><span class="text-xs px-2 py-0.5 bg-gray-100 rounded">${statusMap[a.status] || a.status}</span></td>
                <td class="text-right">
                    <button onclick="changeStatus(${a.id}, 'confirmed')" class="text-[#8FA68F] hover:underline text-xs mr-1">確認</button>
                    <button onclick="changeStatus(${a.id}, 'completed')" class="text-[#8FA68F] hover:underline text-xs mr-1">完成</button>
                    <button onclick="changeStatus(${a.id}, 'cancelled')" class="text-red-500 hover:underline text-xs">取消</button>
                </td>
            </tr>
        `;
    });
    tbody.innerHTML = html;
}

function showCreateModal() {
    document.getElementById('modal-title').textContent = '新增預約';
    document.getElementById('appt-id').value = '';
    document.getElementById('customer-id').value = '';
    document.getElementById('customer-search').value = '';
    document.getElementById('staff-select').value = '';
    document.getElementById('room-select').value = '';
    document.getElementById('start-time').value = '';
    document.getElementById('end-time').value = '';
    document.getElementById('end-time-display').value = '';
    document.getElementById('notes').value = '';

    // 清空已選服務
    document.querySelectorAll('#services-checkboxes input').forEach(cb => cb.checked = false);

    document.getElementById('save-btn').textContent = '建立預約';

    document.getElementById('appt-modal').classList.remove('hidden');
    document.getElementById('appt-modal').classList.add('flex');
}

function hideApptModal() {
    const modal = document.getElementById('appt-modal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}

async function saveAppointment() {
    const customerId = document.getElementById('customer-id').value;
    const staffId = document.getElementById('staff-select').value;
    const roomId = document.getElementById('room-select').value;
    const startTime = document.getElementById('start-time').value;
    const endTime = document.getElementById('end-time').value;
    const notes = document.getElementById('notes').value;

    if (!customerId || !staffId || !startTime || !endTime) {
        alert('請填寫客戶、美容師及時間');
        return;
    }

    // 收集已選服務
    const services = [];
    document.querySelectorAll('#services-checkboxes input:checked').forEach(cb => {
        services.push(cb.value);
    });

    const payload = {
        customer_id: customerId,
        staff_id: staffId,
        room_id: roomId || '',
        start_time: startTime.replace('T', ' ') + ':00',
        end_time: endTime.replace('T', ' ') + ':00',
        notes: notes,
        services: services
    };

    try {
        await SalonEase.fetch('/api/appointments.php?action=create', {
            method: 'POST',
            body: payload
        });

        hideApptModal();
        SalonEase.toast('預約建立成功');
        loadAppointments();
    } catch (err) {
        SalonEase.toast(err.message, 'error');
    }
}

async function changeStatus(id, status) {
    if (!confirm('確定要更改狀態嗎？')) return;

    try {
        await SalonEase.fetch('/api/appointments.php?action=change_status', {
            method: 'POST',
            body: { id, status }
        });
        SalonEase.toast('狀態已更新');
        loadAppointments();
    } catch (err) {
        SalonEase.toast(err.message, 'error');
    }
}

// 熱鍵
document.addEventListener('keydown', function(e) {
    if (e.key.toLowerCase() === 'n' && !['INPUT','TEXTAREA'].includes(document.activeElement.tagName)) {
        e.preventDefault();
        showCreateModal();
    }
});

function e(str) {
    return str ? str.replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m])) : '';
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
