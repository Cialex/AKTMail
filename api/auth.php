<?php
/**
 * AktMail - Kimlik Doğrulama API
 * 
 * Login, Register, Logout endpoint'leri
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/Security.php';
require_once __DIR__ . '/../includes/Auth.php';

use AktMail\Security;
use AktMail\Auth;

// JSON response header
header('Content-Type: application/json; charset=utf-8');

// Güvenli oturum başlat
Security::startSecureSession();

// Request method ve action
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Response fonksiyonu
function jsonResponse(array $data, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// Sadece POST isteklerini kabul et
if ($method !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Sadece POST istekleri kabul edilir'], 405);
}

// JSON body'yi al
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$auth = Auth::getInstance();

switch ($action) {
    case 'register':
        $username = trim($input['username'] ?? '');
        $email = trim($input['email'] ?? '');
        $password = $input['password'] ?? '';
        $confirmPassword = $input['confirm_password'] ?? '';

        // Validasyon
        if (empty($username) || empty($email) || empty($password)) {
            jsonResponse(['success' => false, 'message' => 'Tüm alanları doldurun'], 400);
        }

        if ($password !== $confirmPassword) {
            jsonResponse(['success' => false, 'message' => 'Şifreler eşleşmiyor'], 400);
        }

        $result = $auth->register($username, $email, $password);
        jsonResponse($result, $result['success'] ? 200 : 400);
        break;

    case 'login':
        $username = trim($input['username'] ?? '');
        $password = $input['password'] ?? '';
        $remember = isset($input['remember']) && ($input['remember'] === true || $input['remember'] === '1' || $input['remember'] === 'on');

        if (empty($username) || empty($password)) {
            jsonResponse(['success' => false, 'message' => 'Kullanıcı adı ve şifre gerekli'], 400);
        }

        $result = $auth->login($username, $password, $remember);
        jsonResponse($result, $result['success'] ? 200 : 401);
        break;

    case 'logout':
        $auth->logout();
        jsonResponse(['success' => true, 'message' => 'Çıkış yapıldı']);
        break;

    case 'check':
        // Oturum durumunu kontrol et
        if ($auth->isLoggedIn()) {
            $user = $auth->getCurrentUser();
            jsonResponse(['success' => true, 'logged_in' => true, 'user' => $user]);
        } else {
            jsonResponse(['success' => true, 'logged_in' => false]);
        }
        break;

    default:
        jsonResponse(['success' => false, 'message' => 'Geçersiz action'], 400);
}
