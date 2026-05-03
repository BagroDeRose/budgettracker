<?php
require_once 'includes/auth.php';
requireLogin();

$user = getCurrentUser();
if (!$user) {
    logoutUser();
    header("Location: login.php");
    exit;
}

$theme = $_SESSION['theme'] ?? 'light';
?>
<!DOCTYPE html>
<html lang="ru" data-theme="<?= htmlspecialchars($theme) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Дашборд | BudgetTracker</title>
    <link rel="icon" href="assets/img/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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

    <main class="container dashboard">
        <!-- Статистика -->
        <section class="dashboard-grid">
            <div class="card stats">
                <h2>💵 Баланс</h2>
                <p class="balance" id="balance">0.00 ₽</p>
            </div>
            <div class="card income">
                <h2>📈 Доходы</h2>
                <p class="amount positive" id="income-total">0.00 ₽</p>
            </div>
            <div class="card expense">
                <h2>📉 Расходы</h2>
                <p class="amount negative" id="expense-total">0.00 ₽</p>
            </div>
        </section>

        <!-- Фильтры и поиск -->
        <section class="card filters-section">
            <h2>🔍 Фильтры и поиск</h2>
            <div class="filter-row">
                <div class="form-group">
                    <label for="filter-date-from">Период с</label>
                    <input type="date" id="filter-date-from">
                </div>
                <div class="form-group">
                    <label for="filter-date-to">Период по</label>
                    <input type="date" id="filter-date-to">
                </div>
                <div class="form-group">
                    <label for="filter-type">Тип операции</label>
                    <select id="filter-type">
                        <option value="">Все</option>
                        <option value="income">Доходы</option>
                        <option value="expense">Расходы</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="filter-category">Категория</label>
                    <select id="filter-category">
                        <option value="">Все</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="filter-search">Поиск по описанию</label>
                    <input type="text" id="filter-search" placeholder="Например: зарплата...">
                </div>
                <div class="form-group">
                    <label>&nbsp;</label>
                    <div class="filter-buttons">
                        <button class="btn btn-primary" id="apply-filters">🔍 Применить</button>
                        <button class="btn btn-secondary" id="reset-filters">🔄 Сбросить</button>
                    </div>
                </div>
            </div>
        </section>

        <!-- Основной контент: диаграмма и таблица -->
        <div class="content-grid">
            <section class="card chart-section">
                <h2>📊 Структура расходов</h2>
                <canvas id="expenseChart"></canvas>
                <div id="no-chart-data" class="empty-state" style="display: none;">
                    Нет данных для отображения диаграммы
                </div>
            </section>

            <section class="card transactions">
                <div class="section-header">
                    <h2>📋 Последние операции</h2>
                    <div>
                        <a href="api/transactions.php?export=1" class="btn btn-secondary">📥 Экспорт CSV</a>
                        <button class="btn btn-primary" id="add-transaction">+ Добавить</button>
                    </div>
                </div>

                <div class="print-header" style="display:none;">
                    <h1>BudgetTracker – Отчет по операциям</h1>
                    <p>Дата формирования: <?= date('d.m.Y') ?></p>
                </div>

                <div class="table-wrapper">
                    <table id="transactions-table">
                        <thead>
                            <tr>
                                <th>Дата</th>
                                <th>Категория</th>
                                <th>Описание</th>
                                <th>Сумма</th>
                                <th>Действия</th>
                            </tr>
                        </thead>
                        <tbody id="transactions-body">
                            <tr><td colspan="5" class="empty-state">Загрузка...</td></tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </main>

    <!-- Модальное окно добавления/редактирования -->
    <dialog id="transaction-modal" class="modal">
        <form method="dialog" id="transaction-form">
            <input type="hidden" name="id" id="trans-id">
            <h3 id="modal-title">➕ Новая операция</h3>
            <div class="form-group">
                <label for="trans-type">Тип</label>
                <select name="type" id="trans-type" required>
                    <option value="expense">💸 Расход</option>
                    <option value="income">💰 Доход</option>
                </select>
            </div>
            <div class="form-group">
                <label for="category-select">Категория</label>
                <select name="category_id" id="category-select">
                    <option value="">Без категории</option>
                </select>
            </div>
            <div class="form-group">
                <label for="amount">Сумма</label>
                <input type="number" name="amount" id="amount" step="0.01" min="0.01" required>
            </div>
            <div class="form-group">
                <label for="description">Описание</label>
                <input type="text" name="description" id="description" placeholder="ОПИСАНИЕ">
            </div>
            <div class="form-group">
                <label for="date">Дата</label>
                <input type="date" name="date" id="date" value="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="modal-actions">
                <button type="submit" class="btn btn-primary">💾 Сохранить</button>
                <button type="button" class="btn btn-secondary" onclick="this.closest('dialog').close()">❌ Отмена</button>
            </div>
        </form>
    </dialog>

    <!-- МОДАЛЬНОЕ ОКНО УДАЛЕНИЯ (НОВОЕ) -->
    <dialog id="delete-trans-modal" class="modal confirm-modal">
        <div class="modal-content" style="text-align: center; padding: 2rem;">
            <h3 style="color: var(--danger); margin-bottom: 1rem;"><i class="fa-solid fa-trash-can"></i> Удаление</h3>
            <p style="font-size: 1.1rem;">Вы действительно хотите удалить эту операцию?</p>
            <p class="hint" style="color: var(--danger); font-size: 0.9rem; margin-top: 0.5rem;">Это действие нельзя отменить.</p>
            <div style="display: flex; justify-content: center; gap: 1rem; margin-top: 2rem;">
                <button onclick="confirmTransDelete()" class="btn btn-danger">Да, удалить</button>
                <button onclick="closeDeleteTransModal()" class="btn btn-secondary">Отмена</button>
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
                        <li><a href="#">Помощь и поддержка</a></li>
                    </ul>
                </div>
                <div class="footer-col">
                    <h4>🎨 Тема оформления</h4>
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

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="assets/js/app.js"></script>
    <script src="assets/js/theme.js"></script>
</body>
</html>