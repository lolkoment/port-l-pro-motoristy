<?php
require __DIR__ . '/db.php';

$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("
  SELECT id, title, category, price, currency, location, description, phone, email, images_json, created_at
  FROM ads
  WHERE id = ? AND status='active'
  LIMIT 1
");
$stmt->execute([$id]);
$ad = $stmt->fetch();

if (!$ad) {
  http_response_code(404);
  die('Inzerát nenalezen.');
}

$imgs = [];
if (!empty($ad['images_json'])) {
  $decoded = json_decode($ad['images_json'], true);
  if (is_array($decoded)) $imgs = $decoded;
}
?>
<!doctype html>
<html lang="cs">
<head>
  <meta charset="utf-8">
  <title><?= htmlspecialchars($ad['title']) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="style.css">
</head>

<body class="bg-dark center-screen">
  <div class="card page-wrap">

  <div style="margin-bottom:14px;">
  <a href="ads.php" class="back-btn">← Zpět na inzeráty</a>
</div>
    <h1 class="page-title"><?= htmlspecialchars($ad['title']) ?></h1>

    <div class="ad-detail__meta">
      <span class="pill"><?= htmlspecialchars($ad['category']) ?></span>
      <span class="muted" style="margin:0;text-align:left;">📍 <?= htmlspecialchars($ad['location'] ?? '—') ?></span>
      <span>
        💰
        <?php if ($ad['price'] !== null): ?>
          <b><?= htmlspecialchars($ad['price']) ?> <?= htmlspecialchars($ad['currency']) ?></b>
        <?php else: ?>
          <b>dohodou</b>
        <?php endif; ?>
      </span>
    </div>

    <?php if ($imgs): ?>
      <div class="ad-photos">
        <?php foreach ($imgs as $url): ?>
          <img src="<?= htmlspecialchars($url) ?>" alt="">
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <div class="ad-desc">
      <?= nl2br(htmlspecialchars($ad['description'])) ?>
    </div>

    <div class="ad-contact">
      <h3 style="margin:0 0 8px 0">Kontakt</h3>

      <?php if (!empty($ad['phone'])): ?>
        <div>📞 <?= htmlspecialchars($ad['phone']) ?></div>
      <?php endif; ?>

      <?php if (!empty($ad['email'])): ?>
        <div>✉️ <?= htmlspecialchars($ad['email']) ?></div>
      <?php endif; ?>

      <?php if (empty($ad['phone']) && empty($ad['email'])): ?>
        <div class="muted" style="margin:0;text-align:left;">Kontakt není vyplněn.</div>
      <?php endif; ?>

      <div class="muted" style="margin-top:10px;text-align:left;">Vloženo: <?= htmlspecialchars($ad['created_at']) ?></div>
    </div>

  </div>
</body>
</html>