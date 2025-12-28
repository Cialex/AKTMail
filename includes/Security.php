<?php
/**
 * AktMail - Güvenlik Sınıfı
 * 
 * Şifre hashleme, veri şifreleme, CSRF koruması
 */

namespace AktMail;

class Security
{
    private static array $config;

    /**
     * Konfigürasyonu yükler
     */
    private static function loadConfig(): void
    {
        if (!isset(self::$config)) {
            self::$config = require __DIR__ . '/../config/app.php';
        }
    }

    /**
     * Şifreyi hashler (bcrypt)
     */
    public static function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    /**
     * Şifreyi doğrular
     */
    public static function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    /**
     * Veriyi AES-256-CBC ile şifreler
     */
    public static function encrypt(string $data): string
    {
        self::loadConfig();

        $key = hex2bin(self::$config['encryption_key']);
        $method = self::$config['encryption_method'];

        $ivLength = openssl_cipher_iv_length($method);
        $iv = openssl_random_pseudo_bytes($ivLength);

        $encrypted = openssl_encrypt($data, $method, $key, OPENSSL_RAW_DATA, $iv);

        if ($encrypted === false) {
            throw new \Exception("Şifreleme başarısız");
        }

        // IV'yi encrypted data ile birlikte sakla
        return base64_encode($iv . $encrypted);
    }

    /**
     * Şifrelenmiş veriyi çözer
     */
    public static function decrypt(string $encryptedData): string
    {
        self::loadConfig();

        $key = hex2bin(self::$config['encryption_key']);
        $method = self::$config['encryption_method'];

        $data = base64_decode($encryptedData);

        $ivLength = openssl_cipher_iv_length($method);
        $iv = substr($data, 0, $ivLength);
        $encrypted = substr($data, $ivLength);

        $decrypted = openssl_decrypt($encrypted, $method, $key, OPENSSL_RAW_DATA, $iv);

        if ($decrypted === false) {
            throw new \Exception("Şifre çözme başarısız");
        }

        return $decrypted;
    }

    /**
     * CSRF token oluşturur
     */
    public static function generateCSRFToken(): string
    {
        self::loadConfig();

        $token = bin2hex(random_bytes(32));

        $_SESSION[self::$config['csrf']['token_name']] = [
            'token' => $token,
            'expires' => time() + self::$config['csrf']['lifetime']
        ];

        return $token;
    }

    /**
     * CSRF token'ı doğrular
     */
    public static function validateCSRFToken(string $token): bool
    {
        self::loadConfig();

        $tokenName = self::$config['csrf']['token_name'];

        if (!isset($_SESSION[$tokenName])) {
            return false;
        }

        $storedData = $_SESSION[$tokenName];

        // Token süresi dolmuş mu?
        if (time() > $storedData['expires']) {
            unset($_SESSION[$tokenName]);
            return false;
        }

        // Token eşleşiyor mu?
        if (!hash_equals($storedData['token'], $token)) {
            return false;
        }

        return true;
    }

    /**
     * Güvenli random token oluşturur
     */
    public static function generateToken(int $length = 32): string
    {
        return bin2hex(random_bytes($length));
    }

    /**
     * Token hashler (remember me için)
     */
    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * XSS koruması için HTML escape
     */
    public static function escape(string $string): string
    {
        return htmlspecialchars($string, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Input sanitization
     */
    public static function sanitize(string $input): string
    {
        $input = trim($input);
        $input = stripslashes($input);
        return self::escape($input);
    }

    /**
     * E-posta validasyonu
     */
    public static function validateEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Güvenli oturum başlatır
     */
    public static function startSecureSession(): void
    {
        self::loadConfig();

        if (session_status() === PHP_SESSION_NONE) {
            $sessionConfig = self::$config['session'];

            session_name($sessionConfig['name']);

            session_set_cookie_params([
                'lifetime' => $sessionConfig['lifetime'],
                'path' => $sessionConfig['path'],
                'domain' => $sessionConfig['domain'],
                'secure' => $sessionConfig['secure'],
                'httponly' => $sessionConfig['httponly'],
                'samesite' => $sessionConfig['samesite']
            ]);

            session_start();

            // Session fixation koruması
            if (!isset($_SESSION['_initiated'])) {
                session_regenerate_id(true);
                $_SESSION['_initiated'] = true;
            }
        }
    }

    /**
     * Oturumu sonlandırır
     */
    public static function destroySession(): void
    {
        $_SESSION = [];

        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }

        session_destroy();
    }
}
