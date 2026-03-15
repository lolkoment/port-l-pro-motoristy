<?php
require_once __DIR__ . '/admin_check.php';
require_once __DIR__ . '/db.php';

function table_count(PDO $pdo, string $table): int {
  try {
    return (int)$pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
  } catch (Throwable $e) {
    return 0; // tabulka neexistuje → 0
  }
}

$carsCount     = table_count($pdo, 'cars');
$articlesCount = table_count($pdo, 'articles');
$adsCount      = table_count($pdo, 'ads');

$page = basename($_SERVER['PHP_SELF']);
?>
<!doctype html>
<html lang="cs">
<head>
  <meta charset="utf-8">
  <title>Administrace – Portál pro motoristy</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="style.css?v=<?php echo urlencode(date('YmdHi')); ?>">

  <style>
    /* malé vylepšení layoutu jen pro admin dashboard */
    .admin-grid {
      display:grid;
      grid-template-columns: 320px 1fr;
      gap:16px;
      align-items:start;
    }
    @media (max-width: 920px){
      .admin-grid { grid-template-columns: 1fr; }
    }

    .admin-card h2,.admin-card h3 { letter-spacing:.2px; }
    .admin-divider { height:1px; background:rgba(255,255,255,.08); margin:16px 0; border:0; }

    .admin-nav a{
      display:flex;
      gap:10px;
      align-items:center;
      padding:10px 12px;
      border-radius:12px;
      text-decoration:none;
      color:var(--text);
      border:1px solid rgba(255,255,255,.08);
      background:rgba(0,0,0,.12);
      margin-bottom:10px;
      transition:.15s;
    }
    .admin-nav a:hover{
      transform: translateY(-1px);
      border-color: rgba(255,255,255,.18);
    }
    .admin-nav a.active{
      border-color: rgba(37,99,235,.55);
      background: rgba(37,99,235,.16);
    }

    .kpis {
      display:grid;
      grid-template-columns: 1fr;
      gap:10px;
      margin-top:14px;
    }
    .kpi{
      border:1px solid rgba(255,255,255,.08);
      border-radius:14px;
      padding:12px;
      background: rgba(0,0,0,.10);
      display:flex;
      justify-content:space-between;
      align-items:flex-end;
    }
    .kpi .label{ color:var(--muted); font-size:12px; }
    .kpi .value{ font-size:22px; font-weight:800; line-height:1; }

    .quick-actions{
      display:grid;
      grid-template-columns: 1fr 1fr;
      gap:12px;
      margin-top:12px;
    }
    @media (max-width: 700px){
      .quick-actions{ grid-template-columns: 1fr; }
    }
    .action-card{
      border:1px solid rgba(255,255,255,.08);
      border-radius:16px;
      padding:14px;
      background: rgba(0,0,0,.10);
    }
    .action-card h3{ margin:0 0 6px 0; }
    .action-card p{ margin:0; color:var(--muted); font-size:13px; line-height:1.45; }

    .btn-row{ display:flex; gap:10px; flex-wrap:wrap; margin-top:12px; }
  </style>
</head>

<body class="bg-dark">
<div class="admin-wrap">

  <div class="admin-topbar">
    <div>
      <h1 class="admin-title">Administrace</h1>
      <p class="admin-subtitle">Správa obsahu portálu (auta / články / inzeráty)</p>
    </div>

    <div class="admin-actions">
      <a class="a-btn primary" href="home.php">Zpět na web</a>
      <a class="a-btn danger" href="logout.php">Odhlásit se</a>
    </div>
  </div>

  <div class="admin-grid">

    <!-- levý panel -->
    <div class="admin-card">
      <h3 style="margin:0 0 10px 0;">Menu</h3>

      <div class="admin-nav">
        <a class="<?php echo $page==='admin.php' ? 'active' : ''; ?>" href="admin.php">📌 Dashboard</a>
        <a class="<?php echo $page==='admin_cars.php' ? 'active' : ''; ?>" href="admin_cars.php">🚗 Auta</a>
        <a class="<?php echo $page==='admin_articles.php' ? 'active' : ''; ?>" href="admin_articles.php">📰 Články</a>
        <a class="<?php echo $page==='admin_ads.php' ? 'active' : ''; ?>" href="admin_ads.php">📣 Inzeráty</a>
      </div>

      <hr class="admin-divider">

      <h3 style="margin:0 0 10px 0;">Přehled</h3>
      <div class="kpis">
        <div class="kpi">
          <div>
            <div class="label">Počet aut</div>
            <div class="value"><?php echo (int)$carsCount; ?></div>
          </div>
          <div class="label">🚗</div>
        </div>

        <div class="kpi">
          <div>
            <div class="label">Články</div>
            <div class="value"><?php echo (int)$articlesCount; ?></div>
          </div>
          <div class="label">📰</div>
        </div>

        <div class="kpi">
          <div>
            <div class="label">Inzeráty</div>
            <div class="value"><?php echo (int)$adsCount; ?></div>
          </div>
          <div class="label">📣</div>
        </div>
      </div>

      <p class="admin-subtitle" style="margin-top:14px;">
        Tip: Nejčastěji budeš používat sekci <strong>Auta</strong>.
      </p>
    </div>

    <!-- hlavní panel -->
    <div class="admin-card">
      <h2 style="margin:0;">Dashboard</h2>
      <p class="admin-subtitle" style="margin-top:6px;">
        Rychlé akce a stav obsahu.
      </p>

      <div class="quick-actions">
        <div class="action-card">
          <h3>🚗 Správa aut</h3>
          <p>Přidávej, upravuj parametry, popisy a vybírej/nahrávej 3D modely (GLB).</p>
          <div class="btn-row">
            <a class="a-btn primary" href="admin_car_edit.php">+ Přidat auto</a>
            <a class="a-btn" href="admin_cars.php">Seznam aut</a>
          </div>
        </div>

        <div class="action-card">
          <h3>📰 Správa článků</h3>
          <p>Přidávej a upravuj články, perex a obsah. Články pak zobrazíš i veřejně na webu.</p>
          <div class="btn-row">
            <a class="a-btn primary" href="article_new.php">+ Přidat článek</a>
            <a class="a-btn" href="admin_articles.php">Seznam článků</a>
          </div>
        </div>

        <div class="action-card">
          <h3>🔧 Rychlá kontrola</h3>
          <p>Když něco „nejde“, zkontroluj, že modely jsou v <code>/assets/model</code> a DB ukládá cestu <code>/assets/model/nazev.glb</code>.</p>
          <div class="btn-row">
            <a class="a-btn" href="admin_cars.php">Ověřit auta</a>
            <a class="a-btn" href="home.php">Otevřít web</a>
          </div>
        </div>

        <div class="action-card">
          <h3>📣 Správa inzerátů</h3>
          <p>Kontrola veřejných inzerátů, případně možnost je deaktivovat nebo smazat.</p>
          <div class="btn-row">
            <a class="a-btn" href="admin_ads.php">Seznam inzerátů</a>
            <a class="a-btn" href="ads.php">Veřejné inzeráty</a>
          </div>
        </div>
      </div>

      <hr class="admin-divider">

      <h3 style="margin:0 0 6px 0;">Bezpečnost</h3>
      <ul style="margin:0; color:var(--muted); line-height:1.6;">
        <li>Přístup mají jen uživatelé s <code>is_admin = 1</code>.</li>
        <li>Stránky adminu jsou chráněné serverově (PHP), ne přes JS.</li>
        <li>Doporučení: nenechávej admin účet s jednoduchým heslem.</li>
      </ul>

    </div>

  </div>
</div>
</body>
</html>