<?php

$host = 'db.r4.active24.cz';
$db   = 'ZCenwVfs';   // to je jméno databáze z obrázku
$user = 'leonard';
$pass = 'Leonarde1U2U3U.';  // sem dej nové heslo

$dsn = "mysql:host=$host;port=3306;dbname=$db;charset=utf8mb4";

try {
    $pdo = new PDO(
        $dsn,
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    die('Chyba připojení k DB: ' . $e->getMessage());
}
