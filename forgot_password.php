<?php
require __DIR__.'/auth.php';
require __DIR__.'/db.php';

check_csrf_post(); // stejný princip jako u loginu

$error = '';
$info  = '';
$mode  = 'request'; // 'request' = zadání emailu, 'reset' = změna hesla

$token = $_GET['token'] ?? '';

// Pokud je v URL token, přepínáme do režimu přenastavení hesla
if ($token !== '') {
    $mode = 'reset';

    // Najdeme uživatele podle tokenu
    $stmt = $pdo->prepare('SELECT id, email FROM users WHERE reset_token = ? AND reset_expires > NOW()');
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if (!$user) {
        $error = 'Odkaz pro obnovu hesla je neplatný nebo vypršel. Zkus požádat o nový.';
        $mode = 'invalid_token';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1) FORMULÁŘ PRO ZADÁNÍ EMAILU (request)
    if (isset($_POST['action']) && $_POST['action'] === 'request') {
        $email = trim($_POST['email'] ?? '');

        if ($email === '') {
            $error = 'Zadej e-mail, se kterým jsi registrován.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'E-mail nemá správný tvar.';
        } else {
            // Zkusíme najít uživatele
            $stmt = $pdo->prepare('SELECT id, email FROM users WHERE email = ?');
            $stmt->execute([$email]);
            $u = $stmt->fetch();

            // BEZPEČNOST: i když uživatel neexistuje, napíšeme stejnou zprávu
            // aby nikdo nepoznal, jestli ten e-mail v systému je nebo není
            if ($u) {
                // Vygenerujeme token
                $resetToken   = bin2hex(random_bytes(32));
                $expiresAfter = 60; // minut

                $upd = $pdo->prepare(
                    'UPDATE users SET reset_token = ?, reset_expires = (NOW() + INTERVAL ? MINUTE) WHERE id = ?'
                );
                $upd->execute([$resetToken, $expiresAfter, $u['id']]);

                // Sestavíme odkaz
                $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
                $path   = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
                $link   = $scheme.'://'.$host.$path.'/forgot_password.php?token='.$resetToken;

                // Pošleme e-mail – funguje hlavně na hostingu (Active24), v XAMPPu to většinou nepůjde
                $subject = 'Obnova hesla – Portál pro motoristy';
                $message = "Ahoj,\n\n"
                         . "požádal(a) jsi o obnovu hesla na Portálu pro motoristy.\n"
                         . "Pro nastavení nového hesla klikni na tento odkaz:\n\n"
                         . $link . "\n\n"
                         . "Odkaz je platný přibližně $expiresAfter minut.\n\n"
                         . "Pokud jsi o změnu nepožádal(a) ty, můžeš tento e-mail ignorovat.\n";

                // Můžeš upravit na svou adresu FROM:
                $headers = "From: noreply@".$host."\r\n";

                // Pokud mail() nepůjde, stále to pro školní projekt můžeš ukázat jako „logika“.
                @mail($u['email'], $subject, $message, $headers);
            }

            $info = 'Pokud existuje účet s tímto e-mailem, byl na něj odeslán odkaz pro změnu hesla.';
        }

    // 2) FORMULÁŘ PRO ZMĚNU HESLA (reset)
    } elseif (isset($_POST['action']) && $_POST['action'] === 'reset' && $mode === 'reset' && !empty($user)) {

        $pw  = $_POST['password']  ?? '';
        $pw2 = $_POST['password2'] ?? '';

        if ($pw === '' || $pw2 === '') {
            $error = 'Vyplň obě pole pro nové heslo.';
        } elseif ($pw !== $pw2) {
            $error = 'Hesla se neshodují.';
        } elseif (strlen($pw) < 6) {
            $error = 'Heslo musí mít alespoň 6 znaků.';
        } else {
            // Změníme heslo a smažeme token
            $hash = password_hash($pw, PASSWORD_DEFAULT);

            $upd = $pdo->prepare(
                'UPDATE users SET password_hash = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?'
            );
            $upd->execute([$hash, $user['id']]);

            $info = 'Heslo bylo úspěšně změněno. Nyní se můžeš přihlásit.';
            $mode = 'done';
        }
    }
}
?>
<!doctype html>
<html lang="cs">
<head>
  <meta charset="utf-8">
  <title>Obnova hesla – Portál pro motoristy</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="style.css?v=<?php echo urlencode(date('YmdHi')); ?>">
</head>
<body class="bg-dark">
  <div class="login-wrap">
    <div class="card">
      <h1>Obnova hesla</h1>

      <?php if ($error): ?>
        <div class="error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
      <?php endif; ?>

      <?php if ($info): ?>
        <p><?php echo nl2br(htmlspecialchars($info, ENT_QUOTES, 'UTF-8')); ?></p>
      <?php endif; ?>

      <?php if ($mode === 'request'): ?>

        <!-- FORMULÁŘ – ZADÁNÍ EMAILU -->
        <form method="post" action="forgot_password.php" autocomplete="off" novalidate>
          <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
          <input type="hidden" name="action" value="request">

          <div class="row">
            <input type="email" name="email" placeholder="Tvůj registrační e-mail" required>
          </div>
          <button type="submit">Poslat odkaz pro obnovu</button>
        </form>

      <?php elseif ($mode === 'reset'): ?>

        <!-- FORMULÁŘ – NOVÉ HESLO (PO KLIKNUTÍ NA ODKAZ Z MAILU) -->
        <form method="post" action="forgot_password.php?token=<?php echo htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off" novalidate>
          <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
          <input type="hidden" name="action" value="reset">

          <div class="row">
            <input type="password" name="password" placeholder="Nové heslo" required>
          </div>
          <div class="row">
            <input type="password" name="password2" placeholder="Nové heslo znovu" required>
          </div>
          <button type="submit">Změnit heslo</button>
        </form>

      <?php elseif ($mode === 'done'): ?>

        <p><a href="login.php">Přejít na přihlášení</a></p>

      <?php elseif ($mode === 'invalid_token'): ?>

        <p><a href="forgot_password.php">Zkusit znovu požádat o obnovu hesla</a></p>

      <?php endif; ?>

      <p class="muted">
        Pamatuješ si heslo? <a href="login.php">Zpět na přihlášení</a>
      </p>

    </div>
  </div>
</body>
</html>
