<?php require_once __DIR__ . '/includes/auth.php'; require_login(); $pageTitle = 'POS 銷售'; include __DIR__ . '/includes/header.php'; ?>
<div class="max-w-2xl mx-auto mt-12 text-center">
    <div class="text-6xl mb-4">🛒</div>
    <h2 class="text-2xl font-semibold mb-2">POS 銷售模組</h2>
    <p class="text-[#5A5A5C]">Phase 3 即將實作。現已支援完整熱鍵框架。</p>
    <div class="mt-6 text-xs text-[#8A8A8C]">按 <span class="font-semibold">?</span> 查看目前可用的全域快捷鍵</div>
</div>
<?php $extraJs = 'hotkeys.js'; include __DIR__ . '/includes/footer.php'; ?>
