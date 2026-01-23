<?php
require __DIR__.'/auth.php';
require __DIR__.'/db.php';

if (!is_logged_in()) {
    header('Location: login.php');
    exit;
}

$user_id = (int)$_SESSION['user']['id'];
$error = '';
$success = '';

check_csrf_post();

/*PROFIL: změna zobrazovaného jména*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_profile') {
    $new_name = trim($_POST['display_name'] ?? '');
    if ($new_name === '') {
        $error = 'Zobrazované jméno nesmí být prázdné.';
    } elseif (mb_strlen($new_name) > 191) {
        $error = 'Zobrazované jméno je příliš dlouhé (max 191 znaků).';
    } else {
        $stmt = $pdo->prepare('UPDATE users SET display_name = ? WHERE id = ?');
        $stmt->execute([$new_name, $user_id]);
        $_SESSION['user']['display_name'] = $new_name;
        $success = 'Jméno bylo aktualizováno.';
    }
}

/*OBLÍBENÉ: přidat/odebrat*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_fav') {
    $car_id = (int)$_POST['car_id'];
    $stmt = $pdo->prepare('SELECT 1 FROM favorites WHERE user_id = ? AND car_id = ?');
    $stmt->execute([$user_id, $car_id]);
    if ($stmt->fetch()) {
        $pdo->prepare('DELETE FROM favorites WHERE user_id = ? AND car_id = ?')->execute([$user_id, $car_id]);
        $success = 'Auto bylo odebráno z oblíbených.';
    } else {
        $pdo->prepare('INSERT INTO favorites (user_id, car_id) VALUES (?, ?)')->execute([$user_id, $car_id]);
        $success = 'Auto bylo přidáno do oblíbených.';
    }
}

/*OBLÍBENÉ: odebrat vše*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'clear_favs') {
    $pdo->prepare('DELETE FROM favorites WHERE user_id = ?')->execute([$user_id]);
    $success = 'Všechna oblíbená auta byla odebrána.';
}

/*Načtení uživatele*/
$stmt = $pdo->prepare('SELECT id, email, display_name, created_at FROM users WHERE id = ?');
$stmt->execute([$user_id]);
$user = $stmt->fetch() ?: $_SESSION['user'];
$display = $user['display_name'] ?: $user['email'];

/*Filtry vyhledávání*/
$q         = trim($_GET['q'] ?? '');
$fuel      = trim($_GET['fuel'] ?? '');
$year_min  = ($_GET['year_min'] ?? '') !== '' ? (int)$_GET['year_min'] : null;
$year_max  = ($_GET['year_max'] ?? '') !== '' ? (int)$_GET['year_max'] : null;
$kw_min    = ($_GET['kw_min'] ?? '') !== '' ? (int)$_GET['kw_min'] : null;
$kw_max    = ($_GET['kw_max'] ?? '') !== '' ? (int)$_GET['kw_max'] : null;
$only_fav  = isset($_GET['only_fav']) && $_GET['only_fav'] === '1';

/*BEZPEČNOST: nepovolit 0 ani záporná čísla (kdyby to někdo obešel přes URL)*/
if ($year_min !== null && $year_min < 1) $year_min = null;
if ($year_max !== null && $year_max < 1) $year_max = null;
if ($kw_min   !== null && $kw_min   < 1) $kw_min   = null;
if ($kw_max   !== null && $kw_max   < 1) $kw_max   = null;

$page     = max(1, (int)($_GET['page'] ?? 1));
$per_page = 10;
$offset   = ($page - 1) * $per_page;

/*Přednačtení oblíbených ID*/
$stmt = $pdo->prepare('SELECT car_id FROM favorites WHERE user_id = ?');
$stmt->execute([$user_id]);
$fav_ids = array_map('intval', array_column($stmt->fetchAll(), 'car_id'));

/*WHERE podmínky*/
$where = [];
$params = [];

if ($q !== '') {
    $where[] = '(c.brand LIKE ? OR c.model LIKE ? OR c.variant LIKE ?)';
    $like = '%'.$q.'%';
    array_push($params, $like, $like, $like);
}
if ($fuel !== '')      { $where[] = 'c.fuel = ?';       $params[] = $fuel; }
if ($year_min !== null){ $where[] = 'c.year >= ?';      $params[] = $year_min; }
if ($year_max !== null){ $where[] = 'c.year <= ?';      $params[] = $year_max; }
if ($kw_min !== null)  { $where[] = 'c.power_kw >= ?';  $params[] = $kw_min; }
if ($kw_max !== null)  { $where[] = 'c.power_kw <= ?';  $params[] = $kw_max; }

$join = '';
if ($only_fav) {
    $join = 'INNER JOIN favorites f ON f.car_id = c.id AND f.user_id = ?';
    array_unshift($params, $user_id);
}

$where_sql = $where ? ('WHERE '.implode(' AND ', $where)) : '';

/*ŘAZENÍ (klik na hlavičku)*/
$allowedSort = [
  'brand'    => 'c.brand',
  'model'    => 'c.model',
  'variant'  => 'c.variant',
  'year'     => 'c.year',
  'fuel'     => 'c.fuel',
  'power_kw' => 'c.power_kw',
  'body'     => 'c.body',
  'price'    => 'c.price',
];

$sort = $_GET['sort'] ?? '';
$dir  = strtolower($_GET['dir'] ?? 'asc');
$dir  = in_array($dir, ['asc', 'desc'], true) ? $dir : 'asc';

$orderBy = "c.created_at DESC, c.id DESC"; // výchozí řazení
if (isset($allowedSort[$sort])) {
    // u ceny: NULL dáme vždy dolů
    if ($sort === 'price') {
        $orderBy = "({$allowedSort[$sort]} IS NULL) ASC, {$allowedSort[$sort]} $dir, c.id DESC";
    } else {
        $orderBy = "{$allowedSort[$sort]} $dir, c.id DESC";
    }
}

/*Count + data*/
$sql_count = "SELECT COUNT(*) AS cnt FROM cars c $join $where_sql";
$stmt = $pdo->prepare($sql_count);
$stmt->execute($params);
$total = (int)$stmt->fetch()['cnt'];
$pages = max(1, (int)ceil($total / $per_page));

$sql = "SELECT c.id, c.brand, c.model, c.variant, c.year, c.fuel, c.power_kw, c.body, c.price, c.mileage
        FROM cars c
        $join
        $where_sql
        ORDER BY $orderBy
        LIMIT $per_page OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$cars = $stmt->fetchAll();

/*helper pro URL stránkování*/
function build_page_url($pageNum) {
    $params = $_GET;
    $params['page'] = $pageNum;
    return 'home.php?'.http_build_query($params);
}

/*helper pro URL řazení*/
function build_sort_url($col) {
    $params = $_GET;

    // při změně řazení začni od 1. stránky
    $params['page'] = 1;

    $currentSort = $params['sort'] ?? '';
    $currentDir  = strtolower($params['dir'] ?? 'asc');

    if ($currentSort === $col) {
        $params['dir'] = ($currentDir === 'asc') ? 'desc' : 'asc';
    } else {
        $params['dir']  = 'asc';
    }

    $params['sort'] = $col;
    return 'home.php?'.http_build_query($params);
}

function sort_arrow($col) {
    $currentSort = $_GET['sort'] ?? '';
    $currentDir  = strtolower($_GET['dir'] ?? '');

    if ($currentSort !== $col) return '';
    return $currentDir === 'asc' ? ' ▲' : ' ▼';
}
?>
<!doctype html>
<html lang="cs">
<head>
  <meta charset="utf-8">
  <title>Portál pro motoristy – Domů</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="style.css?v=<?php echo urlencode(date('YmdHi')); ?>">
  <style>
    .grid{display:grid;gap:16px}
    .section{background:var(--panel);padding:16px;border-radius:12px;box-shadow:var(--shadow)}
    table{width:100%;border-collapse:collapse;margin-top:10px}
    th,td{padding:10px;border-bottom:1px solid var(--border);text-align:left}
    .pagination{display:flex;gap:8px;justify-content:center;margin-top:12px;flex-wrap:wrap}
    .pagination a{padding:6px 10px;border-radius:8px;border:1px solid var(--border);text-decoration:none;color:var(--text)}
    .pagination .active{background:var(--accent);border-color:var(--accent);color:#fff}
    .success{color:#22c55e}
    th a{color:inherit;text-decoration:none}
    th a:hover{text-decoration:underline}
  </style>
</head>
<body class="bg-dark center-screen">

  <!-- VŽDY VIDITELNÉ TLAČÍTKO ODLÁŠENÍ VPRAVO NAHOŘE -->
  <a href="logout.php" class="logout-fixed">Odhlásit se</a>

  <div class="card" style="width:1000px;max-width:95vw">
    <h1>Ahoj, <?php echo htmlspecialchars($display, ENT_QUOTES, 'UTF-8'); ?> 👋</h1>

    <?php if ($success): ?><div class="success"><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
    <?php if ($error): ?><div class="error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>

    <div class="grid">
      <!-- PROFIL -->
      <div class="section">
        <form method="post" action="home.php" autocomplete="off">
          <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
          <input type="hidden" name="action" value="update_profile">
          <div class="row">
            <label for="display_name">Zobrazované jméno</label>
            <input id="display_name" type="text" name="display_name"
                   value="<?php echo htmlspecialchars($user['display_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
          </div>
          <button type="submit">Uložit</button>
          <p class="muted" style="margin-top:8px">
            E-mail: <?php echo htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8'); ?>
            <?php if (!empty($user['created_at'])): ?>
              • účet od: <?php echo htmlspecialchars($user['created_at'], ENT_QUOTES, 'UTF-8'); ?>
            <?php endif; ?>
          </p>
        </form>
      </div>

      <!-- FILTRY + VÝSLEDKY -->
      <div class="section">
        <h2 style="margin-top:0">Vyhledávání sportovních aut</h2>

        <form class="filters" method="get" action="home.php" autocomplete="off">

          <!-- 1. řádek – fulltext -->
          <div class="filters-row filters-row--full">
            <div class="filter-item filter-item--full">
              <label for="q">Hledat</label>
              <input id="q" type="text" name="q"
                     placeholder="Značka, model, varianta (např. GT-R, RS6, M4, 488)"
                     value="<?php echo htmlspecialchars($q, ENT_QUOTES, 'UTF-8'); ?>">
            </div>
          </div>

          <!-- 2. řádek – základní parametry -->
          <div class="filters-row">
            <div class="filter-item">
              <label for="fuel">Palivo</label>
              <select id="fuel" name="fuel">
                <option value="">Vše</option>
                <?php
                $fuels = ['Benzín','Nafta','Hybrid','Elektro','LPG','CNG'];
                foreach ($fuels as $f) {
                  $sel = $fuel === $f ? 'selected' : '';
                  echo '<option '.$sel.'>'.htmlspecialchars($f, ENT_QUOTES, 'UTF-8').'</option>';
                }
                ?>
              </select>
            </div>

            <div class="filter-item">
              <label for="year_min">Rok od</label>
              <input id="year_min" type="number" name="year_min"
                     min="1" step="1" inputmode="numeric"
                     value="<?php echo $year_min !== null ? (int)$year_min : ''; ?>">
            </div>

            <div class="filter-item">
              <label for="year_max">Rok do</label>
              <input id="year_max" type="number" name="year_max"
                     min="1" step="1" inputmode="numeric"
                     value="<?php echo $year_max !== null ? (int)$year_max : ''; ?>">
            </div>

            <div class="filter-item">
              <label for="kw_min">kW od</label>
              <input id="kw_min" type="number" name="kw_min"
                     min="1" step="1" inputmode="numeric"
                     value="<?php echo $kw_min !== null ? (int)$kw_min : ''; ?>">
            </div>
          </div>

          <!-- 3. řádek – kW do, oblíbené, akce -->
          <div class="filters-row filters-row--bottom">
            <div class="filter-item">
              <label for="kw_max">kW do</label>
              <input id="kw_max" type="number" name="kw_max"
                     min="1" step="1" inputmode="numeric"
                     value="<?php echo $kw_max !== null ? (int)$kw_max : ''; ?>">
            </div>

            <div class="filter-item filter-item--checkbox">
              <label>
                <input type="checkbox" name="only_fav" value="1" <?php echo $only_fav ? 'checked' : ''; ?>>
                Pouze oblíbené
              </label>
            </div>

            <div class="filter-item filter-item--actions">
              <button type="submit">Hledat</button>
              <a href="home.php" class="pill pill-outline">Vymazat filtry</a>
            </div>
          </div>

        </form>

        <p class="muted" style="margin-top:10px">
          Nalezeno: <strong><?php echo (int)$total; ?></strong> výsledků
          <?php if ($q): ?> • dotaz: <span class="pill"><?php echo htmlspecialchars($q, ENT_QUOTES, 'UTF-8'); ?></span><?php endif; ?>
          <?php if ($fuel): ?> • palivo: <span class="pill"><?php echo htmlspecialchars($fuel, ENT_QUOTES, 'UTF-8'); ?></span><?php endif; ?>
          <?php if ($only_fav): ?> • <span class="pill">Jen oblíbené</span><?php endif; ?>
        </p>

        <?php if ($cars): ?>
          <table>
            <thead>
              <tr>
                <th><a href="<?php echo htmlspecialchars(build_sort_url('brand'), ENT_QUOTES, 'UTF-8'); ?>">Značka<?php echo sort_arrow('brand'); ?></a></th>
                <th><a href="<?php echo htmlspecialchars(build_sort_url('model'), ENT_QUOTES, 'UTF-8'); ?>">Model<?php echo sort_arrow('model'); ?></a></th>
                <th><a href="<?php echo htmlspecialchars(build_sort_url('variant'), ENT_QUOTES, 'UTF-8'); ?>">Varianta<?php echo sort_arrow('variant'); ?></a></th>
                <th><a href="<?php echo htmlspecialchars(build_sort_url('year'), ENT_QUOTES, 'UTF-8'); ?>">Rok<?php echo sort_arrow('year'); ?></a></th>
                <th><a href="<?php echo htmlspecialchars(build_sort_url('fuel'), ENT_QUOTES, 'UTF-8'); ?>">Palivo<?php echo sort_arrow('fuel'); ?></a></th>
                <th><a href="<?php echo htmlspecialchars(build_sort_url('power_kw'), ENT_QUOTES, 'UTF-8'); ?>">kW<?php echo sort_arrow('power_kw'); ?></a></th>
                <th><a href="<?php echo htmlspecialchars(build_sort_url('body'), ENT_QUOTES, 'UTF-8'); ?>">Karoserie<?php echo sort_arrow('body'); ?></a></th>
                <th><a href="<?php echo htmlspecialchars(build_sort_url('price'), ENT_QUOTES, 'UTF-8'); ?>">Cena<?php echo sort_arrow('price'); ?></a></th>
                <th>Oblíbené</th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ($cars as $c):
                $in_fav = in_array((int)$c['id'], $fav_ids, true);
            ?>
              <tr>
                <td><?php echo htmlspecialchars($c['brand'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td>
                  <a href="car.php?id=<?php echo (int)$c['id']; ?>">
                    <?php echo htmlspecialchars($c['model'], ENT_QUOTES, 'UTF-8'); ?>
                  </a>
                </td>
                <td><?php echo htmlspecialchars($c['variant'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo (int)$c['year']; ?></td>
                <td><?php echo htmlspecialchars($c['fuel'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo (int)$c['power_kw']; ?></td>
                <td><?php echo htmlspecialchars($c['body'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo $c['price'] !== null ? number_format((int)$c['price'], 0, ',', ' ') . ' Kč' : '—'; ?></td>
                <td>
                  <form method="post" style="display:inline">
                    <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="action" value="toggle_fav">
                    <input type="hidden" name="car_id" value="<?php echo (int)$c['id']; ?>">
                    <button class="small" type="submit" style="background:<?php echo $in_fav ? '#dc2626' : '#2563eb'; ?>">
                      <?php echo $in_fav ? 'Odebrat' : 'Přidat'; ?>
                    </button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>

          <!-- Stránkování -->
          <div class="pagination">
            <?php for ($i = 1; $i <= $pages; $i++): ?>
              <a class="<?php echo $i === $page ? 'active' : ''; ?>"
                 href="<?php echo htmlspecialchars(build_page_url($i), ENT_QUOTES, 'UTF-8'); ?>">
                 <?php echo $i; ?>
              </a>
            <?php endfor; ?>
          </div>
        <?php else: ?>
          <p>Žádné výsledky. Zkus upravit filtry.</p>
        <?php endif; ?>
      </div>

      <!-- MOJE OBLÍBENÉ (rychlý přehled) -->
      <div class="section">
        <h3 style="margin:0 0 8px 0">Moje oblíbená auta ❤️</h3>

        <?php if ($fav_ids): ?>
          <form method="post" style="margin-bottom:10px">
            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="action" value="clear_favs">
            <button type="submit"
                    onclick="return confirm('Opravdu chceš odebrat všechna oblíbená auta?');"
                    style="background:#991b1b;color:white;border:0;padding:8px 14px;border-radius:10px;cursor:pointer;">
              Odebrat vše
            </button>
          </form>
        <?php endif; ?>

        <?php
        if ($fav_ids) {
            $in = implode(',', array_fill(0, count($fav_ids), '?'));
            $stmt = $pdo->prepare("SELECT id, brand, model, variant, year FROM cars WHERE id IN ($in) ORDER BY brand, model");
            $stmt->execute($fav_ids);
            $favs = $stmt->fetchAll();
            foreach ($favs as $f) {
                echo '<div style="display:flex; justify-content:space-between; align-items:center; padding:6px 0;">';

                echo '<span>'
                    . htmlspecialchars($f['brand'].' '.$f['model'].' '.($f['variant'] ?? '').' ('.$f['year'].')', ENT_QUOTES, 'UTF-8')
                    . '</span>';

                echo '<form method="post" style="margin:0;">'
                   . '<input type="hidden" name="csrf" value="'.htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8').'">'
                   . '<input type="hidden" name="action" value="toggle_fav">'
                   . '<input type="hidden" name="car_id" value="'.(int)$f['id'].'">'
                   . '<button class="small" type="submit" style="background:#dc2626;color:white;border:0;padding:6px 10px;border-radius:8px;cursor:pointer;">Odebrat</button>'
                   . '</form>';

                echo '</div>';
            }
        } else {
            echo '<p class="muted">Nemáš žádné oblíbené auto.</p>';
        }
        ?>
      </div>
    </div>
  </div>
</body>
</html>
