<?php
require_once 'includes/auth.php';

if (!empty($_SESSION['logged_in'])) {
    header("Location: dashboard.php");
    exit;
}

$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';

    if (!$username || !$email || !$password) {
        $error = 'Заполните все обязательные поля';
    } elseif ($password !== $password2) {
        $error = 'Пароли не совпадают';
    } elseif (strlen($password) < 6) {
        $error = 'Пароль должен содержать минимум 6 символов';
    } else {
        $result = registerUser($username, $email, $password);
        if ($result['success']) {
            $success = 'Аккаунт создан! Перенаправляем на вход...';
            header("Refresh: 2; url=login.php");
        } else {
            $error = $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Регистрация | <?= APP_NAME ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="auth-page">
    <main class="auth-container">
        <div class="auth-box">
            <h1>Регистрация</h1>

            <?php if ($error): ?>
                <div class="alert error"><?= htmlspecialchars($error) ?></div>
            <?php elseif ($success): ?>
                <div class="alert success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="username">Имя пользователя</label>
                    <input type="text" id="username" name="username" required minlength="3">
                </div>
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required>
                </div>
                <div class="form-group">
                    <label for="password">Пароль</label>
                    <input type="password" id="password" name="password" required minlength="6">
                </div>
                <div class="form-group">
                    <label for="password2">Повторите пароль</label>
                    <input type="password" id="password2" name="password2" required>
                </div>
                <button type="submit" class="btn primary">Зарегистрироваться</button>
            </form>

            <div class="auth-links">
                <a href="login.php">Уже есть аккаунт? Войти</a>
            </div>
        </div>
    </main>
</body>
</html>