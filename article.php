<?php
require __DIR__ . '/auth.php';
require __DIR__ . '/db.php';

$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT id, title, perex, content, created_at FROM articles WHERE id=? LIMIT 1");
$stmt->execute([$id]);
$a = $stmt->fetch();

if (!$a) { http_response_code(404); die('Článek nenalezen.'); }
?>
<!doctype html>
<html lang="cs">
<head>
  <meta charset="utf-8">
  <title><?= htmlspecialchars($a['title']) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="style.css?v=10">
</head>

<body class="bg-dark center-screen">
  <div class="card page-wrap">

    <div class="page-topbar">
      <a href="articles.php" class="back-btn">← Zpět na články</a>
      <?php if (!empty($_SESSION['user']['is_admin'])): ?>
        <a href="article_edit.php?id=<?= (int)$a['id'] ?>" class="a-btn primary" style="text-decoration:none;">Upravit</a>
      <?php endif; ?>
    </div>

    <h1 class="page-title"><?= htmlspecialchars($a['title']) ?></h1>

    <div class="article-meta">
      <span class="pill">🗓 <?= htmlspecialchars($a['created_at']) ?></span>
    </div>

    <?php if (!empty($a['perex'])): ?>
      <div class="article-perexbox">
        <?= nl2br(htmlspecialchars($a['perex'])) ?>
      </div>
    <?php endif; ?>

    <div class="article-content">
      <?= nl2br(htmlspecialchars($a['content'] ?? '')) ?>
    </div>

  </div>
</body>
</html>