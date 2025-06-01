<?php
$dbConfig = [
    'user' => 'sa',
    'password' => '123',
    'server' => 'localhost',
    'database' => 'Maj5',
    'port' => 1433
];

try {
    $dsn = "sqlsrv:Server={$dbConfig['server']},{$dbConfig['port']};Database={$dbConfig['database']}";
    $pdo = new PDO($dsn, $dbConfig['user'], $dbConfig['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::SQLSRV_ATTR_ENCODING => PDO::SQLSRV_ENCODING_UTF8
    ]);
} catch (PDOException $e) {
    die("Kết nối database thất bại: " . $e->getMessage());
}