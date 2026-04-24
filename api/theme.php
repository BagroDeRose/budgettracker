<?php
// Файл: api/theme.php
require_once '../includes/auth.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method Not Allowed']);
    exit;
}

requireLogin();

$input = json_decode(file_get_contents('php://input'), true);
$theme = $input['theme'] ?? 'light';

// Валидация темы
$allowed_themes = ['light', 'dark', 'money'];
if (!in_array($theme, $allowed_themes)) {
    $theme = 'light';
}

// Сохраняем в сессии
$_SESSION['theme'] = $theme;

echo json_encode(['status' => 'success', 'theme' => $theme]);