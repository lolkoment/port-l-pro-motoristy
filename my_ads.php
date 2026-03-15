<?php
require __DIR__ . '/auth.php';
require __DIR__ . '/db.php';

if (!is_logged_in()) { header('Location: login.php'); exit; }
$user_id = (int)$_SESSION['user']['id'];

$stmt = $pdo->prepare("
  SELECT id, title, category, price, currency, status, location, created_at
  FROM ads
  WHERE user_id = ?
  ORDER BY created_at DESC
");
$stmt->execute([$user_id]);
$ads = $stmt->fetchAll();
?>
<!doctype html>
<html lang="cs">
<head>
  <meta charset="utf-8">
  <title>Moje inzeráty</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="style.css?v=1">
</head>

<body class="bg-dark center-screen">
  <div class="card page-wrap">

    <div class="page-topbar">
      <a href="home.php" class="back-btn">← Zpět na Auta</a>

      <a href="ad_new.php" class="a-btn primary" style="text-decoration:none;">+ Přidat inzerát</a>
    </div>

    <h1 class="page-title">Moje inzeráty</h1>

    <?php if (!$ads): ?>
      <p class="muted" style="text-align:left;margin-top:10px;">Zatím nemáš žádný inzerát.</p>
    <?php else: ?>
      <div class="ads-grid">
        <?php foreach ($ads as $a): ?>
          <div class="ad-card" style="cursor:default;">
            <div class="ad-top">
              <div class="ad-title"><?= htmlspecialchars($a['title']) ?></div>
              <span class="pill ad-cat"><?= htmlspecialchars($a['status']) ?></span>
            </div>

            <div class="ad-meta">
              <span>📦 <?= htmlspecialchars($a['category']) ?></span>
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

            <div class="ad-foot" style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;align-items:center;">
              <span>Vloženo: <?= htmlspecialchars($a['created_at']) ?></span>
              <span style="display:flex;gap:10px;flex-wrap:wrap;">
                <a class="pill pill-outline" href="ad.php?id=<?= (int)$a['id'] ?>">Zobrazit</a>
                <a class="pill" href="ad_edit.php?id=<?= (int)$a['id'] ?>">Upravit</a>
              </span>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

  </div>
</body>
</html>