# SalonEase API 測試系統 - 執行指南

## 快速開始（伺服器端）

```bash
# 1. 先上傳整個 tests/ 目錄到 https://salonease.ysk.hk/tests/

# 2. SSH 登入伺服器，進入專案根目錄
cd /var/www/salonease   # 或實際路徑

# 3. 建立測試資料（強烈建議先執行）
php tests/fixtures/seed_test_data.php

# 4. 執行最高優先的佣金計算測試
php tests/api/test_sales_checkout_commission.php

# 5. 或使用主控程式（未來支援更多過濾）
php tests/run_tests.php --api=sales
```

## 目前已完成

- ✅ 佣金計算專項測試矩陣 C001–C011（純函數規格 + bccomp 精準斷言）
- ✅ `calculateExpectedCommissions()` 完全複製生產邏輯
- ✅ `assertCommissionEqual()` 使用 bccomp 避免浮點誤差
- ✅ 種子腳本（建立 4 角色測試帳號 + 設定已知佣金率）
- ✅ 真實 API 煙霧測試（登入 + 結帳 + 印出唯一 notes 供人工交叉驗證）

## 驗證方法

1. 執行測試後，複製輸出的 `API_TEST_COMMISSION_xxxx` notes
2. 前往 https://salonease.ysk.hk/commissions.php
3. 篩選今日 + 相關員工
4. 對照終端機印出的「純函數預期值」檢查是否完全一致

全部一致才算通過，可進行 --no-ff merge。

## 下一步

- 補充其他 API 測試檔案（payments, payment_plans, staff 等）
- 加強 commissions API 支援 sale_id 過濾，讓整合測試可全自動斷言
- 產生 HTML/JSON 報告

嚴格遵守：本地 php -l → commit & push → server-only 驗證 → merge --no-ff
