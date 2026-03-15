<?php
require __DIR__ . '/auth.php';
require __DIR__ . '/db.php';

$isAdmin = !empty($_SESSION['user']['is_admin']);

/* (volitelné) filtr podle tagu */
$tag = trim($_GET['tag'] ?? '');

$sql = "SELECT id, title, perex, created_at FROM articles";
$params = [];
if ($tag !== '') {
  $sql .= " WHERE tags LIKE ?";
  $params[] = '%'.$tag.'%';
}
$sql .= " ORDER BY created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$articles = $stmt->fetchAll();
?>
<!doctype html>
<html lang="cs">
<head>
  <meta charset="utf-8">
  <title>Články</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="style.css?v=10">
</head>

<body class="bg-dark center-screen">
  <div class="card page-wrap">

    <div class="page-topbar">
      <a href="home.php" class="back-btn">← Zpět</a>

      <?php if ($isAdmin): ?>
        <a href="article_new.php" class="a-btn primary" style="text-decoration:none;">+ Přidat článek</a>
      <?php endif; ?>
    </div>

    <h1 class="page-title">Články</h1>

    <?php if (!$articles): ?>
      <p class="muted" style="text-align:left;margin-top:10px;">Zatím tu nejsou žádné články.</p>
    <?php else: ?>
      <div class="articles-grid">
        <?php foreach ($articles as $a): ?>
          <a class="article-card" href="article.php?id=<?= (int)$a['id'] ?>">
            <div class="article-title"><?= htmlspecialchars($a['title']) ?></div>
            <div class="article-perex">
              <?= htmlspecialchars($a['perex'] ?? '—') ?>
            </div>
            <div class="article-foot">
              Vydáno: <?= htmlspecialchars($a['created_at']) ?>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

  </div>
</body>
</html>