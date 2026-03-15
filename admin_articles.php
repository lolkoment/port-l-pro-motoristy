<?php
require __DIR__ . '/auth.php';
require __DIR__ . '/db.php';

if (!is_logged_in() || empty($_SESSION['user']['is_admin'])) {
  header('Location: home.php');
  exit;
}

$stmt = $pdo->query("SELECT id, title, created_at FROM articles ORDER BY created_at DESC");
$articles = $stmt->fetchAll();
?>
<!doctype html>
<html lang="cs">
<head>
  <meta charset="utf-8">
  <title>Admin – Články</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="style.css?v=30">
</head>

<body class="bg-dark">
  <div class="admin-wrap">

    <div class="admin-topbar">
      <div>
        <h1 class="admin-title">Správa článků</h1>
        <p class="admin-subtitle">Vytváření a úprava článků</p>
      </div>

      <div class="admin-actions">
        <a href="article_new.php" class="a-btn primary">+ Přidat článek</a>
        <a href="admin.php" class="a-btn">Zpět do adminu</a>
      </div>
    </div>

    <div class="admin-card">

      <?php if (!$articles): ?>
        <p class="muted">Zatím nejsou žádné články.</p>
      <?php else: ?>
        <table class="admin-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>Titulek</th>
              <th>Datum</th>
              <th>Akce</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($articles as $a): ?>
              <tr>
                <td><?= (int)$a['id'] ?></td>
                <td><?= htmlspecialchars($a['title']) ?></td>
                <td><?= htmlspecialchars($a['created_at']) ?></td>
                <td>
                  <a class="a-btn small" href="article_edit.php?id=<?= (int)$a['id'] ?>">Upravit</a>
                  <a class="a-btn danger small"
                     href="admin_article_delete.php?id=<?= (int)$a['id'] ?>"
                     onclick="return confirm('Opravdu smazat článek?');">
                     Smazat
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>

    </div>
  </div>
</body>
</html>