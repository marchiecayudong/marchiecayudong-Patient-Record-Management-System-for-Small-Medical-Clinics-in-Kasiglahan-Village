<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="patients-' . date('Ymd') . '.csv"');
$out = fopen('php://output', 'w');
fputcsv($out, ['ID','Name','Age','Gender','Contact','Email','Address','Date Added']);
$rows = $pdo->query("SELECT id,name,age,gender,contact,email,address,date_added FROM patients ORDER BY id")->fetchAll();
foreach ($rows as $r) fputcsv($out, $r);
fclose($out);

$pdo->prepare("INSERT INTO reports (title,type,generated_by) VALUES (?,?,?)")
    ->execute(['Patients CSV Export','csv', $_SESSION['user_id'] ?? null]);
