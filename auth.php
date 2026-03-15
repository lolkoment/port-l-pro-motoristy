<?php
// ====== PRODUKCE vs. VÝVOJ ======
// Na hostingu radši nezobrazuj chyby uživatelům.
// Když budeš ladit, můžeš si to dočasně přepnout na 1.
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// Session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Vygenerování CSRF tokenu
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

// Kontrola CSRF tokenu (jen pro POST)
function check_csrf_post(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }

    // Když token v session ještě není, rovnou fail
    if (empty($_SESSION['csrf'])) {
        http_response_code(400);
        die('Neplatný CSRF token.');
    }

    $token = (string)($_POST['csrf'] ?? '');

    // hash_equals je OK, ale porovnávej jen neprázdné
    if ($token === '' || !hash_equals($_SESSION['csrf'], $token)) {
        http_response_code(400);
        die('Neplatný CSRF token.');
    }
}

// Kontrola přihlášení
function is_logged_in(): bool {
    return !empty($_SESSION['user']['id']);
}
