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

    function showCommandPalette() {
        // Remove any existing palette
        document.getElementById('commandPaletteModal')?.remove();

        const modalHTML = `
            <div class="modal fade" id="commandPaletteModal" tabindex="-1" aria-hidden="true" style="z-index: 1080;">
                <div class="modal-dialog modal-dialog-centered" style="max-width: 520px;">
                    <div class="modal-content shadow-lg">
                        <div class="modal-header py-2 px-3 border-bottom-0">
                            <input type="text" id="cmd-input" 
                                   class="form-control form-control-sm border-0 shadow-none px-0" 
                                   placeholder="搜尋頁面或快速動作..." 
                                   style="font-size: 15px;">
                        </div>
                        <div class="modal-body p-0" id="cmd-results" style="max-height: 340px; overflow-y: auto;">
                            <!-- Populated dynamically -->
                        </div>
                        <div class="modal-footer py-2 px-3 small text-muted border-top-0 d-flex justify-content-between">
                            <span>↑↓ 選擇 • Enter 執行 • Esc 關閉</span>
                            <span class="text-muted">Ctrl+K</span>
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

        const commands = [
            // 頁面導航
            { label: 'POS 銷售', type: 'nav', url: '/pos.php', shortcut: 'Alt+P' },
            { label: '預約管理', type: 'nav', url: '/appointments.php', shortcut: 'Alt+A' },
            { label: '客戶管理', type: 'nav', url: '/customers.php', shortcut: 'Alt+C' },
            { label: '概覽首頁', type: 'nav', url: '/dashboard.php', shortcut: 'Alt+H' },
            { label: '報表', type: 'nav', url: '/reports.php', shortcut: 'Alt+R' },
            { label: '佣金查詢', type: 'nav', url: '/commissions.php', shortcut: 'Alt+M' },
            { label: '系統設定', type: 'nav', url: '/settings.php', shortcut: 'Alt+S' },
            { label: '員工管理', type: 'nav', url: '/staff.php' },
            { label: '服務項目', type: 'nav', url: '/services.php' },
            { label: '產品管理', type: 'nav', url: '/products.php' },
            { label: '套票管理', type: 'nav', url: '/packages.php' },
            { label: '房間管理', type: 'nav', url: '/rooms.php' },

            // 快速動作
            { label: '新增客戶', type: 'action', action: () => { modal.hide(); setTimeout(() => window.location.href = '/customers.php?new=1', 200); } },
            { label: '新增預約', type: 'action', action: () => { modal.hide(); setTimeout(() => window.location.href = '/appointments.php?new=1', 200); } },
            { label: '打印上一張收據 (58mm)', type: 'action', action: () => { modal.hide(); setTimeout(() => window.printLastReceipt?.('58'), 150); } },
            { label: '切換到今日報表', type: 'action', action: () => { modal.hide(); setTimeout(() => { window.location.href = '/reports.php'; /* 可擴充 setQuickRange */ }, 200); } },
        ];

        function renderResults(filter = '') {
            const q = filter.toLowerCase().trim();
            currentFiltered = commands.filter(cmd =>
                cmd.label.toLowerCase().includes(q)
            );

            if (currentFiltered.length === 0) {
                resultsContainer.innerHTML = `<div class="px-3 py-3 text-muted small">找不到符合的結果</div>`;
                selectedIndex = -1;
                return;
            }

            if (selectedIndex >= currentFiltered.length) selectedIndex = currentFiltered.length - 1;
            if (selectedIndex < 0) selectedIndex = 0;

            let html = '';
            currentFiltered.forEach((cmd, index) => {
                const isSelected = index === selectedIndex;
                html += `
                    <div class="cmd-item px-3 py-2 d-flex justify-content-between align-items-center ${isSelected ? 'bg-primary-subtle' : ''}" 
                         data-index="${index}" style="cursor: pointer;">
                        <span>${cmd.label}</span>
                        ${cmd.shortcut ? `<span class="text-muted small">${cmd.shortcut}</span>` : ''}
                    </div>
                `;
            });

            resultsContainer.innerHTML = html;

            // Click handlers
            resultsContainer.querySelectorAll('.cmd-item').forEach(item => {
                const idx = parseInt(item.dataset.index);
                item.addEventListener('click', () => {
                    executeCommand(currentFiltered[idx]);
                });
                item.addEventListener('mouseenter', () => {
                    selectedIndex = idx;
                    renderResults(input.value); // re-render to update highlight
                });
            });
        }

        function executeCommand(cmd) {
            modal.hide();
            setTimeout(() => {
                if (cmd.url) {
                    window.location.href = cmd.url;
                } else if (cmd.action) {
                    cmd.action();
                }
            }, 120);
        }

        function updateSelection() {
            const items = resultsContainer.querySelectorAll('.cmd-item');
            items.forEach((item, idx) => {
                if (idx === selectedIndex) {
                    item.classList.add('bg-primary-subtle');
                } else {
                    item.classList.remove('bg-primary-subtle');
                }
            });
        }

        // Initial render
        renderResults('');
        updateSelection();

        // Live search
        input.addEventListener('input', () => {
            selectedIndex = 0;
            renderResults(input.value);
            updateSelection();
        });

        // Full keyboard navigation
        input.addEventListener('keydown', (e) => {
            const max = currentFiltered.length - 1;

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                selectedIndex = Math.min(selectedIndex + 1, max);
                updateSelection();
            } 
            else if (e.key === 'ArrowUp') {
                e.preventDefault();
                selectedIndex = Math.max(selectedIndex - 1, 0);
                updateSelection();
            } 
            else if (e.key === 'Enter') {
                e.preventDefault();
                if (currentFiltered[selectedIndex]) {
                    executeCommand(currentFiltered[selectedIndex]);
                }
            } 
            else if (e.key === 'Escape') {
                modal.hide();
            }
        });

        // Auto focus input
        setTimeout(() => {
            input.focus();
            input.select();
        }, 80);

        // Cleanup
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
