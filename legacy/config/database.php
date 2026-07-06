<?php
// config/database.php

$host = env('DB_HOST', '127.0.0.1');
$db_name = env('DB_DATABASE', 'promindo_mms');
$username = env('DB_USERNAME', 'root');
$password = env('DB_PASSWORD', '');
$port = env('DB_PORT', '3306');

try {
    $dsn = "mysql:host=$host;port=$port;dbname=$db_name;charset=utf8mb4";
    $pdo = new PDO($dsn, $username, $password);
    
    // Set error mode ke exception (penting untuk debugging)
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Set default fetch mode ke Associative Array
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $GLOBALS['pdo'] = $pdo;
    
} catch(PDOException $e) {
    // Jangan tampilkan detail error ke user di production
    die("Koneksi Database Gagal: " . $e->getMessage());
}
?>
