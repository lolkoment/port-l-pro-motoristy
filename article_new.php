<?php
require __DIR__ . '/auth.php';
require __DIR__ . '/db.php';

if (!is_logged_in() || empty($_SESSION['user']['is_admin'])) {
  header('Location: home.php');
  exit;
}

$error = '';

function csrf_value(): string {
  return function_exists('csrf_token') ? (string)csrf_token() : '';
}
function csrf_ok(): bool {
  if (!function_exists('csrf_token')) return true;
  $posted = (string)($_POST['csrf'] ?? '');
  return hash_equals((string)csrf_token(), $posted);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!csrf_ok()) {
    $error = 'Neplatný bezpečnostní token (CSRF). Obnov stránku (F5) a zkus to znovu.';
  }

  $title   = trim($_POST['title'] ?? '');
  $perex   = trim($_POST['perex'] ?? '');
  $content = trim($_POST['content'] ?? '');
  $tags    = trim($_POST['tags'] ?? ''); // volitelné

  if ($error === '') {
    if ($title === '' || mb_strlen($title) > 180) $error = 'Titulek je povinný (max 180 znaků).';
    elseif ($content === '') $error = 'Obsah článku je povinný.';
  }

  if ($error === '') {
    try {
      $stmt = $pdo->prepare("
        INSERT INTO articles (title, perex, content, tags, created_at)
        VALUES (?, ?, ?, ?, NOW())
      ");
      $stmt->execute([
        $title,
        $perex !== '' ? $perex : null,
        $content,
        $tags !== '' ? $tags : null
      ]);

      $newId = (int)$pdo->lastInsertId();
      header('Location: article.php?id=' . $newId);
      exit;

    } catch (PDOException $e) {
      $error = 'Chyba DB: ' . $e->getMessage();
    }
  }
}
?>
<!doctype html>
<html lang="cs">
<head>
  <meta charset="utf-8">
  <title>Přidat článek</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="style.css?v=20">
</head>

<body class="bg-dark center-screen">
  <div class="card page-wrap">

    <div class="page-topbar">
      <a href="articles.php" class="back-btn">← Zpět na články</a>
      <a href="articles.php" class="a-btn primary" style="text-decoration:none;">Seznam článků</a>
    </div>

    <h1 class="page-title">Přidat článek</h1>

    <?php if ($error): ?>
      <div class="error" style="margin-bottom:12px;"><b><?= htmlspecialchars($error) ?></b></div>
    <?php endif; ?>

    <form method="post" class="form-grid" action="article_new.php">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_value(), ENT_QUOTES, 'UTF-8') ?>">

      <div class="field field-full">
        <label class="label">Titulek</label>
        <input class="input" name="title" maxlength="180" required
               value="<?= htmlspecialchars($_POST['title'] ?? '') ?>">
      </div>

      <div class="field field-full">
        <label class="label">Perex (krátký úvod – volitelné)</label>
        <textarea class="input" name="perex" rows="3"><?= htmlspecialchars($_POST['perex'] ?? '') ?></textarea>
      </div>

      <div class="field field-full">
        <label class="label">Obsah</label>
        <textarea class="input" name="content" rows="12" required><?= htmlspecialchars($_POST['content'] ?? '') ?></textarea>
      </div>

      <div class="field field-full">
        <label class="label">Tagy (volitelné, např. “VW, servis, tipy”)</label>
        <input class="input" name="tags" value="<?= htmlspecialchars($_POST['tags'] ?? '') ?>">
      </div>

      <div class="field field-full">
        <button class="btn btn-primary" type="submit">Uložit článek</button>
      </div>
    </form>

  </div>
</body>
</html>