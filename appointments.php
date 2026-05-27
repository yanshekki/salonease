<?php require_once __DIR__ . '/includes/auth.php'; require_login(); $pageTitle = '預約管理'; include __DIR__ . '/includes/header.php'; ?>
<div class="max-w-2xl mx-auto mt-12 text-center">
    <div class="text-6xl mb-4">📅</div>
    <h2 class="text-2xl font-semibold mb-2">預約管理</h2>
    <p class="text-[#5A5A5C]">Phase 2 開發中。支援時間衝突檢查與熱鍵操作。</p>
</div>
<?php $extraJs = 'hotkeys.js'; include __DIR__ . '/includes/footer.php'; ?>
