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

<!-- 檢視切換 -->
<div class="flex items-center gap-2 mb-4">
    <span class="text-sm text-[#5A5A5C] mr-2">檢視模式：</span>
    <button onclick="switchView('list')" id="btn-view-list"
            class="px-4 py-1.5 text-sm rounded-xl border bg-[#2C2C2E] text-white">列表檢視</button>
    <button onclick="switchView('calendar')" id="btn-view-calendar"
            class="px-4 py-1.5 text-sm rounded-xl border hover:bg-gray-100">今日時程</button>
    <button onclick="switchView('week')" id="btn-view-week"
            class="px-4 py-1.5 text-sm rounded-xl border hover:bg-gray-100">週檢視</button>
</div>

<!-- 列表檢視容器 -->
<div id="list-view">

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
                <th class="text-right">操作 <span class="font-normal text-[10px]">(點擊列查看詳情)</span></th>
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

<!-- 今日時程卡片式檢視 (時間軸) -->
<div id="calendar-view" class="hidden mt-2">
    <div class="bg-white rounded-2xl border border-gray-100 p-5">
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-3">
                <div class="flex items-center gap-1">
                    <button onclick="navigateTodayDay(-1)" class="px-2 py-0.5 text-sm rounded hover:bg-gray-100">←</button>
                    <span class="font-semibold">時程</span>
                    <span id="today-date" class="text-sm text-[#5A5A5C] ml-1"></span>
                    <button onclick="navigateTodayDay(1)" class="px-2 py-0.5 text-sm rounded hover:bg-gray-100">→</button>
                </div>
                <select id="today-staff-filter" class="text-sm border rounded-lg px-2 py-1" onchange="loadTodaySchedule()">
                    <option value="">全部美容師</option>
                </select>
            </div>
            <button onclick="loadTodaySchedule()" class="text-sm px-3 py-1 rounded-lg hover:bg-gray-100">重新整理</button>
        </div>

        <!-- 時間軸容器 -->
        <div id="today-timeline" class="border rounded-xl bg-white min-h-[420px]">
            <!-- JS 會渲染專業時間軸 -->
        </div>
    </div>
    <div class="text-xs text-[#8A8A8C] mt-3">空白時段可點擊直接新增預約 • 點擊預約區塊查看詳情</div>
</div>

<!-- 週檢視 -->
<div id="week-view" class="hidden">
    <div class="bg-white rounded-2xl border border-gray-100 p-4">
        <div class="flex items-center justify-between mb-3 px-1">
            <div class="font-semibold">未來七天預約概覽</div>
            <button onclick="loadWeekView()" class="text-sm px-3 py-1 rounded-lg hover:bg-gray-100">重新整理</button>
        </div>
        <div id="week-grid" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-7 gap-3">
            <!-- JS 動態渲染 7 天卡片 -->
        </div>
    </div>
</div>

<!-- 預約詳情 Modal -->
<div id="detail-modal" class="hidden fixed inset-0 bg-black/40 z-[70] flex items-center justify-center" onclick="hideDetailModal()">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-xl mx-4" onclick="event.stopImmediatePropagation()">
        <div class="px-5 py-4 border-b flex items-center justify-between">
            <div class="font-semibold text-lg">預約詳情</div>
            <button onclick="hideDetailModal()" class="text-2xl leading-none text-gray-400 hover:text-gray-600">×</button>
        </div>

        <div class="p-5 space-y-4" id="detail-content">
            <!-- JS 動態填入 -->
        </div>

        <div class="px-5 py-4 bg-gray-50 flex flex-wrap gap-2 justify-between rounded-b-2xl">
            <div class="flex gap-2">
                <button onclick="quickChangeStatus('confirmed')" class="salon-btn salon-btn-secondary text-sm">標記已確認</button>
                <button onclick="quickChangeStatus('completed')" class="salon-btn salon-btn-secondary text-sm">標記已完成</button>
                <button onclick="quickChangeStatus('no_show')" class="salon-btn salon-btn-secondary text-sm">標記未到</button>
            </div>
            <div class="flex gap-2">
                <button onclick="editCurrentAppointment()" 
                        class="salon-btn salon-btn-secondary text-sm">
                    ✏️ 編輯
                </button>
                <button onclick="duplicateCurrentAppointment()" 
                        class="salon-btn salon-btn-secondary text-sm">
                    📋 複製
                </button>
                <button onclick="openPosFromAppointment()" 
                        class="salon-btn salon-btn-primary text-sm flex items-center gap-1">
                    🛒 從此預約開單
                </button>
            </div>
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

    // 預設列表檢視 + 按鈕樣式
    const btnList = document.getElementById('btn-view-list');
    if (btnList) {
        btnList.classList.add('bg-[#2C2C2E]', 'text-white');
    }

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
            <tr class="hover:bg-[#F8F5F0] cursor-pointer" onclick="showDetailModal(${a.id})">
                <td>${time}</td>
                <td>${e(a.customer_name || '-')} <span class="text-xs text-[#8A8A8C]">(${e(a.customer_phone || '')})</span></td>
                <td>${e(a.staff_name || '-')}</td>
                <td>${e(a.room_name || '不指定')}</td>
                <td><span class="text-xs px-2 py-0.5 bg-gray-100 rounded">${statusMap[a.status] || a.status}</span></td>
                <td class="text-right" onclick="event.stopImmediatePropagation()">
                    <button onclick="showDetailModal(${a.id}); event.stopImmediatePropagation();" 
                            class="text-[#8FA68F] hover:underline text-xs mr-2">詳情</button>
                    <button onclick="changeStatus(${a.id}, 'confirmed'); event.stopImmediatePropagation();" class="text-[#8FA68F] hover:underline text-xs mr-1">確認</button>
                    <button onclick="changeStatus(${a.id}, 'completed'); event.stopImmediatePropagation();" class="text-[#8FA68F] hover:underline text-xs mr-1">完成</button>
                    <button onclick="changeStatus(${a.id}, 'cancelled'); event.stopImmediatePropagation();" class="text-red-500 hover:underline text-xs">取消</button>
                </td>
            </tr>
        `;
    });
    tbody.innerHTML = html;
}

function showCreateModal() {
    showCreateModalWithTime('');
}

function showCreateModalWithTime(startTimeStr) {
    document.getElementById('modal-title').textContent = '新增預約';
    document.getElementById('appt-id').value = '';
    document.getElementById('customer-id').value = '';
    document.getElementById('customer-search').value = '';
    document.getElementById('staff-select').value = '';
    document.getElementById('room-select').value = '';
    document.getElementById('notes').value = '';

    // 清空已選服務
    document.querySelectorAll('#services-checkboxes input').forEach(cb => cb.checked = false);

    // 預填開始時間
    if (startTimeStr) {
        const dt = startTimeStr.replace(' ', 'T');
        document.getElementById('start-time').value = dt;
        setTimeout(() => {
            const startInput = document.getElementById('start-time');
            if (startInput.value) {
                const d = new Date(startInput.value);
                d.setMinutes(d.getMinutes() + 60);
                const endVal = d.toISOString().slice(0,16);
                document.getElementById('end-time').value = endVal.replace('T',' ') + ':00';
                document.getElementById('end-time-display').value = endVal.replace('T',' ');
            }
        }, 50);
    } else {
        document.getElementById('start-time').value = '';
        document.getElementById('end-time').value = '';
        document.getElementById('end-time-display').value = '';
    }

    document.getElementById('save-btn').textContent = '建立預約';

    document.getElementById('appt-modal').classList.remove('hidden');
    document.getElementById('appt-modal').classList.add('flex');
}

// 從週檢視快速在某日新增預約（預設早上 10:00）
function showCreateForDate(dateStr) {
    const defaultTime = `${dateStr} 10:00:00`;
    showCreateModalWithTime(defaultTime);
}

// 複製預約（常用於定期客戶）
function duplicateCurrentAppointment() {
    if (!currentAppointmentData) return;

    hideDetailModal();

    const a = currentAppointmentData;

    document.getElementById('modal-title').textContent = '複製預約';
    document.getElementById('appt-id').value = '';
    document.getElementById('customer-id').value = a.customer_id;
    document.getElementById('customer-search').value = `${a.customer_name} (${a.customer_phone || ''})`;
    document.getElementById('staff-select').value = a.staff_id || '';
    document.getElementById('room-select').value = a.room_id || '';
    document.getElementById('notes').value = a.notes || '';

    // 預設 +7 天同一時間
    const originalStart = new Date(a.start_time);
    originalStart.setDate(originalStart.getDate() + 7);
    const newStart = originalStart.toISOString().slice(0, 16);
    document.getElementById('start-time').value = newStart;

    const duration = new Date(a.end_time) - new Date(a.start_time);
    const newEnd = new Date(originalStart.getTime() + duration);
    const newEndStr = newEnd.toISOString().slice(0, 16);
    document.getElementById('end-time').value = newEndStr.replace('T', ' ') + ':00';
    document.getElementById('end-time-display').value = newEndStr.replace('T', ' ');

    document.querySelectorAll('#services-checkboxes input').forEach(cb => cb.checked = false);
    if (a.items && a.items.length > 0) {
        a.items.forEach(item => {
            const cb = document.querySelector(`#services-checkboxes input[value="${item.service_id}"]`);
            if (cb) cb.checked = true;
        });
    }

    document.getElementById('save-btn').textContent = '建立複製預約';

    document.getElementById('appt-modal').classList.remove('hidden');
    document.getElementById('appt-modal').classList.add('flex');
}

function hideApptModal() {
    const modal = document.getElementById('appt-modal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');

    // 清空編輯狀態
    document.getElementById('appt-id').value = '';
}

async function saveAppointment() {
    const apptId = document.getElementById('appt-id').value;
    const isEdit = !!apptId;

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

    if (isEdit) {
        payload.id = apptId;
    }

    try {
        const action = isEdit ? 'update' : 'create';
        await SalonEase.fetch(`/api/appointments.php?action=${action}`, {
            method: 'POST',
            body: payload
        });

        hideApptModal();
        SalonEase.toast(isEdit ? '預約已更新' : '預約建立成功');

        // 重新載入目前視圖
        if (currentView === 'week') {
            loadWeekView();
        } else if (currentView === 'calendar') {
            loadTodaySchedule();
        } else {
            loadAppointments();
        }
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

// 顯示詳情 Modal
let currentDetailId = null;
let currentAppointmentData = null; // 用於編輯時的資料

async function showDetailModal(id) {
    currentDetailId = id;
    const container = document.getElementById('detail-content');
    container.innerHTML = `<div class="py-6 text-center text-[#8A8A8C]">載入中...</div>`;

    document.getElementById('detail-modal').classList.remove('hidden');
    document.getElementById('detail-modal').classList.add('flex');

    try {
        const res = await SalonEase.fetch(`/api/appointments.php?action=get&id=${id}`);
        currentAppointmentData = res.data;
        const a = res.data;

        const statusMap = {
            'pending': '待確認',
            'confirmed': '已確認',
            'completed': '已完成',
            'cancelled': '已取消',
            'no_show': '未到'
        };

        let servicesHtml = '<div class="text-sm text-[#8A8A8C]">無服務項目</div>';
        if (a.items && a.items.length > 0) {
            servicesHtml = '<ul class="text-sm space-y-1">';
            a.items.forEach(item => {
                servicesHtml += `<li>• ${e(item.service_name)} — $${parseFloat(item.price_at_time).toFixed(0)}</li>`;
            });
            servicesHtml += '</ul>';
        }

        const start = new Date(a.start_time);
        const end = new Date(a.end_time);

        container.innerHTML = `
            <div class="grid grid-cols-2 gap-x-6 gap-y-3 text-sm">
                <div><span class="text-[#5A5A5C]">客戶：</span><span class="font-medium">${e(a.customer_name || '-')}</span></div>
                <div><span class="text-[#5A5A5C]">美容師：</span>${e(a.staff_name || '-')}</div>
                <div><span class="text-[#5A5A5C]">時間：</span>${start.toLocaleString('zh-HK', {month:'2-digit',day:'2-digit',hour:'2-digit',minute:'2-digit'})} ~ ${end.toLocaleTimeString('zh-HK', {hour:'2-digit',minute:'2-digit'})}</div>
                <div><span class="text-[#5A5A5C]">房間：</span>${e(a.room_name || '不指定')}</div>
                <div class="col-span-2"><span class="text-[#5A5A5C]">狀態：</span> <span class="font-medium">${statusMap[a.status] || a.status}</span></div>
            </div>

            <div class="pt-3 border-t">
                <div class="text-[#5A5A5C] text-xs mb-1">服務項目</div>
                ${servicesHtml}
            </div>

            ${a.notes ? `<div class="pt-2"><div class="text-[#5A5A5C] text-xs mb-1">備註</div><div class="text-sm bg-gray-50 p-2 rounded">${e(a.notes)}</div></div>` : ''}
        `;
    } catch (err) {
        container.innerHTML = `<div class="text-red-500">${err.message}</div>`;
    }
}

function hideDetailModal() {
    document.getElementById('detail-modal').classList.add('hidden');
    document.getElementById('detail-modal').classList.remove('flex');
}

function quickChangeStatus(newStatus) {
    if (!currentDetailId) return;
    hideDetailModal();
    changeStatus(currentDetailId, newStatus);
}

// 時間軸上的快速狀態變更
async function quickStatusChange(id, status, viewType = 'list') {
    try {
        await SalonEase.fetch('/api/appointments.php?action=change_status', {
            method: 'POST',
            body: { id, status }
        });
        SalonEase.toast('狀態已更新');

        if (viewType === 'calendar') {
            loadTodaySchedule();
        } else if (viewType === 'week') {
            loadWeekView();
        } else {
            loadAppointments();
        }
    } catch (err) {
        SalonEase.toast(err.message, 'error');
    }
}

function openPosFromAppointment() {
    if (!currentDetailId) return;
    // 跳轉到 POS 並帶上預約 ID（Phase 3 會處理自動帶入客戶與服務）
    window.location.href = `/pos.php?appointment_id=${currentDetailId}`;
}

function editCurrentAppointment() {
    if (!currentDetailId || !currentAppointmentData) return;

    hideDetailModal();

    const a = currentAppointmentData;

    // 重用新增 Modal 做編輯
    document.getElementById('modal-title').textContent = '編輯預約';
    document.getElementById('appt-id').value = a.id;
    document.getElementById('customer-id').value = a.customer_id;
    document.getElementById('customer-search').value = `${a.customer_name} (${a.customer_phone || ''})`;
    document.getElementById('staff-select').value = a.staff_id || '';
    document.getElementById('room-select').value = a.room_id || '';
    document.getElementById('notes').value = a.notes || '';

    // 設定時間
    const startDT = a.start_time.replace(' ', 'T').slice(0, 16);
    const endDT = a.end_time.replace(' ', 'T').slice(0, 16);
    document.getElementById('start-time').value = startDT;
    document.getElementById('end-time').value = endDT.replace('T', ' ') + ':00';
    document.getElementById('end-time-display').value = endDT.replace('T', ' ');

    // 清空並勾選服務
    document.querySelectorAll('#services-checkboxes input').forEach(cb => cb.checked = false);
    if (a.items && a.items.length > 0) {
        a.items.forEach(item => {
            const cb = document.querySelector(`#services-checkboxes input[value="${item.service_id}"]`);
            if (cb) cb.checked = true;
        });
    }

    document.getElementById('save-btn').textContent = '儲存變更';

    document.getElementById('appt-modal').classList.remove('hidden');
    document.getElementById('appt-modal').classList.add('flex');
}

// ==================== 檢視切換與今日時程 ====================

let currentView = 'list';

function switchView(view) {
    currentView = view;

    const listView = document.getElementById('list-view');
    const calendarView = document.getElementById('calendar-view');
    const weekView = document.getElementById('week-view');

    const btnList = document.getElementById('btn-view-list');
    const btnCalendar = document.getElementById('btn-view-calendar');
    const btnWeek = document.getElementById('btn-view-week');

    // 隱藏所有視圖
    listView.classList.add('hidden');
    calendarView.classList.add('hidden');
    weekView.classList.add('hidden');

    // 重置按鈕樣式
    [btnList, btnCalendar, btnWeek].forEach(b => {
        if (b) {
            b.classList.remove('bg-[#2C2C2E]', 'text-white');
            b.classList.add('hover:bg-gray-100');
        }
    });

    if (view === 'list') {
        listView.classList.remove('hidden');
        if (btnList) btnList.classList.add('bg-[#2C2C2E]', 'text-white');
    } else if (view === 'calendar') {
        calendarView.classList.remove('hidden');
        if (btnCalendar) btnCalendar.classList.add('bg-[#2C2C2E]', 'text-white');
        loadTodaySchedule();
    } else if (view === 'week') {
        weekView.classList.remove('hidden');
        if (btnWeek) btnWeek.classList.add('bg-[#2C2C2E]', 'text-white');
        loadWeekView();
    }
}

async function loadTodaySchedule() {
    const container = document.getElementById('today-timeline');
    const dateEl = document.getElementById('today-date');
    const staffFilter = document.getElementById('today-staff-filter');

    const today = new Date();
    const todayStr = today.toISOString().split('T')[0];
    dateEl.textContent = today.toLocaleDateString('zh-HK', { weekday: 'long', month: 'long', day: 'numeric' });

    if (staffFilter.options.length <= 1) {
        try {
            const staffRes = await SalonEase.fetch('/api/staff.php?action=list&status=1');
            let html = '<option value="">全部美容師</option>';
            staffRes.data.forEach(s => {
                html += `<option value="${s.id}">${e(s.name)}</option>`;
            });
            staffFilter.innerHTML = html;
        } catch (e) {}
    }

    const selectedStaff = staffFilter.value;

    container.innerHTML = `<div class="py-8 text-center text-[#8A8A8C]">載入今日時程中...</div>`;

    try {
        let url = `/api/appointments.php?action=list&date_from=${todayStr}&date_to=${todayStr}`;
        if (selectedStaff) url += `&staff_id=${selectedStaff}`;

        const res = await SalonEase.fetch(url);
        const appts = (res.data || []).sort((a, b) => a.start_time.localeCompare(b.start_time));

        // 建立時間格（09:00 - 21:00，每 30 分鐘）
        const startHour = 9;
        const endHour = 21;
        const slotMinutes = 30;
        const totalSlots = ((endHour - startHour) * 60) / slotMinutes;

        // 建立 grid 容器
        let html = `
            <div class="grid" style="grid-template-columns: 60px 1fr; font-size: 13px;">
                <!-- 時間軸 -->
                <div class="border-r bg-[#F8F5F0]">
        `;

        for (let i = 0; i <= totalSlots; i++) {
            const minutes = i * slotMinutes;
            const hour = startHour + Math.floor(minutes / 60);
            const min = minutes % 60;
            const timeLabel = `${String(hour).padStart(2,'0')}:${String(min).padStart(2,'0')}`;

            html += `<div class="h-[28px] flex items-center justify-end pr-2 text-[#5A5A5C] border-b text-xs">${timeLabel}</div>`;
        }

        html += `</div><div class="relative" style="height: ${totalSlots * 28}px;">`;

        // 簡單重疊處理：為重疊的預約分配左右欄位
        const placed = [];
        const getLane = (appt) => {
            const aStart = new Date(appt.start_time);
            const aEnd = new Date(appt.end_time);
            for (let lane = 0; lane < 3; lane++) {
                const conflict = placed.some(p => {
                    if (p.lane !== lane) return false;
                    const pStart = new Date(p.start_time);
                    const pEnd = new Date(p.end_time);
                    return Math.max(aStart, pStart) < Math.min(aEnd, pEnd);
                });
                if (!conflict) return lane;
            }
            return 0;
        };

        appts.forEach(a => {
            a._lane = getLane(a);
            placed.push(a);
        });

        // 渲染預約區塊
        appts.forEach(a => {
            const aStart = new Date(a.start_time);
            const aEnd = new Date(a.end_time);

            const startMinutes = (aStart.getHours() - startHour) * 60 + aStart.getMinutes();
            const endMinutes = (aEnd.getHours() - startHour) * 60 + aEnd.getMinutes();

            const top = Math.max(0, (startMinutes / slotMinutes) * 28);
            const height = Math.max(28, ((endMinutes - startMinutes) / slotMinutes) * 28);

            const lane = a._lane || 0;
            const colWidth = 100 / 3;
            const left = lane * colWidth + 2;
            const width = colWidth - 4;

            const statusColor = a.status === 'confirmed' ? '#C6E2C6' : 
                               (a.status === 'pending' ? '#FFF3C6' : '#E5E5E5');

            const quickActions = [];
            if (a.status === 'pending') {
                quickActions.push(`<span onclick="event.stopImmediatePropagation(); quickStatusChange(${a.id}, 'confirmed', 'calendar')" class="px-1.5 py-0 text-[10px] bg-green-200 hover:bg-green-300 rounded">確認</span>`);
            }
            if (a.status === 'pending' || a.status === 'confirmed') {
                quickActions.push(`<span onclick="event.stopImmediatePropagation(); quickStatusChange(${a.id}, 'completed', 'calendar')" class="px-1.5 py-0 text-[10px] bg-blue-200 hover:bg-blue-300 rounded">完成</span>`);
            }
            if (a.status !== 'cancelled' && a.status !== 'no_show') {
                quickActions.push(`<span onclick="event.stopImmediatePropagation(); quickStatusChange(${a.id}, 'cancelled', 'calendar')" class="px-1.5 py-0 text-[10px] bg-red-200 hover:bg-red-300 rounded">取消</span>`);
            }

            const actionsHtml = quickActions.length > 0 
                ? `<div class="absolute top-0.5 right-0.5 hidden group-hover:flex gap-1 text-[9px]">${quickActions.join('')}</div>` 
                : '';

            html += `
                <div onclick="showDetailModal(${a.id})" 
                     class="group absolute rounded-lg px-2 py-1 text-xs cursor-pointer shadow-sm border border-gray-200 flex flex-col justify-center hover:brightness-95 transition"
                     style="top: ${top}px; height: ${height}px; left: ${left}%; width: ${width}%; background-color: ${statusColor}; z-index: ${10 + lane};">
                    <div class="font-medium truncate">${e(a.customer_name || '客戶')}</div>
                    <div class="text-[10px] text-[#555] truncate">${e(a.staff_name || '')}</div>
                    ${actionsHtml}
                </div>
            `;
        });

        html += `</div></div>`;
        container.innerHTML = html;

    } catch (err) {
        container.innerHTML = `<div class="text-red-500 py-4">載入失敗：${err.message}</div>`;
    }
}

async function loadWeekView() {
    const container = document.getElementById('week-grid');
    container.innerHTML = `<div class="col-span-full py-8 text-center text-[#8A8A8C]">載入中...</div>`;

    const today = new Date();
    const days = [];

    // 產生未來 7 天
    for (let i = 0; i < 7; i++) {
        const d = new Date(today);
        d.setDate(d.getDate() + i);
        const dateStr = d.toISOString().split('T')[0];
        days.push({
            date: dateStr,
            label: d.toLocaleDateString('zh-HK', { weekday: 'short', month: 'numeric', day: 'numeric' }),
            isToday: i === 0
        });
    }

    const from = days[0].date;
    const to = days[6].date;

    try {
        const res = await SalonEase.fetch(`/api/appointments.php?action=list&date_from=${from}&date_to=${to}`);
        const allAppts = res.data || [];

        let html = '';

        days.forEach(day => {
            const dayAppts = allAppts.filter(a => a.start_time.startsWith(day.date));
            const count = dayAppts.length;

            let apptHtml = '';
            if (count === 0) {
                apptHtml = `<div class="text-xs text-[#8A8A8C] mt-1">無預約</div>`;
            } else {
                dayAppts.slice(0, 4).forEach(a => {
                    const start = new Date(a.start_time).toLocaleTimeString('zh-HK', {hour:'2-digit', minute:'2-digit'});
                    const end = new Date(a.end_time).toLocaleTimeString('zh-HK', {hour:'2-digit', minute:'2-digit'});
                    apptHtml += `
                        <div onclick="showDetailModal(${a.id}); event.stopImmediatePropagation();" 
                             class="text-xs truncate px-2 py-0.5 mt-1 rounded bg-[#E8F0E8] hover:bg-[#D4E6D4] cursor-pointer">
                            ${start}-${end} ${e(a.customer_name || '')}
                        </div>
                    `;
                });
                if (count > 4) {
                    apptHtml += `<div class="text-xs text-[#8A8A8C] mt-1">+${count-4} 更多</div>`;
                }
            }

            const todayClass = day.isToday ? 'ring-2 ring-[#8FA68F]' : '';

            html += `
                <div class="border rounded-2xl p-3 hover:border-[#8FA68F] transition ${todayClass}">
                    <div class="font-semibold text-sm flex justify-between items-center">
                        <span onclick="switchToDayTimeline('${day.date}')" class="cursor-pointer hover:underline">${day.label}</span>
                        <button onclick="showCreateForDate('${day.date}'); event.stopImmediatePropagation();" 
                                class="text-xs px-2 py-0.5 rounded-lg bg-[#8FA68F] text-white hover:bg-[#7A947A] flex items-center gap-1">
                            <span>+</span>
                        </button>
                    </div>
                    <div onclick="switchToDayTimeline('${day.date}')" class="mt-2 min-h-[60px] cursor-pointer">
                        ${apptHtml}
                    </div>
                </div>
            `;
        });

        container.innerHTML = html;

    } catch (err) {
        container.innerHTML = `<div class="col-span-full text-red-500">載入失敗：${err.message}</div>`;
    }
}

function filterListByDate(dateStr) {
    // 切換回列表檢視，並設定日期篩選
    switchView('list');
    setTimeout(() => {
        document.getElementById('date-from').value = dateStr;
        document.getElementById('date-to').value = dateStr;
        loadAppointments();
    }, 100);
}

// 從週檢視切換到該日的詳細時間軸
function switchToDayTimeline(dateStr) {
    switchView('calendar');

    // 強制載入指定日期的時間軸
    setTimeout(() => {
        loadTodayScheduleForDate(dateStr);
    }, 50);
}

// 支援載入指定日期的今日時程（非只限今天）
function navigateTodayDay(offset) {
    const dateEl = document.getElementById('today-date');
    // 從目前顯示的日期推算
    const currentText = dateEl.textContent;
    // 簡單做法：使用一個隱藏的日期 input 或全域變數
    if (!window.currentTimelineDate) {
        window.currentTimelineDate = new Date();
    }
    window.currentTimelineDate.setDate(window.currentTimelineDate.getDate() + offset);

    const newDateStr = window.currentTimelineDate.toISOString().split('T')[0];
    loadTodayScheduleForDate(newDateStr);
}

async function loadTodayScheduleForDate(targetDateStr) {
    const container = document.getElementById('today-timeline');
    const dateEl = document.getElementById('today-date');
    const staffFilter = document.getElementById('today-staff-filter');

    dateEl.textContent = new Date(targetDateStr).toLocaleDateString('zh-HK', { weekday: 'long', month: 'long', day: 'numeric' });

    if (staffFilter.options.length <= 1) {
        try {
            const staffRes = await SalonEase.fetch('/api/staff.php?action=list&status=1');
            let html = '<option value="">全部美容師</option>';
            staffRes.data.forEach(s => {
                html += `<option value="${s.id}">${e(s.name)}</option>`;
            });
            staffFilter.innerHTML = html;
        } catch (e) {}
    }

    const selectedStaff = staffFilter.value;

    container.innerHTML = `<div class="py-8 text-center text-[#8A8A8C]">載入時程中...</div>`;

    try {
        let url = `/api/appointments.php?action=list&date_from=${targetDateStr}&date_to=${targetDateStr}`;
        if (selectedStaff) url += `&staff_id=${selectedStaff}`;

        const res = await SalonEase.fetch(url);
        const appts = (res.data || []).sort((a, b) => a.start_time.localeCompare(b.start_time));

        // 使用和 loadTodaySchedule 相同的區塊渲染邏輯
        const startHour = 9;
        const endHour = 21;
        const slotMinutes = 30;
        const totalSlots = ((endHour - startHour) * 60) / slotMinutes;

        let html = `
            <div class="grid" style="grid-template-columns: 60px 1fr; font-size: 13px;">
                <div class="border-r bg-[#F8F5F0]">
        `;

        for (let i = 0; i <= totalSlots; i++) {
            const minutes = i * slotMinutes;
            const hour = startHour + Math.floor(minutes / 60);
            const min = minutes % 60;
            const timeLabel = `${String(hour).padStart(2,'0')}:${String(min).padStart(2,'0')}`;
            html += `<div class="h-[28px] flex items-center justify-end pr-2 text-[#5A5A5C] border-b text-xs">${timeLabel}</div>`;
        }

        html += `</div><div class="relative" style="height: ${totalSlots * 28}px;">`;

        // 重疊處理
        const placed = [];
        const getLane = (appt) => {
            const aStart = new Date(appt.start_time);
            const aEnd = new Date(appt.end_time);
            for (let lane = 0; lane < 3; lane++) {
                const conflict = placed.some(p => {
                    if (p.lane !== lane) return false;
                    const pStart = new Date(p.start_time);
                    const pEnd = new Date(p.end_time);
                    return Math.max(aStart, pStart) < Math.min(aEnd, pEnd);
                });
                if (!conflict) return lane;
            }
            return 0;
        };

        appts.forEach(a => {
            a._lane = getLane(a);
            placed.push(a);
        });

        appts.forEach(a => {
            const aStart = new Date(a.start_time);
            const aEnd = new Date(a.end_time);

            const startMinutes = (aStart.getHours() - startHour) * 60 + aStart.getMinutes();
            const endMinutes = (aEnd.getHours() - startHour) * 60 + aEnd.getMinutes();

            const top = Math.max(0, (startMinutes / slotMinutes) * 28);
            const height = Math.max(28, ((endMinutes - startMinutes) / slotMinutes) * 28);

            const lane = a._lane || 0;
            const colWidth = 100 / 3;
            const left = lane * colWidth + 2;
            const width = colWidth - 4;

            const statusColor = a.status === 'confirmed' ? '#C6E2C6' : 
                               (a.status === 'pending' ? '#FFF3C6' : '#E5E5E5');

            const quickActions = [];
            if (a.status === 'pending') quickActions.push(`<span onclick="event.stopImmediatePropagation(); quickStatusChange(${a.id}, 'confirmed', 'calendar')" class="px-1 py-0 text-[9px] bg-green-200 hover:bg-green-300 rounded">確認</span>`);
            if (a.status === 'pending' || a.status === 'confirmed') quickActions.push(`<span onclick="event.stopImmediatePropagation(); quickStatusChange(${a.id}, 'completed', 'calendar')" class="px-1 py-0 text-[9px] bg-blue-200 hover:bg-blue-300 rounded">完成</span>`);
            if (a.status !== 'cancelled' && a.status !== 'no_show') quickActions.push(`<span onclick="event.stopImmediatePropagation(); quickStatusChange(${a.id}, 'cancelled', 'calendar')" class="px-1 py-0 text-[9px] bg-red-200 hover:bg-red-300 rounded">取消</span>`);

            const actionsHtml = quickActions.length > 0 ? `<div class="absolute top-0.5 right-0.5 hidden group-hover:flex gap-1 text-[9px]">${quickActions.join('')}</div>` : '';

            html += `
                <div onclick="showDetailModal(${a.id})" 
                     class="group absolute rounded-lg px-2 py-1 text-xs cursor-pointer shadow-sm border border-gray-200 flex flex-col justify-center hover:brightness-95 transition"
                     style="top: ${top}px; height: ${height}px; left: ${left}%; width: ${width}%; background-color: ${statusColor}; z-index: ${10 + lane};">
                    <div class="font-medium truncate">${e(a.customer_name || '客戶')}</div>
                    <div class="text-[10px] text-[#555] truncate">${e(a.staff_name || '')}</div>
                    ${actionsHtml}
                </div>
            `;
        });

        html += `</div></div>`;
        container.innerHTML = html;

    } catch (err) {
        container.innerHTML = `<div class="text-red-500 py-4">載入失敗：${err.message}</div>`;
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
