<?php
// portal_motoristy_single_file.php — Jednosouborový mini portál pro motoristy (SQLite + PHP)
// Požadavky: PHP 8+, povolené SQLite (PDO_sqlite)
// Nasazení: hoď do webrootu a otevři v prohlížeči. Vytvoří se soubor portal.db

session_start();

// ====== Konfigurace ======
const DB_FILE = __DIR__ . '/portal.db';
const APP_NAME = 'Portál pro motoristy';

// Základní CSRF ochrana
if (!isset($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}
function csrf_field(): string { return '<input type="hidden" name="csrf" value="'.htmlspecialchars($_SESSION['csrf']).'">'; }
function csrf_check(): bool { return isset($_POST['csrf']) && hash_equals($_SESSION['csrf'], $_POST['csrf']); }

// ====== DB připojení a inicializace ======
function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO('sqlite:' . DB_FILE);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        init_db($pdo);
    }
    return $pdo;
}

function init_db(PDO $pdo): void {
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        email TEXT UNIQUE NOT NULL,
        pass_hash TEXT NOT NULL,
        created_at TEXT NOT NULL
    )');
    $pdo->exec('CREATE TABLE IF NOT EXISTS cars (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        brand TEXT NOT NULL,
        model TEXT NOT NULL,
        year INTEGER,
        power_kw INTEGER,
        price INTEGER,
        description TEXT
    )');
    $pdo->exec('CREATE TABLE IF NOT EXISTS favorites (
        user_id INTEGER NOT NULL,
        car_id INTEGER NOT NULL,
        created_at TEXT NOT NULL,
        PRIMARY KEY (user_id, car_id),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (car_id) REFERENCES cars(id) ON DELETE CASCADE
    )');

    // Seed ukázkových aut, pokud je tabulka prázdná
    $count = (int)$pdo->query('SELECT COUNT(*) FROM cars')->fetchColumn();
    if ($count === 0) {
        $cars = [
            ['Volkswagen', 'Passat B6 2.0 TDI', 2007, 103, 99000, 'Praktický kombík, manuál, servisní historie.'],
            ['Škoda', 'Superb 2.0 TDI', 2012, 125, 159000, 'Dvouzonová klima, park. senzory, vyhřívané sedačky.'],
            ['Audi', 'A3 2.0 TDI', 2009, 103, 129000, 'S-line vzhled, po rozvodech, pěkný stav.'],
            ['Toyota', 'Yaris 1.33', 2010, 73, 99000, 'Spolehlivý benzín, nízká spotřeba.'],
            ['BMW', '330d E90', 2008, 170, 179000, 'Silný naftový šestiválec, M volant.'],
            ['Volkswagen', 'Golf IV 1.9 TDI', 2003, 74, 59000, 'Legendární PD motor, zachovalý interiér.'],
        ];
        $stmt = $pdo->prepare('INSERT INTO cars(brand, model, year, power_kw, price, description) VALUES (?,?,?,?,?,?)');
        foreach ($cars as $c) { $stmt->execute($c); }
    }
}

// ====== Helpery ======
function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function is_logged(): bool { return isset($_SESSION['user']); }
function user_id(): ?int { return $_SESSION['user']['id'] ?? null; }
function flash(?string $msg = null): ?string {
    if ($msg !== null) { $_SESSION['flash'] = $msg; return null; }
    $m = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); return $m;
}

// ====== Akce (POST) ======
$action = $_POST['action'] ?? null;
if ($action && !csrf_check()) { http_response_code(400); exit('Neplatný CSRF token'); }

try {
    switch ($action) {
        case 'register':
            $email = trim($_POST['email'] ?? '');
            $pass = $_POST['pass'] ?? '';
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Zadej platný e‑mail.');
            if (strlen($pass) < 6) throw new RuntimeException('Heslo musí mít alespoň 6 znaků.');
            $stmt = db()->prepare('INSERT INTO users(email, pass_hash, created_at) VALUES (?,?,datetime("now"))');
            $stmt->execute([$email, password_hash($pass, PASSWORD_DEFAULT)]);
            flash('Registrace proběhla úspěšně. Můžeš se přihlásit.');
            header('Location: ?page=login'); exit;
        case 'login':
            $email = trim($_POST['email'] ?? '');
            $pass = $_POST['pass'] ?? '';
            $stmt = db()->prepare('SELECT * FROM users WHERE email = ?');
            $stmt->execute([$email]);
            $u = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$u || !password_verify($pass, $u['pass_hash'])) throw new RuntimeException('Neplatný e‑mail nebo heslo.');
            $_SESSION['user'] = ['id' => (int)$u['id'], 'email' => $u['email']];
            flash('Vítej zpět, '.$u['email'].'!');
            header('Location: ./'); exit;
        case 'logout':
            session_destroy();
            session_start();
            flash('Byl jsi odhlášen.');
            header('Location: ./'); exit;
        case 'add_car':
            if (!is_logged()) throw new RuntimeException('Musíš být přihlášen.');
            $brand = trim($_POST['brand'] ?? '');
            $model = trim($_POST['model'] ?? '');
            $year = (int)($_POST['year'] ?? 0);
            $power = (int)($_POST['power_kw'] ?? 0);
            $price = (int)($_POST['price'] ?? 0);
            $desc = trim($_POST['description'] ?? '');
            if ($brand === '' || $model === '') throw new RuntimeException('Značka a model jsou povinné.');
            $stmt = db()->prepare('INSERT INTO cars(brand, model, year, power_kw, price, description) VALUES (?,?,?,?,?,?)');
            $stmt->execute([$brand, $model, $year, $power, $price, $desc]);
            flash('Auto přidáno.');
            header('Location: ./'); exit;
        case 'fav_add':
            if (!is_logged()) throw new RuntimeException('Musíš být přihlášen.');
            $car_id = (int)($_POST['car_id'] ?? 0);
            $stmt = db()->prepare('INSERT OR IGNORE INTO favorites(user_id, car_id, created_at) VALUES (?,?,datetime("now"))');
            $stmt->execute([user_id(), $car_id]);
            flash('Přidáno do oblíbených.');
            header('Location: '.$_SERVER['HTTP_REFERER']); exit;
        case 'fav_remove':
            if (!is_logged()) throw new RuntimeException('Musíš být přihlášen.');
            $car_id = (int)($_POST['car_id'] ?? 0);
            $stmt = db()->prepare('DELETE FROM favorites WHERE user_id=? AND car_id=?');
            $stmt->execute([user_id(), $car_id]);
            flash('Odebráno z oblíbených.');
            header('Location: '.$_SERVER['HTTP_REFERER']); exit;
    }
} catch (Throwable $ex) {
    flash('Chyba: ' . $ex->getMessage());
}

// ====== Router (GET) ======
$page = $_GET['page'] ?? 'home';
$allowed = ['home','login','register','favorites','add'];
if (!in_array($page, $allowed, true)) { $page = 'home'; }

//Filtry vyhledávání
$q = trim($_GET['q'] ?? '');
$brand = trim($_GET['brand'] ?? '');
$price_max = (int)($_GET['price_max'] ?? 0);

function fetch_cars(string $q, string $brand, int $price_max): array {
    $sql = 'SELECT c.*, EXISTS(SELECT 1 FROM favorites f WHERE f.user_id = :uid AND f.car_id = c.id) AS is_fav FROM cars c WHERE 1=1';
    $params = [':uid' => user_id() ?? 0];
    if ($q !== '') { $sql .= ' AND (brand LIKE :q OR model LIKE :q OR description LIKE :q)'; $params[':q'] = "%$q%"; }
    if ($brand !== '') { $sql .= ' AND brand = :brand'; $params[':brand'] = $brand; }
    if ($price_max > 0) { $sql .= ' AND price <= :price_max'; $params[':price_max'] = $price_max; }
    $sql .= ' ORDER BY price ASC NULLS LAST, year DESC';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function unique_brands(): array {
    return db()->query('SELECT DISTINCT brand FROM cars ORDER BY brand')->fetchAll(PDO::FETCH_COLUMN);
}

// ====== HTML šablona ======
function header_html(string $title): void {
    $flash = flash();
    echo '<!doctype html><html lang="cs"><head><meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>'.e($title).' — '.e(APP_NAME).'</title>';
    echo '<style>
        :root{--bg:#0b1220;--card:#131c2b;--muted:#9bb0d1;--text:#e8eefc;--accent:#4da3ff;--accent2:#60d394;}
        *{box-sizing:border-box} body{margin:0;font-family:system-ui,Segoe UI,Roboto,Arial,sans-serif;background:linear-gradient(180deg,#0b1220,#0e1729);color:var(--text)}
        a{color:var(--accent);text-decoration:none} a:hover{text-decoration:underline}
        header{display:flex;gap:16px;align-items:center;justify-content:space-between;padding:16px 24px;background:#0f1830;position:sticky;top:0;z-index:10;border-bottom:1px solid #1e2a44}
        .logo{font-weight:700;letter-spacing:.3px}
        .wrap{max-width:1100px;margin:0 auto;padding:24px}
        .card{background:var(--card);border:1px solid #1e2a44;border-radius:16px;padding:16px;box-shadow:0 10px 30px rgba(0,0,0,.25)}
        .grid{display:grid;grid-template-columns:1fr 2fr;gap:16px}
        .cars{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:16px}
        .car{background:#101a2c;border:1px solid #1f2a45;border-radius:16px;padding:14px;display:flex;flex-direction:column;gap:8px}
        .car h3{margin:0 0 6px;font-size:18px}
        .pill{display:inline-block;padding:2px 8px;border:1px solid #2b3a60;border-radius:999px;font-size:12px;color:var(--muted)}
        .row{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
        .btn{display:inline-block;background:var(--accent);color:#071021;border:0;padding:10px 14px;border-radius:10px;font-weight:600}
        .btn.secondary{background:#1f2b48;color:var(--text);border:1px solid #2a3a60}
        .btn.danger{background:#cc3344;color:#fff}
        form.inline{display:inline}
        input,select,textarea{width:100%;background:#0d1629;color:var(--text);border:1px solid #223251;border-radius:10px;padding:10px}
        label{font-size:12px;color:var(--muted);display:block;margin-bottom:6px}
        .flash{margin:12px 0;padding:10px 14px;border-radius:10px;background:#10243a;border:1px solid #214a77;color:#bfe0ff}
        footer{margin-top:40px;color:#7f97bf;text-align:center}
    </style>';
    echo '</head><body>';
    echo '<header><div class="logo">🚗 '.e(APP_NAME).'</div><nav class="row">';
    echo '<a href="./">Domů</a>';
    echo '<a href="?page=favorites">Oblíbené</a>';
    if (is_logged()) {
        echo '<a href="?page=add">Přidat auto</a>';
        echo '<form class="inline" method="post">'.csrf_field().'<input type="hidden" name="action" value="logout"><button class="btn secondary" type="submit">Odhlásit '.e($_SESSION['user']['email']).'</button></form>';
    } else {
        echo '<a href="?page=login">Přihlášení</a><a href="?page=register">Registrace</a>';
    }
    echo '</nav></header><div class="wrap">';
    if ($flash) { echo '<div class="flash">'.e($flash).'</div>'; }
}

function footer_html(): void {
    echo '<footer>© '.date('Y').' '.e(APP_NAME).' • Jednosouborová ukázka • SQLite databáze: '.e(basename(DB_FILE)).'</footer>';
    echo '</div></body></html>';
}

// ====== Stránky ======
function page_home(): void {
    global $q, $brand, $price_max;
    $cars = fetch_cars($q, $brand, $price_max);
    $brands = unique_brands();
    header_html('Domů');
    echo '<div class="grid"><div class="card">';
    echo '<h2>Vyhledávání aut</h2><form method="get">';
    echo '<input type="hidden" name="page" value="home">';
    echo '<label>Hledat (značka, model, popis)</label><input name="q" value="'.e($q).'" placeholder="např. Passat, 2.0 TDI, vyhřívané" />';
    echo '<div class="row"><div style="flex:1"><label>Značka</label><select name="brand"><option value="">— libovolná —</option>';
    foreach ($brands as $b) { $sel = $brand===$b?' selected':''; echo '<option'.$sel.'>'.e($b).'</option>'; }
    echo '</select></div><div style="flex:1"><label>Max. cena (Kč)</label><input type="number" name="price_max" value="'.($price_max?:'').'" placeholder="např. 120000"></div></div>';
    echo '<div class="row"><button class="btn" type="submit">Vyhledat</button><a class="btn secondary" href="./">Reset</a></div>';
    echo '</form></div>';

    echo '<div class="card"><div class="row" style="justify-content:space-between"><h2>Nabídka aut</h2><span class="pill">'.count($cars).' položek</span></div>';
    echo '<div class="cars">';
    foreach ($cars as $c) {
        echo '<div class="car">';
        echo '<h3>'.e($c['brand'].' '.$c['model']).'</h3>';
        echo '<div class="row"><span class="pill">'.e((string)$c['year']).'</span><span class="pill">'.e((string)$c['power_kw']).' kW</span><span class="pill">'.(is_null($c['price'])? '—' : e(number_format((int)$c['price'],0,'',' ')).' Kč').'</span></div>';
        if (!empty($c['description'])) echo '<p style="margin:.3rem 0 0;color:#b8c7e6">'.e($c['description']).'</p>';
        echo '<div class="row" style="margin-top:auto;justify-content:space-between">';
        if (is_logged()) {
            if ((int)$c['is_fav'] === 1) {
                echo '<form class="inline" method="post">'.csrf_field().'<input type="hidden" name="action" value="fav_remove"><input type="hidden" name="car_id" value="'.(int)$c['id'].'"><button class="btn danger" type="submit">Odebrat z oblíbených</button></form>';
            } else {
                echo '<form class="inline" method="post">'.csrf_field().'<input type="hidden" name="action" value="fav_add"><input type="hidden" name="car_id" value="'.(int)$c['id'].'"><button class="btn" type="submit">Přidat do oblíbených</button></form>';
            }
        } else {
            echo '<a class="btn secondary" href="?page=login">Přihlásit se pro oblíbené</a>';
        }
        echo '</div></div>';
    }
    echo '</div></div></div>';
    footer_html();
}

function page_login(): void {
    header_html('Přihlášení');
    echo '<div class="card" style="max-width:480px;margin:auto">';
    echo '<h2>Přihlášení</h2><form method="post">'.csrf_field().'<input type="hidden" name="action" value="login">';
    echo '<label>E‑mail</label><input type="email" name="email" required>'; 
    echo '<label>Heslo</label><input type="password" name="pass" required>';
    echo '<button class="btn" type="submit">Přihlásit</button> <a class="btn secondary" href="?page=register">Nemám účet</a>';
    echo '</form></div>';
    footer_html();
}

function page_register(): void {
    header_html('Registrace');
    echo '<div class="card" style="max-width:520px;margin:auto">';
    echo '<h2>Registrace</h2><form method="post">'.csrf_field().'<input type="hidden" name="action" value="register">';
    echo '<label>E‑mail</label><input type="email" name="email" required placeholder="např. ty@domena.cz">';
    echo '<label>Heslo</label><input type="password" name="pass" required placeholder="alespoň 6 znaků">';
    echo '<button class="btn" type="submit">Vytvořit účet</button>';
    echo '</form></div>';
    footer_html();
}

function page_favorites(): void {
    if (!is_logged()) { header('Location: ?page=login'); exit; }
    header_html('Oblíbené');
    echo '<div class="card">';
    echo '<h2>Moje oblíbená auta</h2>';
    $stmt = db()->prepare('SELECT c.* FROM favorites f JOIN cars c ON c.id=f.car_id WHERE f.user_id=? ORDER BY f.created_at DESC');
    $stmt->execute([user_id()]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!$rows) { echo '<p>Ještě nic nemáš. Projdi si <a href="./">nabídku</a> a přidej pár kousků.</p>'; }
    echo '<div class="cars">';
    foreach ($rows as $c) {
        echo '<div class="car">';
        echo '<h3>'.e($c['brand'].' '.$c['model']).'</h3>';
        echo '<div class="row"><span class="pill">'.e((string)$c['year']).'</span><span class="pill">'.e((string)$c['power_kw']).' kW</span><span class="pill">'.(is_null($c['price'])? '—' : e(number_format((int)$c['price'],0,'',' ')).' Kč').'</span></div>';
        if (!empty($c['description'])) echo '<p style="margin:.3rem 0 0;color:#b8c7e6">'.e($c['description']).'</p>';
        echo '<form class="inline" method="post" style="margin-top:auto">'.csrf_field().'<input type="hidden" name="action" value="fav_remove"><input type="hidden" name="car_id" value="'.(int)$c['id'].'"><button class="btn danger" type="submit">Odebrat</button></form>';
        echo '</div>';
    }
    echo '</div></div>';
    footer_html();
}

function page_add(): void {
    if (!is_logged()) { header('Location: ?page=login'); exit; }
    header_html('Přidat auto');
    echo '<div class="card" style="max-width:720px;margin:auto">';
    echo '<h2>Přidat auto</h2><form method="post">'.csrf_field().'<input type="hidden" name="action" value="add_car">';
    echo '<div class="row"><div style="flex:1"><label>Značka</label><input name="brand" required placeholder="např. Volkswagen"></div>';
    echo '<div style="flex:1"><label>Model</label><input name="model" required placeholder="např. Passat B6"></div></div>';
    echo '<div class="row"><div style="flex:1"><label>Rok</label><input type="number" name="year" placeholder="např. 2007"></div>';
    echo '<div style="flex:1"><label>Výkon (kW)</label><input type="number" name="power_kw" placeholder="např. 103"></div>';
    echo '<div style="flex:1"><label>Cena (Kč)</label><input type="number" name="price" placeholder="např. 99000"></div></div>';
    echo '<label>Popis</label><textarea name="description" rows="4" placeholder="stav, výbava, poznámky…"></textarea>';
    echo '<button class="btn" type="submit">Uložit</button>';
    echo '</form></div>';
    footer_html();
}

// ====== Render ======
match ($page) {
    'home' => page_home(),
    'login' => page_login(),
    'register' => page_register(),
    'favorites' => page_favorites(),
    'add' => page_add(),
    default => page_home(),
};

?>
