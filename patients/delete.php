<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();
$id = (int)($_GET['id'] ?? 0);
$pdo->prepare("DELETE FROM patients WHERE id=?")->execute([$id]);
header('Location: ' . BASE_URL . '/patients/index.php');
