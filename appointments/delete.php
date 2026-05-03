<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();
$pdo->prepare("DELETE FROM appointments WHERE id=?")->execute([(int)($_GET['id'] ?? 0)]);
header('Location: ' . BASE_URL . '/appointments/index.php');
