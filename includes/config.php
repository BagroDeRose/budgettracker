<?php
// === КОНФИГУРАЦИЯ БЮДЖЕТ-ТРЕКЕРА ===
define('APP_NAME', 'BudgetTracker');
define('APP_VERSION', '1.0.0');
define('APP_URL', 'http://localhost/budgettracker');

// Параметры подключения к БД (XAMPP по умолчанию)
define('DB_HOST', 'localhost');
define('DB_NAME', 'budgettracker');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Настройки безопасности сессии
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 0); // 1 если HTTPS
ini_set('session.use_strict_mode', 1);
session_start();

// Временная зона и формат даты
date_default_timezone_set('Europe/Moscow');
define('DATE_FORMAT', 'd.m.Y');

// Отображение ошибок только в режиме разработки
if (getenv('APP_ENV') === 'production') {
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}