<?php
require_once __DIR__ . '/admin_check.php';
require_once __DIR__ . '/db.php';

$q = trim($_GET['q'] ?? '');

$params = [];
$where = "";
if ($q !== '') {
  $where = "WHERE brand LIKE ? OR model LIKE ? OR variant LIKE ?";
  $like = "%$q%";
  $params = [$like, $like, $like];
}

$stmt = $pdo->prepare("SELECT id, brand, model, variant, year, fuel, power_kw, price
                       FROM cars
                       $where
                       ORDER BY id DESC
                       LIMIT 200");
$stmt->execute($params);
$cars = $stmt->fetchAll();

$total = count($cars);
?>
<!doctype html>
<html lang="cs">
<head>
  <meta charset="utf-8">
  <title>Admin – Auta</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="style.css?v=<?php echo urlencode(date('YmdHi')); ?>">
</head>
<body class="bg-dark">

<div class="admin-wrap">

  <div class="admin-topbar">
    <div>
      <h1 class="admin-title">Auta</h1>
      <p class="admin-subtitle">Správa databáze vozidel • Zobrazeno: <strong><?php echo $total; ?></strong></p>
    </div>
    <div class="admin-actions">
      <a class="a-btn" href="admin.php">Dashboard</a>
      <a class="a-btn primary" href="admin_car_edit.php">+ Přidat auto</a>
    </div>
  </div>

  <div class="admin-card">

    <form method="get" class="admin-row" action="admin_cars.php" autocomplete="off">
      <div class="field" style="min-width:280px; flex:1;">
        <label style="color:var(--muted); font-size:13px;">Hledat</label>
        <input name="q" value="<?php echo htmlspecialchars($q, ENT_QUOTES, 'UTF-8'); ?>"
               placeholder="Značka / model / varianta (např. RS6, M4, GT-R)">
      </div>

      <div class="field">
        <label style="color:var(--muted); font-size:13px;">&nbsp;</label>
        <button class="a-btn primary" type="submit">Hledat</button>
      </div>

      <div class="field">
        <label style="color:var(--muted); font-size:13px;">&nbsp;</label>
        <a class="a-btn" href="admin_cars.php">Reset</a>
      </div>
    </form>

    <table class="admin-table">
      <thead>
        <tr>
          <th>ID</th>
          <th>Značka</th>
          <th>Model</th>
          <th>Varianta</th>
          <th>Rok</th>
          <th>Palivo</th>
          <th>kW</th>
          <th>Cena</th>
          <th style="text-align:right;">Akce</th>
        </tr>
      </thead>
      <tbody>
      <?php if (!$cars): ?>
        <tr><td colspan="9" style="color:var(--muted);">Nic nenalezeno.</td></tr>
      <?php endif; ?>

      <?php foreach ($cars as $c): ?>
        <tr>
          <td><?php echo (int)$c['id']; ?></td>
          <td><?php echo htmlspecialchars($c['brand'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
          <td><?php echo htmlspecialchars($c['model'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
          <td><?php echo htmlspecialchars($c['variant'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
          <td><?php echo (int)($c['year'] ?? 0); ?></td>
          <td><?php echo htmlspecialchars($c['fuel'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
          <td><?php echo (int)($c['power_kw'] ?? 0); ?></td>
          <td>
            <?php echo $c['price'] !== null ? number_format((int)$c['price'], 0, ',', ' ') . ' Kč' : '—'; ?>
          </td>
          <td style="text-align:right; white-space:nowrap;">
            <a class="a-btn small" href="car.php?id=<?php echo (int)$c['id']; ?>">Detail</a>
            <a class="a-btn small primary" href="admin_car_edit.php?id=<?php echo (int)$c['id']; ?>">Upravit</a>
            <a class="a-btn small danger"
               href="admin_car_delete.php?id=<?php echo (int)$c['id']; ?>"
               onclick="return confirm('Opravdu smazat auto #<?php echo (int)$c['id']; ?>?');">Smazat</a>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>

  </div>
</div>

</body>
</html>
