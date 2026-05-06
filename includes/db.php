<?php

use Dotenv\Dotenv;

    require_once __DIR__ . '/../vendor/autoload.php';

    $dotenv = Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->safeLoad();

    //DB information
    $host = $_ENV['DB_HOST'];
    $db = $_ENV['DB_NAME'];
    $user = $_ENV['DB_USERNAME'];
    $password = $_ENV['DB_PASS'];
    $charset = 'utf8mb4';

    //Data Source Name 
    $dsn = "mysql:host=$host;dbname=$db;charset=$charset";

    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, //Hata olursa durdur ve bildir
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,//Verileri dizi oalrak gelsin
        PDO::ATTR_EMULATE_PREPARES => false,//Sorduyu temizler SQL Injection onlemi
    ];

    try {
        $pdo = new PDO($dsn , $user , $password , $options);
    } catch (\PDOException $e) {
        die("Database connection error: " . $e->getMessage());   
    }
?>