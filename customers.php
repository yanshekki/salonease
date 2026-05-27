<?php require_once __DIR__ . '/includes/auth.php'; require_login(); $pageTitle = '客戶管理'; include __DIR__ . '/includes/header.php'; ?>
<div class="max-w-2xl mx-auto mt-12 text-center">
    <div class="text-6xl mb-4">👥</div>
    <h2 class="text-2xl font-semibold mb-2">客戶管理</h2>
    <p class="text-[#5A5A5C]">Phase 1 即將實作完整 CRUD 與套票查詢。</p>
</div>
<?php $extraJs = 'hotkeys.js'; include __DIR__ . '/includes/footer.php'; ?>
