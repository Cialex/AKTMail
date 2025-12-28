<?php
/**
 * AktMail - Veritabanı Konfigürasyonu
 * 
 * MySQL bağlantı bilgilerini buradan ayarlayın.
 */

return [
    'host' => 'localhost',
    'port' => 3306,
    'database' => 'aktmail',
    'username' => 'root',
    'password' => '',
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
    ]
];
