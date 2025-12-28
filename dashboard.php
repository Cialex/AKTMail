<?php
/**
 * AktMail - Dashboard (Ana Panel)
 */

require_once __DIR__ . '/includes/Security.php';
require_once __DIR__ . '/includes/Auth.php';
require_once __DIR__ . '/includes/Database.php';

use AktMail\Security;
use AktMail\Auth;

Security::startSecureSession();

$auth = Auth::getInstance();

if (!$auth->isLoggedIn()) {
    // Giriş yapılmamış - login sayfasına yönlendir
    header('Location: login.php');
    exit;
}

$user = $auth->getCurrentUser();
$csrfToken = Security::generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="AktMail - Dashboard">
    <title>Gelen Kutusu - AktMail</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body class="compact-mode">
    <div id="dashboard" class="dashboard">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-logo">📧 AktMail</div>
            </div>

            <nav class="sidebar-nav">
                <div class="nav-section">
                    <div class="nav-section-title">E-postalar</div>
                    <a class="nav-item active" data-folder="inbox" onclick="switchFolder('inbox')">
                        <span class="nav-item-icon">📥</span>
                        <span>Gelen Kutusu</span>
                        <span id="inbox-badge" class="nav-item-badge" style="display:none;"></span>
                    </a>
                    <a class="nav-item" data-folder="sent" onclick="switchFolder('sent')">
                        <span class="nav-item-icon">📤</span>
                        <span>Gönderilenler</span>
                    </a>
                    <a class="nav-item" data-folder="spam" onclick="loadSpam()">
                        <span class="nav-item-icon">🚫</span>
                        <span>Spam</span>
                        <span id="spam-badge" class="nav-item-badge" style="display:none;"></span>
                    </a>
                    <a class="nav-item" data-folder="trash" onclick="loadTrash()">
                        <span class="nav-item-icon">🗑️</span>
                        <span>Çöp Kutusu</span>
                    </a>
                </div>

                <div class="nav-section">
                    <div class="nav-section-title">Hesaplar</div>
                    <div id="account-list">
                        <!-- Accounts will be loaded here -->
                        <div class="loading">
                            <div class="spinner"></div>
                        </div>
                    </div>
                </div>

                <!-- Özel Klasörler -->
                <div class="nav-section custom-folders-section">
                    <div class="nav-section-title">Özel Klasörler</div>
                    <div id="custom-folders-list">
                        <!-- Custom folders will be loaded here -->
                    </div>
                    <div class="add-folder-btn" onclick="createFolder()">
                        <span>➕</span>
                        <span>Yeni Klasör</span>
                    </div>
                </div>
            </nav>

            <div class="sidebar-footer">
                <div class="user-menu" onclick="toggleUserMenu()">
                    <div class="user-avatar"><?php echo strtoupper(substr($user['username'], 0, 1)); ?></div>
                    <div class="user-info">
                        <div class="user-name"><?php echo htmlspecialchars($user['username']); ?></div>
                        <div class="user-status">● Çevrimiçi</div>
                    </div>
                </div>
                <div style="margin-top: 0.5rem; display: flex; gap: 0.5rem;">
                    <a href="accounts.php" class="btn btn-secondary btn-sm" style="flex:1;">⚙️ Hesaplar</a>
                    <button class="btn btn-ghost btn-sm" onclick="handleLogout()">🚪 Çıkış</button>
                </div>
                <div class="theme-selector" style="margin-top:0.5rem;justify-content:center;">
                    <div class="theme-option theme-dark-opt active" data-theme="dark" title="Koyu"></div>
                    <div class="theme-option theme-light-opt" data-theme="light" title="Açık"></div>
                    <div class="theme-option theme-purple-opt" data-theme="purple" title="Mor"></div>
                    <div class="theme-option theme-blue-opt" data-theme="blue" title="Mavi"></div>
                    <div class="theme-option theme-green-opt" data-theme="green" title="Yeşil"></div>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <header class="header">
                <button class="menu-toggle" onclick="toggleSidebar()">☰</button>

                <div class="header-search">
                    <span class="header-search-icon">🔍</span>
                    <input type="text" placeholder="E-posta ara..." id="search-input">
                </div>

                <div class="header-actions">
                    <button class="btn btn-primary" onclick="openComposeModal()">
                        ✏️ Yeni E-posta
                    </button>
                    <button class="btn btn-ghost" onclick="loadEmails()">🔄</button>
                </div>
            </header>

            <!-- Email Container -->
            <div class="email-container">
                <!-- Email List -->
                <div class="email-list">
                    <div class="email-list-header">
                        <div style="display:flex;align-items:center;gap:0.5rem;">
                            <label class="select-all-checkbox">
                                <input type="checkbox" id="select-all-checkbox" onchange="selectAllEmails()">
                            </label>
                            <span id="folder-title" class="email-list-title">Gelen Kutusu</span>
                        </div>
                        <div style="display:flex;gap:0.25rem;">
                            <button class="btn btn-ghost btn-sm" onclick="markAllAsRead()"
                                title="Tümünü okundu işaretle">✓✓</button>
                            <button class="btn btn-ghost btn-sm" onclick="loadEmails()" title="Yenile">🔄</button>
                        </div>
                    </div>

                    <!-- Bulk Action Toolbar -->
                    <div id="bulk-toolbar" class="bulk-toolbar">
                        <span class="bulk-toolbar-info"><strong class="selected-count">0</strong> seçili</span>
                        <button class="bulk-btn" onclick="bulkMarkRead(true)">✓ Okundu</button>
                        <button class="bulk-btn" onclick="bulkMarkRead(false)">○ Okunmadı</button>
                        <button class="bulk-btn" onclick="bulkSpam()">🚫 Spam</button>
                        <div style="position:relative;">
                            <button class="bulk-btn" onclick="toggleFolderDropdown()">📁 Taşı</button>
                            <div id="folder-dropdown" class="folder-dropdown">
                                <div class="folder-dropdown-item" onclick="bulkMoveToFolder('INBOX')">📥 Gelen Kutusu
                                </div>
                                <div class="folder-dropdown-item" onclick="bulkMoveToFolder('Sent')">📤 Gönderilenler
                                </div>
                                <div class="folder-dropdown-item" onclick="bulkMoveToFolder('Spam')">🚫 Spam</div>
                                <div class="folder-dropdown-item" onclick="bulkMoveToFolder('Trash')">🗑️ Çöp</div>
                            </div>
                        </div>
                        <button class="bulk-btn danger" onclick="bulkDelete()">🗑 Sil</button>
                        <button class="bulk-btn" onclick="clearSelection()">✖ İptal</button>
                    </div>

                    <div id="email-list-content">
                        <!-- Emails will be loaded here -->
                        <div class="loading">
                            <div class="spinner"></div>
                        </div>
                    </div>
                </div>

                <!-- Email View -->
                <div class="email-view">
                    <div id="email-view-content">
                        <div class="email-view-empty">
                            <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="1.5">
                                <path d="M3 8l9 6 9-6" />
                                <rect x="3" y="5" width="18" height="14" rx="2" />
                            </svg>
                            <p>Okumak için bir e-posta seçin</p>
                        </div>
                    </div>
                    <button class="btn btn-ghost" style="position:absolute;top:1rem;left:1rem;display:none;"
                        onclick="clearEmailView()" id="back-btn">
                        ← Geri
                    </button>
                </div>
            </div>
        </main>
    </div>

    <!-- Compose Modal -->
    <div id="compose-modal" class="modal-overlay">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title">✏️ Yeni E-posta</h3>
                <button class="modal-close" onclick="closeModal('compose-modal')">✕</button>
            </div>
            <form id="compose-form">
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label">Gönderen Hesap(lar)</label>
                        <div id="account-selector">
                            <div class="loading">
                                <div class="spinner"></div>
                            </div>
                        </div>
                        <span class="form-hint">Birden fazla hesap seçerek aynı e-postayı hepsinden
                            gönderebilirsiniz</span>
                    </div>

                    <div class="compose-row">
                        <label class="compose-label">Kime:</label>
                        <input type="text" name="to" class="compose-input" placeholder="alici@ornek.com" required>
                    </div>

                    <div class="compose-row">
                        <label class="compose-label">CC:</label>
                        <input type="text" name="cc" class="compose-input" placeholder="cc@ornek.com (opsiyonel)">
                    </div>

                    <div class="compose-row">
                        <label class="compose-label">Konu:</label>
                        <input type="text" name="subject" class="compose-input" placeholder="E-posta konusu">
                    </div>

                    <div class="compose-body">
                        <textarea name="body" placeholder="Mesajınızı yazın..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('compose-modal')">İptal</button>
                    <button type="submit" class="btn btn-primary">📤 Gönder</button>
                </div>
            </form>
        </div>
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
                        <span class="form-hint">Gmail için <a href="https://myaccount.google.com/apppasswords"
                                target="_blank">Uygulama Şifresi</a> oluşturmanız gerekir</span>
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

    <!-- Create Folder Modal -->
    <div id="create-folder-modal" class="modal-overlay">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title">📁 Yeni Klasör Oluştur</h3>
                <button class="modal-close" onclick="closeModal('create-folder-modal')">✕</button>
            </div>
            <form id="create-folder-form" onsubmit="handleCreateFolder(event)">
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label">Klasör Adı</label>
                        <input type="text" id="folder-name-input" class="form-input"
                            placeholder="Örn: İş, Kişisel, Faturalar" required autofocus>
                        <span class="form-hint">Klasör adı en az 1 karakter olmalıdır</span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary"
                        onclick="closeModal('create-folder-modal')">İptal</button>
                    <button type="submit" class="btn btn-primary">✓ Oluştur</button>
                </div>
            </form>
        </div>
    </div>

    <div id="toast-container" class="toast-container"></div>

    <script src="assets/js/app.js"></script>

    <style>
        /* Mobile back button */
        @media (max-width: 992px) {
            #back-btn {
                display: block !important;
            }
        }
    </style>
</body>

</html>