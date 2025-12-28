<?php
/**
 * AktMail - Hesap Yönetimi Sayfası
 */

require_once __DIR__ . '/includes/Security.php';
require_once __DIR__ . '/includes/Auth.php';
require_once __DIR__ . '/includes/Database.php';
require_once __DIR__ . '/includes/EmailAccount.php';

use AktMail\Security;
use AktMail\Auth;
use AktMail\EmailAccount;

Security::startSecureSession();

$auth = Auth::getInstance();
$auth->requireLogin();

$user = $auth->getCurrentUser();
$accountManager = new EmailAccount($auth->getUserId());
$accounts = $accountManager->getAll(false);
$csrfToken = Security::generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="AktMail - E-posta Hesap Yönetimi">
    <title>Hesap Yönetimi - AktMail</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body>
    <div id="dashboard" class="dashboard">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-logo">📧 AktMail</div>
            </div>

            <nav class="sidebar-nav">
                <div class="nav-section">
                    <div class="nav-section-title">Navigasyon</div>
                    <a class="nav-item" href="dashboard.php">
                        <span class="nav-item-icon">📥</span>
                        <span>Gelen Kutusu</span>
                    </a>
                    <a class="nav-item active">
                        <span class="nav-item-icon">⚙️</span>
                        <span>Hesap Yönetimi</span>
                    </a>
                </div>
            </nav>

            <div class="sidebar-footer">
                <div class="user-menu">
                    <div class="user-avatar"><?php echo strtoupper(substr($user['username'], 0, 1)); ?></div>
                    <div class="user-info">
                        <div class="user-name"><?php echo htmlspecialchars($user['username']); ?></div>
                        <div class="user-status">● Çevrimiçi</div>
                    </div>
                </div>
                <div style="margin-top: 0.5rem;">
                    <button class="btn btn-ghost btn-sm btn-block" onclick="handleLogout()">🚪 Çıkış Yap</button>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <header class="header">
                <button class="menu-toggle" onclick="toggleSidebar()">☰</button>
                <h2 style="margin: 0;">⚙️ E-posta Hesap Yönetimi</h2>
                <div class="header-actions">
                    <button class="btn btn-primary" onclick="openAddAccountModal()">
                        + Yeni Hesap Ekle
                    </button>
                </div>
            </header>

            <div style="padding: var(--spacing-xl);">
                <?php if (empty($accounts)): ?>
                    <div class="card">
                        <div class="empty-state">
                            <svg class="empty-state-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="1.5">
                                <path d="M3 8l9 6 9-6" />
                                <rect x="3" y="5" width="18" height="14" rx="2" />
                            </svg>
                            <div class="empty-state-title">Henüz e-posta hesabı eklenmemiş</div>
                            <p class="empty-state-description">
                                Gmail, Outlook, Yahoo veya özel domain e-posta hesaplarınızı ekleyerek başlayın.
                            </p>
                            <button class="btn btn-primary" onclick="openAddAccountModal()">
                                + İlk Hesabınızı Ekleyin
                            </button>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="settings-grid">
                        <?php foreach ($accounts as $account): ?>
                            <div class="card settings-card">
                                <div class="settings-card-header">
                                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                                        <div class="account-avatar"
                                            style="background: <?php echo $account['is_active'] ? 'var(--accent-gradient)' : 'var(--bg-tertiary)'; ?>">
                                            <?php echo strtoupper(substr($account['email'], 0, 1)); ?>
                                        </div>
                                        <div>
                                            <div class="settings-card-title">
                                                <?php echo htmlspecialchars($account['display_name'] ?: $account['email']); ?>
                                            </div>
                                            <div style="font-size: 0.75rem; color: var(--text-muted);">
                                                <?php echo htmlspecialchars($account['email']); ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div style="font-size: 0.875rem; color: var(--text-secondary); margin-bottom: 1rem;">
                                    <div style="margin-bottom: 0.5rem;">
                                        <strong>IMAP:</strong>
                                        <?php echo htmlspecialchars($account['imap_host']); ?>:<?php echo $account['imap_port']; ?>
                                    </div>
                                    <div style="margin-bottom: 0.5rem;">
                                        <strong>SMTP:</strong>
                                        <?php echo htmlspecialchars($account['smtp_host']); ?>:<?php echo $account['smtp_port']; ?>
                                    </div>
                                    <div>
                                        <strong>Durum:</strong>
                                        <span
                                            style="color: <?php echo $account['is_active'] ? 'var(--success)' : 'var(--danger)'; ?>">
                                            <?php echo $account['is_active'] ? '● Aktif' : '○ Devre Dışı'; ?>
                                        </span>
                                    </div>
                                    <?php if ($account['last_sync']): ?>
                                        <div style="margin-top: 0.5rem; font-size: 0.75rem; color: var(--text-muted);">
                                            Son senkronizasyon: <?php echo date('d.m.Y H:i', strtotime($account['last_sync'])); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="settings-card-actions" style="display: flex; gap: 0.5rem;">
                                    <button class="btn btn-secondary btn-sm"
                                        onclick="toggleAccountStatus(<?php echo $account['id']; ?>, <?php echo $account['is_active'] ? 'false' : 'true'; ?>)">
                                        <?php echo $account['is_active'] ? '⏸ Devre Dışı Bırak' : '▶ Aktifleştir'; ?>
                                    </button>
                                    <button class="btn btn-danger btn-sm"
                                        onclick="deleteAccount(<?php echo $account['id']; ?>)">
                                        🗑 Sil
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="card" style="margin-top: 2rem;">
                    <div class="card-header">
                        <h3 class="card-title">📚 Popüler E-posta Sağlayıcı Ayarları</h3>
                    </div>
                    <div style="overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse; font-size: 0.875rem;">
                            <thead>
                                <tr style="border-bottom: 1px solid var(--border-color);">
                                    <th style="padding: 0.75rem; text-align: left;">Sağlayıcı</th>
                                    <th style="padding: 0.75rem; text-align: left;">IMAP Sunucu</th>
                                    <th style="padding: 0.75rem; text-align: left;">IMAP Port</th>
                                    <th style="padding: 0.75rem; text-align: left;">SMTP Sunucu</th>
                                    <th style="padding: 0.75rem; text-align: left;">SMTP Port</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr style="border-bottom: 1px solid var(--border-color);">
                                    <td style="padding: 0.75rem;"><span class="provider-gmail">●</span> Gmail</td>
                                    <td style="padding: 0.75rem;">imap.gmail.com</td>
                                    <td style="padding: 0.75rem;">993 (SSL)</td>
                                    <td style="padding: 0.75rem;">smtp.gmail.com</td>
                                    <td style="padding: 0.75rem;">587 (TLS)</td>
                                </tr>
                                <tr style="border-bottom: 1px solid var(--border-color);">
                                    <td style="padding: 0.75rem;"><span class="provider-outlook">●</span>
                                        Outlook/Hotmail</td>
                                    <td style="padding: 0.75rem;">outlook.office365.com</td>
                                    <td style="padding: 0.75rem;">993 (SSL)</td>
                                    <td style="padding: 0.75rem;">smtp.office365.com</td>
                                    <td style="padding: 0.75rem;">587 (TLS)</td>
                                </tr>
                                <tr style="border-bottom: 1px solid var(--border-color);">
                                    <td style="padding: 0.75rem;"><span class="provider-yahoo">●</span> Yahoo</td>
                                    <td style="padding: 0.75rem;">imap.mail.yahoo.com</td>
                                    <td style="padding: 0.75rem;">993 (SSL)</td>
                                    <td style="padding: 0.75rem;">smtp.mail.yahoo.com</td>
                                    <td style="padding: 0.75rem;">587 (TLS)</td>
                                </tr>
                                <tr>
                                    <td style="padding: 0.75rem;"><span class="provider-yandex">●</span> Yandex</td>
                                    <td style="padding: 0.75rem;">imap.yandex.com</td>
                                    <td style="padding: 0.75rem;">993 (SSL)</td>
                                    <td style="padding: 0.75rem;">smtp.yandex.com</td>
                                    <td style="padding: 0.75rem;">587 (TLS)</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div
                        style="margin-top: 1rem; padding: 1rem; background: var(--bg-tertiary); border-radius: var(--radius-md);">
                        <strong style="color: var(--warning);">⚠️ Gmail için önemli not:</strong>
                        <p style="margin-top: 0.5rem; font-size: 0.875rem; color: var(--text-secondary);">
                            Gmail hesabınızı kullanmak için normal şifreniz yerine "Uygulama Şifresi" oluşturmanız
                            gerekir.
                            <a href="https://myaccount.google.com/apppasswords" target="_blank">Buradan uygulama şifresi
                                oluşturabilirsiniz.</a>
                        </p>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Add Account Modal -->
    <div id="add-account-modal" class="modal-overlay">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title">📧 E-posta Hesabı Ekle</h3>
                <button class="modal-close" onclick="closeModal('add-account-modal')">✕</button>
            </div>
            <form id="add-account-form">
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label">E-posta Adresi</label>
                        <input type="email" name="email" class="form-input" placeholder="ornek@gmail.com" required>
                        <span class="form-hint">Gmail, Outlook, Yahoo veya özel domain e-posta adresi</span>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Şifre / Uygulama Şifresi</label>
                        <input type="password" name="password" class="form-input" placeholder="••••••••" required>
                        <span class="form-hint">Gmail için Uygulama Şifresi gereklidir</span>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Görünen İsim (Opsiyonel)</label>
                        <input type="text" name="display_name" class="form-input" placeholder="Ad Soyad">
                    </div>

                    <div class="auth-divider"><span>Sunucu Ayarları</span></div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label class="form-label">IMAP Sunucu</label>
                            <input type="text" name="imap_host" class="form-input" placeholder="imap.gmail.com"
                                required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">IMAP Port</label>
                            <input type="number" name="imap_port" class="form-input" placeholder="993" value="993"
                                required>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label class="form-label">SMTP Sunucu</label>
                            <input type="text" name="smtp_host" class="form-input" placeholder="smtp.gmail.com"
                                required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">SMTP Port</label>
                            <input type="number" name="smtp_port" class="form-input" placeholder="587" value="587"
                                required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary"
                        onclick="closeModal('add-account-modal')">İptal</button>
                    <button type="submit" class="btn btn-primary">✓ Hesap Ekle</button>
                </div>
            </form>
        </div>
    </div>

    <div id="toast-container" class="toast-container"></div>

    <script src="assets/js/app.js"></script>
    <script>
        async function toggleAccountStatus(accountId, activate) {
            const result = await apiCall(AktMail.api.accounts + '?action=update', {
                method: 'POST',
                body: { id: accountId, is_active: activate ? 1 : 0 }
            });

            if (result?.success) {
                showToast(activate ? 'Hesap aktifleştirildi' : 'Hesap devre dışı bırakıldı', 'success');
                setTimeout(() => location.reload(), 500);
            } else {
                showToast(result?.message || 'İşlem başarısız', 'error');
            }
        }
    </script>
</body>

</html>