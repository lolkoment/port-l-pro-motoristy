<?php
require_once 'admin_check.php';
require_once 'db.php';

$id = $_GET['id'] ?? null;

if ($id) {
    $stmt = $pdo->prepare("DELETE FROM cars WHERE id = ?");
    $stmt->execute([$id]);
}

header('Location: admin_cars.php');
exit;
