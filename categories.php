<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
requireLogin();

$user = getCurrentUser();
$db = getDB();
$user_id = $user['id'];
$theme = $_SESSION['theme'] ?? 'light';

// Добавление новой категории
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_category'])) {
    $name = trim($_POST['name'] ?? '');
    $type = $_POST['type'] ?? '';

    if (!empty($name) && in_array($type, ['income', 'expense'])) {
        $stmt = $db->prepare("INSERT INTO categories (name, type, user_id) VALUES (?, ?, ?)");
        $stmt->execute([$name, $type, $user_id]);
        header("Location: categories.php");
        exit;
    }
}

// Удаление категории (срабатывает после подтверждения в модальном окне)
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $db->prepare("DELETE FROM categories WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $user_id]);
    header("Location: categories.php");
    exit;
}

// Получение списка категорий
$stmt = $db->prepare("SELECT * FROM categories WHERE user_id = ? ORDER BY type, name");
$stmt->execute([$user_id]);
$categories = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ru" data-theme="<?= htmlspecialchars($theme) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Категории | BudgetTracker</title>
    <link rel="icon" href="assets/img/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Дополнительные стили для этого окна -->
    <style>
        .delete-icon-btn {
            background: none;
            border: none;
            cursor: pointer;
            color: var(--danger);
            font-size: 1.2rem;
            transition: 0.2s;
            padding: 5px;
        }
        .delete-icon-btn:hover {
            transform: scale(1.2);
            color: #ff6b6b;
        }
        /* Стили для модального окна подтверждения */
        .confirm-content {
            text-align: center;
            padding: 2rem;
        }
        .confirm-content h3 {
            color: var(--danger);
            margin-bottom: 1rem;
        }
        .confirm-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 1.5rem;
        }
    </style>
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

    <!-- Основной контент -->
    <main class="container dashboard">
        <h1>📁 Управление категориями</h1>

        <!-- Форма добавления -->
        <section class="card" style="margin-bottom: 2rem;">
            <h2>➕ Добавить категорию</h2>
            <form method="POST" class="category-form">
                <div class="form-row">
                    <div class="form-group">
                        <label>Название категории</label>
                        <input type="text" name="name" required placeholder="Например: Продукты">
                    </div>
                    <div class="form-group">
                        <label>Тип категории</label>
                        <select name="type" required>
                            <option value="income">💰 Доход</option>
                            <option value="expense">💸 Расход</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>&nbsp;</label>
                        <button type="submit" name="add_category" class="btn btn-primary">➕ Добавить</button>
                    </div>
                </div>
            </form>
        </section>

        <!-- Список категорий -->
        <section class="card">
            <h2>📋 Список категорий</h2>
            <?php if ($categories): ?>
                <div class="categories-grid">
                    <!-- Доходы -->
                    <div class="category-section">
                        <h3>💰 Доходы</h3>
                        <div class="category-list">
                            <?php foreach ($categories as $cat): ?>
                                <?php if ($cat['type'] === 'income'): ?>
                                <div class="category-item">
                                    <span class="category-name"><?= htmlspecialchars($cat['name']) ?></span>
                                    <button class="delete-icon-btn" onclick="showConfirmModal(<?= $cat['id'] ?>, '<?= htmlspecialchars($cat['name']) ?>')">
                                        <i class="fa-solid fa-trash-can"></i>
                                    </button>
                                </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Расходы -->
                    <div class="category-section">
                        <h3>💸 Расходы</h3>
                        <div class="category-list">
                            <?php foreach ($categories as $cat): ?>
                                <?php if ($cat['type'] === 'expense'): ?>
                                <div class="category-item">
                                    <span class="category-name"><?= htmlspecialchars($cat['name']) ?></span>
                                    <button class="delete-icon-btn" onclick="showConfirmModal(<?= $cat['id'] ?>, '<?= htmlspecialchars($cat['name']) ?>')">
                                        <i class="fa-solid fa-trash-can"></i>
                                    </button>
                                </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <p class="empty-state">Нет категорий. Добавьте первую!</p>
            <?php endif; ?>
        </section>
    </main>

    <!-- Модальное окно подтверждения (Скрыто по умолчанию) -->
    <dialog id="confirm-dialog" class="modal">
        <div class="confirm-content">
            <h3><i class="fa-solid fa-triangle-exclamation"></i> Внимание</h3>
            <p>Вы уверены, что хотите удалить категорию<br><strong id="delete-cat-name" style="color: var(--primary);">?</strong></p>
            <p class="hint" style="color: var(--danger); font-size: 0.8rem;">Это действие нельзя отменить.</p>
            <div class="confirm-buttons">
                <button onclick="confirmDelete()" class="btn btn-danger">Да, удалить</button>
                <button onclick="closeConfirmModal()" class="btn btn-secondary">Отмена</button>
            </div>
        </div>
    </dialog>

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
                    </ul>
                </div>
                <div class="footer-col">
                    <h4>🎨 Тема</h4>
                    <div class="theme-switcher">
                        <button class="theme-btn active" data-theme="light">☀️</button>
                        <button class="theme-btn" data-theme="dark">🌙</button>
                        <button class="theme-btn" data-theme="money">💵</button>
                    </div>
                </div>
            </div>
            <div class="footer-bottom">
                <p>ООО "ФинТех Решения"</p>
            </div>
        </div>
    </footer>

    <script src="assets/js/theme.js"></script>
    <script>
        let categoryToDeleteId = null;
        const modal = document.getElementById('confirm-dialog');
        const nameSpan = document.getElementById('delete-cat-name');

        // Показать окно
        function showConfirmModal(id, name) {
            categoryToDeleteId = id;
            nameSpan.textContent = name;
            modal.showModal();
        }

        // Закрыть окно
        function closeConfirmModal() {
            modal.close();
            categoryToDeleteId = null;
        }

        // Подтвердить удаление (переход по ссылке)
        function confirmDelete() {
            if (categoryToDeleteId !== null) {
                window.location.href = '?delete=' + categoryToDeleteId;
            }
        }

        // Закрытие по клику на затемнение
        modal.addEventListener('click', function(e) {
            const rect = modal.getBoundingClientRect();
            if (e.clientX < rect.left || e.clientX > rect.right ||
                e.clientY < rect.top || e.clientY > rect.bottom) {
                closeConfirmModal();
            }
        });
    </script>
</body>
</html>