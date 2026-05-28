<?php
/**
 * SalonEase - 資料庫存取層（純 PDO）
 * 單例模式 + 交易支援 + 常用 helper
 * 嚴禁直接使用全域 $pdo，請一律透過本檔函式
 */

require_once __DIR__ . '/config.php';

class Database
{
    private static ?PDO $pdo = null;

    /**
     * 取得 PDO 單例連線
     */
    public static function getConnection(): PDO
    {
        if (self::$pdo === null) {
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET . " COLLATE " . DB_CHARSET . "_unicode_ci",
            ];

            self::$pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        }
        return self::$pdo;
    }

    /**
     * 執行查詢（SELECT）
     */
    public static function query(string $sql, array $params = []): array
    {
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * 執行單一查詢並取第一列（常用於 get by id）
     */
    public static function queryOne(string $sql, array $params = []): ?array
    {
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * 執行寫入 / 更新 / 刪除
     * 回傳影響行數
     */
    public static function execute(string $sql, array $params = []): int
    {
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    /**
     * 取得最後插入的 ID
     */
    public static function lastInsertId(): string
    {
        return self::getConnection()->lastInsertId();
    }

    /**
     * 安全執行交易
     * 用法：
     *   $result = Database::transaction(function (PDO $pdo) {
     *       // 多個寫入操作
     *       return $someId;
     *   });
     */
    public static function transaction(callable $callback)
    {
        $pdo = self::getConnection();
        $pdo->beginTransaction();
        try {
            $result = $callback($pdo);
            $pdo->commit();
            return $result;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * 關閉連線（測試或特殊情境用）
     */
    public static function close(): void
    {
        self::$pdo = null;
    }
}

/**
 * 全域快捷函式（可選，方便其他檔案呼叫）
 */
function db_query(string $sql, array $params = []): array
{
    return Database::query($sql, $params);
}

function db_query_one(string $sql, array $params = []): ?array
{
    return Database::queryOne($sql, $params);
}

function db_exec(string $sql, array $params = []): int
{
    return Database::execute($sql, $params);
}

function db_last_id(): string
{
    return Database::lastInsertId();
}

function db_transaction(callable $callback)
{
    return Database::transaction($callback);
}

/**
 * 取得 PDO 連線實例（最常用快捷方式）
 * 用法：$pdo = db();
 * 
 * 與 db_query / db_query_one 等 helper 配合使用
 */
function db(): PDO
{
    return Database::getConnection();
}
