<?php
require_once __DIR__ . '/auth.php';

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

if (empty($_SESSION['user']['is_admin'])) {
    http_response_code(403);
    echo "Přístup pouze pro administrátora.";
    exit;
}
