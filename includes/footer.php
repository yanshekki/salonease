<?php
/**
 * SalonEase - 共用頁尾
 * 包含全域熱鍵提示列 + 版權
 */
?>
    </div><!-- /.max-w-screen-2xl -->

    <!-- 全域熱鍵提示列（Bootstrap 版本） -->
    <div class="fixed-bottom bg-white border-top shadow-sm" style="z-index: 1040;">
        <div class="container-fluid d-flex align-items-center justify-content-between px-3" style="height: 42px; max-width: 1400px; margin: 0 auto;">
            <div class="d-flex align-items-center">
                <button onclick="showHotkeyHelp()" 
                        class="btn btn-light btn-sm d-flex align-items-center gap-1 px-3 py-1">
                    <span class="fw-bold">?</span>
                    <span class="d-none d-sm-inline">快捷鍵</span>
                </button>
            </div>

            <div class="d-none d-md-flex align-items-center gap-3 small text-muted">
                <span><strong>Ctrl+K</strong> 命令面板</span>
                <span class="text-secondary">|</span>
                <span><strong>Esc</strong> 關閉</span>
            </div>
            
            <div class="small text-muted text-end">
                <span class="d-none d-sm-inline">SalonEase v<?= e(APP_VERSION ?? '0.1') ?> · </span>
                專業 · 簡單 · 高效
            </div>
        </div>
    </div>

    <!-- 熱鍵幫助 Modal（Bootstrap Modal） -->
    <div class="modal fade" id="hotkeyModal" tabindex="-1" aria-labelledby="hotkeyModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="hotkeyModalLabel">快捷鍵說明</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="hotkey-content">
                    <p class="text-muted">正在載入快捷鍵清單...</p>
                </div>
                <div class="modal-footer">
                    <small class="text-muted">所有頁面皆支援 Esc 與 ?</small>
                </div>
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
