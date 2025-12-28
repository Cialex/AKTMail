<?php
/**
 * AktMail - Kullanıcı Ayarları API
 */

require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/Security.php';
require_once __DIR__ . '/../includes/Auth.php';

use AktMail\Security;
use AktMail\Auth;
use AktMail\Database;

header('Content-Type: application/json; charset=utf-8');

Security::startSecureSession();

function jsonResponse(array $data, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

$auth = Auth::getInstance();
if (!$auth->isLoggedIn()) {
    jsonResponse(['success' => false, 'message' => 'Oturum açmanız gerekiyor'], 401);
}

$userId = $auth->getUserId();
$db = Database::getInstance();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

switch ($action) {
    case 'get':
        $settings = $db->fetchOne(
            "SELECT * FROM user_settings WHERE user_id = ?",
            [$userId]
        );

        if (!$settings) {
            // Varsayılan ayarlar
            $settings = [
                'theme' => 'dark',
                'compact_mode' => 1
            ];
        }

        jsonResponse(['success' => true, 'settings' => $settings]);
        break;

    case 'update':
        if ($method !== 'POST') {
            jsonResponse(['success' => false, 'message' => 'POST gerekli'], 405);
        }

        $theme = $input['theme'] ?? 'dark';
        $compactMode = isset($input['compact_mode']) ? (int) $input['compact_mode'] : 1;

        // Geçerli temalar
        $validThemes = ['dark', 'light', 'purple', 'blue', 'green'];
        if (!in_array($theme, $validThemes)) {
            $theme = 'dark';
        }

        // Mevcut ayar var mı kontrol et
        $existing = $db->fetchOne(
            "SELECT user_id FROM user_settings WHERE user_id = ?",
            [$userId]
        );

        if ($existing) {
            $db->update('user_settings', [
                'theme' => $theme,
                'compact_mode' => $compactMode
            ], 'user_id = ?', [$userId]);
        } else {
            $db->insert('user_settings', [
                'user_id' => $userId,
                'theme' => $theme,
                'compact_mode' => $compactMode
            ]);
        }

        jsonResponse(['success' => true, 'message' => 'Ayarlar güncellendi']);
        break;

    default:
        jsonResponse(['success' => false, 'message' => 'Geçersiz action'], 400);
}
