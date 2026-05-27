<?php require_once __DIR__ . '/includes/auth.php'; require_login(); $pageTitle = '報表'; include __DIR__ . '/includes/header.php'; ?>
<div class="max-w-2xl mx-auto mt-12 text-center">
    <div class="text-6xl mb-4">📊</div>
    <h2 class="text-2xl font-semibold mb-2">營業與佣金報表</h2>
    <p class="text-[#5A5A5C]">Phase 4 實作。將提供按員工、日期的詳細佣金計算。</p>
</div>
<?php $extraJs = 'hotkeys.js'; include __DIR__ . '/includes/footer.php'; ?>
