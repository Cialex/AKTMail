<?php
/**
 * AktMail - Filtre Kuralları API
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
        // Kuralları listele
        try {
            $rules = $db->fetchAll(
                "SELECT r.*, f.name as folder_name, f.color as folder_color, f.icon as folder_icon 
                 FROM filter_rules r 
                 LEFT JOIN custom_folders f ON r.target_folder_id = f.id 
                 WHERE r.user_id = ? 
                 ORDER BY r.created_at DESC",
                [$userId]
            );
            jsonResponse(['success' => true, 'rules' => $rules]);
        } catch (\Exception $e) {
            // Tablo yoksa boş liste döndür
            jsonResponse(['success' => true, 'rules' => []]);
        }
        break;

    case 'add':
        // Yeni kural ekle
        $name = trim($input['name'] ?? '');
        $senderEmail = trim($input['sender_email'] ?? '');
        $targetFolderId = (int) ($input['target_folder_id'] ?? 0);

        if (empty($senderEmail)) {
            jsonResponse(['success' => false, 'message' => 'Gönderen e-posta adresi gerekli'], 400);
        }

        if (!$targetFolderId) {
            jsonResponse(['success' => false, 'message' => 'Hedef klasör seçilmeli'], 400);
        }

        // Klasör bu kullanıcıya ait mi?
        $folder = $db->fetchOne(
            "SELECT id FROM custom_folders WHERE id = ? AND user_id = ?",
            [$targetFolderId, $userId]
        );

        if (!$folder) {
            jsonResponse(['success' => false, 'message' => 'Geçersiz hedef klasör'], 400);
        }

        // Domain çıkar
        $senderDomain = null;
        if (strpos($senderEmail, '@') !== false) {
            $senderDomain = substr(strrchr($senderEmail, "@"), 1);
        }

        try {
            $ruleId = $db->insert('filter_rules', [
                'user_id' => $userId,
                'name' => $name ?: "Kural: {$senderEmail}",
                'sender_email' => $senderEmail,
                'sender_domain' => $senderDomain,
                'target_folder_id' => $targetFolderId,
                'is_active' => 1
            ]);

            jsonResponse([
                'success' => true,
                'message' => 'Filtre kuralı oluşturuldu',
                'rule_id' => $ruleId
            ]);
        } catch (\Exception $e) {
            jsonResponse(['success' => false, 'message' => 'Kural oluşturulamadı'], 500);
        }
        break;

    case 'toggle':
        // Kuralı aktif/pasif yap
        $ruleId = (int) ($input['id'] ?? 0);

        if (!$ruleId) {
            jsonResponse(['success' => false, 'message' => 'Kural ID gerekli'], 400);
        }

        $rule = $db->fetchOne(
            "SELECT * FROM filter_rules WHERE id = ? AND user_id = ?",
            [$ruleId, $userId]
        );

        if (!$rule) {
            jsonResponse(['success' => false, 'message' => 'Kural bulunamadı'], 404);
        }

        try {
            $newStatus = $rule['is_active'] ? 0 : 1;
            $db->update('filter_rules', ['is_active' => $newStatus], 'id = ?', [$ruleId]);
            jsonResponse([
                'success' => true,
                'message' => $newStatus ? 'Kural aktif edildi' : 'Kural devre dışı bırakıldı',
                'is_active' => $newStatus
            ]);
        } catch (\Exception $e) {
            jsonResponse(['success' => false, 'message' => 'Güncelleme hatası'], 500);
        }
        break;

    case 'delete':
        // Kuralı sil
        $ruleId = (int) ($input['id'] ?? 0);

        if (!$ruleId) {
            jsonResponse(['success' => false, 'message' => 'Kural ID gerekli'], 400);
        }

        $rule = $db->fetchOne(
            "SELECT id FROM filter_rules WHERE id = ? AND user_id = ?",
            [$ruleId, $userId]
        );

        if (!$rule) {
            jsonResponse(['success' => false, 'message' => 'Kural bulunamadı'], 404);
        }

        try {
            $db->delete('filter_rules', 'id = ?', [$ruleId]);
            jsonResponse(['success' => true, 'message' => 'Kural silindi']);
        } catch (\Exception $e) {
            jsonResponse(['success' => false, 'message' => 'Silme hatası'], 500);
        }
        break;

    case 'check':
        // E-posta için eşleşen kural var mı kontrol et
        $senderEmail = trim($input['sender_email'] ?? '');

        if (empty($senderEmail)) {
            jsonResponse(['success' => false, 'message' => 'Gönderen e-posta adresi gerekli'], 400);
        }

        $senderDomain = substr(strrchr($senderEmail, "@"), 1);

        // Önce tam e-posta eşleşmesi, sonra domain eşleşmesi
        $rule = $db->fetchOne(
            "SELECT r.*, f.name as folder_name 
             FROM filter_rules r 
             LEFT JOIN custom_folders f ON r.target_folder_id = f.id 
             WHERE r.user_id = ? AND r.is_active = 1 
             AND (r.sender_email = ? OR r.sender_email = ?)
             ORDER BY CASE WHEN r.sender_email = ? THEN 0 ELSE 1 END
             LIMIT 1",
            [$userId, $senderEmail, "@{$senderDomain}", $senderEmail]
        );

        if ($rule) {
            jsonResponse([
                'success' => true,
                'matched' => true,
                'rule' => $rule
            ]);
        } else {
            jsonResponse([
                'success' => true,
                'matched' => false
            ]);
        }
        break;

    default:
        jsonResponse(['success' => false, 'message' => 'Geçersiz action'], 400);
}
