<?php
/**
 * AktMail - Uygulama Konfigürasyonu
 * 
 * UYARI: ENCRYPTION_KEY değerini güçlü ve benzersiz bir değerle değiştirin!
 * Örnek oluşturmak için: bin2hex(random_bytes(32))
 */

return [
    // Uygulama ayarları
    'name' => 'AktMail',
    'version' => '1.0.0',
    'debug' => true, // Prodüksiyonda false yapın
    'timezone' => 'Europe/Istanbul',
    'base_url' => 'http://localhost:8080',

    // Session ayarları
    'session' => [
        'name' => 'aktmail_session',
        'lifetime' => 7200, // 2 saat (saniye)
        'path' => '/',
        'domain' => '',
        'secure' => false, // HTTPS kullanıyorsanız true yapın
        'httponly' => true,
        'samesite' => 'Lax'
    ],

    // Remember me ayarları
    'remember' => [
        'cookie_name' => 'aktmail_remember',
        'lifetime' => 2592000, // 30 gün (saniye)
    ],

    // Şifreleme ayarları
    // ÖNEMLİ: Bu anahtarı değiştirin! Örnek: bin2hex(random_bytes(32))
    'encryption_key' => 'a66262d0926ffc602c5e4da8772d403710093ea564e15cbe10760e7e964ec849',
    'encryption_method' => 'aes-256-cbc',

    // CSRF ayarları
    'csrf' => [
        'token_name' => 'csrf_token',
        'lifetime' => 3600, // 1 saat
    ],

    // E-posta sağlayıcı varsayılanları
    'email_providers' => [
        'gmail' => [
            'imap_host' => 'imap.gmail.com',
            'imap_port' => 993,
            'imap_encryption' => 'ssl',
            'smtp_host' => 'smtp.gmail.com',
            'smtp_port' => 587,
            'smtp_encryption' => 'tls'
        ],
        'outlook' => [
            'imap_host' => 'outlook.office365.com',
            'imap_port' => 993,
            'imap_encryption' => 'ssl',
            'smtp_host' => 'smtp.office365.com',
            'smtp_port' => 587,
            'smtp_encryption' => 'tls'
        ],
        'yahoo' => [
            'imap_host' => 'imap.mail.yahoo.com',
            'imap_port' => 993,
            'imap_encryption' => 'ssl',
            'smtp_host' => 'smtp.mail.yahoo.com',
            'smtp_port' => 587,
            'smtp_encryption' => 'tls'
        ],
        'yandex' => [
            'imap_host' => 'imap.yandex.com',
            'imap_port' => 993,
            'imap_encryption' => 'ssl',
            'smtp_host' => 'smtp.yandex.com',
            'smtp_port' => 587,
            'smtp_encryption' => 'tls'
        ]
    ],

    // Pagination
    'emails_per_page' => 25,
];
