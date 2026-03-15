<?php
require_once __DIR__ . '/admin_check.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php'; // kvůli csrf_token() / check_csrf_post()

check_csrf_post();

function safe_filename($name) {
  $name = strtolower($name);
  $name = preg_replace('~[^a-z0-9._-]+~', '_', $name);
  $name = trim($name, '_');
  return $name;
}

$id = (int)($_GET['id'] ?? 0);
$error = '';
$success = '';

$car = [
  'brand' => '',
  'model' => '',
  'variant' => '',
  'year' => '',
  'fuel' => '',
  'power_kw' => '',
  'body' => '',
  'price' => '',
  'mileage' => '',
  'description' => '',
  'model_3d' => '', // ✅ ukládá cestu např. /assets/model/neco.glb
];

if ($id > 0) {
  $stmt = $pdo->prepare("SELECT * FROM cars WHERE id = ?");
  $stmt->execute([$id]);
  $row = $stmt->fetch();
  if (!$row) { http_response_code(404); exit('Auto nenalezeno.'); }
  $car = array_merge($car, $row);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $brand = trim($_POST['brand'] ?? '');
  $model = trim($_POST['model'] ?? '');
  $variant = trim($_POST['variant'] ?? '');
  $year = ($_POST['year'] ?? '') !== '' ? (int)$_POST['year'] : null;
  $fuel = trim($_POST['fuel'] ?? '');
  $power_kw = ($_POST['power_kw'] ?? '') !== '' ? (int)$_POST['power_kw'] : null;
  $body = trim($_POST['body'] ?? '');
  $price = ($_POST['price'] ?? '') !== '' ? (int)$_POST['price'] : null;
  $mileage = ($_POST['mileage'] ?? '') !== '' ? (int)$_POST['mileage'] : null;

  $description = trim($_POST['description'] ?? '');

  // vybere se model z rozbalováku (např. /assets/model/tesla.glb)
  $model_3d = trim($_POST['model_3d'] ?? '');

  // ✅ UPLOAD nového GLB (volitelné)
  if (!empty($_FILES['glb_upload']['name'])) {
    $f = $_FILES['glb_upload'];

    if ($f['error'] !== UPLOAD_ERR_OK) {
      $error = 'Nahrání souboru se nepovedlo.';
    } else {
      $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
      if ($ext !== 'glb') {
        $error = 'Povoleny jsou pouze soubory .glb';
      } else {
        // max 50 MB
        if (($f['size'] ?? 0) > 50 * 1024 * 1024) {
          $error = 'Soubor je příliš velký (max 50 MB).';
        } else {
          $destDir = __DIR__ . '/assets/model/';
          if (!is_dir($destDir)) {
            $error = 'Složka assets/model neexistuje.';
          } else {
            $base = safe_filename(pathinfo($f['name'], PATHINFO_FILENAME));
            if ($base === '') $base = 'model';

            $final = $base . '.glb';
            $i = 2;
            while (file_exists($destDir . $final)) {
              $final = $base . '_' . $i . '.glb';
              $i++;
            }

            if (!move_uploaded_file($f['tmp_name'], $destDir . $final)) {
              $error = 'Soubor se nepodařilo uložit na server.';
            } else {
              // po úspěchu nastavíme vybraný model
              $model_3d = '/assets/model/' . $final;
              $success = '3D model byl nahrán: ' . $final;
            }
          }
        }
      }
    }
  }

  // ✅ validace: buď prázdné, nebo přesně /assets/model/<soubor>.glb
  if ($error === '' && $model_3d !== '') {
    if (!preg_match('~^/assets/model/[a-zA-Z0-9._-]+\.glb$~', $model_3d)) {
      $error = 'Neplatný výběr 3D modelu.';
    } else {
      $fs = __DIR__ . '/' . ltrim($model_3d, '/');
      if (!is_file($fs)) {
        $error = 'Vybraný GLB soubor neexistuje na serveru.';
      }
    }
  }

  if ($error === '' && ($brand === '' || $model === '')) {
    $error = 'Značka a model jsou povinné.';
  }

  if ($error === '') {
    if ($id > 0) {
      $stmt = $pdo->prepare("UPDATE cars SET
          brand = ?, model = ?, variant = ?, year = ?, fuel = ?, power_kw = ?, body = ?,
          price = ?, mileage = ?, description = ?, model_3d = ?
        WHERE id = ?");
      $stmt->execute([
        $brand, $model, $variant, $year, $fuel, $power_kw, $body,
        $price, $mileage, $description, $model_3d,
        $id
      ]);
      $success = $success ?: 'Auto bylo upraveno.';
    } else {
      $stmt = $pdo->prepare("INSERT INTO cars
        (brand, model, variant, year, fuel, power_kw, body, price, mileage, description, model_3d)
        VALUES (?,?,?,?,?,?,?,?,?,?,?)");
      $stmt->execute([
        $brand, $model, $variant, $year, $fuel, $power_kw, $body,
        $price, $mileage, $description, $model_3d
      ]);
      $id = (int)$pdo->lastInsertId();
      $success = $success ?: 'Auto bylo přidáno.';
    }

    // refresh values
    $car = [
      'brand'=>$brand,'model'=>$model,'variant'=>$variant,'year'=>$year,'fuel'=>$fuel,'power_kw'=>$power_kw,
      'body'=>$body,'price'=>$price,'mileage'=>$mileage,'description'=>$description,'model_3d'=>$model_3d
    ];
  }
}

// ✅ načteme dostupné GLB soubory (po POST, aby se nový po uploadu hned objevil)
$models = glob(__DIR__ . '/assets/model/*.glb') ?: [];
?>
<!doctype html>
<html lang="cs">
<head>
  <meta charset="utf-8">
  <title><?php echo $id>0 ? 'Upravit auto' : 'Přidat auto'; ?> – Admin</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="style.css?v=<?php echo urlencode(date('YmdHi')); ?>">
</head>
<body class="bg-dark">

<div class="admin-wrap">
  <div class="admin-topbar">
    <div>
      <h1 class="admin-title"><?php echo $id>0 ? 'Upravit auto' : 'Přidat auto'; ?></h1>
      <p class="admin-subtitle">Zde můžeš upravit specifikace, popis i 3D model.</p>
    </div>
    <div class="admin-actions">
      <a class="a-btn" href="admin_cars.php">Zpět na auta</a>
      <a class="a-btn" href="admin.php">Dashboard</a>
    </div>
  </div>

  <div class="admin-card">
    <?php if ($error): ?><div class="error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
    <?php if ($success): ?><div class="success"><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>

    <form method="post" enctype="multipart/form-data" autocomplete="off">
      <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">

      <div class="admin-row">
        <div class="field" style="flex:1; min-width:220px;">
          <label style="color:var(--muted); font-size:13px;">Značka</label>
          <input name="brand" required value="<?php echo htmlspecialchars($car['brand'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
        </div>

        <div class="field" style="flex:1; min-width:220px;">
          <label style="color:var(--muted); font-size:13px;">Model</label>
          <input name="model" required value="<?php echo htmlspecialchars($car['model'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
        </div>

        <div class="field" style="flex:1; min-width:220px;">
          <label style="color:var(--muted); font-size:13px;">Varianta</label>
          <input name="variant" value="<?php echo htmlspecialchars($car['variant'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
        </div>
      </div>

      <div style="height:10px;"></div>

      <div class="admin-row">
        <div class="field">
          <label style="color:var(--muted); font-size:13px;">Rok</label>
          <input type="number" name="year" min="1" value="<?php echo htmlspecialchars((string)($car['year'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
        </div>

        <div class="field">
          <label style="color:var(--muted); font-size:13px;">Palivo</label>
          <select name="fuel">
            <?php
              $fuels = ['', 'Benzín','Nafta','Hybrid','Elektro','LPG','CNG'];
              foreach ($fuels as $f) {
                $sel = (($car['fuel'] ?? '') === $f) ? 'selected' : '';
                $label = $f === '' ? '—' : $f;
                echo "<option value=\"".htmlspecialchars($f, ENT_QUOTES, 'UTF-8')."\" $sel>$label</option>";
              }
            ?>
          </select>
        </div>

        <div class="field">
          <label style="color:var(--muted); font-size:13px;">Výkon (kW)</label>
          <input type="number" name="power_kw" min="1" value="<?php echo htmlspecialchars((string)($car['power_kw'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
        </div>

        <div class="field" style="min-width:180px;">
          <label style="color:var(--muted); font-size:13px;">Karoserie</label>
          <input name="body" value="<?php echo htmlspecialchars($car['body'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
        </div>
      </div>

      <div style="height:10px;"></div>

      <div class="admin-row">
        <div class="field">
          <label style="color:var(--muted); font-size:13px;">Cena (Kč)</label>
          <input type="number" name="price" min="0" value="<?php echo htmlspecialchars((string)($car['price'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
        </div>

        <div class="field">
          <label style="color:var(--muted); font-size:13px;">Nájezd (km)</label>
          <input type="number" name="mileage" min="0" value="<?php echo htmlspecialchars((string)($car['mileage'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
        </div>

        <div class="field" style="flex:1; min-width:260px;">
          <label style="color:var(--muted); font-size:13px;">3D model (GLB)</label>
          <select name="model_3d">
            <option value="">— bez 3D modelu —</option>
            <?php foreach ($models as $path):
              $file = basename($path);
              $val  = '/assets/model/' . $file;
              $sel  = (($car['model_3d'] ?? '') === $val) ? 'selected' : '';
            ?>
              <option value="<?php echo htmlspecialchars($val, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $sel; ?>>
                <?php echo htmlspecialchars($file, ENT_QUOTES, 'UTF-8'); ?>
              </option>
            <?php endforeach; ?>
          </select>

          <div style="margin-top:10px;">
            <label style="color:var(--muted); font-size:13px;">Nahrát nový GLB</label>
            <input type="file" name="glb_upload" accept=".glb">
            <div class="muted" style="font-size:12px;margin-top:6px;">
              Nahraj .glb soubor (max 50 MB). Po uložení se automaticky přiřadí k autu.
            </div>
          </div>

        </div>
      </div>

      <div style="height:10px;"></div>

      <div class="field">
        <label style="color:var(--muted); font-size:13px;">Popis</label>
        <textarea name="description" rows="7" style="width:100%; padding:12px; border-radius:12px; border:1px solid var(--border); background:var(--input-bg); color:var(--text);"><?php
          echo htmlspecialchars($car['description'] ?? '', ENT_QUOTES, 'UTF-8');
        ?></textarea>
      </div>

      <div style="display:flex; gap:10px; margin-top:14px; flex-wrap:wrap;">
        <button class="a-btn primary" type="submit">Uložit změny</button>
        <?php if ($id>0): ?>
          <a class="a-btn" href="car.php?id=<?php echo $id; ?>">Zobrazit detail</a>
        <?php endif; ?>
      </div>

    </form>
  </div>
</div>

</body>
</html>
