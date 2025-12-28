<?php
/**
 * AktMail - Özel Klasörler API
 */

// Hata gösterimini kapat (JSON çıktısını bozmasın)
error_reporting(0);
ini_set('display_errors', 0);

require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/Security.php';
require_once __DIR__ . '/../includes/Auth.php';

use AktMail\Database;
use AktMail\Security;
use AktMail\Auth;

header('Content-Type: application/json; charset=utf-8');

Security::startSecureSession();
$auth = Auth::getInstance();

if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Oturum gerekli']);
    exit;
}

$userId = $auth->getCurrentUser()['id'];
$db = Database::getInstance();
$action = $_GET['action'] ?? '';
$input = json_decode(file_get_contents('php://input'), true) ?? [];

function jsonResponse($data, $code = 200)
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

switch ($action) {
    case 'list':
        // Özel klasörleri listele
        try {
            $folders = $db->fetchAll(
                "SELECT * FROM custom_folders WHERE user_id = ? ORDER BY name ASC",
                [$userId]
            );
            jsonResponse(['success' => true, 'folders' => $folders]);
        } catch (\Exception $e) {
            // Tablo yoksa boş liste döndür
            jsonResponse(['success' => true, 'folders' => []]);
        }
        break;

    case 'create':
        // Yeni klasör oluştur
        $name = trim($input['name'] ?? '');
        $color = $input['color'] ?? '#6366f1';
        $icon = $input['icon'] ?? '📁';

        if (empty($name)) {
            jsonResponse(['success' => false, 'message' => 'Klasör adı gerekli'], 400);
        }

        if (strlen($name) > 100) {
            jsonResponse(['success' => false, 'message' => 'Klasör adı 100 karakterden uzun olamaz'], 400);
        }

        // Aynı isimde klasör var mı?
        $existing = $db->fetchOne(
            "SELECT id FROM custom_folders WHERE user_id = ? AND name = ?",
            [$userId, $name]
        );

        if ($existing) {
            jsonResponse(['success' => false, 'message' => 'Bu isimde bir klasör zaten var'], 400);
        }

        try {
            $folderId = $db->insert('custom_folders', [
                'user_id' => $userId,
                'name' => $name,
                'color' => $color,
                'icon' => $icon
            ]);

            jsonResponse([
                'success' => true,
                'message' => 'Klasör oluşturuldu',
                'folder' => [
                    'id' => $folderId,
                    'name' => $name,
                    'color' => $color,
                    'icon' => $icon
                ]
            ]);
        } catch (\Exception $e) {
            jsonResponse(['success' => false, 'message' => 'Klasör oluşturulamadı'], 500);
        }
        break;

    case 'update':
        // Klasör güncelle
        $folderId = (int) ($input['id'] ?? 0);
        $name = trim($input['name'] ?? '');
        $color = $input['color'] ?? null;
        $icon = $input['icon'] ?? null;

        if (!$folderId) {
            jsonResponse(['success' => false, 'message' => 'Klasör ID gerekli'], 400);
        }

        $folder = $db->fetchOne(
            "SELECT * FROM custom_folders WHERE id = ? AND user_id = ?",
            [$folderId, $userId]
        );

        if (!$folder) {
            jsonResponse(['success' => false, 'message' => 'Klasör bulunamadı'], 404);
        }

        $updateData = [];
        if (!empty($name))
            $updateData['name'] = $name;
        if ($color !== null)
            $updateData['color'] = $color;
        if ($icon !== null)
            $updateData['icon'] = $icon;

        if (empty($updateData)) {
            jsonResponse(['success' => false, 'message' => 'Güncellenecek veri yok'], 400);
        }

        try {
            $db->update('custom_folders', $updateData, 'id = ? AND user_id = ?', [$folderId, $userId]);
            jsonResponse(['success' => true, 'message' => 'Klasör güncellendi']);
        } catch (\Exception $e) {
            jsonResponse(['success' => false, 'message' => 'Güncelleme hatası'], 500);
        }
        break;

    case 'delete':
        // Klasör sil
        $folderId = (int) ($input['id'] ?? 0);

        if (!$folderId) {
            jsonResponse(['success' => false, 'message' => 'Klasör ID gerekli'], 400);
        }

        $folder = $db->fetchOne(
            "SELECT * FROM custom_folders WHERE id = ? AND user_id = ?",
            [$folderId, $userId]
        );

        if (!$folder) {
            jsonResponse(['success' => false, 'message' => 'Klasör bulunamadı'], 404);
        }

        try {
            // Önce bu klasöre ait kuralları sil
            $db->delete('filter_rules', 'target_folder_id = ?', [$folderId]);
            // Sonra klasörü sil
            $db->delete('custom_folders', 'id = ? AND user_id = ?', [$folderId, $userId]);
            jsonResponse(['success' => true, 'message' => 'Klasör silindi']);
        } catch (\Exception $e) {
            jsonResponse(['success' => false, 'message' => 'Silme hatası'], 500);
        }
        break;

    default:
        jsonResponse(['success' => false, 'message' => 'Geçersiz action'], 400);
}
