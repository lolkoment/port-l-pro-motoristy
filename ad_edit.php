<?php
require __DIR__ . '/auth.php';
require __DIR__ . '/db.php';

if (!is_logged_in()) { header('Location: login.php'); exit; }
$user_id = (int)($_SESSION['user']['id'] ?? 0);

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

function save_uploaded_images(array $files, int $maxFiles = 6): array {
  if (!isset($files['name']) || !is_array($files['name'])) return [];

  $out = [];
  $count = min(count($files['name']), $maxFiles);

  $uploadDir = __DIR__ . '/uploads/ads';
  if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

  for ($i = 0; $i < $count; $i++) {
    if (($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) continue;
    if (($files['error'][$i] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) continue;

    $tmp = $files['tmp_name'][$i] ?? '';
    if ($tmp === '' || !is_uploaded_file($tmp)) continue;

    $size = (int)($files['size'][$i] ?? 0);
    if ($size <= 0 || $size > 5 * 1024 * 1024) {
      throw new RuntimeException('Obrázek je moc velký (max 5 MB).');
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $tmp);
    finfo_close($finfo);

    $ext = '';
    if ($mime === 'image/jpeg') $ext = 'jpg';
    elseif ($mime === 'image/png') $ext = 'png';
    elseif ($mime === 'image/webp') $ext = 'webp';
    else throw new RuntimeException('Povoleny jsou jen JPG, PNG, WEBP.');

    $name = 'ad_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    $dest = $uploadDir . '/' . $name;

    if (!move_uploaded_file($tmp, $dest)) {
      throw new RuntimeException('Nepodařilo se uložit obrázek.');
    }

    $out[] = 'uploads/ads/' . $name;
  }

  return $out;
}

function load_images($json): array {
  if (!$json) return [];
  $d = json_decode($json, true);
  return is_array($d) ? $d : [];
}
function save_images(array $arr): string {
  return json_encode(array_values($arr), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

/* načti inzerát a ověř, že je tvůj */
$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM ads WHERE id = ? AND user_id = ? LIMIT 1");
$stmt->execute([$id, $user_id]);
$ad = $stmt->fetch();

if (!$ad) {
  http_response_code(404);
  die('Inzerát nenalezen nebo nemáš oprávnění.');
}

/* mazání fotky */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_image') {
  if (!csrf_ok()) {
    $error = 'Neplatný CSRF token. Obnov stránku a zkus to znovu.';
  } else {
    $img = (string)($_POST['img'] ?? '');
    $imgs = load_images($ad['images_json'] ?? null);

    $idx = array_search($img, $imgs, true);
    if ($idx !== false) {
      unset($imgs[$idx]);

      // smaž i soubor (jen pokud je v uploads/ads)
      if (strpos($img, 'uploads/ads/') === 0) {
        $path = __DIR__ . '/' . $img;
        if (is_file($path)) @unlink($path);
      }

      $pdo->prepare("UPDATE ads SET images_json = ? WHERE id = ? AND user_id = ?")
          ->execute([ save_images($imgs), $id, $user_id ]);

      // reload dat
      $stmt = $pdo->prepare("SELECT * FROM ads WHERE id = ? AND user_id = ? LIMIT 1");
      $stmt->execute([$id, $user_id]);
      $ad = $stmt->fetch();

      $success = 'Fotka byla smazána.';
    }
  }
}

/* uložení úprav */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
  if (!csrf_ok()) {
    $error = 'Neplatný CSRF token. Obnov stránku a zkus to znovu.';
  }

  $title = trim($_POST['title'] ?? '');
  $category = $_POST['category'] ?? 'parts';
  $price = trim($_POST['price'] ?? '');
  $currency = trim($_POST['currency'] ?? 'CZK');
  $location = trim($_POST['location'] ?? '');
  $description = trim($_POST['description'] ?? '');
  $phone = trim($_POST['phone'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $status = $_POST['status'] ?? 'active';

  $allowedCats = ['car','parts','services','other'];
  if (!in_array($category, $allowedCats, true)) $category = 'parts';

  $allowedStatus = ['active','inactive'];
  if (!in_array($status, $allowedStatus, true)) $status = 'active';

  if ($error === '') {
    if ($title === '' || mb_strlen($title) > 180) $error = 'Název je povinný (max 180 znaků).';
    elseif ($description === '') $error = 'Popis je povinný.';
  }

  // telefon bez písmen
  if ($error === '' && $phone !== '') {
    if (!preg_match('~^\+?[0-9 ]{9,16}$~', $phone)) {
      $error = 'Telefon může obsahovat jen čísla, mezery a případně + na začátku.';
    }
  }

  // email validace
  if ($error === '' && $email !== '') {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      $error = 'Email není ve správném formátu (musí obsahovat @).';
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

  // přidání nových fotek
  $imgs = load_images($ad['images_json'] ?? null);
  if ($error === '') {
    try {
      if (!empty($_FILES['images'])) {
        $new = save_uploaded_images($_FILES['images'], 6);
        $imgs = array_merge($imgs, $new);
        $imgs = array_slice($imgs, 0, 10); // max 10 celkem
      }
    } catch (Throwable $e) {
      $error = $e->getMessage();
    }
  }

  if ($error === '') {
    try {
      $pdo->prepare("
        UPDATE ads
        SET title=?, category=?, price=?, currency=?, location=?, description=?, phone=?, email=?, status=?, images_json=?
        WHERE id=? AND user_id=?
      ")->execute([
        $title,
        $category,
        $priceVal,
        $currency ?: 'CZK',
        $location ?: null,
        $description,
        $phone ?: null,
        $email ?: null,
        $status,
        $imgs ? save_images($imgs) : null,
        $id,
        $user_id
      ]);

      // refresh
      $stmt = $pdo->prepare("SELECT * FROM ads WHERE id = ? AND user_id = ? LIMIT 1");
      $stmt->execute([$id, $user_id]);
      $ad = $stmt->fetch();

      $success = 'Uloženo.';
    } catch (PDOException $e) {
      $error = 'Chyba DB: ' . $e->getMessage();
    }
  }
}

$imgs = load_images($ad['images_json'] ?? null);
?>
<!doctype html>
<html lang="cs">
<head>
  <meta charset="utf-8">
  <title>Upravit inzerát</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="style.css?v=4">
</head>

<body class="bg-dark center-screen">
  <div class="card page-wrap">

    <div class="page-topbar">
      <a href="my_ads.php" class="back-btn">← Moje inzeráty</a>
      <a href="ad.php?id=<?= (int)$ad['id'] ?>" class="a-btn primary" style="text-decoration:none;">Zobrazit</a>
    </div>

    <h1 class="page-title">Upravit inzerát</h1>

    <?php if ($success): ?>
      <div class="success" style="margin-bottom:12px;"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
      <div class="error" style="margin-bottom:12px;"><b><?= htmlspecialchars($error) ?></b></div>
    <?php endif; ?>

    <?php if ($imgs): ?>
      <div class="ad-photos">
        <?php foreach ($imgs as $url): ?>
          <div style="display:inline-block;">
            <img src="<?= htmlspecialchars($url) ?>" alt="">
            <form method="post" style="margin-top:8px;">
              <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_value(), ENT_QUOTES, 'UTF-8') ?>">
              <input type="hidden" name="action" value="delete_image">
              <input type="hidden" name="img" value="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>">
              <button type="submit" class="a-btn danger small">Smazat fotku</button>
            </form>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <form method="post" class="form-grid" enctype="multipart/form-data">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_value(), ENT_QUOTES, 'UTF-8') ?>">
      <input type="hidden" name="action" value="save">

      <div class="field">
        <label class="label">Název</label>
        <input class="input" name="title" maxlength="180" required value="<?= htmlspecialchars($ad['title'] ?? '') ?>">
      </div>

      <div class="field">
        <label class="label">Kategorie</label>
        <select class="input" name="category">
          <?php
            $cur = $ad['category'] ?? 'parts';
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
          <input class="input" name="price" inputmode="decimal" placeholder="např. 6500" value="<?= htmlspecialchars($ad['price'] ?? '') ?>">
          <input class="input" name="currency" style="max-width:110px" value="<?= htmlspecialchars($ad['currency'] ?? 'CZK') ?>">
        </div>
      </div>

      <div class="field">
        <label class="label">Status</label>
        <select class="input" name="status">
          <option value="active" <?= ($ad['status'] ?? '')==='active' ? 'selected' : '' ?>>Aktivní</option>
          <option value="inactive" <?= ($ad['status'] ?? '')==='inactive' ? 'selected' : '' ?>>Neaktivní</option>
        </select>
      </div>

      <div class="field">
        <label class="label">Lokalita</label>
        <input class="input" name="location" value="<?= htmlspecialchars($ad['location'] ?? '') ?>">
      </div>

      <div class="field field-full">
        <label class="label">Popis</label>
        <textarea class="input" name="description" required rows="8"><?= htmlspecialchars($ad['description'] ?? '') ?></textarea>
      </div>

      <div class="field">
        <label class="label">Telefon</label>
        <input class="input" name="phone" inputmode="tel" value="<?= htmlspecialchars($ad['phone'] ?? '') ?>">
      </div>

      <div class="field">
        <label class="label">Email</label>
        <input class="input" name="email" inputmode="email" value="<?= htmlspecialchars($ad['email'] ?? '') ?>">
      </div>

      <div class="field field-full">
        <label class="label">Přidat fotky (JPG/PNG/WEBP)</label>
        <input class="input" type="file" name="images[]" accept="image/jpeg,image/png,image/webp" multiple>
      </div>

      <div class="field field-full">
        <button class="btn btn-primary" type="submit">Uložit změny</button>
      </div>
    </form>

  </div>
</body>
</html>