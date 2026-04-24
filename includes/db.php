<?php
require_once __DIR__ . '/config.php';

/**
 * Класс-обёртка для безопасной работы с базой данных
 */
class Database {
    private static ?PDO $instance = null;
    private PDO $connection;

    private function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
            ];

            $this->connection = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log("DB Connection Error: " . $e->getMessage());
            die("⚠️ Ошибка подключения к базе данных. Проверьте настройки XAMPP.");
        }
    }

    public static function getInstance(): PDO {
        if (self::$instance === null) {
            $db = new self();
            self::$instance = $db->connection;
        }
        return self::$instance;
    }

    private function __clone() {}
    public function __wakeup(): void {
        throw new Exception("Singleton unserialization disabled");
    }
}

// Глобальная функция для быстрого доступа
function getDB(): PDO {
    return Database::getInstance();
}