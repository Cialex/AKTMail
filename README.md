# 📧 AKTMail

<p align="center">
  <strong>Modern Web Tabanlı E-posta İstemcisi</strong><br>
  Thunderbird benzeri, PHP ile geliştirilmiş web e-posta uygulaması
</p>

<p align="center">
  <img src="https://img.shields.io/badge/PHP-7.4+-777BB4?style=flat&logo=php&logoColor=white" alt="PHP 7.4+">
  <img src="https://img.shields.io/badge/MySQL-5.7+-4479A1?style=flat&logo=mysql&logoColor=white" alt="MySQL 5.7+">
  <img src="https://img.shields.io/badge/License-MIT-green?style=flat" alt="MIT License">
</p>

---

## 📋 İçindekiler

- [Özellikler](#-özellikler)
- [Gereksinimler](#-gereksinimler)
- [Kurulum](#-kurulum)
- [Yapılandırma](#-yapılandırma)
- [Proje Yapısı](#-proje-yapısı)
- [Desteklenen E-posta Sağlayıcıları](#-desteklenen-e-posta-sağlayıcıları)
- [Ekran Görüntüleri](#-ekran-görüntüleri)
- [API Referansı](#-api-referansı)
- [Güvenlik](#-güvenlik)
- [Lisans](#-lisans)

---

## ✨ Özellikler

### 📬 E-posta Yönetimi
- **Birleşik Gelen Kutusu** - Tüm hesaplarınızdan gelen e-postaları tek bir yerde görüntüleyin
- **E-posta Okuma/Yazma** - HTML ve düz metin e-posta desteği
- **Çoklu Hesap Desteği** - Birden fazla e-posta hesabı ekleyebilme
- **Toplu İşlemler** - Birden fazla e-postayı seçip silme, taşıma, okundu/okunmadı işaretleme
- **Ek Dosyalar** - E-posta eklerini görüntüleme ve indirme

### 📁 Klasör Yönetimi
- **Standart Klasörler** - Gelen Kutusu, Gönderilenler, Spam, Çöp Kutusu
- **Özel Klasörler** - Kendi klasörlerinizi oluşturun ve e-postaları organize edin
- **Klasörler Arası Taşıma** - E-postaları klasörler arasında taşıyın

### 🎨 Modern Arayüz
- **Karanlık Tema** - Göz yormayan koyu tema
- **Çoklu Tema Seçenekleri** - Koyu, Açık, Mor, Mavi, Yeşil temalar
- **Responsive Tasarım** - Mobil ve masaüstü uyumlu
- **Glassmorphism Efektleri** - Modern cam efektli tasarım

### 🔒 Güvenlik
- **Şifreli Parola Saklama** - AES-256-CBC ile e-posta şifreleri şifrelenir
- **CSRF Koruması** - Form güvenliği için CSRF token
- **Güvenli Oturum Yönetimi** - Session fixation koruması
- **"Beni Hatırla" Özelliği** - Güvenli token tabanlı oturum hatırlama

### 🔧 Ek Özellikler
- **E-posta İmzaları** - Her hesap için özel imzalar oluşturun
- **Filtre Kuralları** - Gönderene göre otomatik klasörleme
- **Arama** - E-postalarınızda arama yapın
- **Okunmamış Sayacı** - Anlık okunmamış e-posta sayısı

---

## 📦 Gereksinimler

| Gereksinim | Minimum Versiyon |
|------------|------------------|
| PHP | 7.4+ |
| MySQL / MariaDB | 5.7+ / 10.2+ |
| PHP Eklentileri | `imap`, `openssl`, `pdo`, `mbstring` |
| Composer | 2.0+ |

---

## 🚀 Kurulum

### 1. Projeyi Klonlayın

```bash
git clone https://github.com/Cialex/AKTMail.git
cd AKTMail
```

### 2. Bağımlılıkları Yükleyin

```bash
composer install
```

### 3. Veritabanını Oluşturun

MySQL veritabanınızda `setup.sql` dosyasını çalıştırın:

```bash
mysql -u root -p < setup.sql
```

Veya phpMyAdmin üzerinden `setup.sql` dosyasını içe aktarın.

### 4. Yapılandırma Dosyalarını Düzenleyin

#### Veritabanı Ayarları (`config/database.php`)

```php
return [
    'host' => 'localhost',
    'port' => 3306,
    'database' => 'aktmail',
    'username' => 'root',
    'password' => 'your_password',
    // ...
];
```

#### Uygulama Ayarları (`config/app.php`)

```php
return [
    'debug' => false, // Prodüksiyonda false yapın
    'base_url' => 'https://your-domain.com',
    
    // ÖNEMLİ: Bu anahtarı değiştirin!
    'encryption_key' => bin2hex(random_bytes(32)),
    
    // HTTPS kullanıyorsanız:
    'session' => [
        'secure' => true,
        // ...
    ],
];
```

### 5. PHP IMAP Eklentisini Etkinleştirin

**Windows (XAMPP/WAMP):**
`php.ini` dosyasında şu satırın başındaki noktalı virgülü kaldırın:
```ini
extension=imap
```

**Linux (Ubuntu/Debian):**
```bash
sudo apt-get install php-imap
sudo systemctl restart apache2
```

**cPanel/Plesk:**
PHP Selector'dan IMAP eklentisini etkinleştirin.

### 6. Web Sunucusunu Başlatın

**Geliştirme:**
```bash
php -S localhost:8080
```

**Prodüksiyon:**
Apache veya Nginx ile kök dizini projenin ana klasörüne yönlendirin.

---

## ⚙️ Yapılandırma

### E-posta Sağlayıcı Ayarları

Uygulama, popüler e-posta sağlayıcıları için önceden yapılandırılmış ayarlarla gelir:

| Sağlayıcı | IMAP Sunucu | IMAP Port | SMTP Sunucu | SMTP Port |
|-----------|-------------|-----------|-------------|-----------|
| Gmail | imap.gmail.com | 993 | smtp.gmail.com | 587 |
| Outlook | outlook.office365.com | 993 | smtp.office365.com | 587 |
| Yahoo | imap.mail.yahoo.com | 993 | smtp.mail.yahoo.com | 587 |
| Yandex | imap.yandex.com | 993 | smtp.yandex.com | 587 |

> **Gmail Kullanıcıları için Not:** Gmail hesabınız için [Uygulama Şifresi](https://myaccount.google.com/apppasswords) oluşturmanız gerekmektedir.

---

## 📂 Proje Yapısı

```
AKTMail/
├── api/                    # REST API endpoint'leri
│   ├── accounts.php        # Hesap yönetimi API
│   ├── auth.php           # Kimlik doğrulama API
│   ├── emails.php         # E-posta işlemleri API
│   ├── filters.php        # Filtre kuralları API
│   ├── folders.php        # Klasör yönetimi API
│   ├── settings.php       # Kullanıcı ayarları API
│   └── signatures.php     # İmza yönetimi API
│
├── assets/                 # Statik dosyalar
│   ├── css/
│   │   └── style.css      # Ana stil dosyası (~1900 satır)
│   └── js/
│       └── app.js         # İstemci tarafı JavaScript
│
├── config/                 # Yapılandırma dosyaları
│   ├── app.php            # Uygulama ayarları
│   └── database.php       # Veritabanı bağlantı ayarları
│
├── includes/               # PHP sınıfları
│   ├── Auth.php           # Kimlik doğrulama sınıfı
│   ├── Database.php       # Veritabanı bağlantı sınıfı
│   ├── EmailAccount.php   # E-posta hesap yönetimi
│   ├── EmailClient.php    # IMAP/SMTP istemcisi (~1000 satır)
│   └── Security.php       # Güvenlik fonksiyonları
│
├── vendor/                 # Composer bağımlılıkları
│
├── accounts.php           # Hesap yönetimi sayfası
├── dashboard.php          # Ana panel (gelen kutusu)
├── index.php              # Açılış sayfası
├── login.php              # Giriş sayfası
├── logout.php             # Çıkış işlemi
├── register.php           # Kayıt sayfası
├── setup.sql              # Veritabanı şeması
├── composer.json          # PHP bağımlılıkları
└── LICENSE                # MIT Lisansı
```

---

## 📧 Desteklenen E-posta Sağlayıcıları

AKTMail, IMAP ve SMTP protokollerini destekleyen tüm e-posta sağlayıcılarıyla çalışır:

- ✅ Gmail
- ✅ Outlook / Hotmail / Live
- ✅ Yahoo Mail
- ✅ Yandex
- ✅ iCloud
- ✅ ProtonMail (Bridge ile)
- ✅ Özel domain e-postaları
- ✅ Kurumsal e-posta sunucuları

---

## 🔌 API Referansı

### Kimlik Doğrulama
| Endpoint | Metod | Açıklama |
|----------|-------|----------|
| `/api/auth.php?action=login` | POST | Kullanıcı girişi |
| `/api/auth.php?action=register` | POST | Yeni kullanıcı kaydı |
| `/api/auth.php?action=logout` | POST | Oturumu sonlandır |

### E-posta İşlemleri
| Endpoint | Metod | Açıklama |
|----------|-------|----------|
| `/api/emails.php?action=inbox` | GET | Gelen kutusunu getir |
| `/api/emails.php?action=sent` | GET | Gönderilenleri getir |
| `/api/emails.php?action=read` | GET | E-posta detayını oku |
| `/api/emails.php?action=send` | POST | E-posta gönder |
| `/api/emails.php?action=delete` | POST | E-postayı sil |
| `/api/emails.php?action=mark_read` | POST | Okundu işaretle |
| `/api/emails.php?action=mark_unread` | POST | Okunmadı işaretle |
| `/api/emails.php?action=move_to_folder` | POST | Klasöre taşı |

### Hesap Yönetimi
| Endpoint | Metod | Açıklama |
|----------|-------|----------|
| `/api/accounts.php?action=list` | GET | Hesapları listele |
| `/api/accounts.php?action=add` | POST | Yeni hesap ekle |
| `/api/accounts.php?action=delete` | POST | Hesabı sil |

---

## 🔒 Güvenlik

AKTMail, güvenlik konusunda aşağıdaki önlemleri içerir:

- **Şifre Hashleme** - Kullanıcı şifreleri bcrypt ile hashlenir
- **E-posta Şifre Şifreleme** - AES-256-CBC ile e-posta hesap şifreleri şifrelenir
- **CSRF Koruması** - Tüm form işlemleri CSRF token ile korunur
- **Session Güvenliği** - Session fixation saldırılarına karşı koruma
- **Prepared Statements** - SQL injection koruması
- **XSS Koruması** - HTML çıktıları kaçırılır

### Prodüksiyon için Öneriler

1. `config/app.php` içinde `debug` değerini `false` yapın
2. `encryption_key` değerini benzersiz bir değerle değiştirin
3. HTTPS kullanın ve `session.secure` değerini `true` yapın
4. Güçlü veritabanı şifresi kullanın

---

## 📄 Lisans

Bu proje [MIT Lisansı](LICENSE) altında lisanslanmıştır.

```
MIT License

Copyright (c) 2025 Aykut Meral

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.
```

---

## 🤝 Katkıda Bulunma

Katkılarınızı memnuniyetle karşılıyoruz! Lütfen:

1. Bu depoyu fork edin
2. Özellik dalı oluşturun (`git checkout -b feature/YeniOzellik`)
3. Değişikliklerinizi commit edin (`git commit -m 'Yeni özellik eklendi'`)
4. Dalınıza push edin (`git push origin feature/YeniOzellik`)
5. Pull Request açın

---

## 📞 İletişim

- **GitHub:** [@Cialex](https://github.com/Cialex)
- **Proje:** [AKTMail](https://github.com/Cialex/AKTMail)

---

<p align="center">
  ⭐ Bu projeyi beğendiyseniz yıldız vermeyi unutmayın!
</p>
