<?php
require __DIR__.'/auth.php';
require __DIR__.'/db.php';

$error = '';
check_csrf_post();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $pw    = $_POST['password'] ?? '';

    if ($email === '' || $pw === '') {
        $error = 'Vyplň e-mail i heslo.';
    } else {
        // 🔴 přidáno is_admin
        $stmt = $pdo->prepare(
            'SELECT id, email, password_hash, display_name, is_admin FROM users WHERE email = ?'
        );
        $stmt->execute([$email]);
        $u = $stmt->fetch();

        if (!$u || !password_verify($pw, $u['password_hash'])) {
            $error = 'Špatný e-mail nebo heslo.';
        } else {
            session_regenerate_id(true);

            // 🔴 přidáno is_admin do session
            $_SESSION['user'] = [
                'id'           => (int)$u['id'],
                'email'        => $u['email'],
                'display_name' => $u['display_name'] ?? '',
                'is_admin'     => (int)($u['is_admin'] ?? 0)
            ];

            header('Location: home.php');
            exit;
        }
    }
}
?>
<!doctype html>
<html lang="cs">
<head>
  <meta charset="utf-8">
  <title>Přihlášení – Portál pro motoristy</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="style.css?v=<?php echo urlencode(date('YmdHi')); ?>">
</head>
<body class="bg-dark">
  <div class="bg-noise" aria-hidden="true"></div>

  <div class="login-wrap">
    <div class="card">
      <h1>Přihlášení</h1>

      <?php if ($error): ?>
        <div class="error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
      <?php endif; ?>

      <form method="post" action="login.php" autocomplete="off" novalidate>
        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
        <div class="row">
          <input type="email" name="email" placeholder="E-mail" required>
        </div>
        <div class="row">
          <input type="password" name="password" placeholder="Heslo" required>
        </div>
        <button type="submit">Přihlásit se</button>
      </form>

      <p class="muted">
        Nemáš účet? <a class="js-nav" href="register.php">Zaregistruj se</a>
        Zapomněl jsi heslo? <a class="js-nav" href="forgot_password.php">Obnovit heslo</a>
      </p>
    </div>
  </div>
</body>
</html>
