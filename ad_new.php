<?php
require __DIR__ . '/auth.php';
require __DIR__ . '/db.php';

if (!is_logged_in()) { header('Location: login.php'); exit; }
$user_id = (int)($_SESSION['user']['id'] ?? 0);

$error = '';

function csrf_value(): string {
  return function_exists('csrf_token') ? (string)csrf_token() : '';
}
function csrf_ok(): bool {
  if (!function_exists('csrf_token')) return true;
  $posted = (string)($_POST['csrf'] ?? '');
  return hash_equals((string)csrf_token(), $posted);
}

/* bezpečné uložení obrázků */
function save_uploaded_images(array $files, int $maxFiles = 6): array {
  if (!isset($files['name']) || !is_array($files['name'])) return [];

  $out = [];
  $count = count($files['name']);
  $count = min($count, $maxFiles);

  $uploadDir = __DIR__ . '/uploads/ads';
  if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
  }

  for ($i = 0; $i < $count; $i++) {
    if (($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) continue;
    if (($files['error'][$i] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) continue;

    $tmp = $files['tmp_name'][$i] ?? '';
    if ($tmp === '' || !is_uploaded_file($tmp)) continue;

    $size = (int)($files['size'][$i] ?? 0);
    if ($size <= 0 || $size > 5 * 1024 * 1024) { // max 5MB
      throw new RuntimeException('Obrázek je moc velký (max 5 MB).');
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $tmp);
    finfo_close($finfo);

    $ext = '';
    if ($mime === 'image/jpeg') $ext = 'jpg';
    elseif ($mime === 'image/png') $ext = 'png';
    elseif ($mime === 'image/webp') $ext = 'webp';
    else {
      throw new RuntimeException('Povoleny jsou jen JPG, PNG, WEBP.');
    }

    $name = 'ad_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    $dest = $uploadDir . '/' . $name;

    if (!move_uploaded_file($tmp, $dest)) {
      throw new RuntimeException('Nepodařilo se uložit obrázek.');
    }

    // cesta do webu
    $out[] = 'uploads/ads/' . $name;
  }

  return $out;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  if (!csrf_ok()) {
    $error = 'Neplatný bezpečnostní token (CSRF). Obnov stránku (F5) a zkus to znovu.';
  }

  $title = trim($_POST['title'] ?? '');
  $category = $_POST['category'] ?? 'parts';
  $price = trim($_POST['price'] ?? '');
  $currency = trim($_POST['currency'] ?? 'CZK');
  $location = trim($_POST['location'] ?? '');
  $description = trim($_POST['description'] ?? '');
  $phone = trim($_POST['phone'] ?? '');
  $email = trim($_POST['email'] ?? '');

  $allowedCats = ['car','parts','services','other'];
  if (!in_array($category, $allowedCats, true)) $category = 'parts';

  // VALIDACE telefonu (jen čísla + mezera + +)
  if ($error === '' && $phone !== '') {
    if (!preg_match('~^\+?[0-9 ]{9,16}$~', $phone)) {
      $error = 'Telefon může obsahovat jen čísla';
    }
  }

  // VALIDACE emailu
  if ($error === '' && $email !== '') {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      $error = 'Email není ve správném formátu.';
    }
  }

  // aspoň jeden kontakt
  if ($error === '' && $phone === '' && $email === '') {
    $error = 'Vyplň telefon nebo email (aspoň jeden kontakt je povinný).';
  }

  $priceVal = null;
  if ($error === '' && $price !== '') {
    $p = str_replace(',', '.', $price);
    if (!is_numeric($p) || (float)$p < 0) {
      $error = 'Cena musí být číslo (nebo nech prázdné pro "dohodou").';
    } else {
      $priceVal = (float)$p;
    }
  }

  if ($error === '') {
    if ($title === '' || mb_strlen($title) > 180) $error = 'Název je povinný (max 180 znaků).';
    elseif ($description === '') $error = 'Popis je povinný.';
  }

  // UPLOAD obrázků
  $images = [];
  if ($error === '') {
    try {
      if (!empty($_FILES['images'])) {
        $images = save_uploaded_images($_FILES['images'], 6); // max 6 fotek
      }
    } catch (Throwable $e) {
      $error = $e->getMessage();
    }
  }

  if ($error === '') {
    try {
      $stmt = $pdo->prepare("
        INSERT INTO ads (user_id, title, category, price, currency, location, description, phone, email, images_json, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')
      ");
      $stmt->execute([
        $user_id,
        $title,
        $category,
        $priceVal,
        $currency ?: 'CZK',
        $location ?: null,
        $description,
        $phone ?: null,
        $email ?: null,
        $images ? json_encode($images, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null
      ]);

      $newId = (int)$pdo->lastInsertId();
      header("Location: ad.php?id=".$newId);
      exit;

    } catch (PDOException $e) {
      $error = 'Chyba při ukládání do databáze: ' . $e->getMessage();
    }
  }
}
?>
<!doctype html>
<html lang="cs">
<head>
  <meta charset="utf-8">
  <title>Přidat inzerát</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="style.css?v=3">
</head>

<body class="bg-dark center-screen">
  <div class="card page-wrap">

    <div class="page-topbar">
      <a href="home.php" class="back-btn">← Zpět na auta</a>
      <!-- MODRÉ tlačítko -->
      <a href="ads.php" class="a-btn primary" style="text-decoration:none;">Veřejné inzeráty</a>
    </div>

    <h1 class="page-title">Přidat inzerát</h1>

    <?php if ($error): ?>
      <div class="error" style="margin-bottom:12px;">
        <b><?= htmlspecialchars($error) ?></b>
      </div>
    <?php endif; ?>

    <form method="post" class="form-grid" action="ad_new.php" enctype="multipart/form-data">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_value(), ENT_QUOTES, 'UTF-8') ?>">

      <div class="field">
        <label class="label">Název</label>
        <input class="input" name="title" maxlength="180" required value="<?= htmlspecialchars($_POST['title'] ?? '') ?>">
      </div>

      <div class="field">
        <label class="label">Kategorie</label>
        <select class="input" name="category">
          <?php
            $cur = $_POST['category'] ?? 'parts';
            foreach (['car'=>'Auta','parts'=>'Díly','services'=>'Služby','other'=>'Ostatní'] as $k=>$v) {
              $sel = ($cur===$k) ? 'selected' : '';
              echo "<option value=\"$k\" $sel>$v</option>";
            }
          ?>
        </select>
      </div>

      <div class="field">
        <label class="label">Cena (prázdné = dohodou)</label>
        <div class="inline-two">
          <input class="input" name="price" inputmode="decimal" placeholder="např. 6500" value="<?= htmlspecialchars($_POST['price'] ?? '') ?>">
          <input class="input" name="currency" style="max-width:110px" value="<?= htmlspecialchars($_POST['currency'] ?? 'CZK') ?>">
        </div>
      </div>

      <div class="field">
        <label class="label">Lokalita</label>
        <input class="input" name="location" placeholder="např. Ústí nad Labem" value="<?= htmlspecialchars($_POST['location'] ?? '') ?>">
      </div>

      <div class="field field-full">
        <label class="label">Popis</label>
        <textarea class="input" name="description" required rows="8"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
      </div>

      <div class="field">
        <label class="label">Telefon</label>
        <input class="input" name="phone" inputmode="tel" placeholder="např. 777 111 222" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
      </div>

      <div class="field">
        <label class="label">Email</label>
        <input class="input" name="email" inputmode="email" placeholder="např. ja@email.cz" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
      </div>

      <div class="field field-full">
        <label class="label">Obrázky (JPG/PNG/WEBP, max 6, max 5MB/ks)</label>
        <input class="input" type="file" name="images[]" accept="image/jpeg,image/png,image/webp" multiple>
        <div class="muted" style="text-align:left;margin:8px 0 0 0;">Tip: vyber víc fotek najednou (Ctrl/Shift).</div>
      </div>

      <div class="field field-full">
        <button class="btn btn-primary" type="submit">Uložit inzerát</button>
      </div>
    </form>

  </div>
</body>
</html>