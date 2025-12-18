<?php
// Connessione al database MySQL/MariaDB tramite PDO
$dsn = 'mysql:host=localhost;dbname=artigiani_finder;charset=utf8mb4';
$username = 'root';
$password = '';

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $username, $password, $options);
} catch (PDOException $e) {
    die('Connessione al database fallita: ' . $e->getMessage());
}
