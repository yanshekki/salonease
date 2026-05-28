#!/bin/bash
#
# SalonEase Phase 1 驗證輔助腳本
# 使用方法：
#   chmod +x docs/verify-phase1.sh
#   ./docs/verify-phase1.sh
#
# 這個腳本會幫你執行一些自動可檢查的項目，
# 手動測試部分（管理頁面操作、upgrade.php）仍需你親自進行。
#

set -e

echo "========================================"
echo "  SalonEase Phase 1 驗證輔助腳本"
echo "  目前目錄: $(pwd)"
echo "  分支: $(git branch --show-current)"
echo "========================================"
echo ""

# 1. 確認關鍵檔案存在
echo "【1/6】檢查 Phase 1 關鍵檔案是否存在..."
FILES=(
    "migrations/009_add_payment_methods_table.php"
    "api/payment_methods.php"
    "payment_methods.php"
    "docs/PHASE_MULTI_PAYMENT_1.md"
)

MISSING=0
for f in "${FILES[@]}"; do
    if [ -f "$f" ]; then
        echo "  ✓ $f"
    else
        echo "  ✗ 缺少: $f"
        MISSING=1
    fi
done

if [ $MISSING -eq 1 ]; then
    echo "錯誤：有檔案缺失，請確認檔案已正確複製。"
    exit 1
fi
echo ""

# 2. PHP 語法檢查
echo "【2/6】執行 PHP 語法檢查 (php -l)..."
php -l migrations/009_add_payment_methods_table.php
php -l api/payment_methods.php
php -l payment_methods.php
echo "  ✓ 所有新 PHP 檔案語法正確"
echo ""

# 3. 確認 migration 檔案內容
echo "【3/6】快速檢查 migration 009 內容..."
if grep -q "payment_methods" migrations/009_add_payment_methods_table.php; then
    echo "  ✓ Migration 包含 payment_methods 表建立"
else
    echo "  ✗ Migration 內容異常"
    exit 1
fi
echo ""

# 4. 確認 API 有主要 action
echo "【4/6】檢查 API 是否包含必要 action..."
if grep -q "case 'list':" api/payment_methods.php && \
   grep -q "case 'create':" api/payment_methods.php && \
   grep -q "case 'calculate_fee':" api/payment_methods.php; then
    echo "  ✓ API 包含 list、create、calculate_fee 等主要功能"
else
    echo "  ✗ API 缺少關鍵 action"
    exit 1
fi
echo ""

# 5. 確認管理頁面有前端計算器
echo "【5/6】檢查管理頁面是否包含即時試算器..."
if grep -q "updateFeeCalculator" payment_methods.php; then
    echo "  ✓ 管理頁面包含手續費即時試算器"
else
    echo "  ✗ 管理頁面缺少試算器功能"
    exit 1
fi
echo ""

# 6. 目前 Git 狀態摘要
echo "【6/6】目前 Git 狀態摘要："
git status --short | head -15
echo ""

echo "========================================"
echo "  自動檢查完成！"
echo ""
echo "  接下來請手動執行以下關鍵驗證（見 docs/PHASE_MULTI_PAYMENT_1.md）："
echo ""
echo "  1. 備份資料庫"
echo "  2. 透過 /upgrade.php 執行 Migration 009"
echo "  3. 登入後台 → 系統設定 → 點擊「付款方法管理」"
echo "  4. 完整測試新增、編輯、排序、即時試算、啟用/停用、刪除保護"
echo "  5. 確認 POS、報表、收據等現有功能完全正常"
echo ""
echo "  驗證通過後，請直接告訴我："
echo "    「Phase 1 驗證全部通過」"
echo "========================================"