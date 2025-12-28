<?php
/**
 * AktMail - Kimlik Doğrulama Sınıfı
 * 
 * Kullanıcı kaydı, giriş, çıkış ve oturum yönetimi
 */

namespace AktMail;

class Auth
{
    private Database $db;
    private array $config;
    private static ?Auth $instance = null;

    private function __construct()
    {
        $this->db = Database::getInstance();
        $this->config = require __DIR__ . '/../config/app.php';
    }

    /**
     * Singleton instance döndürür
     */
    public static function getInstance(): Auth
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Yeni kullanıcı kaydeder
     */
    public function register(string $username, string $email, string $password): array
    {
        // Validasyon
        if (strlen($username) < 3 || strlen($username) > 50) {
            return ['success' => false, 'message' => 'Kullanıcı adı 3-50 karakter arasında olmalıdır'];
        }

        if (!Security::validateEmail($email)) {
            return ['success' => false, 'message' => 'Geçersiz e-posta adresi'];
        }

        if (strlen($password) < 6) {
            return ['success' => false, 'message' => 'Şifre en az 6 karakter olmalıdır'];
        }

        // Kullanıcı adı kontrolü
        $existing = $this->db->fetchOne(
            "SELECT id FROM users WHERE username = ? OR email = ?",
            [$username, $email]
        );

        if ($existing) {
            return ['success' => false, 'message' => 'Bu kullanıcı adı veya e-posta zaten kayıtlı'];
        }

        // Kullanıcı oluştur
        try {
            $userId = $this->db->insert('users', [
                'username' => $username,
                'email' => $email,
                'password_hash' => Security::hashPassword($password)
            ]);

            return ['success' => true, 'message' => 'Kayıt başarılı', 'user_id' => $userId];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Kayıt sırasında bir hata oluştu'];
        }
    }

    /**
     * Kullanıcı girişi yapar
     */
    public function login(string $username, string $password, bool $remember = false): array
    {
        // Kullanıcıyı bul
        $user = $this->db->fetchOne(
            "SELECT id, username, email, password_hash FROM users WHERE username = ? OR email = ?",
            [$username, $username]
        );

        if (!$user) {
            return ['success' => false, 'message' => 'Kullanıcı adı veya şifre hatalı'];
        }

        // Şifre kontrolü
        if (!Security::verifyPassword($password, $user['password_hash'])) {
            return ['success' => false, 'message' => 'Kullanıcı adı veya şifre hatalı'];
        }

        // Session'a kullanıcı bilgilerini kaydet
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['logged_in'] = true;
        $_SESSION['login_time'] = time();

        // Session ID'yi yenile (session fixation koruması)
        session_regenerate_id(true);

        // Beni hatırla
        if ($remember) {
            $this->createRememberToken($user['id']);
        }

        return [
            'success' => true,
            'message' => 'Giriş başarılı',
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email']
            ]
        ];
    }

    /**
     * Çıkış yapar
     */
    public function logout(): void
    {
        $userId = $_SESSION['user_id'] ?? null;

        // Remember token'ı sil
        if ($userId) {
            $this->db->delete('remember_tokens', 'user_id = ?', [$userId]);
        }

        // Remember cookie'yi sil
        if (isset($_COOKIE[$this->config['remember']['cookie_name']])) {
            setcookie(
                $this->config['remember']['cookie_name'],
                '',
                time() - 3600,
                '/',
                '',
                false,
                true
            );
        }

        // Session'ı yok et
        Security::destroySession();
    }

    /**
     * Oturum açık mı kontrol eder
     */
    public function isLoggedIn(): bool
    {
        // Session kontrolü
        if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
            return true;
        }

        // Remember token kontrolü
        if (isset($_COOKIE[$this->config['remember']['cookie_name']])) {
            return $this->validateRememberToken($_COOKIE[$this->config['remember']['cookie_name']]);
        }

        return false;
    }

    /**
     * Mevcut kullanıcıyı döndürür
     */
    public function getCurrentUser(): ?array
    {
        if (!$this->isLoggedIn()) {
            return null;
        }

        return $this->db->fetchOne(
            "SELECT id, username, email, created_at FROM users WHERE id = ?",
            [$_SESSION['user_id']]
        );
    }

    /**
     * Remember token oluşturur
     */
    private function createRememberToken(int $userId): void
    {
        // Eski token'ları sil
        $this->db->delete('remember_tokens', 'user_id = ?', [$userId]);

        // Yeni token oluştur
        $token = Security::generateToken(32);
        $tokenHash = Security::hashToken($token);
        $expiresAt = date('Y-m-d H:i:s', time() + $this->config['remember']['lifetime']);

        // Token'ı veritabanına kaydet
        $this->db->insert('remember_tokens', [
            'user_id' => $userId,
            'token_hash' => $tokenHash,
            'expires_at' => $expiresAt
        ]);

        // Cookie'yi ayarla
        setcookie(
            $this->config['remember']['cookie_name'],
            $token,
            time() + $this->config['remember']['lifetime'],
            '/',
            '',
            false, // Secure - HTTPS için true yapın
            true   // HttpOnly
        );
    }

    /**
     * Remember token'ı doğrular
     */
    private function validateRememberToken(string $token): bool
    {
        $tokenHash = Security::hashToken($token);

        $tokenData = $this->db->fetchOne(
            "SELECT rt.user_id, rt.expires_at, u.username, u.email 
             FROM remember_tokens rt 
             JOIN users u ON rt.user_id = u.id 
             WHERE rt.token_hash = ? AND rt.expires_at > NOW()",
            [$tokenHash]
        );

        if (!$tokenData) {
            // Geçersiz veya süresi dolmuş token
            setcookie($this->config['remember']['cookie_name'], '', time() - 3600, '/');
            return false;
        }

        // Kullanıcıyı session'a ekle
        $_SESSION['user_id'] = $tokenData['user_id'];
        $_SESSION['username'] = $tokenData['username'];
        $_SESSION['email'] = $tokenData['email'];
        $_SESSION['logged_in'] = true;
        $_SESSION['login_time'] = time();

        // Token'ı yenile (token rotation)
        $this->createRememberToken($tokenData['user_id']);

        return true;
    }

    /**
     * Kullanıcı ID'sini döndürür
     */
    public function getUserId(): ?int
    {
        return $_SESSION['user_id'] ?? null;
    }

    /**
     * Giriş gerektiren sayfalar için kontrol
     */
    public function requireLogin(): void
    {
        if (!$this->isLoggedIn()) {
            header('Location: login.php');
            exit;
        }
    }

    /**
     * Zaten giriş yapmışsa yönlendir
     */
    public function redirectIfLoggedIn(): void
    {
        if ($this->isLoggedIn()) {
            header('Location: dashboard.php');
            exit;
        }
    }
}
