<?php
/**
 * AktMail - Giriş Sayfası
 */

require_once __DIR__ . '/includes/Database.php';
require_once __DIR__ . '/includes/Security.php';
require_once __DIR__ . '/includes/Auth.php';

use AktMail\Security;
use AktMail\Auth;

Security::startSecureSession();

$auth = Auth::getInstance();

// Zaten giriş yapmışsa dashboard'a yönlendir
if ($auth->isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$csrfToken = Security::generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="AktMail - Modern Web E-posta İstemcisi">
    <title>Giriş Yap - AktMail</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body>
    <div class="auth-container">
        <div class="auth-card glass-card">
            <div class="auth-logo">
                <h1>📧 AktMail</h1>
                <p>Tüm e-postalarınız tek bir yerde</p>
            </div>

            <form id="login-form" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">

                <div class="form-group">
                    <label class="form-label" for="username">Kullanıcı Adı veya E-posta</label>
                    <input type="text" id="username" name="username" class="form-input" placeholder="kullanici_adi"
                        autocomplete="username" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">Şifre</label>
                    <input type="password" id="password" name="password" class="form-input" placeholder="••••••••"
                        autocomplete="current-password" required>
                </div>

                <div class="form-group">
                    <label class="form-checkbox">
                        <input type="checkbox" name="remember" id="remember">
                        <span>Beni hatırla</span>
                    </label>
                </div>

                <button type="submit" class="btn btn-primary btn-block btn-lg">
                    Giriş Yap
                </button>
            </form>

            <div class="auth-footer">
                <p>Hesabınız yok mu? <a href="register.php">Kayıt olun</a></p>
                <p><a href="index.php">← Ana Sayfaya Dön</a></p>
            </div>
        </div>
    </div>

    <div id="toast-container" class="toast-container"></div>

    <script src="assets/js/app.js"></script>
</body>

</html>