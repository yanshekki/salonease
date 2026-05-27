<?php
/**
 * SalonEase - 共用頁尾
 * 包含全域熱鍵提示列 + 版權
 */
?>
    </div><!-- /.max-w-screen-2xl -->

    <!-- 全域熱鍵提示列（手機版極致精簡） -->
    <div class="fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 z-[60] shadow-[0_-4px_6px_-1px_rgb(0,0,0,0.03)]">
        <div class="max-w-screen-2xl mx-auto px-3 sm:px-6 h-9 sm:h-10 flex items-center justify-between text-xs text-[#5A5A5C]">
            <!-- 手機版：只顯示大按鈕 -->
            <div class="flex items-center">
                <button onclick="showHotkeyHelp()" 
                        class="flex items-center justify-center px-4 py-1.5 rounded-lg bg-gray-100 hover:bg-gray-200 font-medium text-[#2C2C2E] active:scale-[0.985] transition text-sm min-h-[36px]">
                    <span class="font-mono text-base mr-1">?</span>
                    <span>快捷鍵</span>
                </button>
            </div>

            <!-- 平板以上才顯示詳細熱鍵 -->
            <div class="hidden md:flex items-center gap-x-3 text-[10px]">
                <span><span class="font-semibold">Ctrl+K</span> 命令面板</span>
                <span class="text-gray-300">|</span>
                <span><span class="font-semibold">Esc</span> 關閉</span>
            </div>
            
            <div class="text-right text-[10px] sm:text-xs">
                <span class="hidden sm:inline">SalonEase v<?= e(APP_VERSION ?? '0.1') ?> · </span>
                <span class="text-[#8A8A8C]">專業 · 簡單 · 高效</span>
            </div>
        </div>
    </div>

    <!-- 熱鍵幫助 Modal（手機優化版） -->
    <div id="hotkey-modal" class="hidden fixed inset-0 bg-black/40 z-[70] flex items-center justify-center p-2 sm:p-4" onclick="hideHotkeyHelp()">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg mx-2 sm:mx-4 overflow-hidden max-h-[85vh] flex flex-col" onclick="event.stopImmediatePropagation()">
            <div class="px-4 sm:px-5 py-3 sm:py-4 border-b flex items-center justify-between flex-shrink-0">
                <div class="font-semibold text-lg">快捷鍵說明</div>
                <button onclick="hideHotkeyHelp()" class="text-3xl leading-none text-gray-400 hover:text-gray-600 px-2 -mr-2">×</button>
            </div>
            <div class="p-4 sm:p-5 text-sm overflow-auto flex-1" id="hotkey-content">
                <!-- 由 assets/js/hotkeys.js 動態填入 -->
                <p class="text-[#5A5A5C]">正在載入快捷鍵清單...</p>
            </div>
            <div class="bg-gray-50 px-4 sm:px-5 py-3 text-xs text-[#8A8A8C] flex flex-col sm:flex-row sm:justify-between gap-1 flex-shrink-0">
                <div>所有頁面皆支援 Esc 與 ?</div>
                <div>按 <span class="font-mono font-medium">Esc</span> 關閉</div>
            </div>
        </div>
    </div>

    <!-- 共用 JS -->
    <script src="/assets/js/app.js"></script>
    <?php if (isset($extraJs) && $extraJs): ?>
        <script src="/assets/js/<?= e($extraJs) ?>"></script>
    <?php endif; ?>
</body>
</html>
