/**
 * SalonEase - 熱鍵管理系統（核心功能）
 * 支援全域 + 頁面專屬熱鍵
 * 命令面板已移至獨立 command-palette.js
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
        page: []
    };

    // 命令面板已完全移至獨立 assets/js/command-palette.js（v3）
    function showCommandPalette() {
        if (window.showCommandPalette) {
            window.showCommandPalette();
        } else {
            console.warn('[SalonEase] command-palette.js 未載入');
        }
    }

    // 公開 API
    window.SalonEase = window.SalonEase || {};
    window.SalonEase.Hotkeys = {
        registerPage: function(keys) { registry.page = keys; },
        renderHelp: function(container) {
            let html = '<div class="space-y-6 text-sm">';
            html += '<div><div class="font-semibold mb-2 text-[#2C2C2E]">全域快捷鍵（任何頁面）</div>';
            html += '<div class="grid grid-cols-1 gap-x-6 gap-y-1">';
            registry.global.forEach(item => {
                html += `<div class="flex justify-between py-px"><span class="font-mono text-[#8FA68F]">${item.key}</span><span>${item.desc}</span></div>`;
            });
            html += '</div></div>';
            if (registry.page.length) {
                html += '<div><div class="font-semibold mb-2 text-[#2C2C2E]">本頁專屬</div><div class="grid grid-cols-1 gap-x-6 gap-y-1">';
                registry.page.forEach(item => {
                    html += `<div class="flex justify-between py-px"><span class="font-mono text-[#8FA68F]">${item.key}</span><span>${item.desc}</span></div>`;
                });
                html += '</div></div>';
            }
            html += '<div class="pt-2 text-[11px] text-[#8A8A8C]">提示：Ctrl+K 開啟強大命令面板</div>';
            html += '</div>';
            container.innerHTML = html;
        },
        getAll: () => ({ global: registry.global, page: registry.page })
    };

    // 全域 Alt 熱鍵
    document.addEventListener('keydown', function(e) {
        const tag = document.activeElement.tagName;
        if (['INPUT', 'TEXTAREA', 'SELECT'].includes(tag) && e.key !== 'Escape') return;
        if (e.altKey) {
            const map = { 'h': '/dashboard.php', 'p': '/pos.php', 'a': '/appointments.php', 'c': '/customers.php', 'r': '/reports.php', 's': '/settings.php', 'm': '/commissions.php' };
            if (map[e.key.toLowerCase()]) {
                e.preventDefault();
                location.href = map[e.key.toLowerCase()];
            }
        }
    });

    console.log('%c[SalonEase] 熱鍵系統已就緒（Ctrl+K 使用獨立命令面板）', 'color:#8FA68F;font-size:9px');
})();
