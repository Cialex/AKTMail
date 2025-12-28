<?php
/**
 * AktMail - İmza Yönetimi API
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
    case 'list':
        $signatures = $db->fetchAll(
            "SELECT s.*, ea.email as account_email 
             FROM signatures s 
             LEFT JOIN email_accounts ea ON s.account_id = ea.id 
             WHERE s.user_id = ? 
             ORDER BY s.is_default DESC, s.name ASC",
            [$userId]
        );
        jsonResponse(['success' => true, 'signatures' => $signatures]);
        break;

    case 'get':
        $id = (int) ($_GET['id'] ?? 0);
        $signature = $db->fetchOne(
            "SELECT * FROM signatures WHERE id = ? AND user_id = ?",
            [$id, $userId]
        );
        if (!$signature) {
            jsonResponse(['success' => false, 'message' => 'İmza bulunamadı'], 404);
        }
        jsonResponse(['success' => true, 'signature' => $signature]);
        break;

    case 'add':
        if ($method !== 'POST') {
            jsonResponse(['success' => false, 'message' => 'POST gerekli'], 405);
        }

        $name = trim($input['name'] ?? '');
        $content = $input['content'] ?? '';
        $accountId = !empty($input['account_id']) ? (int) $input['account_id'] : null;
        $isDefault = (int) ($input['is_default'] ?? 0);

        if (empty($name)) {
            jsonResponse(['success' => false, 'message' => 'İmza adı gerekli'], 400);
        }

        // Eğer varsayılan yapılıyorsa, diğerlerini kaldır
        if ($isDefault) {
            $db->query(
                "UPDATE signatures SET is_default = 0 WHERE user_id = ?",
                [$userId]
            );
        }

        $signatureId = $db->insert('signatures', [
            'user_id' => $userId,
            'account_id' => $accountId,
            'name' => $name,
            'content' => $content,
            'is_default' => $isDefault
        ]);

        jsonResponse(['success' => true, 'message' => 'İmza eklendi', 'id' => $signatureId]);
        break;

    case 'update':
        if ($method !== 'POST') {
            jsonResponse(['success' => false, 'message' => 'POST gerekli'], 405);
        }

        $id = (int) ($input['id'] ?? 0);
        if (!$id) {
            jsonResponse(['success' => false, 'message' => 'İmza ID gerekli'], 400);
        }

        // Yetki kontrolü
        $existing = $db->fetchOne(
            "SELECT id FROM signatures WHERE id = ? AND user_id = ?",
            [$id, $userId]
        );
        if (!$existing) {
            jsonResponse(['success' => false, 'message' => 'İmza bulunamadı'], 404);
        }

        $updateData = [];
        if (isset($input['name']))
            $updateData['name'] = trim($input['name']);
        if (isset($input['content']))
            $updateData['content'] = $input['content'];
        if (isset($input['account_id']))
            $updateData['account_id'] = !empty($input['account_id']) ? (int) $input['account_id'] : null;

        if (isset($input['is_default']) && $input['is_default']) {
            $db->query("UPDATE signatures SET is_default = 0 WHERE user_id = ?", [$userId]);
            $updateData['is_default'] = 1;
        } elseif (isset($input['is_default'])) {
            $updateData['is_default'] = 0;
        }

        if (!empty($updateData)) {
            $db->update('signatures', $updateData, 'id = ?', [$id]);
        }

        jsonResponse(['success' => true, 'message' => 'İmza güncellendi']);
        break;

    case 'delete':
        if ($method !== 'POST') {
            jsonResponse(['success' => false, 'message' => 'POST gerekli'], 405);
        }

        $id = (int) ($input['id'] ?? 0);
        if (!$id) {
            jsonResponse(['success' => false, 'message' => 'İmza ID gerekli'], 400);
        }

        $deleted = $db->delete('signatures', 'id = ? AND user_id = ?', [$id, $userId]);
        if ($deleted) {
            jsonResponse(['success' => true, 'message' => 'İmza silindi']);
        } else {
            jsonResponse(['success' => false, 'message' => 'İmza bulunamadı'], 404);
        }
        break;

    case 'get_default':
        $accountId = (int) ($_GET['account_id'] ?? 0);

        // Önce hesaba özel imza ara
        if ($accountId) {
            $signature = $db->fetchOne(
                "SELECT * FROM signatures WHERE user_id = ? AND account_id = ? AND is_default = 1",
                [$userId, $accountId]
            );
            if ($signature) {
                jsonResponse(['success' => true, 'signature' => $signature]);
            }
        }

        // Genel varsayılan imza
        $signature = $db->fetchOne(
            "SELECT * FROM signatures WHERE user_id = ? AND account_id IS NULL AND is_default = 1",
            [$userId]
        );

        jsonResponse(['success' => true, 'signature' => $signature]);
        break;

    default:
        jsonResponse(['success' => false, 'message' => 'Geçersiz action'], 400);
}
