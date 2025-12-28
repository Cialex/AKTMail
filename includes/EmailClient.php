<?php
/**
 * AktMail - E-posta İstemci Sınıfı
 * 
 * IMAP ile e-posta alma, PHPMailer ile e-posta gönderme
 */

namespace AktMail;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Global IMAP fonksiyonlarını namespace'e import et
use function imap_open;
use function imap_close;
use function imap_reopen;
use function imap_last_error;
use function imap_errors;
use function imap_alerts;
use function imap_num_msg;
use function imap_headerinfo;
use function imap_fetchstructure;
use function imap_fetchbody;
use function imap_msgno;
use function imap_uid;
use function imap_setflag_full;
use function imap_clearflag_full;
use function imap_delete;
use function imap_expunge;
use function imap_mail_move;
use function imap_mail_copy;
use function imap_mime_header_decode;
use function imap_createmailbox;
use function imap_list;

class EmailClient
{
    private Database $db;
    private int $userId;
    private array $config;
    private EmailAccount $accountManager;

    public function __construct(int $userId)
    {
        $this->db = Database::getInstance();
        $this->userId = $userId;
        $this->config = require __DIR__ . '/../config/app.php';
        $this->accountManager = new EmailAccount($userId);

        require_once __DIR__ . '/../vendor/autoload.php';
    }

    /**
     * Tüm hesaplardan gelen kutusu e-postalarını birleştirir
     */
    public function fetchUnifiedInbox(int $limit = 50): array
    {
        $accounts = $this->accountManager->getAll();
        $allEmails = [];

        foreach ($accounts as $account) {
            try {
                $emails = $this->fetchInbox($account['id'], $limit);
                $allEmails = array_merge($allEmails, $emails);
            } catch (\Exception $e) {
                // Hata logla ama devam et
                error_log("Inbox fetch error for account {$account['email']}: " . $e->getMessage());
            }
        }

        // Tarihe göre sırala (en yeni önce)
        usort($allEmails, function ($a, $b) {
            return strtotime($b['date']) - strtotime($a['date']);
        });

        return array_slice($allEmails, 0, $limit);
    }

    /**
     * Belirli bir hesabın gelen kutusunu getirir
     */
    public function fetchInbox(int $accountId, int $limit = 50, int $offset = 0): array
    {
        $account = $this->accountManager->getWithPassword($accountId);
        if (!$account) {
            throw new \Exception('Hesap bulunamadı');
        }

        $imap = $this->connectImap($account);

        try {
            // INBOX'ı seç
            $emails = $this->fetchFromFolder($imap, 'INBOX', $limit, $offset, $account);

            // Son senkronizasyonu güncelle
            $this->accountManager->updateLastSync($accountId);

            imap_close($imap);
            return $emails;
        } catch (\Exception $e) {
            if ($imap) {
                imap_close($imap);
            }
            throw $e;
        }
    }

    /**
     * Gönderilmiş e-postaları getirir
     */
    public function fetchSent(int $accountId, int $limit = 50, int $offset = 0): array
    {
        $account = $this->accountManager->getWithPassword($accountId);
        if (!$account) {
            throw new \Exception('Hesap bulunamadı');
        }

        $imap = $this->connectImap($account);

        try {
            // Sent folder isimlerini dene
            $sentFolders = ['Sent', 'INBOX.Sent', '[Gmail]/Sent Mail', 'Sent Items', 'Sent Messages'];
            $emails = [];

            foreach ($sentFolders as $folder) {
                if (@imap_reopen($imap, $this->getMailboxString($account) . $folder)) {
                    $emails = $this->fetchFromFolder($imap, $folder, $limit, $offset, $account);
                    break;
                }
            }

            imap_close($imap);
            return $emails;
        } catch (\Exception $e) {
            if ($imap) {
                imap_close($imap);
            }
            throw $e;
        }
    }

    /**
     * Tüm hesapların gönderilmiş e-postalarını birleştirir
     */
    public function fetchUnifiedSent(int $limit = 50): array
    {
        $accounts = $this->accountManager->getAll();
        $allEmails = [];

        foreach ($accounts as $account) {
            try {
                $emails = $this->fetchSent($account['id'], $limit);
                $allEmails = array_merge($allEmails, $emails);
            } catch (\Exception $e) {
                error_log("Sent fetch error for account {$account['email']}: " . $e->getMessage());
            }
        }

        usort($allEmails, function ($a, $b) {
            return strtotime($b['date']) - strtotime($a['date']);
        });

        return array_slice($allEmails, 0, $limit);
    }

    /**
     * Belirli bir e-postayı okur
     */
    public function readEmail(int $accountId, int $uid, string $folder = 'INBOX'): ?array
    {
        $account = $this->accountManager->getWithPassword($accountId);
        if (!$account) {
            throw new \Exception('Hesap bulunamadı');
        }

        $imap = $this->connectImap($account, $folder);

        try {
            $msgno = imap_msgno($imap, $uid);
            if ($msgno === 0) {
                imap_close($imap);
                return null;
            }

            $header = imap_headerinfo($imap, $msgno);
            $structure = imap_fetchstructure($imap, $uid, FT_UID);

            $email = [
                'uid' => $uid,
                'account_id' => $accountId,
                'account_email' => $account['email'],
                'folder' => $folder,
                'subject' => $this->decodeHeader($header->subject ?? '(Konu Yok)'),
                'from' => $this->parseAddress($header->from[0] ?? null),
                'to' => isset($header->to) ? array_map([$this, 'parseAddress'], $header->to) : [],
                'cc' => isset($header->cc) ? array_map([$this, 'parseAddress'], $header->cc) : [],
                'date' => date('Y-m-d H:i:s', strtotime($header->date ?? 'now')),
                'seen' => ($header->Unseen ?? 'U') !== 'U',
                'body' => $this->getBody($imap, $uid, $structure),
                'attachments' => $this->getAttachments($imap, $uid, $structure)
            ];

            // E-postayı okundu olarak işaretle
            imap_setflag_full($imap, $uid, '\\Seen', ST_UID);

            imap_close($imap);
            return $email;
        } catch (\Exception $e) {
            if ($imap) {
                imap_close($imap);
            }
            throw $e;
        }
    }

    /**
     * E-posta gönderir
     */
    public function sendEmail(int $accountId, array $emailData): array
    {
        $account = $this->accountManager->getWithPassword($accountId);
        if (!$account) {
            return ['success' => false, 'message' => 'Hesap bulunamadı'];
        }

        try {
            $mail = new PHPMailer(true);

            // SMTP ayarları
            $mail->isSMTP();
            $mail->Host = $account['smtp_host'];
            $mail->Port = $account['smtp_port'];
            $mail->SMTPAuth = true;
            $mail->Username = $account['email'];
            $mail->Password = $account['password'];

            if ($account['smtp_encryption'] === 'ssl') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } elseif ($account['smtp_encryption'] === 'tls') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            }

            $mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                ]
            ];

            // Karakter seti
            $mail->CharSet = 'UTF-8';
            $mail->Encoding = 'base64';

            // Gönderen
            $mail->setFrom($account['email'], $account['display_name'] ?? $account['email']);
            $mail->addReplyTo($account['email'], $account['display_name'] ?? $account['email']);

            // Alıcılar
            foreach ((array) $emailData['to'] as $to) {
                $mail->addAddress(trim($to));
            }

            if (!empty($emailData['cc'])) {
                foreach ((array) $emailData['cc'] as $cc) {
                    $mail->addCC(trim($cc));
                }
            }

            if (!empty($emailData['bcc'])) {
                foreach ((array) $emailData['bcc'] as $bcc) {
                    $mail->addBCC(trim($bcc));
                }
            }

            // İçerik
            $mail->isHTML(true);
            $mail->Subject = $emailData['subject'] ?? '';
            $mail->Body = $emailData['body'] ?? '';
            $mail->AltBody = strip_tags($emailData['body'] ?? '');

            // Ekler
            if (!empty($emailData['attachments'])) {
                foreach ($emailData['attachments'] as $attachment) {
                    if (isset($attachment['path']) && file_exists($attachment['path'])) {
                        $mail->addAttachment($attachment['path'], $attachment['name'] ?? basename($attachment['path']));
                    }
                }
            }

            $mail->send();

            return ['success' => true, 'message' => 'E-posta başarıyla gönderildi'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'E-posta gönderilemedi: ' . $mail->ErrorInfo];
        }
    }

    /**
     * Birden fazla hesaptan aynı e-postayı gönderir
     */
    public function sendFromMultipleAccounts(array $accountIds, array $emailData): array
    {
        $results = [];

        foreach ($accountIds as $accountId) {
            $account = $this->accountManager->getById($accountId);
            $accountEmail = $account ? $account['email'] : 'Bilinmiyor';

            $result = $this->sendEmail($accountId, $emailData);
            $results[] = [
                'account_id' => $accountId,
                'account_email' => $accountEmail,
                'success' => $result['success'],
                'message' => $result['message']
            ];
        }

        $successCount = count(array_filter($results, fn($r) => $r['success']));
        $failCount = count($results) - $successCount;

        return [
            'success' => $failCount === 0,
            'message' => "{$successCount} hesaptan gönderildi" . ($failCount > 0 ? ", {$failCount} hesapta hata" : ''),
            'details' => $results
        ];
    }

    /**
     * IMAP bağlantısı oluşturur
     */
    private function connectImap(array $account, string $folder = 'INBOX')
    {
        $mailbox = $this->getMailboxString($account) . $folder;

        $imap = @imap_open($mailbox, $account['email'], $account['password'], 0, 1);

        if ($imap === false) {
            throw new \Exception('IMAP bağlantısı kurulamadı: ' . imap_last_error());
        }

        return $imap;
    }

    /**
     * Mailbox connection string oluşturur
     */
    private function getMailboxString(array $account): string
    {
        $flags = '/imap';

        if ($account['imap_encryption'] === 'ssl') {
            $flags .= '/ssl';
        } elseif ($account['imap_encryption'] === 'tls') {
            $flags .= '/tls';
        }

        $flags .= '/novalidate-cert';

        return '{' . $account['imap_host'] . ':' . $account['imap_port'] . $flags . '}';
    }

    /**
     * Klasörden e-postaları getirir
     */
    private function fetchFromFolder($imap, string $folder, int $limit, int $offset, array $account): array
    {
        $emails = [];
        $totalMessages = imap_num_msg($imap);

        if ($totalMessages === 0) {
            return [];
        }

        $start = max(1, $totalMessages - $offset - $limit + 1);
        $end = max(1, $totalMessages - $offset);

        for ($i = $end; $i >= $start; $i--) {
            $header = @imap_headerinfo($imap, $i);
            if (!$header)
                continue;

            $uid = imap_uid($imap, $i);

            $emails[] = [
                'uid' => $uid,
                'account_id' => $account['id'] ?? 0,
                'account_email' => $account['email'],
                'folder' => $folder,
                'subject' => $this->decodeHeader($header->subject ?? '(Konu Yok)'),
                'from' => $this->parseAddress($header->from[0] ?? null),
                'date' => date('Y-m-d H:i:s', strtotime($header->date ?? 'now')),
                'seen' => ($header->Unseen ?? 'U') !== 'U',
                'size' => $header->Size ?? 0
            ];
        }

        return $emails;
    }

    /**
     * E-posta gövdesini alır
     */
    private function getBody($imap, int $uid, $structure): string
    {
        $body = '';

        if (!$structure->parts) {
            // Tek parçalı e-posta
            $body = $this->fetchPart($imap, $uid, 1, $structure);
        } else {
            // Çok parçalı e-posta
            $body = $this->getPart($imap, $uid, $structure, 'TEXT/HTML');
            if (empty($body)) {
                $body = $this->getPart($imap, $uid, $structure, 'TEXT/PLAIN');
            }
        }

        return $body;
    }

    /**
     * Belirli mime tipinde parça arar
     */
    private function getPart($imap, int $uid, $structure, string $mimeType, string $partNumber = ''): string
    {
        $mimeTypes = [
            'TEXT' => 0,
            'MULTIPART' => 1,
            'MESSAGE' => 2,
            'APPLICATION' => 3,
            'AUDIO' => 4,
            'IMAGE' => 5,
            'VIDEO' => 6,
            'OTHER' => 7
        ];

        $parts = explode('/', $mimeType);
        $primaryType = $mimeTypes[$parts[0]] ?? 0;
        $subType = $parts[1] ?? '';

        if (!isset($structure->parts)) {
            if ($structure->type === $primaryType && strtoupper($structure->subtype) === $subType) {
                return $this->fetchPart($imap, $uid, $partNumber ?: 1, $structure);
            }
            return '';
        }

        foreach ($structure->parts as $index => $part) {
            $currentPart = $partNumber ? "{$partNumber}." . ($index + 1) : ($index + 1);

            if ($part->type === $primaryType && strtoupper($part->subtype) === $subType) {
                return $this->fetchPart($imap, $uid, $currentPart, $part);
            }

            if ($part->type === 1) { // MULTIPART
                $result = $this->getPart($imap, $uid, $part, $mimeType, (string) $currentPart);
                if (!empty($result)) {
                    return $result;
                }
            }
        }

        return '';
    }

    /**
     * E-posta parçasını çeker ve decode eder
     */
    private function fetchPart($imap, int $uid, $partNumber, $structure): string
    {
        $data = imap_fetchbody($imap, $uid, (string) $partNumber, FT_UID);

        // Encoding'e göre decode et
        switch ($structure->encoding) {
            case 3: // BASE64
                $data = base64_decode($data);
                break;
            case 4: // QUOTED-PRINTABLE
                $data = quoted_printable_decode($data);
                break;
        }

        // Karakter setini UTF-8'e dönüştür
        $charset = 'UTF-8';
        if (isset($structure->parameters)) {
            foreach ($structure->parameters as $param) {
                if (strtoupper($param->attribute) === 'CHARSET') {
                    $charset = $param->value;
                    break;
                }
            }
        }

        if (strtoupper($charset) !== 'UTF-8') {
            $data = @iconv($charset, 'UTF-8//IGNORE', $data);
        }

        return $data;
    }

    /**
     * Ekleri listeler
     */
    private function getAttachments($imap, int $uid, $structure): array
    {
        $attachments = [];

        if (!isset($structure->parts)) {
            return [];
        }

        foreach ($structure->parts as $index => $part) {
            $filename = null;

            // Filename'i bul
            if (isset($part->dparameters)) {
                foreach ($part->dparameters as $param) {
                    if (strtoupper($param->attribute) === 'FILENAME') {
                        $filename = $this->decodeHeader($param->value);
                        break;
                    }
                }
            }

            if (!$filename && isset($part->parameters)) {
                foreach ($part->parameters as $param) {
                    if (strtoupper($param->attribute) === 'NAME') {
                        $filename = $this->decodeHeader($param->value);
                        break;
                    }
                }
            }

            if ($filename && $part->type !== 0) { // Text olmayan parçalar
                $attachments[] = [
                    'filename' => $filename,
                    'part_number' => $index + 1,
                    'size' => $part->bytes ?? 0,
                    'type' => $this->getMimeType($part)
                ];
            }
        }

        return $attachments;
    }

    /**
     * MIME tipini döndürür
     */
    private function getMimeType($structure): string
    {
        $types = ['TEXT', 'MULTIPART', 'MESSAGE', 'APPLICATION', 'AUDIO', 'IMAGE', 'VIDEO', 'OTHER'];
        return ($types[$structure->type] ?? 'OTHER') . '/' . ($structure->subtype ?? 'UNKNOWN');
    }

    /**
     * Adres objesini parse eder
     */
    private function parseAddress($address): array
    {
        if (!$address) {
            return ['email' => '', 'name' => 'Bilinmiyor'];
        }

        return [
            'email' => ($address->mailbox ?? '') . '@' . ($address->host ?? ''),
            'name' => $this->decodeHeader($address->personal ?? ($address->mailbox ?? 'Bilinmiyor'))
        ];
    }

    /**
     * MIME header'ı decode eder
     */
    private function decodeHeader(string $text): string
    {
        $elements = imap_mime_header_decode($text);
        $decoded = '';

        foreach ($elements as $element) {
            $charset = $element->charset;
            $text = $element->text;

            if ($charset !== 'default' && $charset !== 'UTF-8') {
                $text = @iconv($charset, 'UTF-8//IGNORE', $text);
            }

            $decoded .= $text;
        }

        return $decoded ?: $text;
    }

    /**
     * E-postayı siler (çöp kutusuna taşır)
     */
    public function deleteEmail(int $accountId, int $uid, string $folder = 'INBOX'): array
    {
        $account = $this->accountManager->getWithPassword($accountId);
        if (!$account) {
            return ['success' => false, 'message' => 'Hesap bulunamadı'];
        }

        try {
            $imap = $this->connectImap($account, $folder);

            imap_delete($imap, $uid, FT_UID);
            imap_expunge($imap);
            imap_close($imap);

            return ['success' => true, 'message' => 'E-posta silindi'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Silme hatası: ' . $e->getMessage()];
        }
    }

    /**
     * E-postayı okundu/okunmadı olarak işaretler
     */
    public function markAs(int $accountId, int $uid, bool $seen, string $folder = 'INBOX'): array
    {
        $account = $this->accountManager->getWithPassword($accountId);
        if (!$account) {
            return ['success' => false, 'message' => 'Hesap bulunamadı'];
        }

        try {
            $imap = $this->connectImap($account, $folder);

            if ($seen) {
                imap_setflag_full($imap, $uid, '\\Seen', ST_UID);
            } else {
                imap_clearflag_full($imap, $uid, '\\Seen', ST_UID);
            }

            imap_close($imap);

            return ['success' => true, 'message' => $seen ? 'Okundu olarak işaretlendi' : 'Okunmadı olarak işaretlendi'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'İşaretleme hatası: ' . $e->getMessage()];
        }
    }

    /**
     * Belirli bir hesabın belirli klasörünü getirir
     */
    public function fetchFolder(int $accountId, string $folderName, int $limit = 50, int $offset = 0): array
    {
        $account = $this->accountManager->getWithPassword($accountId);
        if (!$account) {
            throw new \Exception('Hesap bulunamadı');
        }

        // Farklı e-posta sağlayıcılarının klasör isimlerini dene
        $folderVariants = $this->getFolderVariants($folderName);
        $emails = [];

        foreach ($folderVariants as $folder) {
            try {
                // Doğrudan ilgili klasöre bağlan
                $imap = @$this->connectImap($account, $folder);
                if ($imap) {
                    $emails = $this->fetchFromFolder($imap, $folder, $limit, $offset, $account);
                    @imap_close($imap);
                    break;
                }
            } catch (\Exception $e) {
                // Bu variant çalışmadı, diğerini dene
                continue;
            }
        }

        // IMAP hatalarını temizle
        @imap_errors();
        @imap_alerts();

        return $emails;
    }

    /**
     * Tüm hesaplardan belirli klasörü birleştirir
     */
    public function fetchUnifiedFolder(string $folderName, int $limit = 50): array
    {
        $accounts = $this->accountManager->getAll();
        $allEmails = [];

        foreach ($accounts as $account) {
            try {
                $emails = $this->fetchFolder($account['id'], $folderName, $limit);
                $allEmails = array_merge($allEmails, $emails);
            } catch (\Exception $e) {
                error_log("{$folderName} fetch error for account {$account['email']}: " . $e->getMessage());
            }
        }

        usort($allEmails, function ($a, $b) {
            return strtotime($b['date']) - strtotime($a['date']);
        });

        return array_slice($allEmails, 0, $limit);
    }

    /**
     * E-postayı bir klasörden diğerine taşır
     */
    public function moveToFolder(int $accountId, int $uid, string $fromFolder, string $toFolder): array
    {
        $account = $this->accountManager->getWithPassword($accountId);
        if (!$account) {
            return ['success' => false, 'message' => 'Hesap bulunamadı'];
        }

        // IMAP hatalarını temizle
        @imap_errors();
        @imap_alerts();

        try {
            $imap = $this->connectImap($account, $fromFolder);

            // Hedef klasör varyantlarını dene
            $destVariants = $this->getFolderVariants($toFolder);
            $moved = false;
            $usedFolder = '';
            $imapErrors = [];

            foreach ($destVariants as $dest) {
                // Her denemeden önce hataları temizle
                @imap_errors();

                if (imap_mail_move($imap, (string) $uid, $dest, CP_UID)) {
                    $moved = true;
                    $usedFolder = $dest;
                    break;
                }

                // Hataları topla
                $errors = imap_errors();
                if ($errors) {
                    $imapErrors = array_merge($imapErrors, $errors);
                }
            }

            if (!$moved) {
                // Kopyala ve sil yöntemi
                foreach ($destVariants as $dest) {
                    @imap_errors();

                    if (imap_mail_copy($imap, (string) $uid, $dest, CP_UID)) {
                        imap_delete($imap, $uid, FT_UID);
                        $moved = true;
                        $usedFolder = $dest;
                        break;
                    }

                    $errors = imap_errors();
                    if ($errors) {
                        $imapErrors = array_merge($imapErrors, $errors);
                    }
                }
            }

            // Silinen mesajları temizle
            imap_expunge($imap);
            imap_close($imap);

            // Son hataları temizle
            @imap_errors();
            @imap_alerts();

            if ($moved) {
                return [
                    'success' => true,
                    'message' => 'E-posta taşındı',
                    'destination' => $usedFolder
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Hedef klasör bulunamadı',
                    'tried' => $destVariants,
                    'imap_errors' => $imapErrors
                ];
            }
        } catch (\Exception $e) {
            @imap_errors();
            @imap_alerts();
            return ['success' => false, 'message' => 'Taşıma hatası: ' . $e->getMessage()];
        }
    }

    /**
     * E-postayı kalıcı olarak siler
     */
    public function permanentDelete(int $accountId, int $uid, string $folder = 'Trash'): array
    {
        $account = $this->accountManager->getWithPassword($accountId);
        if (!$account) {
            return ['success' => false, 'message' => 'Hesap bulunamadı'];
        }

        try {
            // Klasör varyantlarını dene
            $folderVariants = $this->getFolderVariants($folder);
            $imap = $this->connectImap($account);
            $deleted = false;

            foreach ($folderVariants as $f) {
                if (@imap_reopen($imap, $this->getMailboxString($account) . $f)) {
                    imap_delete($imap, $uid, FT_UID);
                    imap_expunge($imap);
                    $deleted = true;
                    break;
                }
            }

            imap_close($imap);

            if ($deleted) {
                return ['success' => true, 'message' => 'E-posta kalıcı olarak silindi'];
            } else {
                return ['success' => false, 'message' => 'Klasör bulunamadı'];
            }
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Kalıcı silme hatası: ' . $e->getMessage()];
        }
    }

    /**
     * Klasördeki tüm e-postaları okundu olarak işaretler
     */
    public function markAllAsRead(?int $accountId = null, string $folder = 'INBOX'): array
    {
        $accounts = $accountId
            ? [$this->accountManager->getWithPassword($accountId)]
            : $this->accountManager->getAllWithPasswords();

        $successCount = 0;
        $errorCount = 0;

        foreach ($accounts as $account) {
            if (!$account)
                continue;

            try {
                $imap = $this->connectImap($account, $folder);

                // Tüm okunmamış mesajları bul
                $unseen = imap_search($imap, 'UNSEEN');

                if ($unseen && is_array($unseen)) {
                    foreach ($unseen as $msgno) {
                        imap_setflag_full($imap, $msgno, '\\Seen');
                    }
                    $successCount += count($unseen);
                }

                imap_close($imap);
            } catch (\Exception $e) {
                error_log("Mark all read error for {$account['email']}: " . $e->getMessage());
                $errorCount++;
            }
        }

        return [
            'success' => $errorCount === 0 || $successCount > 0,
            'message' => "{$successCount} e-posta okundu olarak işaretlendi",
            'count' => $successCount
        ];
    }

    /**
     * Okunmamış e-posta sayılarını döndürür
     */
    public function getUnreadCounts(): array
    {
        // IMAP hata ve uyarı mesajlarını bastır (JSON çıktısını bozmasın)
        @imap_errors();
        @imap_alerts();

        $accounts = $this->accountManager->getAll();
        $counts = [
            'inbox' => 0,
            'spam' => 0,
            'trash' => 0,
            'total' => 0,
            'by_account' => []
        ];

        foreach ($accounts as $account) {
            if (!$account)
                continue;

            try {
                $accountWithPassword = $this->accountManager->getWithPassword($account['id']);
                if (!$accountWithPassword)
                    continue;

                $imap = @$this->connectImap($accountWithPassword);
                if (!$imap)
                    continue;

                // Inbox
                $inboxCount = $this->getUnseenCount($imap);
                $counts['inbox'] += $inboxCount;
                $counts['by_account'][$account['id']] = [
                    'email' => $account['email'],
                    'inbox' => $inboxCount,
                    'spam' => 0
                ];

                // Spam klasörlerini kontrol et
                $spamFolders = $this->getFolderVariants('Spam');
                foreach ($spamFolders as $folder) {
                    if (@imap_reopen($imap, $this->getMailboxString($accountWithPassword) . $folder)) {
                        $spamCount = $this->getUnseenCount($imap);
                        $counts['spam'] += $spamCount;
                        $counts['by_account'][$account['id']]['spam'] = $spamCount;
                        break;
                    }
                }

                @imap_close($imap);
                // IMAP hatalarını temizle
                @imap_errors();
                @imap_alerts();
            } catch (\Exception $e) {
                error_log("Unread count error for {$account['email']}: " . $e->getMessage());
                @imap_errors();
                @imap_alerts();
            }
        }

        $counts['total'] = $counts['inbox'] + $counts['spam'];
        return $counts;
    }

    /**
     * Klasördeki okunmamış mesaj sayısını döndürür
     */
    private function getUnseenCount($imap): int
    {
        $unseen = imap_search($imap, 'UNSEEN');
        return $unseen ? count($unseen) : 0;
    }

    /**
     * Klasör ismi varyantlarını döndürür (farklı e-posta sağlayıcıları için)
     */
    private function getFolderVariants(string $folderName): array
    {
        $variants = [
            'Spam' => [
                'Junk', // Outlook/Hotmail için öncelikli
                'Spam',
                'Junk E-mail',
                '[Gmail]/Spam',
                '[Gmail]/İstenmeyen',
                'INBOX.Spam',
                'INBOX.Junk',
                'Junk Mail',
                '[Gmail]/Junk',
                'Bulk Mail',
                'İstenmeyen'
            ],
            'Trash' => [
                'Trash',
                '[Gmail]/Trash',
                '[Gmail]/Çöp Kutusu',
                'Deleted Items',
                'Deleted Messages',
                'INBOX.Trash',
                'Çöp Kutusu',
                '[Gmail]/Bin',
                'Deleted'
            ],
            'Sent' => [
                'Sent',
                '[Gmail]/Sent Mail',
                '[Gmail]/Gönderilmiş Postalar',
                'Sent Items',
                'Sent Messages',
                'INBOX.Sent',
                'Gönderilmiş'
            ],
            'Drafts' => ['Drafts', '[Gmail]/Drafts', '[Gmail]/Taslaklar', 'Draft', 'INBOX.Drafts'],
            'INBOX' => ['INBOX']
        ];

        return $variants[$folderName] ?? [$folderName];
    }
}

