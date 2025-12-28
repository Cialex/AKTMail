<?php
/**
 * AktMail - E-posta İşlemleri API
 * 
 * E-posta listeleme, okuma, gönderme
 */

// IMAP hata/uyarılarını bastır (JSON çıktısını bozmasın)
error_reporting(0);
ini_set('display_errors', 0);


require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/Security.php';
require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/EmailAccount.php';
require_once __DIR__ . '/../includes/EmailClient.php';

use AktMail\Security;
use AktMail\Auth;
use AktMail\EmailClient;

header('Content-Type: application/json; charset=utf-8');

Security::startSecureSession();

function jsonResponse(array $data, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// Auth kontrolü
$auth = Auth::getInstance();
if (!$auth->isLoggedIn()) {
    jsonResponse(['success' => false, 'message' => 'Oturum açmanız gerekiyor'], 401);
}

$userId = $auth->getUserId();
$emailClient = new EmailClient($userId);

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

switch ($action) {
    case 'inbox':
        // Birleşik gelen kutusu
        try {
            $limit = (int) ($_GET['limit'] ?? 50);
            $emails = $emailClient->fetchUnifiedInbox($limit);
            jsonResponse(['success' => true, 'emails' => $emails, 'count' => count($emails)]);
        } catch (\Exception $e) {
            jsonResponse(['success' => false, 'message' => 'E-postalar alınamadı: ' . $e->getMessage()], 500);
        }
        break;

    case 'inbox_account':
        // Belirli bir hesabın gelen kutusu
        try {
            $accountId = (int) ($_GET['account_id'] ?? 0);
            $limit = (int) ($_GET['limit'] ?? 50);
            $offset = (int) ($_GET['offset'] ?? 0);

            if (!$accountId) {
                jsonResponse(['success' => false, 'message' => 'Hesap ID gerekli'], 400);
            }

            $emails = $emailClient->fetchInbox($accountId, $limit, $offset);
            jsonResponse(['success' => true, 'emails' => $emails, 'count' => count($emails)]);
        } catch (\Exception $e) {
            jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
        }
        break;

    case 'sent':
        // Birleşik gönderilmişler
        try {
            $limit = (int) ($_GET['limit'] ?? 50);
            $emails = $emailClient->fetchUnifiedSent($limit);
            jsonResponse(['success' => true, 'emails' => $emails, 'count' => count($emails)]);
        } catch (\Exception $e) {
            jsonResponse(['success' => false, 'message' => 'E-postalar alınamadı: ' . $e->getMessage()], 500);
        }
        break;

    case 'sent_account':
        // Belirli bir hesabın gönderilmişleri
        try {
            $accountId = (int) ($_GET['account_id'] ?? 0);
            $limit = (int) ($_GET['limit'] ?? 50);
            $offset = (int) ($_GET['offset'] ?? 0);

            if (!$accountId) {
                jsonResponse(['success' => false, 'message' => 'Hesap ID gerekli'], 400);
            }

            $emails = $emailClient->fetchSent($accountId, $limit, $offset);
            jsonResponse(['success' => true, 'emails' => $emails, 'count' => count($emails)]);
        } catch (\Exception $e) {
            jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
        }
        break;

    case 'read':
        // E-posta oku
        try {
            $accountId = (int) ($_GET['account_id'] ?? 0);
            $uid = (int) ($_GET['uid'] ?? 0);
            $folder = $_GET['folder'] ?? 'INBOX';

            if (!$accountId || !$uid) {
                jsonResponse(['success' => false, 'message' => 'Hesap ID ve e-posta UID gerekli'], 400);
            }

            $email = $emailClient->readEmail($accountId, $uid, $folder);

            if (!$email) {
                jsonResponse(['success' => false, 'message' => 'E-posta bulunamadı'], 404);
            }

            jsonResponse(['success' => true, 'email' => $email]);
        } catch (\Exception $e) {
            jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
        }
        break;

    case 'send':
        // E-posta gönder
        if ($method !== 'POST') {
            jsonResponse(['success' => false, 'message' => 'POST metodu gerekli'], 405);
        }

        $accountId = (int) ($input['account_id'] ?? 0);
        $to = $input['to'] ?? [];
        $subject = $input['subject'] ?? '';
        $body = $input['body'] ?? '';
        $cc = $input['cc'] ?? [];
        $bcc = $input['bcc'] ?? [];

        if (!$accountId) {
            jsonResponse(['success' => false, 'message' => 'Hesap ID gerekli'], 400);
        }

        if (empty($to)) {
            jsonResponse(['success' => false, 'message' => 'Alıcı adresi gerekli'], 400);
        }

        $emailData = [
            'to' => is_array($to) ? $to : explode(',', $to),
            'subject' => $subject,
            'body' => $body,
            'cc' => is_array($cc) ? $cc : ($cc ? explode(',', $cc) : []),
            'bcc' => is_array($bcc) ? $bcc : ($bcc ? explode(',', $bcc) : [])
        ];

        $result = $emailClient->sendEmail($accountId, $emailData);
        jsonResponse($result, $result['success'] ? 200 : 500);
        break;

    case 'send_multiple':
        // Birden fazla hesaptan gönder
        if ($method !== 'POST') {
            jsonResponse(['success' => false, 'message' => 'POST metodu gerekli'], 405);
        }

        $accountIds = $input['account_ids'] ?? [];
        $to = $input['to'] ?? [];
        $subject = $input['subject'] ?? '';
        $body = $input['body'] ?? '';
        $cc = $input['cc'] ?? [];
        $bcc = $input['bcc'] ?? [];

        if (empty($accountIds)) {
            jsonResponse(['success' => false, 'message' => 'En az bir hesap seçin'], 400);
        }

        if (empty($to)) {
            jsonResponse(['success' => false, 'message' => 'Alıcı adresi gerekli'], 400);
        }

        $emailData = [
            'to' => is_array($to) ? $to : explode(',', $to),
            'subject' => $subject,
            'body' => $body,
            'cc' => is_array($cc) ? $cc : ($cc ? explode(',', $cc) : []),
            'bcc' => is_array($bcc) ? $bcc : ($bcc ? explode(',', $bcc) : [])
        ];

        $result = $emailClient->sendFromMultipleAccounts($accountIds, $emailData);
        jsonResponse($result, $result['success'] ? 200 : 500);
        break;

    case 'delete':
        // E-posta sil
        if ($method !== 'POST') {
            jsonResponse(['success' => false, 'message' => 'POST metodu gerekli'], 405);
        }

        $accountId = (int) ($input['account_id'] ?? 0);
        $uid = (int) ($input['uid'] ?? 0);
        $folder = $input['folder'] ?? 'INBOX';

        if (!$accountId || !$uid) {
            jsonResponse(['success' => false, 'message' => 'Hesap ID ve e-posta UID gerekli'], 400);
        }

        $result = $emailClient->deleteEmail($accountId, $uid, $folder);
        jsonResponse($result, $result['success'] ? 200 : 500);
        break;

    case 'mark_read':
        // Okundu olarak işaretle
        if ($method !== 'POST') {
            jsonResponse(['success' => false, 'message' => 'POST metodu gerekli'], 405);
        }

        $accountId = (int) ($input['account_id'] ?? 0);
        $uid = (int) ($input['uid'] ?? 0);
        $folder = $input['folder'] ?? 'INBOX';

        if (!$accountId || !$uid) {
            jsonResponse(['success' => false, 'message' => 'Hesap ID ve e-posta UID gerekli'], 400);
        }

        $result = $emailClient->markAs($accountId, $uid, true, $folder);
        jsonResponse($result, $result['success'] ? 200 : 500);
        break;

    case 'mark_unread':
        // Okunmadı olarak işaretle
        if ($method !== 'POST') {
            jsonResponse(['success' => false, 'message' => 'POST metodu gerekli'], 405);
        }

        $accountId = (int) ($input['account_id'] ?? 0);
        $uid = (int) ($input['uid'] ?? 0);
        $folder = $input['folder'] ?? 'INBOX';

        if (!$accountId || !$uid) {
            jsonResponse(['success' => false, 'message' => 'Hesap ID ve e-posta UID gerekli'], 400);
        }

        $result = $emailClient->markAs($accountId, $uid, false, $folder);
        jsonResponse($result, $result['success'] ? 200 : 500);
        break;

    case 'spam':
        // Spam klasörü
        try {
            $accountId = (int) ($_GET['account_id'] ?? 0);
            $limit = (int) ($_GET['limit'] ?? 50);

            if ($accountId) {
                $emails = $emailClient->fetchFolder($accountId, 'Spam', $limit);
            } else {
                $emails = $emailClient->fetchUnifiedFolder('Spam', $limit);
            }
            jsonResponse(['success' => true, 'emails' => $emails, 'count' => count($emails)]);
        } catch (\Exception $e) {
            jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
        }
        break;

    case 'trash':
        // Çöp kutusu
        try {
            $accountId = (int) ($_GET['account_id'] ?? 0);
            $limit = (int) ($_GET['limit'] ?? 50);

            if ($accountId) {
                $emails = $emailClient->fetchFolder($accountId, 'Trash', $limit);
            } else {
                $emails = $emailClient->fetchUnifiedFolder('Trash', $limit);
            }
            jsonResponse(['success' => true, 'emails' => $emails, 'count' => count($emails)]);
        } catch (\Exception $e) {
            jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
        }
        break;

    case 'move_to_spam':
        // Spam'e taşı
        if ($method !== 'POST') {
            jsonResponse(['success' => false, 'message' => 'POST metodu gerekli'], 405);
        }

        $accountId = (int) ($input['account_id'] ?? 0);
        $uid = (int) ($input['uid'] ?? 0);
        $folder = $input['folder'] ?? 'INBOX';

        if (!$accountId || !$uid) {
            jsonResponse(['success' => false, 'message' => 'Hesap ID ve e-posta UID gerekli'], 400);
        }

        $result = $emailClient->moveToFolder($accountId, $uid, $folder, 'Spam');
        jsonResponse($result, $result['success'] ? 200 : 500);
        break;

    case 'move_to_trash':
        // Çöp kutusuna taşı
        if ($method !== 'POST') {
            jsonResponse(['success' => false, 'message' => 'POST metodu gerekli'], 405);
        }

        $accountId = (int) ($input['account_id'] ?? 0);
        $uid = (int) ($input['uid'] ?? 0);
        $folder = $input['folder'] ?? 'INBOX';

        if (!$accountId || !$uid) {
            jsonResponse(['success' => false, 'message' => 'Hesap ID ve e-posta UID gerekli'], 400);
        }

        $result = $emailClient->moveToFolder($accountId, $uid, $folder, 'Trash');
        jsonResponse($result, $result['success'] ? 200 : 500);
        break;

    case 'restore':
        // Çöp kutusundan geri yükle
        if ($method !== 'POST') {
            jsonResponse(['success' => false, 'message' => 'POST metodu gerekli'], 405);
        }

        $accountId = (int) ($input['account_id'] ?? 0);
        $uid = (int) ($input['uid'] ?? 0);

        if (!$accountId || !$uid) {
            jsonResponse(['success' => false, 'message' => 'Hesap ID ve e-posta UID gerekli'], 400);
        }

        $result = $emailClient->moveToFolder($accountId, $uid, 'Trash', 'INBOX');
        jsonResponse($result, $result['success'] ? 200 : 500);
        break;

    case 'permanent_delete':
        // Kalıcı sil
        if ($method !== 'POST') {
            jsonResponse(['success' => false, 'message' => 'POST metodu gerekli'], 405);
        }

        $accountId = (int) ($input['account_id'] ?? 0);
        $uid = (int) ($input['uid'] ?? 0);
        $folder = $input['folder'] ?? 'Trash';

        if (!$accountId || !$uid) {
            jsonResponse(['success' => false, 'message' => 'Hesap ID ve e-posta UID gerekli'], 400);
        }

        $result = $emailClient->permanentDelete($accountId, $uid, $folder);
        jsonResponse($result, $result['success'] ? 200 : 500);
        break;

    case 'mark_all_read':
        // Tümünü okundu işaretle
        if ($method !== 'POST') {
            jsonResponse(['success' => false, 'message' => 'POST metodu gerekli'], 405);
        }

        $accountId = (int) ($input['account_id'] ?? 0);
        $folder = $input['folder'] ?? 'INBOX';

        $result = $emailClient->markAllAsRead($accountId ?: null, $folder);
        jsonResponse($result, $result['success'] ? 200 : 500);
        break;

    case 'unread_count':
        // Okunmamış sayısı
        try {
            $counts = $emailClient->getUnreadCounts();
            jsonResponse(['success' => true, 'counts' => $counts]);
        } catch (\Exception $e) {
            jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
        }
        break;

    case 'bulk_delete':
        // Toplu silme (çöp kutusuna taşı)
        $emails = $input['emails'] ?? [];

        if (empty($emails) || !is_array($emails)) {
            jsonResponse(['success' => false, 'message' => 'E-posta listesi gerekli', 'received' => $emails], 400);
        }

        $successCount = 0;
        $errorCount = 0;
        $debugResults = [];

        foreach ($emails as $email) {
            $accountId = (int) ($email['account_id'] ?? 0);
            $uid = (int) ($email['uid'] ?? 0);
            $folder = $email['folder'] ?? 'INBOX';

            if ($accountId && $uid) {
                $result = $emailClient->moveToFolder($accountId, $uid, $folder, 'Trash');
                $debugResults[] = [
                    'account_id' => $accountId,
                    'uid' => $uid,
                    'folder' => $folder,
                    'result' => $result
                ];
                if ($result['success']) {
                    $successCount++;
                } else {
                    $errorCount++;
                }
            } else {
                $debugResults[] = ['error' => 'Invalid account_id or uid', 'email' => $email];
            }
        }

        jsonResponse([
            'success' => $successCount > 0,
            'message' => "{$successCount} e-posta silindi" . ($errorCount ? ", {$errorCount} hata" : ''),
            'deleted' => $successCount,
            'errors' => $errorCount,
            'debug' => $debugResults
        ]);
        break;

    case 'bulk_move':
        // Toplu taşıma
        $emails = $input['emails'] ?? [];
        $targetFolder = $input['target_folder'] ?? '';

        if (empty($emails) || !is_array($emails)) {
            jsonResponse(['success' => false, 'message' => 'E-posta listesi gerekli'], 400);
        }

        if (empty($targetFolder)) {
            jsonResponse(['success' => false, 'message' => 'Hedef klasör gerekli'], 400);
        }

        $successCount = 0;
        $errorCount = 0;

        foreach ($emails as $email) {
            $accountId = (int) ($email['account_id'] ?? 0);
            $uid = (int) ($email['uid'] ?? 0);
            $folder = $email['folder'] ?? 'INBOX';

            if ($accountId && $uid) {
                $result = $emailClient->moveToFolder($accountId, $uid, $folder, $targetFolder);
                if ($result['success']) {
                    $successCount++;
                } else {
                    $errorCount++;
                }
            }
        }

        jsonResponse([
            'success' => $successCount > 0,
            'message' => "{$successCount} e-posta taşındı" . ($errorCount ? ", {$errorCount} hata" : ''),
            'moved' => $successCount,
            'errors' => $errorCount
        ]);
        break;

    case 'bulk_mark_read':
        // Toplu okundu işaretle
        $emails = $input['emails'] ?? [];
        $seen = $input['seen'] ?? true;

        if (empty($emails) || !is_array($emails)) {
            jsonResponse(['success' => false, 'message' => 'E-posta listesi gerekli'], 400);
        }

        $successCount = 0;
        $errorCount = 0;

        foreach ($emails as $email) {
            $accountId = (int) ($email['account_id'] ?? 0);
            $uid = (int) ($email['uid'] ?? 0);
            $folder = $email['folder'] ?? 'INBOX';

            if ($accountId && $uid) {
                $result = $emailClient->markAs($accountId, $uid, $seen, $folder);
                if ($result['success']) {
                    $successCount++;
                } else {
                    $errorCount++;
                }
            }
        }

        $statusText = $seen ? 'okundu' : 'okunmadı';
        jsonResponse([
            'success' => $successCount > 0,
            'message' => "{$successCount} e-posta {$statusText} işaretlendi",
            'marked' => $successCount,
            'errors' => $errorCount
        ]);
        break;

    case 'bulk_spam':
        // Toplu spam işaretle
        $emails = $input['emails'] ?? [];

        if (empty($emails) || !is_array($emails)) {
            jsonResponse(['success' => false, 'message' => 'E-posta listesi gerekli'], 400);
        }

        $successCount = 0;
        $errorCount = 0;
        $debugResults = [];

        foreach ($emails as $email) {
            $accountId = (int) ($email['account_id'] ?? 0);
            $uid = (int) ($email['uid'] ?? 0);
            $folder = $email['folder'] ?? 'INBOX';

            if ($accountId && $uid) {
                $result = $emailClient->moveToFolder($accountId, $uid, $folder, 'Spam');
                $debugResults[] = [
                    'account_id' => $accountId,
                    'uid' => $uid,
                    'from_folder' => $folder,
                    'result' => $result
                ];
                if ($result['success']) {
                    $successCount++;
                } else {
                    $errorCount++;
                }
            }
        }

        jsonResponse([
            'success' => $successCount > 0,
            'message' => "{$successCount} e-posta spam olarak işaretlendi",
            'marked' => $successCount,
            'errors' => $errorCount,
            'debug' => $debugResults
        ]);
        break;

    default:
        jsonResponse(['success' => false, 'message' => 'Geçersiz action'], 400);
}

