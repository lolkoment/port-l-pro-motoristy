<?php
require __DIR__ . '/auth.php';
require __DIR__ . '/db.php';

if (!is_logged_in() || empty($_SESSION['user']['is_admin'])) {
  header('Location: home.php');
  exit;
}

$id = (int)($_GET['id'] ?? 0);

if ($id > 0) {
  $stmt = $pdo->prepare("DELETE FROM articles WHERE id = ?");
  $stmt->execute([$id]);
}

header('Location: admin_articles.php');
exit;