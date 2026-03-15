<?php
require __DIR__.'/auth.php';
require __DIR__.'/db.php';

if (!is_logged_in()) {
    header('Location: login.php');
    exit;
}

$user_id = (int)$_SESSION['user']['id'];
$error   = '';
$success = '';

check_csrf_post();

/* helper: je to AJAX? */
function is_ajax_request(): bool {
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        return true;
    }
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    return strpos($accept, 'application/json') !== false;
}

/* helper: render HTML pro "Moje oblíbená auta" + vrátí i seznam ID */
function render_favs_html(PDO $pdo, int $user_id): array {
    $stmt = $pdo->prepare('SELECT car_id FROM favorites WHERE user_id = ?');
    $stmt->execute([$user_id]);
    $fav_ids = array_map('intval', array_column($stmt->fetchAll(), 'car_id'));

    if (!$fav_ids) {
        return ['fav_ids' => [], 'html' => '<p class="muted">Nemáš žádné oblíbené auto.</p>'];
    }

    $in = implode(',', array_fill(0, count($fav_ids), '?'));
    $stmt = $pdo->prepare("SELECT id, brand, model, variant, year FROM cars WHERE id IN ($in) ORDER BY brand, model");
    $stmt->execute($fav_ids);
    $favs = $stmt->fetchAll();

    ob_start();
    ?>
    <form method="post" class="js-clear-favs" style="margin-bottom:10px">
        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="action" value="clear_favs">
        <button type="submit"
                onclick="return confirm('Opravdu chceš odebrat všechna oblíbená auta?');"
                style="background:#991b1b;color:white;border:0;padding:8px 14px;border-radius:10px;cursor:pointer;">
            Odebrat vše
        </button>
    </form>

    <?php foreach ($favs as $f): ?>
        <div style="display:flex; justify-content:space-between; align-items:center; padding:6px 0;">
            <span>
                <?php echo htmlspecialchars($f['brand'].' '.$f['model'].' '.($f['variant'] ?? '').' ('.$f['year'].')', ENT_QUOTES, 'UTF-8'); ?>
            </span>

            <form method="post" class="js-fav-form" style="margin:0;">
                <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="action" value="toggle_fav">
                <input type="hidden" name="car_id" value="<?php echo (int)$f['id']; ?>">
                <button class="small fav-btn is-on" type="submit"
                        title="Odebrat z oblíbených"
                        style="background:#dc2626;color:white;border:0;padding:6px 10px;border-radius:8px;cursor:pointer;">
                    ♥
                </button>
            </form>
        </div>
    <?php endforeach; ?>
    <?php
    $html = ob_get_clean();
    return ['fav_ids' => $fav_ids, 'html' => $html];
}

/* PROFIL: změna zobrazovaného jména */
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

/* OBLÍBENÉ: přidat/odebrat */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_fav') {
    $car_id = (int)($_POST['car_id'] ?? 0);

    if ($car_id > 0) {
        $stmt = $pdo->prepare('SELECT 1 FROM favorites WHERE user_id = ? AND car_id = ?');
        $stmt->execute([$user_id, $car_id]);

        $in_fav = false;
        if ($stmt->fetch()) {
            $pdo->prepare('DELETE FROM favorites WHERE user_id = ? AND car_id = ?')->execute([$user_id, $car_id]);
            $in_fav = false;
        } else {
            $pdo->prepare('INSERT INTO favorites (user_id, car_id) VALUES (?, ?)')->execute([$user_id, $car_id]);
            $in_fav = true;
        }

        // AJAX: žádná hláška, žádný refresh, vrátíme nový stav + HTML oblíbených
        if (is_ajax_request()) {
            $render = render_favs_html($pdo, $user_id);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'ok'       => true,
                'action'   => 'toggle_fav',
                'car_id'   => $car_id,
                'in_fav'   => $in_fav,
                'fav_ids'  => $render['fav_ids'],
                'fav_html' => $render['html'],
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // fallback: refresh, ale bez hlášek (zůstane stejný query string)
        $qs = $_SERVER['QUERY_STRING'] ?? '';
        header('Location: home.php' . ($qs ? ('?'.$qs) : ''));
        exit;
    }
}

/* OBLÍBENÉ: odebrat vše */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'clear_favs') {
    $pdo->prepare('DELETE FROM favorites WHERE user_id = ?')->execute([$user_id]);

    if (is_ajax_request()) {
        $render = render_favs_html($pdo, $user_id);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok'       => true,
            'action'   => 'clear_favs',
            'fav_ids'  => $render['fav_ids'],
            'fav_html' => $render['html'],
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $qs = $_SERVER['QUERY_STRING'] ?? '';
    header('Location: home.php' . ($qs ? ('?'.$qs) : ''));
    exit;
}

/* Načtení uživatele */
$stmt = $pdo->prepare('SELECT id, email, display_name, created_at FROM users WHERE id = ?');
$stmt->execute([$user_id]);
$user = $stmt->fetch() ?: $_SESSION['user'];
$display = $user['display_name'] ?: $user['email'];

/* Filtry vyhledávání */
$q        = trim($_GET['q'] ?? '');
$fuel     = trim($_GET['fuel'] ?? '');
$year_min = ($_GET['year_min'] ?? '') !== '' ? (int)$_GET['year_min'] : null;
$year_max = ($_GET['year_max'] ?? '') !== '' ? (int)$_GET['year_max'] : null;
$kw_min   = ($_GET['kw_min'] ?? '') !== '' ? (int)$_GET['kw_min'] : null;
$kw_max   = ($_GET['kw_max'] ?? '') !== '' ? (int)$_GET['kw_max'] : null;
$only_fav = isset($_GET['only_fav']) && $_GET['only_fav'] === '1';

/* zobrazení: table | grid */
$view = $_GET['view'] ?? 'table';
$view = in_array($view, ['table','grid'], true) ? $view : 'table';

/* BEZPEČNOST: nepovolit 0 ani záporná čísla */
if ($year_min !== null && $year_min < 1) $year_min = null;
if ($year_max !== null && $year_max < 1) $year_max = null;
if ($kw_min   !== null && $kw_min   < 1) $kw_min   = null;
if ($kw_max   !== null && $kw_max   < 1) $kw_max   = null;

$page     = max(1, (int)($_GET['page'] ?? 1));
$per_page = 10;
$offset   = ($page - 1) * $per_page;

/* Přednačtení oblíbených + HTML box (sjednocené s AJAXem) */
$fav_render = render_favs_html($pdo, $user_id);
$fav_ids    = $fav_render['fav_ids'];
$fav_box_html = $fav_render['html'];

/* WHERE podmínky */
$where  = [];
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

/* ŘAZENÍ */
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

$orderBy = "c.created_at DESC, c.id DESC";
if (isset($allowedSort[$sort])) {
    if ($sort === 'price') {
        $orderBy = "({$allowedSort[$sort]} IS NULL) ASC, {$allowedSort[$sort]} $dir, c.id DESC";
    } else {
        $orderBy = "{$allowedSort[$sort]} $dir, c.id DESC";
    }
}

/* Count + data */
$sql_count = "SELECT COUNT(*) AS cnt FROM cars c $join $where_sql";
$stmt = $pdo->prepare($sql_count);
$stmt->execute($params);
$total = (int)$stmt->fetch()['cnt'];
$pages = max(1, (int)ceil($total / $per_page));

/* DATA */
$sql = "SELECT c.id, c.brand, c.model, c.variant, c.year, c.fuel, c.power_kw, c.body, c.price, c.mileage, c.model_3d
        FROM cars c
        $join
        $where_sql
        ORDER BY $orderBy
        LIMIT $per_page OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$cars = $stmt->fetchAll();

/* base URL projektu (stejné jako v car.php) */
$baseUrl = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');

/* helper pro URL stránkování */
function build_page_url($pageNum) {
    $params = $_GET;
    $params['page'] = $pageNum;
    return 'home.php?'.http_build_query($params);
}

/* helper pro URL řazení */
function build_sort_url($col) {
    $params = $_GET;
    $params['page'] = 1;

    $currentSort = $params['sort'] ?? '';
    $currentDir  = strtolower($params['dir'] ?? 'asc');

    if ($currentSort === $col) {
        $params['dir'] = ($currentDir === 'asc') ? 'desc' : 'asc';
    } else {
        $params['dir'] = 'asc';
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

function model_badge_text($brand, $model) {
    $b = mb_strtoupper(mb_substr(trim((string)$brand), 0, 1));
    $m = mb_strtoupper(mb_substr(trim((string)$model), 0, 1));
    return ($b ?: '•') . ($m ?: '');
}

/* bezpečné složení URL pro model_3d (stejná filozofie jako car.php) */
function build_model_src($baseUrl, $modelPath) {
    $modelPath = trim((string)$modelPath);
    if ($modelPath === '') return '';
    if (!preg_match('~\.glb$~i', $modelPath)) return '';
    if (strpos($modelPath, '..') !== false) return '';
    return $baseUrl . '/' . ltrim($modelPath, '/\\');
}
?>
<!doctype html>
<html lang="cs">
<head>
  <meta charset="utf-8">
  <title>Portál pro motoristy – Domů</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="style.css?v=<?php echo urlencode(date('YmdHi')); ?>">

  <!-- model-viewer -->
  <script type="module" src="https://unpkg.com/@google/model-viewer/dist/model-viewer.min.js"></script>
  <script nomodule src="https://unpkg.com/@google/model-viewer/dist/model-viewer-legacy.js"></script>

  <style>
    .grid{display:grid;gap:16px}
    .section{background:var(--panel);padding:16px;border-radius:12px;box-shadow:var(--shadow)}
    .success{color:#22c55e}

    .results-toolbar{
      display:flex; justify-content:space-between; align-items:flex-start;
      gap:12px; margin: 10px 0 12px 0; flex-wrap:wrap;
    }
    .results-meta{display:flex; flex-direction:column; gap:8px}
    .results-count{font-size:14px; color:var(--muted)}
    .results-tags{display:flex; gap:8px; flex-wrap:wrap}

    .view-toggle{
      display:inline-flex;
      border:1px solid rgba(148,163,184,.18);
      background: rgba(2,6,23,.22);
      border-radius:999px;
      padding:4px;
      gap:4px;
    }
    .view-btn{
      width:auto;
      padding:8px 12px;
      border-radius:999px;
      border:0;
      background:transparent;
      box-shadow:none;
      color:var(--text);
      font-weight:650;
      cursor:pointer;
    }
    .view-btn:hover{ background: rgba(255,255,255,.06); transform:none; box-shadow:none; filter:none; }
    .view-btn.is-active{
      background: rgba(37,99,235,.22);
      border: 1px solid rgba(37,99,235,.40);
    }

    .cars-table{
      width:100%;
      border-collapse:separate;
      border-spacing:0;
      margin-top:6px;
      overflow:hidden;
      border-radius:14px;
      border:1px solid rgba(148,163,184,.14);
    }
    .cars-table thead th{
      background: rgba(2,6,23,.35);
      color: var(--muted);
      font-size: 13px;
      font-weight: 650;
    }
    .cars-table th,.cars-table td{
      padding: 12px 10px;
      border-bottom: 1px solid rgba(148,163,184,.12);
      text-align:left;
    }
    .cars-table tbody tr:hover{ background: rgba(255,255,255,.03); }
    .fav-cell{ width:1%; white-space:nowrap; text-align:center; }

    .fav-btn{
      width:auto;
      padding:6px 10px;
      border-radius:999px;
      border:1px solid rgba(148,163,184,.18);
      background: rgba(2,6,23,.25);
      box-shadow:none;
      font-size:16px;
      line-height:1;
      cursor:pointer;
    }
    .fav-btn:hover{ transform:none; box-shadow:none; filter:none; background: rgba(255,255,255,.06); }
    .fav-btn.is-on{ border-color: rgba(239,68,68,.35); background: rgba(239,68,68,.12); }

    tr.row-link { cursor: pointer; }
    tr.row-link:hover { background: rgba(255,255,255,.04); }

    /* GRID */
    .cars-grid{
      display:grid;
      grid-template-columns: repeat(3, minmax(0, 1fr));
      gap:14px;
      margin-top:6px;
    }
    @media (max-width: 1100px){ .cars-grid{ grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    @media (max-width: 700px){ .cars-grid{ grid-template-columns: 1fr; } }

    .car-card{
      position:relative;
      border-radius:16px;
      border:1px solid rgba(148,163,184,.14);
      background: rgba(2,6,23,.20);
      box-shadow: 0 10px 30px rgba(0,0,0,.25);
      overflow:hidden;
      cursor:pointer;
    }
    .car-card:hover{ border-color: rgba(59,130,246,.35); box-shadow: 0 18px 44px rgba(0,0,0,.35); }

    .car-card__top{
      display:flex;
      justify-content:space-between;
      align-items:center;
      padding:12px 12px 0 12px;
      gap: 10px;
    }

    .car-badge{
      width:54px; height:54px;
      border-radius:16px;
      display:grid;
      place-items:center;
      font-weight:800;
      letter-spacing:.6px;
      background: radial-gradient(circle at 30% 20%, rgba(59,130,246,.42), rgba(37,99,235,.18));
      border: 1px solid rgba(37,99,235,.25);
      flex: 0 0 auto;
    }

    model-viewer.car-3d{
      width: 160px;
      height: 90px;
      border-radius: 14px;
      background: rgba(2,6,23,.30);
      border: 1px solid rgba(148,163,184,.14);
      overflow: hidden;
      flex: 1 1 auto;
      max-width: 240px;
      pointer-events: none; /* aby model neblokoval klik */
    }

    .car-3d-fallback{ pointer-events:none; }
    .car-fav{ flex: 0 0 auto; }

    .car-card__main{ padding:12px; }
    .car-title{ display:flex; gap:10px; align-items:baseline; flex-wrap:wrap; }
    .car-brand{ color:var(--muted); font-size:13px; font-weight:650; }
    .car-model{ font-size:18px; font-weight:800; letter-spacing:-.3px; }
    .car-variant{ margin-top:6px; color: rgba(226,232,240,.92); font-size:13.5px; }

    .car-specs{
      margin-top:10px;
      display:flex;
      flex-wrap:wrap;
      gap:8px;
      color:var(--muted);
      font-size:13px;
    }
    .car-specs span{
      padding:4px 10px;
      border-radius:999px;
      border:1px solid rgba(148,163,184,.14);
      background: rgba(2,6,23,.25);
    }
    .car-price{ margin-top:12px; font-size:16px; font-weight:800; }

    .pagination{display:flex;gap:8px;justify-content:center;margin-top:12px;flex-wrap:wrap}
    .pagination a{padding:6px 10px;border-radius:8px;border:1px solid var(--border);text-decoration:none;color:var(--text)}
    .pagination .active{background:var(--accent);border-color:var(--accent);color:#fff}

    th a{color:inherit;text-decoration:none}
    th a:hover{text-decoration:underline}
  </style>
</head>

<body class="bg-dark center-screen">
  <a href="logout.php" class="logout-fixed">Odhlásit se</a>
  <?php if (!empty($_SESSION['user']['is_admin'])): ?>
    <a href="admin.php"
       class="logout-fixed"
       style="top:60px;background:#16a34a;box-shadow:0 6px 20px rgba(22,163,74,.45);">
      Administrace
    </a>
  <?php endif; ?>

  <div class="card" style="width:1000px;max-width:95vw">
    <h1>Ahoj, <?php echo htmlspecialchars($display, ENT_QUOTES, 'UTF-8'); ?> 👋</h1>
<nav class="top-nav" style="margin:10px 0 18px 0; display:flex; gap:10px; flex-wrap:wrap;">
  <a class="pill pill-outline" href="home.php">Auta</a>
  <a class="pill pill-outline" href="articles.php">Články</a>
  <a class="pill pill-outline" href="ads.php">Inzeráty</a>

  <?php if (is_logged_in()): ?>
    <a class="pill" href="my_ads.php">Moje inzeráty</a>
    <a class="pill" href="ad_new.php">Přidat inzerát</a>
  <?php endif; ?>
</nav>
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
          <input type="hidden" name="view" value="<?php echo htmlspecialchars($view, ENT_QUOTES, 'UTF-8'); ?>">

          <div class="filters-row filters-row--full">
            <div class="filter-item filter-item--full">
              <label for="q">Hledat</label>
              <input id="q" type="text" name="q"
                     placeholder="Značka, model, varianta (např. GT-R, RS6, M4, 488)"
                     value="<?php echo htmlspecialchars($q, ENT_QUOTES, 'UTF-8'); ?>">
            </div>
          </div>

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
              <input id="year_min" type="number" name="year_min" min="1" step="1" inputmode="numeric"
                     value="<?php echo $year_min !== null ? (int)$year_min : ''; ?>">
            </div>

            <div class="filter-item">
              <label for="year_max">Rok do</label>
              <input id="year_max" type="number" name="year_max" min="1" step="1" inputmode="numeric"
                     value="<?php echo $year_max !== null ? (int)$year_max : ''; ?>">
            </div>

            <div class="filter-item">
              <label for="kw_min">kW od</label>
              <input id="kw_min" type="number" name="kw_min" min="1" step="1" inputmode="numeric"
                     value="<?php echo $kw_min !== null ? (int)$kw_min : ''; ?>">
            </div>
          </div>

          <div class="filters-row filters-row--bottom">
            <div class="filter-item">
              <label for="kw_max">kW do</label>
              <input id="kw_max" type="number" name="kw_max" min="1" step="1" inputmode="numeric"
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

        <div class="results-toolbar">
          <div class="results-meta">
            <div class="results-count">
              Nalezeno: <strong><?php echo (int)$total; ?></strong> výsledků
            </div>
            <div class="results-tags">
              <?php if ($q): ?> <span class="pill"><?php echo htmlspecialchars($q, ENT_QUOTES, 'UTF-8'); ?></span><?php endif; ?>
              <?php if ($fuel): ?> <span class="pill"><?php echo htmlspecialchars($fuel, ENT_QUOTES, 'UTF-8'); ?></span><?php endif; ?>
              <?php if ($only_fav): ?> <span class="pill">Jen oblíbené</span><?php endif; ?>
            </div>
          </div>

          <div class="results-actions">
            <div class="view-toggle" role="group" aria-label="Zobrazení výsledků">
              <button type="button" class="view-btn <?php echo $view==='table'?'is-active':''; ?>" data-view="table">Tabulka</button>
              <button type="button" class="view-btn <?php echo $view==='grid'?'is-active':''; ?>" data-view="grid">Mřížka</button>
            </div>
          </div>
        </div>

        <?php if ($cars): ?>

          <?php if ($view === 'table'): ?>
            <table class="cars-table">
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
                  <th>❤️</th>
                </tr>
              </thead>

              <tbody>
              <?php foreach ($cars as $c):
                  $in_fav = in_array((int)$c['id'], $fav_ids, true);
              ?>
                <tr class="row-link" data-href="car.php?id=<?php echo (int)$c['id']; ?>">
                  <td><?php echo htmlspecialchars($c['brand'], ENT_QUOTES, 'UTF-8'); ?></td>
                  <td><?php echo htmlspecialchars($c['model'], ENT_QUOTES, 'UTF-8'); ?></td>
                  <td><?php echo htmlspecialchars($c['variant'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                  <td><?php echo (int)$c['year']; ?></td>
                  <td><?php echo htmlspecialchars($c['fuel'], ENT_QUOTES, 'UTF-8'); ?></td>
                  <td><?php echo (int)$c['power_kw']; ?></td>
                  <td><?php echo htmlspecialchars($c['body'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                  <td><?php echo $c['price'] !== null ? number_format((int)$c['price'], 0, ',', ' ') . ' Kč' : '—'; ?></td>

                  <td class="fav-cell">
                    <form method="post" class="js-fav-form" style="display:inline">
                      <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                      <input type="hidden" name="action" value="toggle_fav">
                      <input type="hidden" name="car_id" value="<?php echo (int)$c['id']; ?>">
                      <button class="fav-btn <?php echo $in_fav ? 'is-on' : ''; ?>" type="submit"
                              title="<?php echo $in_fav ? 'Odebrat z oblíbených' : 'Přidat do oblíbených'; ?>">
                        <?php echo $in_fav ? '♥' : '♡'; ?>
                      </button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>

          <?php else: ?>
            <div class="cars-grid">
              <?php foreach ($cars as $c):
                  $in_fav   = in_array((int)$c['id'], $fav_ids, true);
                  $badge    = model_badge_text($c['brand'], $c['model']);
                  $title    = trim($c['brand'].' '.$c['model'].' '.($c['variant'] ?? ''));
                  $modelSrc = build_model_src($baseUrl, $c['model_3d'] ?? '');
              ?>
                <div class="car-card" data-href="car.php?id=<?php echo (int)$c['id']; ?>" tabindex="0" aria-label="<?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?>">
                  <div class="car-card__top">
                    <div class="car-badge" aria-hidden="true"><?php echo htmlspecialchars($badge, ENT_QUOTES, 'UTF-8'); ?></div>

                    <?php if ($modelSrc !== ''): ?>
                      <model-viewer
                        class="car-3d js-mv"
                        data-src="<?php echo htmlspecialchars($modelSrc, ENT_QUOTES, 'UTF-8'); ?>"
                        camera-controls
                        disable-zoom
                        auto-rotate
                        interaction-prompt="none"
                        loading="lazy"
                        exposure="1.0">
                      </model-viewer>
                    <?php else: ?>
                      <div class="car-badge car-3d-fallback" style="width:160px;height:90px;border-radius:14px;opacity:.6;font-weight:700">
                        3D —
                      </div>
                    <?php endif; ?>

                    <form method="post" class="car-fav js-fav-form">
                      <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                      <input type="hidden" name="action" value="toggle_fav">
                      <input type="hidden" name="car_id" value="<?php echo (int)$c['id']; ?>">
                      <button class="fav-btn <?php echo $in_fav ? 'is-on' : ''; ?>" type="submit" title="Oblíbené">
                        <?php echo $in_fav ? '♥' : '♡'; ?>
                      </button>
                    </form>
                  </div>

                  <div class="car-card__main">
                    <div class="car-title">
                      <div class="car-brand"><?php echo htmlspecialchars($c['brand'], ENT_QUOTES, 'UTF-8'); ?></div>
                      <div class="car-model"><?php echo htmlspecialchars($c['model'], ENT_QUOTES, 'UTF-8'); ?></div>
                    </div>

                    <div class="car-variant">
                      <?php echo htmlspecialchars($c['variant'] ?? '—', ENT_QUOTES, 'UTF-8'); ?>
                    </div>

                    <div class="car-specs">
                      <span><?php echo (int)$c['year']; ?></span>
                      <span><?php echo htmlspecialchars($c['fuel'], ENT_QUOTES, 'UTF-8'); ?></span>
                      <span><?php echo (int)$c['power_kw']; ?> kW</span>
                      <span><?php echo htmlspecialchars($c['body'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>

                    <div class="car-price">
                      <?php echo $c['price'] !== null ? number_format((int)$c['price'], 0, ',', ' ') . ' Kč' : '—'; ?>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>

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

      <!-- MOJE OBLÍBENÉ -->
      <div class="section">
        <h3 style="margin:0 0 8px 0">Moje oblíbená auta ❤️</h3>
        <div id="favBox">
          <?php echo $fav_box_html; ?>
        </div>
      </div>

    </div>
  </div>

  <script>
  (function(){
    const KEY = 'cars_view';
    const btns = document.querySelectorAll('.view-btn');
    if (!btns.length) return;

    function setParam(url, key, val){
      const u = new URL(url, window.location.origin);
      u.searchParams.set(key, val);
      u.searchParams.set('page', '1');
      return u.pathname + '?' + u.searchParams.toString();
    }

    const url = new URL(window.location.href);
    const hasView = url.searchParams.has('view');
    const saved = localStorage.getItem(KEY);

    if (!hasView && (saved === 'grid' || saved === 'table')) {
      window.location.replace(setParam(window.location.href, 'view', saved));
      return;
    }

    btns.forEach(b => {
      b.addEventListener('click', () => {
        const v = b.dataset.view;
        if (v !== 'grid' && v !== 'table') return;
        localStorage.setItem(KEY, v);

        const hidden = document.querySelector('form.filters input[name="view"]');
        if (hidden) hidden.value = v;

        window.location.href = setParam(window.location.href, 'view', v);
      });
    });
  })();
  </script>

  <script>
  (function(){
    // klik na řádek tabulky -> detail
    document.querySelectorAll('tr.row-link').forEach(row => {
      row.addEventListener('click', (e) => {
        // když klikne na srdce/form, neotvírat detail
        if (e.target.closest('button, form, input, label')) return;
        const href = row.dataset.href;
        if (href) window.location.href = href;
      });
    });

    // klik na kartu v mřížce -> detail (srdce je výjimka)
    document.querySelectorAll('.car-card[data-href]').forEach(card => {
      const go = () => { const href = card.dataset.href; if (href) window.location.href = href; };

      card.addEventListener('click', (e) => {
        if (e.target.closest('.car-fav, .car-fav *')) return;
        go();
      });

      card.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') go();
      });
    });

    // lazy-load model-viewer v mřížce (aby se to nesekalo)
    const els = document.querySelectorAll('model-viewer.js-mv[data-src]');
    if (els.length) {
      const io = new IntersectionObserver((entries, obs) => {
        entries.forEach(ent => {
          if (!ent.isIntersecting) return;
          const mv = ent.target;
          const src = mv.getAttribute('data-src');
          if (src && !mv.getAttribute('src')) mv.setAttribute('src', src);
          obs.unobserve(mv);
        });
      }, { rootMargin: '250px 0px' });

      els.forEach(mv => io.observe(mv));
    }
  })();
  </script>

  <script>
  (function(){
    async function postForm(form){
      const fd = new FormData(form);
      const res = await fetch(form.getAttribute('action') || window.location.href, {
        method: 'POST',
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'Accept': 'application/json'
        },
        body: fd
      });
      return await res.json();
    }

    function setHeartState(carId, inFav){
      document.querySelectorAll('form.js-fav-form').forEach(f => {
        const input = f.querySelector('input[name="car_id"]');
        if (!input) return;
        if (parseInt(input.value, 10) !== carId) return;

        const btn = f.querySelector('button');
        if (!btn) return;

        btn.classList.toggle('is-on', !!inFav);
        btn.textContent = inFav ? '♥' : '♡';
        btn.title = inFav ? 'Odebrat z oblíbených' : 'Přidat do oblíbených';
      });
    }

    function replaceFavBox(html){
      const box = document.getElementById('favBox');
      if (box) box.innerHTML = html;
    }

    document.addEventListener('submit', async (e) => {
      const form = e.target;
      if (!form.classList.contains('js-fav-form') && !form.classList.contains('js-clear-favs')) return;

      e.preventDefault();

      try {
        const data = await postForm(form);
        if (!data || !data.ok) return;

        if (data.action === 'toggle_fav') {
          setHeartState(parseInt(data.car_id, 10), !!data.in_fav);
        }
        if (data.fav_html) {
          replaceFavBox(data.fav_html);
        }
      } catch (err) {
        console.error(err);
      }
    });
  })();
  </script>

</body>
</html>