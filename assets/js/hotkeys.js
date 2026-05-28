/**
 * SalonEase - 熱鍵管理系統（核心功能）
 * 支援全域 + 頁面專屬熱鍵
 * 自動產生幫助 Modal 內容
 */

(function() {
    const registry = {
        global: [
            { key: '?', desc: '顯示目前頁面快捷鍵說明', action: () => window.showHotkeyHelp?.() },
            { key: 'Ctrl+K', desc: '開啟命令面板（快速跳轉）', action: showCommandPalette },
            { key: 'Esc', desc: '關閉彈窗 / 取消操作' },
            { key: 'Alt+H', desc: '返回概覽頁', action: () => location.href = '/dashboard.php' },
            { key: 'Alt+P', desc: '前往 POS 銷售', action: () => location.href = '/pos.php' },
            { key: 'Alt+A', desc: '前往預約管理', action: () => location.href = '/appointments.php' },
            { key: 'Alt+C', desc: '前往客戶管理', action: () => location.href = '/customers.php' },
            { key: 'Alt+R', desc: '前往報表', action: () => location.href = '/reports.php' },
            { key: 'Alt+M', desc: '前往佣金查詢', action: () => location.href = '/commissions.php' },
            { key: 'Alt+S', desc: '前往設定', action: () => location.href = '/settings.php' },
            { key: 'F9', desc: '打印上一張收據（58mm 熱感紙）', action: () => window.printLastReceipt?.('58') },
        ],
        // 各頁面可透過 window.SalonEase.Hotkeys.registerPage 加入
        page: []
    };

    // 命令面板 - 最近使用紀錄
    const RECENT_KEY = 'salonease_cmd_recent';
    const MAX_RECENT = 6;

    function getRecentCommands() {
        try {
            return JSON.parse(localStorage.getItem(RECENT_KEY) || '[]');
        } catch {
            return [];
        }
    }

    function saveRecentCommand(id) {
        try {
            let recent = getRecentCommands();
            recent = recent.filter(x => x !== id);
            recent.unshift(id);
            if (recent.length > MAX_RECENT) recent.length = MAX_RECENT;
            localStorage.setItem(RECENT_KEY, JSON.stringify(recent));
        } catch {}
    }

    function showCommandPalette() {
        document.getElementById('commandPaletteModal')?.remove();

        const modalHTML = `
            <div class="modal fade" id="commandPaletteModal" tabindex="-1" aria-hidden="true" style="z-index: 1080;">
                <div class="modal-dialog modal-dialog-centered" style="max-width: 560px;">
                    <div class="modal-content shadow">
                        <div class="modal-header py-2 px-3 border-bottom-0">
                            <input type="text" id="cmd-input" 
                                   class="form-control form-control-sm border-0 shadow-none px-0" 
                                   placeholder="搜尋頁面、客戶或動作..." 
                                   style="font-size: 15px;">
                        </div>
                        <div class="modal-body p-0" id="cmd-results" style="max-height: 400px; overflow-y: auto;">
                        </div>
                        <div class="modal-footer py-2 px-3 small text-muted border-top-0">
                            ↑↓ 選擇 • Enter 執行 • Esc 關閉
                        </div>
                    </div>
                </div>
            </div>
        `;

        document.body.insertAdjacentHTML('beforeend', modalHTML);

        const modalEl = document.getElementById('commandPaletteModal');
        const modal = new bootstrap.Modal(modalEl, { backdrop: true, keyboard: false });
        modal.show();

        const input = document.getElementById('cmd-input');
        const resultsContainer = document.getElementById('cmd-results');

        let selectedIndex = 0;
        let currentFiltered = [];
        let customerResults = [];

        const staticCommands = [
            // 頁面
            { id: 'pos', label: 'POS 銷售', type: 'page', url: '/pos.php', shortcut: 'Alt+P' },
            { id: 'appointments', label: '預約管理', type: 'page', url: '/appointments.php', shortcut: 'Alt+A' },
            { id: 'customers', label: '客戶管理', type: 'page', url: '/customers.php', shortcut: 'Alt+C' },
            { id: 'dashboard', label: '概覽首頁', type: 'page', url: '/dashboard.php', shortcut: 'Alt+H' },
            { id: 'reports', label: '報表', type: 'page', url: '/reports.php', shortcut: 'Alt+R' },
            { id: 'commissions', label: '佣金查詢', type: 'page', url: '/commissions.php', shortcut: 'Alt+M' },
            { id: 'settings', label: '系統設定', type: 'page', url: '/settings.php', shortcut: 'Alt+S' },
            { id: 'staff', label: '員工管理', type: 'page', url: '/staff.php' },
            { id: 'services', label: '服務項目管理', type: 'page', url: '/services.php' },
            { id: 'products', label: '產品管理', type: 'page', url: '/products.php' },
            { id: 'packages', label: '套票管理', type: 'page', url: '/packages.php' },
            { id: 'rooms', label: '房間管理', type: 'page', url: '/rooms.php' },

            // 動作
            { id: 'new-customer', label: '新增客戶', type: 'action', action: () => { modal.hide(); setTimeout(() => location.href = '/customers.php?new=1', 180); } },
            { id: 'new-appointment', label: '新增預約', type: 'action', action: () => { modal.hide(); setTimeout(() => location.href = '/appointments.php?new=1', 180); } },
            { id: 'print-receipt', label: '打印上一張收據 (58mm)', type: 'action', action: () => { modal.hide(); setTimeout(() => window.printLastReceipt?.('58'), 120); } },
            { id: 'report-today', label: '切換到今日報表', type: 'action', action: () => { modal.hide(); setTimeout(() => location.href = '/reports.php', 180); } },
        ];

        async function searchCustomers(keyword) {
            if (!keyword || keyword.length < 2) {
                customerResults = [];
                return;
            }

            try {
                const res = await window.SalonEase.fetch(`/api/customers.php?action=list&search=${encodeURIComponent(keyword)}&limit=6`);
                customerResults = (res.data || []).map(c => ({
                    id: `customer-${c.id}`,
                    label: `查看客戶：${c.name}`,
                    type: 'customer',
                    action: () => {
                        modal.hide();
                        setTimeout(() => location.href = `/customers.php?id=${c.id}`, 150);
                    },
                    secondaryAction: {
                        label: `為 ${c.name} 新增預約`,
                        action: () => {
                            modal.hide();
                            setTimeout(() => location.href = `/appointments.php?new=1&customer_id=${c.id}`, 150);
                        }
                    },
                    phone: c.phone
                }));
            } catch (e) {
                customerResults = [];
            }
        }

        function getRecentCommandsList() {
            const recentIds = getRecentCommands();
            return recentIds
                .map(id => staticCommands.find(c => c.id === id))
                .filter(Boolean);
        }

        function renderResults(filter = '') {
            const q = filter.toLowerCase().trim();
            const recent = getRecentCommandsList();

            let toShow = [];
            currentFiltered = [];

            if (!q) {
                const recentSet = new Set(recent.map(c => c.id));
                const others = staticCommands.filter(c => !recentSet.has(c.id));

                if (recent.length > 0) {
                    toShow.push({ isSection: true, title: '最近使用' });
                    toShow.push(...recent);
                    currentFiltered.push(...recent);
                }

                toShow.push({ isSection: true, title: '所有功能' });
                toShow.push(...others);
                currentFiltered.push(...others);
            } else {
                // 靜態指令
                const filteredStatic = staticCommands.filter(cmd =>
                    cmd.label.toLowerCase().includes(q)
                );

                // 客戶結果
                const filteredCustomers = customerResults.filter(c =>
                    c.label.toLowerCase().includes(q) || (c.phone && c.phone.includes(q))
                );

                if (filteredStatic.length > 0) {
                    toShow.push({ isSection: true, title: '功能' });
                    toShow.push(...filteredStatic);
                    currentFiltered.push(...filteredStatic);
                }

                if (filteredCustomers.length > 0) {
                    toShow.push({ isSection: true, title: '客戶' });
                    toShow.push(...filteredCustomers);
                    currentFiltered.push(...filteredCustomers);
                }
            }

            if (toShow.length === 0) {
                resultsContainer.innerHTML = `<div class="px-3 py-3 text-muted small">找不到符合的結果</div>`;
                selectedIndex = -1;
                return;
            }

            if (selectedIndex >= currentFiltered.length) selectedIndex = currentFiltered.length - 1;
            if (selectedIndex < 0) selectedIndex = 0;

            let html = '';
            let cmdIndex = 0;

            toShow.forEach(item => {
                if (item.isSection) {
                    html += `<div class="px-3 pt-2 pb-1 small text-muted fw-medium">${item.title}</div>`;
                    return;
                }

                const isSelected = cmdIndex === selectedIndex;
                const hasSecondary = item.secondaryAction;

                html += `
                    <div class="cmd-item px-3 py-2 ${isSelected ? 'bg-primary-subtle' : ''}" 
                         data-cmd-index="${cmdIndex}" style="cursor: pointer;">
                        <div class="d-flex justify-content-between align-items-center">
                            <span>${item.label}</span>
                            ${item.shortcut ? `<span class="text-muted small">${item.shortcut}</span>` : ''}
                        </div>
                        ${hasSecondary ? `
                            <div class="small mt-1">
                                <button class="btn btn-sm btn-outline-secondary py-0 px-2" 
                                        onclick="event.stopImmediatePropagation(); this.closest('.modal').querySelector('.modal').dispatchEvent(new Event('secondaryAction')); return false;">
                                    ${item.secondaryAction.label}
                                </button>
                            </div>
                        ` : ''}
                    </div>
                `;
                cmdIndex++;
            });

            resultsContainer.innerHTML = html;

            // Click handlers
            resultsContainer.querySelectorAll('.cmd-item').forEach(item => {
                const idx = parseInt(item.dataset.cmdIndex);
                item.addEventListener('click', (e) => {
                    if (!e.target.closest('button')) {
                        executeCommand(currentFiltered[idx]);
                    }
                });
            });

            // Secondary action buttons (for customers)
            // We handle secondary via a custom event for simplicity in this version
        }

        function executeCommand(cmd) {
            saveRecentCommand(cmd.id || cmd.label);
            modal.hide();
            setTimeout(() => {
                if (cmd.url) {
                    window.location.href = cmd.url;
                } else if (cmd.action) {
                    cmd.action();
                } else if (cmd.secondaryAction) {
                    // fallback
                    cmd.secondaryAction.action();
                }
            }, 100);
        }

        async function handleSearch() {
            const q = input.value.trim();
            selectedIndex = 0;

            // Fetch customers if query is meaningful
            if (q.length >= 2) {
                await searchCustomers(q);
            } else {
                customerResults = [];
            }

            renderResults(q);
        }

        // Initial render
        renderResults('');

        // Live search with debounce for customer API
        let searchTimeout;
        input.addEventListener('input', () => {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(async () => {
                await handleSearch();
            }, 220);
        });

        // Keyboard navigation
        input.addEventListener('keydown', (e) => {
            const max = currentFiltered.length - 1;

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                selectedIndex = Math.min(selectedIndex + 1, max);
                renderResults(input.value);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                selectedIndex = Math.max(selectedIndex - 1, 0);
                renderResults(input.value);
            } else if (e.key === 'Enter') {
                e.preventDefault();
                if (currentFiltered[selectedIndex]) {
                    executeCommand(currentFiltered[selectedIndex]);
                }
            } else if (e.key === 'Escape') {
                modal.hide();
            }
        });

        setTimeout(() => {
            input.focus();
            input.select();
        }, 60);

        modalEl.addEventListener('hidden.bs.modal', () => {
            modalEl.remove();
        });
    }

    // 公開 API
    window.SalonEase = window.SalonEase || {};
    window.SalonEase.Hotkeys = {
        registerPage: function(keys) {
            registry.page = keys;
        },
        renderHelp: function(container) {
            let html = '<div class="space-y-6 text-sm">';
            
            // 全局
            html += '<div><div class="font-semibold mb-2 text-[#2C2C2E]">全域快捷鍵（任何頁面）</div>';
            html += '<div class="grid grid-cols-1 gap-x-6 gap-y-1">';
            registry.global.forEach(item => {
                html += `<div class="flex justify-between py-px"><span class="font-mono text-[#8FA68F]">${item.key}</span><span>${item.desc}</span></div>`;
            });
            html += '</div></div>';

            // 頁面專屬
            if (registry.page.length) {
                html += '<div><div class="font-semibold mb-2 text-[#2C2C2E]">本頁專屬</div>';
                html += '<div class="grid grid-cols-1 gap-x-6 gap-y-1">';
                registry.page.forEach(item => {
                    html += `<div class="flex justify-between py-px"><span class="font-mono text-[#8FA68F]">${item.key}</span><span>${item.desc}</span></div>`;
                });
                html += '</div></div>';
            }

            html += '<div class="pt-2 text-[11px] text-[#8A8A8C]">提示：F 系列鍵（F2-F10）在 POS 頁最常用</div>';
            html += '</div>';

            container.innerHTML = html;
        },
        getAll: () => ({ global: registry.global, page: registry.page })
    };

    // 自動綁定全域熱鍵
    document.addEventListener('keydown', function(e) {
        const tag = document.activeElement.tagName;
        if (['INPUT', 'TEXTAREA', 'SELECT'].includes(tag) && e.key !== 'Escape') return;

        // 簡單全域 Alt 組合
        if (e.altKey) {
            const map = { 'h': '/dashboard.php', 'p': '/pos.php', 'a': '/appointments.php', 'c': '/customers.php', 'r': '/reports.php', 'm': '/commissions.php', 's': '/settings.php' };
            if (map[e.key.toLowerCase()]) {
                e.preventDefault();
                location.href = map[e.key.toLowerCase()];
            }
        }

        // F9 = 快速打印上一張收據（58mm）
        if (e.key === 'F9') {
            e.preventDefault();
            if (window.printLastReceipt) {
                window.printLastReceipt('58');
            }
        }
    });

    console.log('%c[SalonEase] 熱鍵系統已就緒（按 ? 測試）', 'color:#8FA68F;font-size:9px');
})();
