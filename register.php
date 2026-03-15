<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

/* ====== defaulty (ať nevznikají warningy) ====== */
$errors  = [];
$success = false;
$name    = '';
$email   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name      = trim($_POST['name'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $password  = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';

    // Validace
    if ($name === '' || $email === '' || $password === '' || $password2 === '') {
        $errors[] = 'Vyplň všechna pole.';
    }

    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'E-mail nemá správný tvar.';
    }

    if ($password !== $password2) {
        $errors[] = 'Hesla se neshodují.';
    }

    if (strlen($password) > 0 && strlen($password) < 6) {
        $errors[] = 'Heslo musí mít alespoň 6 znaků.';
    }

    // Registrace
    if (empty($errors)) {
        // Kontrola duplicity e-mailu
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email');
        $stmt->execute(['email' => $email]);

        if ($stmt->fetch()) {
            $errors[] = 'Tento e-mail je již zaregistrován.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare(
                'INSERT INTO users (display_name, email, password_hash, created_at)
                 VALUES (:display_name, :email, :password_hash, NOW())'
            );

            $stmt->execute([
                'display_name'  => $name,
                'email'         => $email,
                'password_hash' => $hash,
            ]);

            $success = true;

            // aby po úspěchu nezůstaly hodnoty ve formuláři
            $name = '';
            $email = '';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="UTF-8">
  <title>Registrace – Portál pro motoristy</title>
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
            <h1>Registrace</h1>
            <p class="sub">Vytvoř si účet a získej přístup k porovnání aut, 3D modelům a bazaru.</p>
          </div>
        </div>
      </header>

      <?php if (!empty($errors)): ?>
        <div class="alert alert-error">
          <?php foreach ($errors as $e): ?>
            <div><?php echo htmlspecialchars($e, ENT_QUOTES, 'UTF-8'); ?></div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <?php if ($success): ?>
        <div class="alert">
          Účet byl úspěšně vytvořen. Můžeš se přihlásit.
        </div>
        <a class="btn btn-primary js-nav" href="login.php">Přejít na přihlášení</a>

      <?php else: ?>
        <form method="post" class="auth-form" autocomplete="on" novalidate>
          <label class="field">
            <span class="label">Jméno</span>
            <input class="input"
                   type="text"
                   name="name"
                   required
                   minlength="2"
                   value="<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>"
                   placeholder="např. Leonard">
          </label>

          <label class="field">
            <span class="label">E-mail</span>
            <input class="input"
                   type="email"
                   name="email"
                   required
                   value="<?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>"
                   placeholder="např. leonard@email.cz">
          </label>

          <label class="field">
            <span class="label">Heslo</span>
            <input class="input"
                   type="password"
                   name="password"
                   required
                   minlength="6"
                   placeholder="••••••••">
          </label>

          <label class="field">
            <span class="label">Heslo znovu</span>
            <input class="input"
                   type="password"
                   name="password2"
                   required
                   minlength="6"
                   placeholder="••••••••">
          </label>

          <button class="btn btn-primary" type="submit">Registrovat</button>

          <div class="links">
            <a class="link js-nav" href="login.php">Už máš účet? Přihlásit se</a>
          </div>
        </form>
      <?php endif; ?>
    </section>
  </main>

  <script src="auth-transition.js"></script>
</body>
</html>
