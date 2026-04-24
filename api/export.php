<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    exit('Method Not Allowed');
}

requireLogin();
$db = getDB();
$user_id = $_SESSION['user_id'];

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
// BOM для корректного открытия в Excel
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