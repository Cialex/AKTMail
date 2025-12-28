<?php
/**
 * AktMail - Kayıt Sayfası
 */

require_once __DIR__ . '/includes/Database.php';
require_once __DIR__ . '/includes/Security.php';
require_once __DIR__ . '/includes/Auth.php';

use AktMail\Security;
use AktMail\Auth;

Security::startSecureSession();

$auth = Auth::getInstance();
$auth->redirectIfLoggedIn();

$csrfToken = Security::generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="AktMail - Hesap Oluştur">
    <title>Kayıt Ol - AktMail</title>
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
                <p>Hesap oluşturun</p>
            </div>

            <form id="register-form" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">

                <div class="form-group">
                    <label class="form-label" for="username">Kullanıcı Adı</label>
                    <input type="text" id="username" name="username" class="form-input" placeholder="kullanici_adi"
                        autocomplete="username" minlength="3" maxlength="50" required>
                    <span class="form-hint">En az 3 karakter</span>
                </div>

                <div class="form-group">
                    <label class="form-label" for="email">E-posta Adresi</label>
                    <input type="email" id="email" name="email" class="form-input" placeholder="ornek@mail.com"
                        autocomplete="email" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">Şifre</label>
                    <input type="password" id="password" name="password" class="form-input" placeholder="••••••••"
                        autocomplete="new-password" minlength="6" required>
                    <span class="form-hint">En az 6 karakter</span>
                </div>

                <div class="form-group">
                    <label class="form-label" for="confirm_password">Şifre Tekrar</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-input"
                        placeholder="••••••••" autocomplete="new-password" minlength="6" required>
                </div>

                <button type="submit" class="btn btn-primary btn-block btn-lg">
                    Kayıt Ol
                </button>
            </form>

            <div class="auth-footer">
                <p>Zaten hesabınız var mı? <a href="login.php">Giriş yapın</a></p>
            </div>
        </div>
    </div>

    <div id="toast-container" class="toast-container"></div>

    <script src="assets/js/app.js"></script>
    <script>
        // Register form handler
        const registerForm = document.getElementById('register-form');
        if (registerForm) {
            registerForm.addEventListener('submit', async (e) => {
                e.preventDefault();

                const formData = new FormData(registerForm);
                const username = formData.get('username');
                const email = formData.get('email');
                const password = formData.get('password');
                const confirm_password = formData.get('confirm_password');

                if (password !== confirm_password) {
                    showToast('Şifreler eşleşmiyor', 'error');
                    return;
                }

                const result = await apiCall('api/auth.php?action=register', {
                    method: 'POST',
                    body: { username, email, password, confirm_password }
                });

                if (result?.success) {
                    showToast('Kayıt başarılı! Giriş yapabilirsiniz.', 'success');
                    setTimeout(() => window.location.href = 'login.php', 1500);
                } else {
                    showToast(result?.message || 'Kayıt başarısız', 'error');
                }
            });
        }
    </script>
</body>

</html>