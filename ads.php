<?php
require __DIR__ . '/db.php';

$allowedCats = ['car','parts','services','other'];
$cat = $_GET['cat'] ?? '';
if ($cat !== '' && !in_array($cat, $allowedCats, true)) {
  $cat = '';
}

$sql = "
  SELECT id, title, category, price, currency, location, created_at
  FROM ads
  WHERE status='active'
";
$params = [];

if ($cat !== '') {
  $sql .= " AND category = ?";
  $params[] = $cat;
}

$sql .= " ORDER BY created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$ads = $stmt->fetchAll();
?>
<!doctype html>
<html lang="cs">
<head>
  <meta charset="utf-8">
  <title>Inzeráty</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="style.css">
</head>

<body class="bg-dark center-screen">
  <div class="card page-wrap">
    <div style="margin-bottom:14px;">
  <a href="home.php" class="back-btn">← Zpět na auta</a>
</div>
    <h1 class="page-title">Inzeráty</h1>

    <div class="pill-row">
      <span class="muted" style="margin:0;text-align:left;">Filtr:</span>
      <a class="pill pill-outline" href="ads.php">Vše</a>
      <a class="pill pill-outline" href="ads.php?cat=car">Auta</a>
      <a class="pill pill-outline" href="ads.php?cat=parts">Díly</a>
      <a class="pill pill-outline" href="ads.php?cat=services">Služby</a>
      <a class="pill pill-outline" href="ads.php?cat=other">Ostatní</a>
    </div>

    <?php if (!$ads): ?>
      <p class="muted" style="margin-top:14px;text-align:left;">Zatím tu nejsou žádné aktivní inzeráty.</p>
    <?php else: ?>
      <div class="ads-grid">
        <?php foreach ($ads as $a): ?>
          <a class="ad-card" href="ad.php?id=<?= (int)$a['id'] ?>">
            <div class="ad-top">
              <div class="ad-title"><?= htmlspecialchars($a['title']) ?></div>
              <span class="pill ad-cat"><?= htmlspecialchars($a['category']) ?></span>
            </div>

            <div class="ad-meta">
              <span>📍 <?= htmlspecialchars($a['location'] ?? '—') ?></span>
              <span>
                💰
                <?php if ($a['price'] !== null): ?>
                  <b><?= htmlspecialchars($a['price']) ?> <?= htmlspecialchars($a['currency']) ?></b>
                <?php else: ?>
                  <b>dohodou</b>
                <?php endif; ?>
              </span>
            </div>

            <div class="ad-foot">Vloženo: <?= htmlspecialchars($a['created_at']) ?></div>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</body>
</html>