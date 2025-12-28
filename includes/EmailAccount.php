<?php
/**
 * AktMail - E-posta Hesap Yönetimi Sınıfı
 * 
 * E-posta hesaplarının eklenmesi, güncellenmesi, silinmesi ve listelenmesi
 */

namespace AktMail;

class EmailAccount
{
    private Database $db;
    private int $userId;
    private array $config;

    public function __construct(int $userId)
    {
        $this->db = Database::getInstance();
        $this->userId = $userId;
        $this->config = require __DIR__ . '/../config/app.php';
    }

    /**
     * Yeni e-posta hesabı ekler
     */
    public function add(array $data): array
    {
        // Validasyon
        $required = ['email', 'password', 'imap_host', 'imap_port', 'smtp_host', 'smtp_port'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return ['success' => false, 'message' => "'{$field}' alanı zorunludur"];
            }
        }

        if (!Security::validateEmail($data['email'])) {
            return ['success' => false, 'message' => 'Geçersiz e-posta adresi'];
        }

        // Bu hesap zaten kayıtlı mı?
        $existing = $this->db->fetchOne(
            "SELECT id FROM email_accounts WHERE user_id = ? AND email = ?",
            [$this->userId, $data['email']]
        );

        if ($existing) {
            return ['success' => false, 'message' => 'Bu e-posta hesabı zaten ekli'];
        }

        // Bağlantı testi yap
        $testResult = $this->testConnection($data);
        if (!$testResult['success']) {
            return $testResult;
        }

        // Şifreyi şifrele
        $encryptedPassword = Security::encrypt($data['password']);

        try {
            $accountId = $this->db->insert('email_accounts', [
                'user_id' => $this->userId,
                'email' => $data['email'],
                'display_name' => $data['display_name'] ?? $data['email'],
                'imap_host' => $data['imap_host'],
                'imap_port' => (int) $data['imap_port'],
                'imap_encryption' => $data['imap_encryption'] ?? 'ssl',
                'smtp_host' => $data['smtp_host'],
                'smtp_port' => (int) $data['smtp_port'],
                'smtp_encryption' => $data['smtp_encryption'] ?? 'tls',
                'encrypted_password' => $encryptedPassword,
                'is_active' => 1
            ]);

            return ['success' => true, 'message' => 'E-posta hesabı başarıyla eklendi', 'account_id' => $accountId];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Hesap eklenirken bir hata oluştu'];
        }
    }

    /**
     * E-posta hesabını günceller
     */
    public function update(int $accountId, array $data): array
    {
        // Hesap bu kullanıcıya ait mi?
        $account = $this->getById($accountId);
        if (!$account) {
            return ['success' => false, 'message' => 'Hesap bulunamadı'];
        }

        $updateData = [];

        if (isset($data['display_name'])) {
            $updateData['display_name'] = $data['display_name'];
        }

        if (isset($data['imap_host'])) {
            $updateData['imap_host'] = $data['imap_host'];
        }

        if (isset($data['imap_port'])) {
            $updateData['imap_port'] = (int) $data['imap_port'];
        }

        if (isset($data['imap_encryption'])) {
            $updateData['imap_encryption'] = $data['imap_encryption'];
        }

        if (isset($data['smtp_host'])) {
            $updateData['smtp_host'] = $data['smtp_host'];
        }

        if (isset($data['smtp_port'])) {
            $updateData['smtp_port'] = (int) $data['smtp_port'];
        }

        if (isset($data['smtp_encryption'])) {
            $updateData['smtp_encryption'] = $data['smtp_encryption'];
        }

        if (isset($data['is_active'])) {
            $updateData['is_active'] = (int) $data['is_active'];
        }

        // Şifre değiştirildi mi?
        if (!empty($data['password'])) {
            // Bağlantı testi yap
            $testData = array_merge($account, $data);
            $testResult = $this->testConnection($testData);
            if (!$testResult['success']) {
                return $testResult;
            }
            $updateData['encrypted_password'] = Security::encrypt($data['password']);
        }

        if (empty($updateData)) {
            return ['success' => false, 'message' => 'Güncellenecek veri bulunamadı'];
        }

        try {
            $this->db->update('email_accounts', $updateData, 'id = ? AND user_id = ?', [$accountId, $this->userId]);
            return ['success' => true, 'message' => 'Hesap başarıyla güncellendi'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Güncelleme sırasında bir hata oluştu'];
        }
    }

    /**
     * E-posta hesabını siler
     */
    public function delete(int $accountId): array
    {
        $account = $this->getById($accountId);
        if (!$account) {
            return ['success' => false, 'message' => 'Hesap bulunamadı'];
        }

        try {
            $this->db->delete('email_accounts', 'id = ? AND user_id = ?', [$accountId, $this->userId]);
            return ['success' => true, 'message' => 'Hesap başarıyla silindi'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Silme sırasında bir hata oluştu'];
        }
    }

    /**
     * Kullanıcının tüm e-posta hesaplarını döndürür
     */
    public function getAll(bool $activeOnly = true): array
    {
        $sql = "SELECT id, email, display_name, imap_host, imap_port, imap_encryption, 
                       smtp_host, smtp_port, smtp_encryption, is_active, last_sync, created_at 
                FROM email_accounts 
                WHERE user_id = ?";

        if ($activeOnly) {
            $sql .= " AND is_active = 1";
        }

        $sql .= " ORDER BY created_at ASC";

        return $this->db->fetchAll($sql, [$this->userId]);
    }

    /**
     * ID ile hesap döndürür
     */
    public function getById(int $accountId): ?array
    {
        return $this->db->fetchOne(
            "SELECT * FROM email_accounts WHERE id = ? AND user_id = ?",
            [$accountId, $this->userId]
        );
    }

    /**
     * Hesap bilgileriyle şifre çözülmüş olarak döndürür (IMAP/SMTP bağlantısı için)
     */
    public function getWithPassword(int $accountId): ?array
    {
        $account = $this->getById($accountId);
        if (!$account) {
            return null;
        }

        $account['password'] = Security::decrypt($account['encrypted_password']);
        unset($account['encrypted_password']);

        return $account;
    }

    /**
     * Tüm hesapları şifre çözülmüş olarak döndürür
     */
    public function getAllWithPasswords(bool $activeOnly = true): array
    {
        $accounts = $this->db->fetchAll(
            "SELECT * FROM email_accounts WHERE user_id = ?" . ($activeOnly ? " AND is_active = 1" : ""),
            [$this->userId]
        );

        foreach ($accounts as &$account) {
            $account['password'] = Security::decrypt($account['encrypted_password']);
            unset($account['encrypted_password']);
        }

        return $accounts;
    }

    /**
     * IMAP ve SMTP bağlantı testi
     */
    public function testConnection(array $data): array
    {
        // IMAP testi
        $imapResult = $this->testImapConnection(
            $data['imap_host'],
            (int) $data['imap_port'],
            $data['email'],
            $data['password'],
            $data['imap_encryption'] ?? 'ssl'
        );

        if (!$imapResult['success']) {
            return ['success' => false, 'message' => 'IMAP bağlantısı başarısız: ' . $imapResult['message']];
        }

        // SMTP testi
        $smtpResult = $this->testSmtpConnection(
            $data['smtp_host'],
            (int) $data['smtp_port'],
            $data['email'],
            $data['password'],
            $data['smtp_encryption'] ?? 'tls'
        );

        if (!$smtpResult['success']) {
            return ['success' => false, 'message' => 'SMTP bağlantısı başarısız: ' . $smtpResult['message']];
        }

        return ['success' => true, 'message' => 'Bağlantı testi başarılı'];
    }

    /**
     * IMAP bağlantı testi
     */
    private function testImapConnection(string $host, int $port, string $email, string $password, string $encryption): array
    {
        $flags = '/imap';

        if ($encryption === 'ssl') {
            $flags .= '/ssl';
        } elseif ($encryption === 'tls') {
            $flags .= '/tls';
        }

        $flags .= '/novalidate-cert';

        $mailbox = '{' . $host . ':' . $port . $flags . '}INBOX';

        try {
            $imap = @\imap_open($mailbox, $email, $password, 0, 1);

            if ($imap === false) {
                $error = \imap_last_error();
                return ['success' => false, 'message' => $error ?: 'Bağlantı kurulamadı'];
            }

            \imap_close($imap);
            return ['success' => true, 'message' => 'IMAP bağlantısı başarılı'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * SMTP bağlantı testi
     */
    private function testSmtpConnection(string $host, int $port, string $email, string $password, string $encryption): array
    {
        require_once __DIR__ . '/../vendor/autoload.php';

        try {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = $host;
            $mail->Port = $port;
            $mail->SMTPAuth = true;
            $mail->Username = $email;
            $mail->Password = $password;

            if ($encryption === 'ssl') {
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            } elseif ($encryption === 'tls') {
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            }

            $mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                ]
            ];

            // Bağlantı testi
            $mail->smtpConnect();
            $mail->smtpClose();

            return ['success' => true, 'message' => 'SMTP bağlantısı başarılı'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Son senkronizasyon zamanını günceller
     */
    public function updateLastSync(int $accountId): void
    {
        $this->db->update(
            'email_accounts',
            ['last_sync' => date('Y-m-d H:i:s')],
            'id = ? AND user_id = ?',
            [$accountId, $this->userId]
        );
    }

    /**
     * E-posta sağlayıcı ayarlarını otomatik doldurur
     */
    public function autoDetectProvider(string $email): ?array
    {
        $domain = substr(strrchr($email, "@"), 1);
        $providers = $this->config['email_providers'];

        // Bilinen sağlayıcılar
        $domainMap = [
            'gmail.com' => 'gmail',
            'googlemail.com' => 'gmail',
            'outlook.com' => 'outlook',
            'hotmail.com' => 'outlook',
            'live.com' => 'outlook',
            'yahoo.com' => 'yahoo',
            'yandex.com' => 'yandex',
            'yandex.ru' => 'yandex'
        ];

        if (isset($domainMap[$domain]) && isset($providers[$domainMap[$domain]])) {
            return $providers[$domainMap[$domain]];
        }

        return null;
    }
}
