<?php
require_once 'includes/auth.php';

// Если пользователь уже авторизован, перенаправляем на дашборд
if (!empty($_SESSION['logged_in'])) {
    header("Location: dashboard.php");
    exit;
}

$error = '';

// Обработка формы входа
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim($_POST['identifier'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($identifier && $password) {
        // Функция проверки логина/пароля из auth.php
        $result = loginUser($identifier, $password);

        if ($result['success']) {
            header("Location: dashboard.php");
            exit;
        } else {
            $error = $result['message'] ?? 'Неверный логин или пароль';
        }
    } else {
        $error = 'Заполните все поля';
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вход | BudgetTracker</title>
    <link rel="icon" href="assets/img/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="auth-page">
    <main class="auth-container">
        <div class="auth-box">
            <h1><span>💰</span> BudgetTracker</h1>
            <p class="subtitle">Вход в систему</p>

            <?php if ($error): ?>
                <div class="alert error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="identifier">Email или имя пользователя</label>
                    <input type="text" id="identifier" name="identifier" required autocomplete="username">
                </div>
                <div class="form-group">
                    <label for="password">Пароль</label>
                    <input type="password" id="password" name="password" required autocomplete="current-password">
                </div>
                <button type="submit" class="btn btn-primary">Войти</button>
            </form>

            <div class="auth-links">
                <a href="register.php">Нет аккаунта? Зарегистрироваться</a>
            </div>
        </div>
    </main>
</body>
</html>