<?php
require __DIR__ . '/auth.php';
require __DIR__ . '/db.php';

if (!is_logged_in() || empty($_SESSION['user']['is_admin'])) {
  header('Location: home.php');
  exit;
}

$error = '';
$success = '';

function csrf_value(): string {
  return function_exists('csrf_token') ? (string)csrf_token() : '';
}
function csrf_ok(): bool {
  if (!function_exists('csrf_token')) return true;
  $posted = (string)($_POST['csrf'] ?? '');
  return hash_equals((string)csrf_token(), $posted);
}

$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT id, title, perex, content, tags, created_at FROM articles WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$article = $stmt->fetch();

if (!$article) {
  http_response_code(404);
  die('Článek nenalezen.');
}

/* smazání článku */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
  if (!csrf_ok()) {
    $error = 'Neplatný CSRF token. Obnov stránku a zkus to znovu.';
  } else {
    try {
      $pdo->prepare("DELETE FROM articles WHERE id = ?")->execute([$id]);
      header('Location: articles.php');
      exit;
    } catch (PDOException $e) {
      $error = 'Chyba DB při mazání: ' . $e->getMessage();
    }
  }
}

/* uložení úprav */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
  if (!csrf_ok()) {
    $error = 'Neplatný CSRF token. Obnov stránku a zkus to znovu.';
  }

  $title   = trim($_POST['title'] ?? '');
  $perex   = trim($_POST['perex'] ?? '');
  $content = trim($_POST['content'] ?? '');
  $tags    = trim($_POST['tags'] ?? '');

  if ($error === '') {
    if ($title === '' || mb_strlen($title) > 180) $error = 'Titulek je povinný (max 180 znaků).';
    elseif ($content === '') $error = 'Obsah článku je povinný.';
  }

  if ($error === '') {
    try {
      $stmt = $pdo->prepare("
        UPDATE articles
        SET title = ?, perex = ?, content = ?, tags = ?
        WHERE id = ?
      ");
      $stmt->execute([
        $title,
        $perex !== '' ? $perex : null,
        $content,
        $tags !== '' ? $tags : null,
        $id
      ]);

      // reload
      $stmt = $pdo->prepare("SELECT id, title, perex, content, tags, created_at FROM articles WHERE id = ? LIMIT 1");
      $stmt->execute([$id]);
      $article = $stmt->fetch();

      $success = 'Uloženo.';
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
  <title>Upravit článek</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="style.css?v=20">
</head>

<body class="bg-dark center-screen">
  <div class="card page-wrap">

    <div class="page-topbar">
      <a href="article.php?id=<?= (int)$article['id'] ?>" class="back-btn">← Zpět na článek</a>
      <a href="articles.php" class="a-btn primary" style="text-decoration:none;">Seznam článků</a>
    </div>

    <h1 class="page-title">Upravit článek</h1>

    <?php if ($success): ?>
      <div class="success" style="margin-bottom:12px;"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
      <div class="error" style="margin-bottom:12px;"><b><?= htmlspecialchars($error) ?></b></div>
    <?php endif; ?>

    <form method="post" class="form-grid" action="article_edit.php?id=<?= (int)$article['id'] ?>">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_value(), ENT_QUOTES, 'UTF-8') ?>">
      <input type="hidden" name="action" value="save">

      <div class="field field-full">
        <label class="label">Titulek</label>
        <input class="input" name="title" maxlength="180" required
               value="<?= htmlspecialchars($article['title'] ?? '') ?>">
      </div>

      <div class="field field-full">
        <label class="label">Perex (volitelné)</label>
        <textarea class="input" name="perex" rows="3"><?= htmlspecialchars($article['perex'] ?? '') ?></textarea>
      </div>

      <div class="field field-full">
        <label class="label">Obsah</label>
        <textarea class="input" name="content" rows="12" required><?= htmlspecialchars($article['content'] ?? '') ?></textarea>
      </div>

      <div class="field field-full">
        <label class="label">Tagy (volitelné)</label>
        <input class="input" name="tags" value="<?= htmlspecialchars($article['tags'] ?? '') ?>">
      </div>

      <div class="field field-full">
        <button class="btn btn-primary" type="submit">Uložit změny</button>
      </div>
    </form>

    <hr style="border:0;border-top:1px solid rgba(148,163,184,.14);margin:14px 0">

    <form method="post" action="article_edit.php?id=<?= (int)$article['id'] ?>"
          onsubmit="return confirm('Opravdu chceš článek smazat?');">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_value(), ENT_QUOTES, 'UTF-8') ?>">
      <input type="hidden" name="action" value="delete">
      <button type="submit" class="a-btn danger" style="width:auto;">Smazat článek</button>
    </form>

  </div>
</body>
</html>