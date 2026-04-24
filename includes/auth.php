<?php
require_once __DIR__ . '/db.php';

function registerUser(string $username, string $email, string $password): array {
    $db = getDB();
    $stmt = $db->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$username, $email]);
    if ($stmt->fetch()) {
        return ['success' => false, 'message' => 'Пользователь с таким именем или email уже существует'];
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $db->prepare("INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)");
    try {
        $stmt->execute([$username, $email, $hash]);
        return ['success' => true, 'user_id' => $db->lastInsertId()];
    } catch (PDOException $e) {
        error_log("Registration failed: " . $e->getMessage());
        return ['success' => false, 'message' => 'Ошибка при создании аккаунта'];
    }
}

function loginUser(string $identifier, string $password): array {
    $db = getDB();
    $stmt = $db->prepare("SELECT id, username, email, password_hash FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$identifier, $identifier]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        return ['success' => false, 'message' => 'Неверные данные для входа'];
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['logged_in'] = true;
    $_SESSION['login_time'] = time();
    return ['success' => true];
}

function logoutUser(): void {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
    }
    session_destroy();
}

function requireLogin(): void {
    if (empty($_SESSION['logged_in']) || empty($_SESSION['user_id'])) {
        header("Location: " . APP_URL . "/login.php");
        exit;
    }
}

function getCurrentUser(): ?array {
    if (empty($_SESSION['user_id'])) return null;
    $db = getDB();
    // Добавлены поля password_hash и avatar для корректной работы профиля
    $stmt = $db->prepare("SELECT id, username, email, password_hash, avatar, created_at FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch() ?: null;
}

function saveUserTheme(string $theme): bool {
    $allowed = ['light', 'dark', 'money'];
    if (!in_array($theme, $allowed)) return false;
    $_SESSION['theme'] = $theme;
    return true;
}

function getUserTheme(): string {
    return $_SESSION['theme'] ?? 'light';
}