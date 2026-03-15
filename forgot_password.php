<?php
require __DIR__.'/auth.php';
require __DIR__.'/db.php';

check_csrf_post();

$error = '';
$info  = '';
$mode  = 'request'; // request | reset | done | invalid_token

$token = $_GET['token'] ?? '';
$user  = null;

// Pokud je v URL token, přepneme do reset režimu
if ($token !== '') {
    $mode = 'reset';

    $stmt = $pdo->prepare('SELECT id, email FROM users WHERE reset_token = ? AND reset_expires > NOW()');
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if (!$user) {
        $error = 'Odkaz pro obnovu hesla je neplatný nebo vypršel. Zkus požádat o nový.';
        $mode  = 'invalid_token';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1) FORM – požadavek na odkaz (request)
    if (isset($_POST['action']) && $_POST['action'] === 'request') {
        $email = trim($_POST['email'] ?? '');

        if ($email === '') {
            $error = 'Zadej e-mail, se kterým jsi registrován.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'E-mail nemá správný tvar.';
        } else {
            $stmt = $pdo->prepare('SELECT id, email FROM users WHERE email = ?');
            $stmt->execute([$email]);
            $u = $stmt->fetch();

            // bezpečnostS: stejná zpráva i když účet neexistuje
            if ($u) {
                $resetToken   = bin2hex(random_bytes(32));
                $expiresAfter = 60; // minut

                $upd = $pdo->prepare(
                    'UPDATE users SET reset_token = ?, reset_expires = (NOW() + INTERVAL ? MINUTE) WHERE id = ?'
                );
                $upd->execute([$resetToken, $expiresAfter, $u['id']]);

                $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
                $path   = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
                $link   = $scheme.'://'.$host.$path.'/forgot_password.php?token='.$resetToken;

                $subject = 'Obnova hesla – Portál pro motoristy';
                $message = "Ahoj,\n\n"
                         . "požádal(a) jsi o obnovu hesla na Portálu pro motoristy.\n"
                         . "Pro nastavení nového hesla klikni na tento odkaz:\n\n"
                         . $link . "\n\n"
                         . "Odkaz je platný přibližně $expiresAfter minut.\n\n"
                         . "Pokud jsi o změnu nepožádal(a) ty, můžeš tento e-mail ignorovat.\n";

                $headers = "From: noreply@".$host."\r\n";

                @mail($u['email'], $subject, $message, $headers);
            }

            $info = 'Pokud existuje účet s tímto e-mailem, byl na něj odeslán odkaz pro změnu hesla.';
        }

    // 2) FORM – reset hesla (reset)
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

<body class="bg-dark auth-page">
  <div class="bg-noise" aria-hidden="true"></div>

  <main class="auth">
    <section class="auth-card page">
      <header class="auth-head">
        <div class="brand">
          <div class="brand-mark" aria-hidden="true"></div>
          <div>
            <h1>Obnova hesla</h1>
            <p class="sub">
              <?php if ($mode === 'reset'): ?>
                Nastav si nové heslo pro svůj účet.
              <?php else: ?>
                Pošleme ti odkaz pro nastavení nového hesla.
              <?php endif; ?>
            </p>
          </div>
        </div>
      </header>

      <?php if ($error): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
      <?php endif; ?>

      <?php if ($info): ?>
        <div class="alert"><?php echo nl2br(htmlspecialchars($info, ENT_QUOTES, 'UTF-8')); ?></div>
      <?php endif; ?>

      <?php if ($mode === 'request'): ?>

        <form method="post" action="forgot_password.php" class="auth-form" autocomplete="off" novalidate>
          <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
          <input type="hidden" name="action" value="request">

          <label class="field">
            <span class="label">E-mail</span>
            <input class="input" type="email" name="email" placeholder="Tvůj registrační e-mail" required>
          </label>

          <button class="btn btn-primary" type="submit">Poslat odkaz pro obnovu</button>

          <div class="links">
            <a class="link js-nav" href="login.php">Zpět na přihlášení</a>
            <a class="link js-nav" href="register.php">Nemáš účet? Zaregistruj se</a>
          </div>
        </form>

      <?php elseif ($mode === 'reset'): ?>

        <form method="post"
              action="forgot_password.php?token=<?php echo htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>"
              class="auth-form"
              autocomplete="off"
              novalidate>
          <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
          <input type="hidden" name="action" value="reset">

          <label class="field">
            <span class="label">Nové heslo</span>
            <input class="input" type="password" name="password" placeholder="••••••••" required minlength="6">
          </label>

          <label class="field">
            <span class="label">Nové heslo znovu</span>
            <input class="input" type="password" name="password2" placeholder="••••••••" required minlength="6">
          </label>

          <button class="btn btn-primary" type="submit">Změnit heslo</button>

          <div class="links">
            <a class="link js-nav" href="login.php">Zpět na přihlášení</a>
          </div>
        </form>

      <?php elseif ($mode === 'done'): ?>

        <a class="btn btn-primary js-nav" href="login.php">Přejít na přihlášení</a>
        <div class="links" style="margin-top:10px;">
          <a class="link js-nav" href="forgot_password.php">Poslat nový odkaz</a>
        </div>

      <?php elseif ($mode === 'invalid_token'): ?>

        <a class="btn btn-primary js-nav" href="forgot_password.php">Požádat o nový odkaz</a>
        <div class="links" style="margin-top:10px;">
          <a class="link js-nav" href="login.php">Zpět na přihlášení</a>
        </div>

      <?php endif; ?>
    </section>
  </main>

  <script src="auth-transition.js"></script>
</body>
</html>
