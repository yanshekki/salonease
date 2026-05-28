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
                <div class="modal-dialog modal-dialog-centered" style="max-width: 480px;">
                    <div class="modal-content">
                        <div class="modal-header py-2 px-3 border-bottom-0">
                            <input type="text" id="cmd-input" 
                                   class="form-control form-control-sm border-0 shadow-none" 
                                   placeholder="搜尋頁面或功能... (例如: pos, 客戶, 報表)" 
                                   style="font-size: 15px;">
                        </div>
                        <div class="modal-body p-0" id="cmd-results" style="max-height: 320px; overflow-y: auto;">
                            <!-- Results populated by JS -->
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
        const modal = new bootstrap.Modal(modalEl, { backdrop: true, keyboard: true });
        modal.show();

        const input = document.getElementById('cmd-input');
        const resultsContainer = document.getElementById('cmd-results');

        const commands = [
            { label: 'POS 銷售', url: '/pos.php', shortcut: 'Alt+P' },
            { label: '預約管理', url: '/appointments.php', shortcut: 'Alt+A' },
            { label: '客戶管理', url: '/customers.php', shortcut: 'Alt+C' },
            { label: '概覽首頁', url: '/dashboard.php', shortcut: 'Alt+H' },
            { label: '報表', url: '/reports.php', shortcut: 'Alt+R' },
            { label: '佣金查詢', url: '/commissions.php', shortcut: 'Alt+M' },
            { label: '系統設定', url: '/settings.php', shortcut: 'Alt+S' },
            { label: '員工管理', url: '/staff.php' },
            { label: '服務項目管理', url: '/services.php' },
            { label: '產品管理', url: '/products.php' },
            { label: '套票管理', url: '/packages.php' },
            { label: '房間管理', url: '/rooms.php' },
        ];

        function renderResults(filter = '') {
            const q = filter.toLowerCase().trim();
            let html = '';

            const filtered = commands.filter(cmd => 
                cmd.label.toLowerCase().includes(q) || 
                (cmd.shortcut && cmd.shortcut.toLowerCase().includes(q))
            );

            if (filtered.length === 0) {
                html = `<div class="px-3 py-3 text-muted small">找不到符合的結果</div>`;
            } else {
                filtered.forEach((cmd, index) => {
                    html += `
                        <div class="cmd-item px-3 py-2 d-flex justify-content-between align-items-center ${index === 0 ? 'bg-light' : ''}" 
                             data-url="${cmd.url}" style="cursor: pointer;">
                            <span>${cmd.label}</span>
                            ${cmd.shortcut ? `<span class="text-muted small">${cmd.shortcut}</span>` : ''}
                        </div>
                    `;
                });
            }

            resultsContainer.innerHTML = html;

            // Add click handlers
            resultsContainer.querySelectorAll('.cmd-item').forEach(item => {
                item.addEventListener('click', () => {
                    modal.hide();
                    setTimeout(() => {
                        window.location.href = item.dataset.url;
                    }, 150);
                });
            });
        }

        // Initial render
        renderResults('');

        // Live search
        input.addEventListener('input', () => {
            renderResults(input.value);
        });

        // Keyboard navigation (basic)
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                const first = resultsContainer.querySelector('.cmd-item');
                if (first) {
                    modal.hide();
                    setTimeout(() => {
                        window.location.href = first.dataset.url;
                    }, 150);
                }
            }
            if (e.key === 'Escape') {
                modal.hide();
            }
        });

        // Auto focus
        setTimeout(() => input.focus(), 100);

        // Clean up when modal hides
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
