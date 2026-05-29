# SalonEase API 測試系統 - 執行指南（專業級）

## 推薦伺服器驗證流程（上傳後一鍵執行）

```bash
# 1. 上傳整個 tests/ 目錄到 https://salonease.ysk.hk/tests/

# 2. SSH 進入專案根目錄
cd /var/www/salonease   # 請改成實際路徑

# 3. 一鍵完整驗證（強烈推薦）
php tests/run_full_verification.php
```

此指令會：
- 自動準備豐富測試資料（seed）
- 執行所有測試
- 產生專業 HTML 報告
- 輸出清晰總結

## 其他常用指令

```bash
# 只執行最高優先的佣金計算測試
php tests/api/test_sales_checkout_commission.php

# 執行特定模組 + HTML 報告
php tests/run_tests.php --api=commission --report=html

# 帶 seed 執行（如果想手動控制）
php tests/run_tests.php --seed --report=html
```

## 驗證重點（務必檢查）

1. 打開 `tests/reports/` 下的最新 HTML 報告
2. **特別關注** 紅色失敗項目，尤其是：
   - 佣金計算專項測試（含 E2E 費率變更）
   - 整合測試（積分扣減、完整付款計劃流程）
3. 確認所有自動驗證（assertCommissionEqual）全部通過

## 目前已完成

- ✅ 佣金計算專項測試矩陣 C001–C011（純函數 + 真實自動驗證）
- ✅ 跨模組 E2E（設定變更 → 結帳 → 佣金驗證）
- ✅ 付款、付款計劃、員工、提醒、設定、客戶、產品、銷售查詢、Auth
- ✅ 4 角色權限矩陣
- ✅ 專業 HTML/JSON 報告 + Bootstrap 環境初始化
- ✅ 豐富種子資料（支援多數自動驗證真正跑起來）

## 注意事項

- 所有測試只在真實路徑開發
- 嚴格遵守：本地 php -l → commit & push → server-only 驗證 → merge --no-ff
- 佣金計算是最高風險領域，已有最強保護

如有任何失敗，請把報告關鍵部分貼給我，我會立即處理。
