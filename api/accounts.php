<?php
/**
 * AktMail - E-posta Hesap Yönetimi API
 * 
 * E-posta hesabı CRUD işlemleri
 */

// En başta JSON header ve output buffering
header('Content-Type: application/json; charset=utf-8');
ob_start();

// Hata yakalama
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Fatal error handler (shutdown function)
register_shutdown_function(function () {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        ob_end_clean();
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Fatal Error: ' . $error['message'],
            'file' => basename($error['file']),
            'line' => $error['line']
        ], JSON_UNESCAPED_UNICODE);
    }
});

// Custom error handler
set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
});

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/Security.php';
require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/EmailAccount.php';

use AktMail\Security;
use AktMail\Auth;
use AktMail\EmailAccount;

function jsonResponse(array $data, int $code = 200): void
{
    ob_end_clean();
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    Security::startSecureSession();

    // Auth kontrolü
    $auth = Auth::getInstance();
    if (!$auth->isLoggedIn()) {
        jsonResponse(['success' => false, 'message' => 'Oturum açmanız gerekiyor'], 401);
    }

    $userId = $auth->getUserId();
    $accountManager = new EmailAccount($userId);

    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? $_POST['action'] ?? '';

    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        $input = $_POST;
    }

    switch ($method) {
        case 'GET':
            switch ($action) {
                case 'list':
                    $activeOnly = ($_GET['active_only'] ?? '1') === '1';
                    $accounts = $accountManager->getAll($activeOnly);
                    jsonResponse(['success' => true, 'accounts' => $accounts]);
                    break;

                case 'get':
                    $accountId = (int) ($_GET['id'] ?? 0);
                    if (!$accountId) {
                        jsonResponse(['success' => false, 'message' => 'Hesap ID gerekli'], 400);
                    }
                    $account = $accountManager->getById($accountId);
                    if (!$account) {
                        jsonResponse(['success' => false, 'message' => 'Hesap bulunamadı'], 404);
                    }
                    unset($account['encrypted_password']); // Şifreyi gönderme
                    jsonResponse(['success' => true, 'account' => $account]);
                    break;

                case 'detect':
                    $email = $_GET['email'] ?? '';
                    if (empty($email)) {
                        jsonResponse(['success' => false, 'message' => 'E-posta adresi gerekli'], 400);
                    }
                    $provider = $accountManager->autoDetectProvider($email);
                    if ($provider) {
                        jsonResponse(['success' => true, 'provider' => $provider, 'detected' => true]);
                    } else {
                        jsonResponse(['success' => true, 'provider' => null, 'detected' => false]);
                    }
                    break;

                default:
                    jsonResponse(['success' => false, 'message' => 'Geçersiz action'], 400);
            }
            break;

        case 'POST':
            switch ($action) {
                case 'add':
                    $result = $accountManager->add($input);
                    jsonResponse($result, $result['success'] ? 200 : 400);
                    break;

                case 'update':
                    $accountId = (int) ($input['id'] ?? 0);
                    if (!$accountId) {
                        jsonResponse(['success' => false, 'message' => 'Hesap ID gerekli'], 400);
                    }
                    $result = $accountManager->update($accountId, $input);
                    jsonResponse($result, $result['success'] ? 200 : 400);
                    break;

                case 'delete':
                    $accountId = (int) ($input['id'] ?? 0);
                    if (!$accountId) {
                        jsonResponse(['success' => false, 'message' => 'Hesap ID gerekli'], 400);
                    }
                    $result = $accountManager->delete($accountId);
                    jsonResponse($result, $result['success'] ? 200 : 400);
                    break;

                case 'test':
                    $result = $accountManager->testConnection($input);
                    jsonResponse($result, $result['success'] ? 200 : 400);
                    break;

                default:
                    jsonResponse(['success' => false, 'message' => 'Geçersiz action'], 400);
            }
            break;

        default:
            jsonResponse(['success' => false, 'message' => 'Desteklenmeyen HTTP metodu'], 405);
    }

} catch (\Exception $e) {
    jsonResponse([
        'success' => false,
        'message' => 'Sunucu hatası: ' . $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ], 500);
}
