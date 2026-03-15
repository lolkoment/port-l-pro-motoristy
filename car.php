<?php
require __DIR__ . '/auth.php';
require __DIR__ . '/db.php';

if (!is_logged_in()) {
    header('Location: login.php');
    exit;
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(404);
    echo "Auto nenalezeno.";
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM cars WHERE id = ?");
$stmt->execute([$id]);
$car = $stmt->fetch();

if (!$car) {
    http_response_code(404);
    echo "Auto nenalezeno.";
    exit;
}

$title = trim(
    ($car['brand'] ?? '') . ' ' .
    ($car['model'] ?? '') . ' ' .
    ($car['variant'] ?? '')
);

// base URL projektu (řeší složky typu /WEB/portal pro motoristy)
$baseUrl = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');

// ✅ MODEL: v DB máš sloupec model_3d s hodnotou např. "/assets/model/peugeot_rcz.glb"
$modelPath = trim($car['model_3d'] ?? '');
$modelSrc = '';
$modelOk = false;

// povolíme jen .glb a zakážeme "divné" cesty
if ($modelPath !== '' && preg_match('~\.glb$~i', $modelPath) && strpos($modelPath, '..') === false) {
    // složení URL pro prohlížeč (vždy přesně 1 lomítko mezi)
    $modelSrc = $baseUrl . '/' . ltrim($modelPath, '/\\');

    // kontrola existence souboru na disku (jen když je to z /assets/...)
    if (strpos($modelPath, '/assets/') === 0 || strpos($modelPath, 'assets/') === 0) {
        $fsPath = __DIR__ . '/' . ltrim($modelPath, '/\\');
        $modelOk = is_file($fsPath);
    } else {
        // kdyby někdo dal absolutní URL (nepředpokládám), tak aspoň zobrazíme bez kontroly
        $modelOk = true;
    }
}
?>
<!doctype html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <title><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?> – Detail</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="stylesheet" href="style.css?v=<?php echo urlencode(date('YmdHi')); ?>">

    <!-- Model Viewer (nejjednodušší 3D prohlížeč) -->
    <script type="module" src="https://unpkg.com/@google/model-viewer/dist/model-viewer.min.js"></script>

    <style>
        .wrap { width: 1000px; max-width: 95vw; }
        .section {
            background: var(--panel);
            padding: 16px;
            border-radius: 12px;
            box-shadow: var(--shadow);
        }
        .top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        .title { margin: 0; font-size: 22px; }
        .meta { color: var(--muted); margin-top: 6px; }
        .grid2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-top: 16px;
        }
        .specs {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-top: 10px;
        }
        .spec {
            padding: 10px;
            border: 1px solid var(--border);
            border-radius: 12px;
            background: var(--input-bg);
        }
        .spec .k { color: var(--muted); font-size: 12px; }
        .spec .v { font-weight: 600; margin-top: 4px; }
        .pill {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 999px;
            border: 1px solid var(--border);
            background: #0b1220;
            font-size: 12px;
        }
        .btn {
            display: inline-block;
            padding: 9px 14px;
            border-radius: 10px;
            background: var(--accent);
            color: #fff;
            text-decoration: none;
            font-weight: 600;
        }
        .btn:hover { background: var(--accent-hover); }

        .viewer-wrap { margin-top: 16px; }
        model-viewer {
            width: 100%;
            height: 520px;
            background: #111;
            border-radius: 12px;
            border: 1px solid var(--border);
            overflow: hidden;
        }

        @media (max-width: 800px) {
            .grid2 { grid-template-columns: 1fr; }
            .specs { grid-template-columns: 1fr; }
        }
    </style>
</head>

<body class="bg-dark center-screen">

<a href="logout.php" class="logout-fixed">Odhlásit se</a>

<div class="wrap">
    <div class="section">
        <div class="top">
            <div>
                <h1 class="title">
                    <?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?>
                </h1>
                <div class="meta">
                    <span class="pill"><?php echo htmlspecialchars($car['fuel'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></span>
                    <span class="pill"><?php echo (int)($car['year'] ?? 0); ?></span>
                    <span class="pill"><?php echo (int)($car['power_kw'] ?? 0); ?> kW</span>
                </div>
            </div>
            <a class="btn" href="home.php">← Zpět na vyhledávání</a>
        </div>

        <!-- ✅ 3D MODEL (jen pokud existuje) -->
        <div class="viewer-wrap">
            <h2 style="margin:0 0 10px 0;">3D model vozidla</h2>

            <?php if ($modelOk && $modelSrc !== ''): ?>
                <model-viewer
                    src="<?php echo htmlspecialchars($modelSrc, ENT_QUOTES, 'UTF-8'); ?>"
                    alt="<?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?>"
                    camera-controls
                    auto-rotate
                    shadow-intensity="1"
                    exposure="1"
                    loading="eager">
                </model-viewer>
            <?php else: ?>
                <p class="muted" style="margin:0;">
                    3D model není k dispozici.
                    <?php if (!empty($car['model_3d'])): ?>
                        <br><span style="font-size:12px;opacity:.7">DB: <?php echo htmlspecialchars($car['model_3d'], ENT_QUOTES, 'UTF-8'); ?></span>
                    <?php endif; ?>
                </p>
            <?php endif; ?>
        </div>

        <div class="grid2">
            <!-- SPECIFIKACE -->
            <div class="section">
                <h2>Specifikace</h2>
                <div class="specs">

                    <div class="spec">
                        <div class="k">Značka</div>
                        <div class="v"><?php echo htmlspecialchars($car['brand'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></div>
                    </div>

                    <div class="spec">
                        <div class="k">Model</div>
                        <div class="v"><?php echo htmlspecialchars($car['model'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></div>
                    </div>

                    <div class="spec">
                        <div class="k">Varianta</div>
                        <div class="v"><?php echo htmlspecialchars($car['variant'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></div>
                    </div>

                    <div class="spec">
                        <div class="k">Karoserie</div>
                        <div class="v"><?php echo htmlspecialchars($car['body'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></div>
                    </div>

                    <div class="spec">
                        <div class="k">Rok</div>
                        <div class="v"><?php echo (int)($car['year'] ?? 0); ?></div>
                    </div>

                    <div class="spec">
                        <div class="k">Palivo</div>
                        <div class="v"><?php echo htmlspecialchars($car['fuel'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></div>
                    </div>

                    <div class="spec">
                        <div class="k">Výkon</div>
                        <div class="v"><?php echo (int)($car['power_kw'] ?? 0); ?> kW</div>
                    </div>

                    <div class="spec">
                        <div class="k">Nájezd</div>
                        <div class="v">
                            <?php
                            echo $car['mileage'] !== null
                                ? number_format((int)$car['mileage'], 0, ',', ' ') . ' km'
                                : '—';
                            ?>
                        </div>
                    </div>

                    <div class="spec" style="grid-column:1 / -1;">
                        <div class="k">Cena</div>
                        <div class="v">
                            <?php
                            echo $car['price'] !== null
                                ? number_format((int)$car['price'], 0, ',', ' ') . ' Kč'
                                : '—';
                            ?>
                        </div>
                    </div>

                </div>
            </div>

            <!-- POPIS -->
            <div class="section">
                <h2>Popis</h2>

                <?php if (!empty($car['description'])): ?>
                    <p style="line-height:1.6;margin:0;">
                        <?php echo nl2br(htmlspecialchars($car['description'], ENT_QUOTES, 'UTF-8')); ?>
                    </p>
                <?php else: ?>
                    <p class="muted" style="margin:0;">
                        Zatím bez popisu.
                    </p>
                <?php endif; ?>

            </div>
        </div>
    </div>
</div>

</body>
</html>
