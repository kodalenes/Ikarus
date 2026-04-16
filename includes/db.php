<?php
    //DB information
    $host = 'localhost';
    $db = 'ikarusdb';
    $user = 'root';
    $password = '';
    $charset = 'utfmb4';

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
        throw new \PDOException($e -> getMessage(),(int) $e ->getCode());   
    }
?>