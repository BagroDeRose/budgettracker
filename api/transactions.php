<?php
/*
*API для работы с транзакциями
*BudgetTracker - Система учета личных финансов
*/

require_once '../includes/auth.php';
require_once '../includes/db.php';

// Разрешаем только POST и GET запросы
header('Content-Type: application/json');

// === ОБРАБОТКА ЭКСПОРТА В CSV (GET запрос) ===
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['export'])) {
    requireLogin();
    $db = getDB();
    $user_id = $_SESSION['user_id'];

    try {
        $stmt = $db->prepare("
            SELECT t.date, t.type, c.name as category, t.description, t.amount
            FROM transactions t
            LEFT JOIN categories c ON t.category_id = c.id
            WHERE t.user_id = ?
            ORDER BY t.date DESC
        ");
        $stmt->execute([$user_id]);
        $transactions = $stmt->fetchAll();

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="budgettracker_export_' . date('Y-m-d') . '.csv"');

        $output = fopen('php://output', 'w');
        // BOM для корректного открытия кириллицы в Excel
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

        fputcsv($output, ['Дата', 'Тип', 'Категория', 'Описание', 'Сумма'], ';');

        foreach ($transactions as $row) {
            $type = $row['type'] === 'income' ? 'Доход' : 'Расход';
            $amount = $row['type'] === 'expense' ? '-' . $row['amount'] : $row['amount'];
            fputcsv($output, [
                date('d.m.Y', strtotime($row['date'])),
                $type,
                $row['category'] ?: 'Без категории',
                $row['description'] ?: '',
                str_replace('.', ',', $amount)
            ], ';');
        }
        fclose($output);
        exit;
    } catch (Exception $e) {
        error_log("Export Error: " . $e->getMessage());
        exit('Error generating export');
    }
}

// === ОБРАБОТКА ОСНОВНЫХ ДЕЙСТВИЙ (POST запрос) ===
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method Not Allowed']);
    exit;
}

requireLogin();

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';
$db = getDB();
$user_id = $_SESSION['user_id'];

try {
    switch ($action) {
        case 'add':
            $type = $input['type'] ?? '';
            $amount = floatval($input['amount'] ?? 0);
            $description = trim($input['description'] ?? '');
            $date = $input['date'] ?? date('Y-m-d');
            $category_id = !empty($input['category_id']) ? intval($input['category_id']) : null;

            // Валидация
            if (!in_array($type, ['income', 'expense'])) throw new Exception('Некорректный тип операции');
            if ($amount <= 0) throw new Exception('Сумма должна быть больше нуля');
            if (!strtotime($date)) throw new Exception('Некорректный формат даты');

            $stmt = $db->prepare("INSERT INTO transactions (user_id, category_id, type, amount, description, date) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$user_id, $category_id, $type, $amount, $description, $date]);

            echo json_encode(['status' => 'success', 'message' => 'Операция добавлена']);
            break;

        case 'update':
            $id = intval($input['id'] ?? 0);
            $type = $input['type'] ?? '';
            $amount = floatval($input['amount'] ?? 0);
            $description = trim($input['description'] ?? '');
            $date = $input['date'] ?? date('Y-m-d');
            $category_id = !empty($input['category_id']) ? intval($input['category_id']) : null;

            if (!in_array($type, ['income', 'expense'])) throw new Exception('Некорректный тип');
            if ($amount <= 0) throw new Exception('Сумма должна быть больше нуля');

            $stmt = $db->prepare("UPDATE transactions SET type = ?, category_id = ?, amount = ?, description = ?, date = ? WHERE id = ? AND user_id = ?");
            $stmt->execute([$type, $category_id, $amount, $description, $date, $id, $user_id]);

            echo json_encode(['status' => 'success', 'message' => 'Операция обновлена']);
            break;

        case 'get_one':
            $id = intval($input['id'] ?? 0);
            $stmt = $db->prepare("SELECT * FROM transactions WHERE id = ? AND user_id = ?");
            $stmt->execute([$id, $user_id]);
            $trans = $stmt->fetch();
            if (!$trans) throw new Exception('Операция не найдена');
            echo json_encode(['status' => 'success', 'data' => $trans]);
            break;

        case 'get_list':
            // Фильтрация
            $date_from = $input['date_from'] ?? null;
            $date_to = $input['date_to'] ?? null;
            $type = $input['type'] ?? null;
            $cat_id = $input['category_id'] ?? null;
            $search = $input['search'] ?? null;

            $where = ["t.user_id = ?"];
            $params = [$user_id];

            if ($date_from) { $where[] = "t.date >= ?"; $params[] = $date_from; }
            if ($date_to) { $where[] = "t.date <= ?"; $params[] = $date_to; }
            if ($type && in_array($type, ['income','expense'])) { $where[] = "t.type = ?"; $params[] = $type; }
            if ($cat_id && is_numeric($cat_id)) { $where[] = "t.category_id = ?"; $params[] = $cat_id; }
            if ($search) { $where[] = "t.description LIKE ?"; $params[] = "%$search%"; }

            $sql = "SELECT t.*, c.name as category_name
                    FROM transactions t
                    LEFT JOIN categories c ON t.category_id = c.id
                    WHERE " . implode(" AND ", $where) . "
                    ORDER BY t.date DESC, t.id DESC LIMIT 100";

            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll()]);
            break;

        case 'delete':
            $id = intval($input['id'] ?? 0);
            $stmt = $db->prepare("DELETE FROM transactions WHERE id = ? AND user_id = ?");
            $stmt->execute([$id, $user_id]);
            echo json_encode(['status' => 'success', 'message' => 'Удалено']);
            break;

        case 'get_stats':
            $stmt = $db->prepare("SELECT
                SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) as total_income,
                SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) as total_expense
                FROM transactions WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $stats = $stmt->fetch();
            $stats['balance'] = $stats['total_income'] - $stats['total_expense'];
            echo json_encode(['status' => 'success', 'data' => $stats]);
            break;

        case 'get_categories':
            $stmt = $db->prepare("SELECT id, name, type FROM categories WHERE user_id = ? ORDER BY name");
            $stmt->execute([$user_id]);
            echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll()]);
            break;

        case 'get_expense_by_category':
            $stmt = $db->prepare("
                SELECT c.name as category_name, SUM(t.amount) as total_amount
                FROM transactions t
                LEFT JOIN categories c ON t.category_id = c.id
                WHERE t.user_id = ? AND t.type = 'expense'
                GROUP BY t.category_id, c.name
                ORDER BY total_amount DESC
            ");
            $stmt->execute([$user_id]);
            echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll()]);
            break;

        default:
            throw new Exception('Неизвестное действие');
    }
} catch (Exception $e) {
    error_log("API Error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}