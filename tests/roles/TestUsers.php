<?php
/**
 * SalonEase API 測試系統 - 測試用戶角色定義
 * 
 * 定義四種不同權限的測試帳號，用於權限控制測試。
 * 這些帳號必須事先存在於測試資料庫中（可透過 fixtures/seed_test_data.php 建立）。
 */

class TestUsers
{
    /**
     * 測試用戶配置
     * 格式：['email' => '...', 'password' => '...', 'role' => '...', 'name' => '...']
     */
    private static array $users = [
        'admin' => [
            'email'    => 'admin@salonease.test',
            'password' => 'TestAdmin123!',
            'role'     => 'admin',
            'name'     => '測試管理員',
        ],
        'manager' => [
            'email'    => 'manager@salonease.test',
            'password' => 'TestManager123!',
            'role'     => 'manager',
            'name'     => '測試店長',
        ],
        'therapist' => [
            'email'    => 'therapist@salonease.test',
            'password' => 'TestTherapist123!',
            'role'     => 'therapist',
            'name'     => '測試治療師',
        ],
        'reception' => [
            'email'    => 'reception@salonease.test',
            'password' => 'TestReception123!',
            'role'     => 'reception',
            'name'     => '測試前台',
        ],
    ];

    /**
     * 取得指定角色的測試用戶資訊
     */
    public static function get(string $role): array
    {
        if (!isset(self::$users[$role])) {
            throw new Exception("未定義的測試角色：{$role}");
        }
        return self::$users[$role];
    }

    /**
     * 取得所有測試角色列表
     */
    public static function all(): array
    {
        return self::$users;
    }

    /**
     * 取得所有角色名稱
     */
    public static function roles(): array
    {
        return array_keys(self::$users);
    }
}
