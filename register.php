<?php
require_once 'db.php';
require_once 'auth.php';

$errors = [];
$success = false;
$name = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name      = trim($_POST['name'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $password  = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';

    // Validace
    if ($name === '' || $email === '' || $password === '' || $password2 === '') {
        $errors[] = 'Vyplň všechna pole.';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'E-mail nemá správný tvar.';
    }

    if ($password !== $password2) {
        $errors[] = 'Hesla se neshodují.';
    }

    if (strlen($password) < 6) {
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

            // OPRAVA: name → display_name
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
        }
    }
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <title>Registrace – Portál pro motoristy</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="bg-dark center-screen">
<div class="card">
    <h1>Registrace</h1>

    <?php if (!empty($errors)): ?>
        <div class="error">
            <?php foreach ($errors as $e): ?>
                <div><?= htmlspecialchars($e) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <p>Účet byl úspěšně vytvořen. Můžeš se přihlásit.</p>
        <p><a href="login.php">Přejít na přihlášení</a></p>
    <?php else: ?>
        <form method="post">
            <div class="row">
                <label>Jméno</label>
                <input type="text" name="name" required value="<?= htmlspecialchars($name) ?>">
            </div>
            <div class="row">
                <label>E-mail</label>
                <input type="email" name="email" required value="<?= htmlspecialchars($email) ?>">
            </div>
            <div class="row">
                <label>Heslo</label>
                <input type="password" name="password" required>
            </div>
            <div class="row">
                <label>Heslo znovu</label>
                <input type="password" name="password2" required>
            </div>
            <button type="submit">Registrovat</button>
        </form>

        <p class="muted">
            Už máš účet? <a href="login.php">Přihlásit se</a>
        </p>
    <?php endif; ?>
</div>
</body>
</html>
