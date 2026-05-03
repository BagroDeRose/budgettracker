<?php
require_once 'includes/auth.php';
requireLogin();

$user = getCurrentUser();
$db = getDB();
$user_id = $user['id'];
$theme = $_SESSION['theme'] ?? 'light';
$avatar = $user['avatar'] ?? 'assets/img/default-avatar.png';

$success_msg = '';
$error_msg = '';

// Обработка загрузки аватара
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_avatar'])) {
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (in_array($_FILES['avatar']['type'], $allowed)) {
            $upload_dir = 'assets/avatars/';
            if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);

            $ext = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
            $new_name = 'user_' . $user_id . '_' . time() . '.' . $ext;
            $path = $upload_dir . $new_name;

            if (move_uploaded_file($_FILES['avatar']['tmp_name'], $path)) {
                if (!empty($user['avatar']) && file_exists($user['avatar'])) {
                    unlink($user['avatar']);
                }
                $stmt = $db->prepare("UPDATE users SET avatar = ? WHERE id = ?");
                $stmt->execute([$path, $user_id]);
                $success_msg = 'Аватар успешно обновлен';
                $user = getCurrentUser();
                $avatar = $user['avatar'];
            } else {
                $error_msg = 'Ошибка при сохранении файла';
            }
        } else {
            $error_msg = 'Недопустимый формат изображения';
        }
    }
}

// Обработка смены Email
if (isset($_POST['change_email'])) {
    $new_email = trim($_POST['new_email'] ?? '');
    $password = $_POST['password_email'] ?? '';

    if (empty($new_email) || empty($password)) {
        $error_msg = 'Заполните все поля для смены Email';
    } elseif (!password_verify($password, $user['password_hash'])) {
        $error_msg = 'Неверный текущий пароль';
    } else {
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$new_email, $user_id]);
        if ($stmt->fetch()) {
            $error_msg = 'Этот Email уже занят';
        } else {
            $stmt = $db->prepare("UPDATE users SET email = ? WHERE id = ?");
            $stmt->execute([$new_email, $user_id]);
            $success_msg = 'Email успешно изменен';
            $user = getCurrentUser();
        }
    }
}

// Обработка смены пароля
if (isset($_POST['change_password'])) {
    $old_pass = $_POST['old_password'] ?? '';
    $new_pass = $_POST['new_password'] ?? '';
    $confirm_pass = $_POST['confirm_password'] ?? '';

    if (empty($old_pass) || empty($new_pass) || empty($confirm_pass)) {
        $error_msg = 'Заполните все поля паролей';
    } elseif (!password_verify($old_pass, $user['password_hash'])) {
        $error_msg = 'Неверный текущий пароль';
    } elseif ($new_pass !== $confirm_pass) {
        $error_msg = 'Новые пароли не совпадают';
    } elseif (strlen($new_pass) < 6) {
        $error_msg = 'Пароль должен содержать минимум 6 символов';
    } else {
        $hash = password_hash($new_pass, PASSWORD_DEFAULT);
        $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $stmt->execute([$hash, $user_id]);
        $success_msg = 'Пароль успешно изменен';
    }
}
?>
<!DOCTYPE html>
<html lang="ru" data-theme="<?= htmlspecialchars($theme) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Профиль | BudgetTracker</title>
    <link rel="icon" href="assets/img/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <!-- Шапка сайта -->
    <header class="app-header">
        <div class="container header-content">
            <div class="logo">
                <a href="dashboard.php"><span>💰</span> BudgetTracker</a>
            </div>
            <nav class="main-nav">
                <a href="categories.php" class="nav-link">📁 Категории</a>
                <a href="profile.php" class="nav-link">⚙️ Профиль</a>
            </nav>
            <div class="user-nav">
                <a href="profile.php" class="user-profile-link">
                    <span class="user-avatar">👤</span>
                    <span class="username"><?= htmlspecialchars($user['username']) ?></span>
                </a>
                <a href="logout.php" class="btn btn-logout">🚪 Выйти</a>
            </div>
        </div>
    </header>

    <!-- Основной контент: настройки профиля -->
    <main class="container profile-page">
        <h1>⚙️ Настройки профиля</h1>

        <?php if ($error_msg): ?>
            <div class="alert error"><?= htmlspecialchars($error_msg) ?></div>
        <?php endif; ?>
        <?php if ($success_msg): ?>
            <div class="alert success"><?= htmlspecialchars($success_msg) ?></div>
        <?php endif; ?>

        <div class="profile-grid">
            <!-- Блок аватара -->
            <section class="card profile-avatar-section">
                <h2>🖼️ Аватар</h2>
                <div class="avatar-preview">
                    <img src="<?= htmlspecialchars($avatar) ?>" alt="Аватар">
                </div>
                <form method="POST" enctype="multipart/form-data" class="avatar-form">
                    <input type="file" name="avatar" accept="image/*" required>
                    <button type="submit" name="upload_avatar" class="btn btn-primary">📤 Загрузить</button>
                </form>
            </section>

            <!-- Информация о пользователе -->
            <section class="card">
                <h2>ℹ️ Информация</h2>
                <table class="info-table">
                    <tr>
                        <td>Имя пользователя:</td>
                        <td><strong><?= htmlspecialchars($user['username']) ?></strong></td>
                    </tr>
                    <tr>
                        <td>Дата регистрации:</td>
                        <td><?= date('d.m.Y', strtotime($user['created_at'])) ?></td>
                    </tr>
                    <tr>
                        <td>Последний вход:</td>
                        <td><?= isset($_SESSION['login_time']) ? date('d.m.Y H:i', $_SESSION['login_time']) : '-' ?></td>
                    </tr>
                </table>
            </section>

            <!-- Смена Email -->
            <section class="card">
                <h2>📧 Сменить Email</h2>
                <form method="POST">
                    <div class="form-group">
                        <label>Текущий Email</label>
                        <input type="email" value="<?= htmlspecialchars($user['email']) ?>" disabled>
                    </div>
                    <div class="form-group">
                        <label>Новый Email</label>
                        <input type="email" name="new_email" required>
                    </div>
                    <div class="form-group">
                        <label>Подтверждение паролем</label>
                        <input type="password" name="password_email" required>
                    </div>
                    <button type="submit" name="change_email" class="btn btn-primary">💾 Сохранить</button>
                </form>
            </section>

            <!-- Смена пароля -->
            <section class="card">
                <h2>🔑 Сменить пароль</h2>
                <form method="POST">
                    <div class="form-group">
                        <label>Текущий пароль</label>
                        <input type="password" name="old_password" required>
                    </div>
                    <div class="form-group">
                        <label>Новый пароль</label>
                        <input type="password" name="new_password" minlength="6" required>
                    </div>
                    <div class="form-group">
                        <label>Повторите новый пароль</label>
                        <input type="password" name="confirm_password" minlength="6" required>
                    </div>
                    <button type="submit" name="change_password" class="btn btn-primary">🔐 Обновить пароль</button>
                </form>
            </section>
        </div>
    </main>

    <!-- Подвал сайта -->
    <footer class="app-footer">
        <div class="container">
            <div class="footer-grid">
                <div class="footer-col">
                    <h4><span>💰</span> BudgetTracker</h4>
                    <p>Система учета личных финансов</p>
                    <p class="copyright">© 2026 Все права защищены</p>
                </div>

                <div class="footer-col">
                    <h4>📍 Контакты</h4>
                    <ul class="footer-contacts">
                        <li>🏢 г. Казань, ул. Примерная, д. 123, оф. 456</li>
                        <li>📞 <a href="tel:+77777777777">+7 (777) 777-77-77</a></li>
                        <li>✉️ <a href="mailto:example@gmail.com">example@gmail.com</a></li>
                    </ul>
                </div>

                <div class="footer-col">
                    <h4>ℹ️ Информация</h4>
                    <ul class="footer-links">
                        <li><a href="#">Политика конфиденциальности</a></li>
                        <li><a href="#">Условия использования</a></li>
                        <li><a href="#">Помощь и поддержка</a></li>
                    </ul>
                </div>

                <div class="footer-col">
                    <h4>🎨 Тема оформления</h4>
                    <div class="theme-switcher">
                        <button class="theme-btn active" data-theme="light" title="Светлая тема">☀️</button>
                        <button class="theme-btn" data-theme="dark" title="Темная тема">🌙</button>
                        <button class="theme-btn" data-theme="money" title="Денежная тема">💵</button>
                    </div>
                </div>
            </div>

            <div class="footer-bottom">
                <p>ООО "ФинТех Решения" | ИНН 1234567890 | КПП 987654321</p>
            </div>
        </div>
    </footer>

    <script src="assets/js/theme.js"></script>
</body>
</html>