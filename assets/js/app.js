/**
 * AktMail - JavaScript Uygulaması
 * 
 * E-posta istemcisi arayüz işlevleri
 */

// Global State
const AktMail = {
    state: {
        currentFolder: 'inbox',
        currentAccountId: null,
        selectedEmail: null,
        selectedEmails: [], // Çoklu seçim için
        accounts: [],
        emails: [],
        customFolders: [], // Özel klasörler
        filterRules: [], // Filtre kuralları
        user: null
    },

    // API Endpoints
    api: {
        base: 'api/',
        auth: 'api/auth.php',
        accounts: 'api/accounts.php',
        emails: 'api/emails.php',
        folders: 'api/folders.php',
        filters: 'api/filters.php'
    }
};

// ========================================
// Utility Functions
// ========================================

/**
 * API çağrısı yapar
 */
async function apiCall(endpoint, options = {}) {
    const defaultOptions = {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json'
        }
    };

    const mergedOptions = { ...defaultOptions, ...options };

    if (mergedOptions.body && typeof mergedOptions.body === 'object') {
        mergedOptions.body = JSON.stringify(mergedOptions.body);
    }

    try {
        const response = await fetch(endpoint, mergedOptions);
        const data = await response.json();

        if (!response.ok && response.status === 401) {
            // Unauthorized - sadece null dön, yönlendirme PHP tarafında yapılacak
            return null;
        }

        return data;
    } catch (error) {
        console.error('API Error:', error);
        showToast('Bir hata oluştu', 'error');
        return null;
    }
}

/**
 * Boş klasör için uygun mesajı döndürür
 */
function getEmptyFolderMessage(folder) {
    const messages = {
        'inbox': 'Gelen kutunuz boş',
        'sent': 'Gönderilmiş e-posta yok',
        'spam': 'Spam klasörünüz boş',
        'trash': 'Çöp kutunuz boş'
    };
    return messages[folder] || 'Bu klasörde e-posta yok';
}

/**
 * Toast bildirimi gösterir
 */
function showToast(message, type = 'info') {
    const container = document.getElementById('toast-container') || createToastContainer();

    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;

    const icons = {
        success: '✓',
        error: '✕',
        warning: '⚠',
        info: 'ℹ'
    };

    toast.innerHTML = `
        <span class="toast-icon">${icons[type] || icons.info}</span>
        <span class="toast-message">${escapeHtml(message)}</span>
        <button class="toast-close" onclick="this.parentElement.remove()">✕</button>
    `;

    container.appendChild(toast);

    // Auto remove after 5 seconds
    setTimeout(() => {
        toast.style.animation = 'slideOut 0.3s ease forwards';
        setTimeout(() => toast.remove(), 300);
    }, 5000);
}

function createToastContainer() {
    const container = document.createElement('div');
    container.id = 'toast-container';
    container.className = 'toast-container';
    document.body.appendChild(container);
    return container;
}

/**
 * HTML escape
 */
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/**
 * Tarih formatla
 */
function formatDate(dateString) {
    const date = new Date(dateString);
    const now = new Date();
    const diff = now - date;

    // Bugün
    if (date.toDateString() === now.toDateString()) {
        return date.toLocaleTimeString('tr-TR', { hour: '2-digit', minute: '2-digit' });
    }

    // Dün
    const yesterday = new Date(now);
    yesterday.setDate(yesterday.getDate() - 1);
    if (date.toDateString() === yesterday.toDateString()) {
        return 'Dün';
    }

    // Bu hafta
    if (diff < 7 * 24 * 60 * 60 * 1000) {
        return date.toLocaleDateString('tr-TR', { weekday: 'short' });
    }

    // Daha eski
    return date.toLocaleDateString('tr-TR', { day: 'numeric', month: 'short' });
}

/**
 * Loading göster/gizle
 */
function showLoading(container) {
    const loading = document.createElement('div');
    loading.className = 'loading';
    loading.innerHTML = '<div class="spinner"></div>';
    container.innerHTML = '';
    container.appendChild(loading);
}

function hideLoading(container) {
    const loading = container.querySelector('.loading');
    if (loading) loading.remove();
}

// ========================================
// Authentication
// ========================================

/**
 * Login işlemi
 */
async function handleLogin(event) {
    event.preventDefault();

    const form = event.target;
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn.textContent;

    submitBtn.disabled = true;
    submitBtn.textContent = 'Giriş yapılıyor...';

    const data = {
        action: 'login',
        username: form.username.value,
        password: form.password.value,
        remember: form.remember?.checked || false
    };

    const result = await apiCall(AktMail.api.auth + '?action=login', {
        method: 'POST',
        body: data
    });

    submitBtn.disabled = false;
    submitBtn.textContent = originalText;

    if (result?.success) {
        showToast('Giriş başarılı!', 'success');
        setTimeout(() => window.location.href = 'dashboard.php', 500);
    } else {
        showToast(result?.message || 'Giriş başarısız', 'error');
    }
}

/**
 * Register işlemi
 */
async function handleRegister(event) {
    event.preventDefault();

    const form = event.target;
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn.textContent;

    if (form.password.value !== form.confirm_password.value) {
        showToast('Şifreler eşleşmiyor', 'error');
        return;
    }

    submitBtn.disabled = true;
    submitBtn.textContent = 'Kayıt yapılıyor...';

    const data = {
        action: 'register',
        username: form.username.value,
        email: form.email.value,
        password: form.password.value,
        confirm_password: form.confirm_password.value
    };

    const result = await apiCall(AktMail.api.auth + '?action=register', {
        method: 'POST',
        body: data
    });

    submitBtn.disabled = false;
    submitBtn.textContent = originalText;

    if (result?.success) {
        showToast('Kayıt başarılı! Giriş yapabilirsiniz.', 'success');
        setTimeout(() => window.location.href = 'login.php', 1500);
    } else {
        showToast(result?.message || 'Kayıt başarısız', 'error');
    }
}

/**
 * Logout işlemi
 */
async function handleLogout() {
    await apiCall(AktMail.api.auth + '?action=logout', { method: 'POST' });
    window.location.href = 'login.php';
}

// ========================================
// Email Accounts
// ========================================

/**
 * E-posta hesaplarını yükler
 */
async function loadAccounts() {
    const result = await apiCall(AktMail.api.accounts + '?action=list');

    if (result?.success) {
        AktMail.state.accounts = result.accounts;
        renderAccountList();
    }

    return result?.accounts || [];
}

/**
 * Hesap listesini render eder
 */
function renderAccountList() {
    const container = document.getElementById('account-list');
    if (!container) return;

    if (AktMail.state.accounts.length === 0) {
        container.innerHTML = `
            <div class="empty-state">
                <p class="empty-state-description">Henüz e-posta hesabı eklenmemiş</p>
                <button class="btn btn-primary btn-sm" onclick="openAddAccountModal()">
                    + Hesap Ekle
                </button>
            </div>
        `;
        return;
    }

    container.innerHTML = AktMail.state.accounts.map(account => `
        <div class="account-item ${AktMail.state.currentAccountId === account.id ? 'selected' : ''}" 
             onclick="selectAccount(${account.id})" data-account-id="${account.id}">
            <div class="account-avatar">${getEmailInitial(account.email)}</div>
            <div class="account-info">
                <div class="account-name">${escapeHtml(account.display_name || account.email)}</div>
                <div class="account-email">${escapeHtml(account.email)}</div>
            </div>
        </div>
    `).join('');
}

function getEmailInitial(email) {
    return email ? email.charAt(0).toUpperCase() : '?';
}

/**
 * Hesap seçer
 */
function selectAccount(accountId) {
    AktMail.state.currentAccountId = accountId === AktMail.state.currentAccountId ? null : accountId;
    renderAccountList();
    loadEmails();
}

/**
 * Hesap ekle modal'ını açar
 */
function openAddAccountModal() {
    openModal('add-account-modal');
}

/**
 * E-posta sağlayıcısını otomatik algıla
 */
async function detectEmailProvider(email) {
    if (!email || !email.includes('@')) return;

    const result = await apiCall(AktMail.api.accounts + '?action=detect&email=' + encodeURIComponent(email));

    if (result?.detected && result.provider) {
        const form = document.getElementById('add-account-form');
        if (form) {
            form.imap_host.value = result.provider.imap_host;
            form.imap_port.value = result.provider.imap_port;
            form.smtp_host.value = result.provider.smtp_host;
            form.smtp_port.value = result.provider.smtp_port;
            showToast('Sunucu ayarları otomatik algılandı', 'success');
        }
    }
}

/**
 * Yeni hesap ekler
 */
async function handleAddAccount(event) {
    event.preventDefault();

    const form = event.target;
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn.textContent;

    submitBtn.disabled = true;
    submitBtn.textContent = 'Bağlantı test ediliyor...';

    const data = {
        action: 'add',
        email: form.email.value,
        password: form.password.value,
        display_name: form.display_name.value || form.email.value,
        imap_host: form.imap_host.value,
        imap_port: form.imap_port.value,
        imap_encryption: form.imap_encryption?.value || 'ssl',
        smtp_host: form.smtp_host.value,
        smtp_port: form.smtp_port.value,
        smtp_encryption: form.smtp_encryption?.value || 'tls'
    };

    const result = await apiCall(AktMail.api.accounts + '?action=add', {
        method: 'POST',
        body: data
    });

    submitBtn.disabled = false;
    submitBtn.textContent = originalText;

    if (result?.success) {
        showToast('Hesap başarıyla eklendi!', 'success');
        closeModal('add-account-modal');
        form.reset();
        await loadAccounts();
        loadEmails();
    } else {
        showToast(result?.message || 'Hesap eklenemedi', 'error');
    }
}

/**
 * Hesap siler
 */
async function deleteAccount(accountId) {
    if (!confirm('Bu hesabı silmek istediğinizden emin misiniz?')) return;

    const result = await apiCall(AktMail.api.accounts + '?action=delete', {
        method: 'POST',
        body: { id: accountId }
    });

    if (result?.success) {
        showToast('Hesap silindi', 'success');
        await loadAccounts();
        if (AktMail.state.currentAccountId === accountId) {
            AktMail.state.currentAccountId = null;
        }
        loadEmails();
    } else {
        showToast(result?.message || 'Hesap silinemedi', 'error');
    }
}

// ========================================
// Emails
// ========================================

/**
 * E-postaları yükler
 */
async function loadEmails() {
    const container = document.getElementById('email-list-content');
    if (!container) return;

    // Seçimleri temizle
    AktMail.state.selectedEmails = [];
    updateBulkToolbar();

    showLoading(container);

    let endpoint = AktMail.api.emails + '?action=';

    if (AktMail.state.currentFolder === 'inbox') {
        endpoint += AktMail.state.currentAccountId
            ? `inbox_account&account_id=${AktMail.state.currentAccountId}`
            : 'inbox';
    } else if (AktMail.state.currentFolder === 'sent') {
        endpoint += AktMail.state.currentAccountId
            ? `sent_account&account_id=${AktMail.state.currentAccountId}`
            : 'sent';
    } else if (AktMail.state.currentFolder === 'spam') {
        endpoint += 'spam';
    } else if (AktMail.state.currentFolder === 'trash') {
        endpoint += 'trash';
    } else {
        // Default to inbox
        endpoint += 'inbox';
    }

    console.log('loadEmails - currentFolder:', AktMail.state.currentFolder, 'endpoint:', endpoint);
    const result = await apiCall(endpoint);

    if (result?.success) {
        AktMail.state.emails = result.emails;
        renderEmailList();
    } else {
        container.innerHTML = `
            <div class="empty-state">
                <div class="empty-state-title">E-postalar yüklenemedi</div>
                <p class="empty-state-description">${escapeHtml(result?.message || 'Bir hata oluştu')}</p>
                <button class="btn btn-secondary btn-sm" onclick="loadEmails()">Tekrar Dene</button>
            </div>
        `;
    }
}

/**
 * E-posta listesini render eder
 */
function renderEmailList() {
    const container = document.getElementById('email-list-content');
    if (!container) return;

    if (AktMail.state.emails.length === 0) {
        container.innerHTML = `
            <div class="empty-state">
                <svg class="empty-state-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <path d="M3 8l9 6 9-6"/>
                    <rect x="3" y="5" width="18" height="14" rx="2"/>
                </svg>
                <div class="empty-state-title">E-posta bulunamadı</div>
                <p class="empty-state-description">
                    ${getEmptyFolderMessage(AktMail.state.currentFolder)}
                </p>
            </div>
        `;
        return;
    }

    container.innerHTML = AktMail.state.emails.map(email => {
        const isSelected = AktMail.state.selectedEmails.some(
            e => e.uid === email.uid && e.account_id === email.account_id
        );
        return `
        <div class="email-item ${email.seen ? '' : 'unread'} ${AktMail.state.selectedEmail?.uid === email.uid ? 'active' : ''} ${isSelected ? 'selected' : ''}"
             data-uid="${email.uid}" data-account="${email.account_id}" data-folder="${email.folder || 'INBOX'}">
            <div class="email-item-select">
                <input type="checkbox" class="email-checkbox" 
                       ${isSelected ? 'checked' : ''}
                       onclick="toggleEmailSelection(event, ${email.account_id}, ${email.uid}, '${escapeHtml(email.folder || 'INBOX')}')"
                       title="Seç">
                <div class="email-item-content" onclick="readEmail(${email.account_id}, ${email.uid}, '${escapeHtml(email.folder || 'INBOX')}')">
                    <div class="email-item-header">
                        <span class="email-sender">${escapeHtml(email.from?.name || email.from?.email || 'Bilinmiyor')}</span>
                        <span class="email-date">${formatDate(email.date)}</span>
                    </div>
                    <div class="email-subject">${escapeHtml(email.subject || '(Konu Yok)')}</div>
                    <div class="email-preview">
                        <span class="email-account-badge">${escapeHtml(email.account_email)}</span>
                    </div>
                </div>
            </div>
        </div>
    `}).join('');

    // Seçim durumuna göre bulk toolbar'ı göster/gizle
    updateBulkToolbar();
}

/**
 * E-postayı okur
 */
async function readEmail(accountId, uid, folder = 'INBOX') {
    const viewContainer = document.getElementById('email-view-content');
    if (!viewContainer) return;

    showLoading(viewContainer);

    // Mobile'da görünüm değiştir
    document.querySelector('.email-view')?.classList.add('active');

    const result = await apiCall(
        `${AktMail.api.emails}?action=read&account_id=${accountId}&uid=${uid}&folder=${encodeURIComponent(folder)}`
    );

    if (result?.success && result.email) {
        AktMail.state.selectedEmail = result.email;
        renderEmailView(result.email);

        // Önce tüm e-postalardan active sınıfını kaldır
        document.querySelectorAll('.email-item.active').forEach(item => {
            item.classList.remove('active');
        });

        // Listeyi güncelle (okundu işareti ve active)
        const emailItem = document.querySelector(`.email-item[data-uid="${uid}"][data-account="${accountId}"]`);
        if (emailItem) {
            emailItem.classList.remove('unread');
            emailItem.classList.add('active');
        }
    } else {
        viewContainer.innerHTML = `
            <div class="email-view-empty">
                <p>E-posta yüklenemedi</p>
                <button class="btn btn-secondary btn-sm" onclick="readEmail(${accountId}, ${uid}, '${folder}')">
                    Tekrar Dene
                </button>
            </div>
        `;
    }
}

/**
 * E-posta görünümünü render eder
 */
function renderEmailView(email) {
    const container = document.getElementById('email-view-content');
    if (!container) return;

    const fromInitial = email.from?.name?.charAt(0) || email.from?.email?.charAt(0) || '?';
    const toList = email.to?.map(t => t.email).join(', ') || '';

    container.innerHTML = `
        <div class="email-view-header">
            <h2 class="email-view-subject">${escapeHtml(email.subject || '(Konu Yok)')}</h2>
            <div class="email-view-meta">
                <div class="email-view-avatar">${fromInitial.toUpperCase()}</div>
                <div class="email-view-info">
                    <div class="email-view-from">
                        <strong>${escapeHtml(email.from?.name || 'Bilinmiyor')}</strong>
                        &lt;${escapeHtml(email.from?.email || '')}&gt;
                    </div>
                    <div class="email-view-to">Kime: ${escapeHtml(toList)}</div>
                    <div class="email-view-date">${new Date(email.date).toLocaleString('tr-TR')}</div>
                </div>
                <div class="header-actions">
                    <button class="btn btn-ghost btn-sm" onclick="replyToEmail()">↩ Yanıtla</button>
                    <button class="btn btn-ghost btn-sm" onclick="forwardEmail()">↪ İlet</button>
                    <button class="btn btn-secondary btn-sm" onclick="moveToSpam(${email.account_id}, ${email.uid}, '${escapeHtml(email.folder || 'INBOX')}')">🚫 Spam</button>
                    <button class="btn btn-danger btn-sm" onclick="moveToTrash(${email.account_id}, ${email.uid}, '${escapeHtml(email.folder || 'INBOX')}')">🗑 Sil</button>
                </div>
            </div>
        </div>
        <div class="email-view-body">
            ${email.body || '<p>İçerik yok</p>'}
        </div>
        ${email.attachments?.length ? `
            <div class="email-view-attachments">
                <div class="email-view-attachments-title">📎 Ekler (${email.attachments.length})</div>
                <div class="attachment-list">
                    ${email.attachments.map(att => `
                        <div class="attachment-item">
                            📄 ${escapeHtml(att.filename)}
                            <span style="color: var(--text-muted); font-size: 0.75rem;">
                                (${formatFileSize(att.size)})
                            </span>
                        </div>
                    `).join('')}
                </div>
            </div>
        ` : ''}
    `;
}

function formatFileSize(bytes) {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
}

/**
 * E-posta siler
 */
async function deleteEmail(accountId, uid, folder) {
    if (!confirm('Bu e-postayı silmek istediğinizden emin misiniz?')) return;

    const result = await apiCall(AktMail.api.emails + '?action=delete', {
        method: 'POST',
        body: { account_id: accountId, uid: uid, folder: folder }
    });

    if (result?.success) {
        showToast('E-posta silindi', 'success');
        AktMail.state.selectedEmail = null;
        loadEmails();
        clearEmailView();
    } else {
        showToast(result?.message || 'E-posta silinemedi', 'error');
    }
}

function clearEmailView() {
    const container = document.getElementById('email-view-content');
    if (container) {
        container.innerHTML = `
            <div class="email-view-empty">
                <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <path d="M3 8l9 6 9-6"/>
                    <rect x="3" y="5" width="18" height="14" rx="2"/>
                </svg>
                <p>E-posta seçin</p>
            </div>
        `;
    }
    document.querySelector('.email-view')?.classList.remove('active');
}

// ========================================
// Compose Email
// ========================================

/**
 * E-posta oluşturma modal'ını açar
 */
function openComposeModal(prefillData = {}) {
    openModal('compose-modal');

    const form = document.getElementById('compose-form');
    if (form) {
        form.reset();
        if (prefillData.to) form.to.value = prefillData.to;
        if (prefillData.subject) form.subject.value = prefillData.subject;
        if (prefillData.body) form.body.value = prefillData.body;
    }

    renderAccountSelector();
}

/**
 * Hesap seçici render eder
 */
function renderAccountSelector() {
    const container = document.getElementById('account-selector');
    if (!container) return;

    if (AktMail.state.accounts.length === 0) {
        container.innerHTML = '<p style="color: var(--text-muted);">Önce bir e-posta hesabı ekleyin</p>';
        return;
    }

    container.innerHTML = `
        <div class="account-select-list">
            ${AktMail.state.accounts.map(account => `
                <label class="account-select-item" data-account-id="${account.id}">
                    <input type="checkbox" name="account_ids[]" value="${account.id}" 
                           ${AktMail.state.accounts.length === 1 ? 'checked' : ''}>
                    <span class="account-avatar" style="width:24px;height:24px;font-size:12px;">
                        ${getEmailInitial(account.email)}
                    </span>
                    <span>${escapeHtml(account.email)}</span>
                </label>
            `).join('')}
        </div>
    `;

    // Click handler for visual feedback
    container.querySelectorAll('.account-select-item').forEach(item => {
        item.addEventListener('click', function () {
            const checkbox = this.querySelector('input[type="checkbox"]');
            setTimeout(() => {
                this.classList.toggle('selected', checkbox.checked);
            }, 0);
        });
    });
}

/**
 * E-posta gönderir
 */
async function handleSendEmail(event) {
    event.preventDefault();

    const form = event.target;
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn.textContent;

    const selectedAccounts = Array.from(form.querySelectorAll('input[name="account_ids[]"]:checked'))
        .map(cb => parseInt(cb.value));

    if (selectedAccounts.length === 0) {
        showToast('En az bir hesap seçin', 'warning');
        return;
    }

    const to = form.to.value.trim();
    if (!to) {
        showToast('Alıcı adresi gerekli', 'warning');
        return;
    }

    submitBtn.disabled = true;
    submitBtn.textContent = 'Gönderiliyor...';

    const data = {
        to: to.split(',').map(e => e.trim()),
        subject: form.subject.value,
        body: form.body.value,
        cc: form.cc?.value ? form.cc.value.split(',').map(e => e.trim()) : [],
        bcc: form.bcc?.value ? form.bcc.value.split(',').map(e => e.trim()) : []
    };

    let result;

    if (selectedAccounts.length === 1) {
        data.account_id = selectedAccounts[0];
        result = await apiCall(AktMail.api.emails + '?action=send', {
            method: 'POST',
            body: data
        });
    } else {
        data.account_ids = selectedAccounts;
        result = await apiCall(AktMail.api.emails + '?action=send_multiple', {
            method: 'POST',
            body: data
        });
    }

    submitBtn.disabled = false;
    submitBtn.textContent = originalText;

    if (result?.success) {
        showToast(result.message || 'E-posta gönderildi!', 'success');
        closeModal('compose-modal');
        form.reset();
    } else {
        showToast(result?.message || 'E-posta gönderilemedi', 'error');
    }
}

/**
 * Yanıtla
 */
function replyToEmail() {
    if (!AktMail.state.selectedEmail) return;

    const email = AktMail.state.selectedEmail;
    openComposeModal({
        to: email.from?.email || '',
        subject: 'Re: ' + (email.subject || ''),
        body: `\n\n--- Orijinal Mesaj ---\nKimden: ${email.from?.name || ''} <${email.from?.email || ''}>\nTarih: ${new Date(email.date).toLocaleString('tr-TR')}\nKonu: ${email.subject || ''}\n\n${stripHtml(email.body || '')}`
    });
}

/**
 * İlet
 */
function forwardEmail() {
    if (!AktMail.state.selectedEmail) return;

    const email = AktMail.state.selectedEmail;
    openComposeModal({
        subject: 'Fwd: ' + (email.subject || ''),
        body: `\n\n--- İletilen Mesaj ---\nKimden: ${email.from?.name || ''} <${email.from?.email || ''}>\nTarih: ${new Date(email.date).toLocaleString('tr-TR')}\nKonu: ${email.subject || ''}\n\n${stripHtml(email.body || '')}`
    });
}

function stripHtml(html) {
    const tmp = document.createElement('div');
    tmp.innerHTML = html;
    return tmp.textContent || tmp.innerText || '';
}

// ========================================
// Folder Navigation
// ========================================

function switchFolder(folder) {
    AktMail.state.currentFolder = folder;
    AktMail.state.selectedEmail = null;

    // Update UI
    document.querySelectorAll('.nav-item[data-folder]').forEach(item => {
        item.classList.toggle('active', item.dataset.folder === folder);
    });

    // Update header
    const headerTitle = document.getElementById('folder-title');
    if (headerTitle) {
        headerTitle.textContent = folder === 'inbox' ? 'Gelen Kutusu' : 'Gönderilenler';
    }

    clearEmailView();
    loadEmails();
}

// ========================================
// Modal Functions
// ========================================

function openModal(modalId) {
    const overlay = document.getElementById(modalId);
    if (overlay) {
        overlay.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
}

function closeModal(modalId) {
    const overlay = document.getElementById(modalId);
    if (overlay) {
        overlay.classList.remove('active');
        document.body.style.overflow = '';
    }
}

// Close modal on overlay click
document.addEventListener('click', (e) => {
    if (e.target.classList.contains('modal-overlay')) {
        e.target.classList.remove('active');
        document.body.style.overflow = '';
    }
});

// Close modal on Escape key
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay.active').forEach(modal => {
            modal.classList.remove('active');
        });
        document.body.style.overflow = '';
    }
});

// ========================================
// Sidebar Toggle (Mobile)
// ========================================

function toggleSidebar() {
    document.querySelector('.sidebar')?.classList.toggle('open');
}

function closeSidebar() {
    document.querySelector('.sidebar')?.classList.remove('open');
}

// ========================================
// Theme System
// ========================================

function setTheme(theme) {
    // Renk temaları
    document.body.classList.remove('theme-light', 'theme-purple', 'theme-blue', 'theme-green');

    if (theme && theme !== 'dark') {
        document.body.classList.add('theme-' + theme);
    }

    // Theme selector'u güncelle
    document.querySelectorAll('.theme-option').forEach(opt => {
        opt.classList.toggle('active', opt.dataset.theme === theme);
    });

    // LocalStorage'a kaydet
    localStorage.setItem('aktmail-theme', theme);

    // Sunucuya kaydet
    apiCall('api/settings.php?action=update', {
        method: 'POST',
        body: { theme: theme }
    });
}

function setCompactMode(enabled) {
    document.body.classList.toggle('compact-mode', enabled);
    localStorage.setItem('aktmail-compact', enabled ? '1' : '0');
}

function loadTheme() {
    const savedTheme = localStorage.getItem('aktmail-theme') || 'dark';
    const compactMode = localStorage.getItem('aktmail-compact') !== '0'; // Varsayılan: açık

    setTheme(savedTheme);
    setCompactMode(compactMode);
}

// ========================================
// Spam/Trash Folders
// ========================================

function switchFolder(folder) {
    AktMail.state.currentFolder = folder;
    AktMail.state.selectedEmail = null;
    AktMail.state.currentAccountId = null; // Tüm hesapları göster

    updateFolderUI(folder);
    clearEmailView();
    loadEmails();
}

async function loadSpam() {
    console.log('loadSpam called');
    AktMail.state.currentFolder = 'spam';
    AktMail.state.selectedEmail = null;
    AktMail.state.selectedEmails = [];

    updateFolderUI('spam');
    clearEmailView();

    await loadEmails();
}

async function loadTrash() {
    AktMail.state.currentFolder = 'trash';
    AktMail.state.selectedEmail = null;
    AktMail.state.selectedEmails = [];

    updateFolderUI('trash');
    clearEmailView();

    await loadEmails();
}

function updateFolderUI(folder, customName = null) {
    // Sidebar'daki aktif klasörü güncelle
    document.querySelectorAll('.nav-item').forEach(item => {
        item.classList.remove('active');
        if (item.dataset.folder === folder) {
            item.classList.add('active');
        }
    });

    // Header başlığını güncelle
    const headerTitle = document.getElementById('folder-title');
    if (headerTitle) {
        if (customName) {
            headerTitle.textContent = customName;
        } else {
            const titles = {
                'inbox': 'Gelen Kutusu',
                'sent': 'Gönderilenler',
                'spam': 'Spam',
                'trash': 'Çöp Kutusu',
                'custom': 'Özel Klasör'
            };
            headerTitle.textContent = titles[folder] || 'E-postalar';
        }
    }
}

async function moveToSpam(accountId, uid, folder) {
    const result = await apiCall('api/emails.php?action=move_to_spam', {
        method: 'POST',
        body: { account_id: accountId, uid: uid, folder: folder }
    });

    if (result?.success) {
        showToast('Spam olarak işaretlendi', 'success');
        loadEmails();
        clearEmailView();
        loadUnreadCounts();
    } else {
        showToast(result?.message || 'Hata oluştu', 'error');
    }
}

async function moveToTrash(accountId, uid, folder) {
    const result = await apiCall('api/emails.php?action=move_to_trash', {
        method: 'POST',
        body: { account_id: accountId, uid: uid, folder: folder }
    });

    if (result?.success) {
        showToast('Çöp kutusuna taşındı', 'success');
        loadEmails();
        clearEmailView();
        loadUnreadCounts();
    } else {
        showToast(result?.message || 'Hata oluştu', 'error');
    }
}

async function restoreEmail(accountId, uid) {
    const result = await apiCall('api/emails.php?action=restore', {
        method: 'POST',
        body: { account_id: accountId, uid: uid }
    });

    if (result?.success) {
        showToast('E-posta geri yüklendi', 'success');
        loadEmails();
        clearEmailView();
    } else {
        showToast(result?.message || 'Hata oluştu', 'error');
    }
}

async function permanentDelete(accountId, uid, folder) {
    if (!confirm('Bu e-postayı kalıcı olarak silmek istediğinizden emin misiniz? Bu işlem geri alınamaz.')) return;

    const result = await apiCall('api/emails.php?action=permanent_delete', {
        method: 'POST',
        body: { account_id: accountId, uid: uid, folder: folder }
    });

    if (result?.success) {
        showToast('E-posta kalıcı olarak silindi', 'success');
        loadEmails();
        clearEmailView();
    } else {
        showToast(result?.message || 'Hata oluştu', 'error');
    }
}

// ========================================
// Mark All As Read & Unread Counts
// ========================================

async function markAllAsRead() {
    const result = await apiCall('api/emails.php?action=mark_all_read', {
        method: 'POST',
        body: { folder: 'INBOX' }
    });

    if (result?.success) {
        showToast(result.message || 'Tümü okundu işaretlendi', 'success');
        loadEmails();
        loadUnreadCounts();
    } else {
        showToast(result?.message || 'Hata oluştu', 'error');
    }
}

async function loadUnreadCounts() {
    const result = await apiCall('api/emails.php?action=unread_count');

    if (result?.success && result.counts) {
        // Inbox badge
        const inboxBadge = document.getElementById('inbox-badge');
        if (inboxBadge) {
            if (result.counts.inbox > 0) {
                inboxBadge.textContent = result.counts.inbox > 99 ? '99+' : result.counts.inbox;
                inboxBadge.style.display = 'inline-block';
            } else {
                inboxBadge.style.display = 'none';
            }
        }

        // Spam badge
        const spamBadge = document.getElementById('spam-badge');
        if (spamBadge) {
            if (result.counts.spam > 0) {
                spamBadge.textContent = result.counts.spam > 99 ? '99+' : result.counts.spam;
                spamBadge.style.display = 'inline-block';
            } else {
                spamBadge.style.display = 'none';
            }
        }
    }
}

// ========================================
// Signatures
// ========================================

async function loadSignatures() {
    const result = await apiCall('api/signatures.php?action=list');
    return result?.signatures || [];
}

async function getDefaultSignature(accountId = null) {
    const url = accountId
        ? `api/signatures.php?action=get_default&account_id=${accountId}`
        : 'api/signatures.php?action=get_default';
    const result = await apiCall(url);
    return result?.signature?.content || '';
}

async function addSignatureToCompose() {
    const signature = await getDefaultSignature();
    if (signature) {
        const bodyField = document.querySelector('#compose-form textarea[name="body"]');
        if (bodyField && !bodyField.value.includes(signature)) {
            bodyField.value = bodyField.value + '\n\n' + signature;
        }
    }
}

// ========================================
// Initialization
// ========================================

document.addEventListener('DOMContentLoaded', () => {
    // Tema yükle
    loadTheme();

    // Login form
    const loginForm = document.getElementById('login-form');
    if (loginForm) {
        loginForm.addEventListener('submit', handleLogin);
    }

    // Register form
    const registerForm = document.getElementById('register-form');
    if (registerForm) {
        registerForm.addEventListener('submit', handleRegister);
    }

    // Dashboard initialization
    if (document.getElementById('dashboard')) {
        initDashboard();
    }

    // Add account form
    const addAccountForm = document.getElementById('add-account-form');
    if (addAccountForm) {
        addAccountForm.addEventListener('submit', handleAddAccount);

        // Auto-detect on email blur
        const emailInput = addAccountForm.querySelector('input[name="email"]');
        if (emailInput) {
            emailInput.addEventListener('blur', () => detectEmailProvider(emailInput.value));
        }
    }

    // Compose form
    const composeForm = document.getElementById('compose-form');
    if (composeForm) {
        composeForm.addEventListener('submit', handleSendEmail);
    }

    // Theme selector
    document.querySelectorAll('.theme-option').forEach(opt => {
        opt.addEventListener('click', () => setTheme(opt.dataset.theme));
    });

    // Compact mode toggle
    const compactToggle = document.getElementById('compact-toggle');
    if (compactToggle) {
        compactToggle.addEventListener('change', (e) => setCompactMode(e.target.checked));
    }
});

async function initDashboard() {
    await loadAccounts();
    await loadCustomFolders();
    loadEmails();
    loadUnreadCounts();
}

// Auto-refresh emails every 5 minutes
setInterval(() => {
    if (document.getElementById('dashboard') && !document.hidden) {
        loadEmails();
        loadUnreadCounts();
    }
}, 5 * 60 * 1000);

// ========================================
// Multi-Select Functions
// ========================================

function toggleEmailSelection(event, accountId, uid, folder) {
    event.stopPropagation();

    const emailData = { account_id: accountId, uid: uid, folder: folder };
    const index = AktMail.state.selectedEmails.findIndex(
        e => e.uid === uid && e.account_id === accountId
    );

    if (index > -1) {
        AktMail.state.selectedEmails.splice(index, 1);
    } else {
        AktMail.state.selectedEmails.push(emailData);
    }

    // UI güncelle
    const emailItem = document.querySelector(`.email-item[data-uid="${uid}"][data-account="${accountId}"]`);
    if (emailItem) {
        emailItem.classList.toggle('selected', index === -1);
    }

    updateBulkToolbar();
}

function selectAllEmails() {
    const selectAllCheckbox = document.getElementById('select-all-checkbox');
    const isChecked = selectAllCheckbox?.checked;

    if (isChecked) {
        AktMail.state.selectedEmails = AktMail.state.emails.map(e => ({
            account_id: e.account_id,
            uid: e.uid,
            folder: e.folder || 'INBOX'
        }));
    } else {
        AktMail.state.selectedEmails = [];
    }

    renderEmailList();
}

function clearSelection() {
    AktMail.state.selectedEmails = [];
    renderEmailList();
}

function updateBulkToolbar() {
    const toolbar = document.getElementById('bulk-toolbar');
    const count = AktMail.state.selectedEmails.length;

    if (toolbar) {
        toolbar.classList.toggle('active', count > 0);
        const countSpan = toolbar.querySelector('.selected-count');
        if (countSpan) countSpan.textContent = count;
    }
}

// ========================================
// Bulk Actions
// ========================================

async function bulkDelete() {
    console.log('bulkDelete called, selectedEmails:', AktMail.state.selectedEmails);

    if (!AktMail.state.selectedEmails || !AktMail.state.selectedEmails.length) {
        showToast('Lütfen silmek için e-posta seçin', 'warning');
        return;
    }

    // Confirm dialogunu geçici olarak devre dışı bıraktık - test için
    // if (!confirm(`${AktMail.state.selectedEmails.length} e-postayı silmek istediğinizden emin misiniz?`)) {
    //     console.log('User cancelled delete');
    //     return;
    // }

    console.log('Sending delete request...');
    const result = await apiCall(AktMail.api.emails + '?action=bulk_delete', {
        method: 'POST',
        body: { emails: AktMail.state.selectedEmails }
    });

    console.log('Delete result:', result);

    if (result?.success) {
        showToast(result.message, 'success');
        AktMail.state.selectedEmails = [];
        loadEmails();
        loadUnreadCounts();
    } else {
        showToast(result?.message || 'Silme işlemi başarısız', 'error');
        console.error('Delete failed:', result);
    }
}

async function bulkSpam() {
    if (!AktMail.state.selectedEmails.length) return;

    const result = await apiCall(AktMail.api.emails + '?action=bulk_spam', {
        method: 'POST',
        body: { emails: AktMail.state.selectedEmails }
    });

    if (result?.success) {
        showToast(result.message, 'success');
        AktMail.state.selectedEmails = [];
        loadEmails();
        loadUnreadCounts();
    } else {
        showToast(result?.message || 'Hata oluştu', 'error');
    }
}

async function bulkMarkRead(seen = true) {
    if (!AktMail.state.selectedEmails.length) return;

    const result = await apiCall(AktMail.api.emails + '?action=bulk_mark_read', {
        method: 'POST',
        body: { emails: AktMail.state.selectedEmails, seen: seen }
    });

    if (result?.success) {
        showToast(result.message, 'success');
        AktMail.state.selectedEmails = [];
        loadEmails();
        loadUnreadCounts();
    } else {
        showToast(result?.message || 'Hata oluştu', 'error');
    }
}

async function bulkMoveToFolder(targetFolder) {
    if (!AktMail.state.selectedEmails.length) return;

    const result = await apiCall(AktMail.api.emails + '?action=bulk_move', {
        method: 'POST',
        body: { emails: AktMail.state.selectedEmails, target_folder: targetFolder }
    });

    if (result?.success) {
        showToast(result.message, 'success');
        AktMail.state.selectedEmails = [];
        loadEmails();
        closeFolderDropdown();
    } else {
        showToast(result?.message || 'Hata oluştu', 'error');
    }
}

// ========================================
// Custom Folders
// ========================================

async function loadCustomFolders() {
    const result = await apiCall(AktMail.api.folders + '?action=list');

    if (result?.success) {
        AktMail.state.customFolders = result.folders;
        renderCustomFolders();
    }
}

function renderCustomFolders() {
    const container = document.getElementById('custom-folders-list');
    if (!container) return;

    if (AktMail.state.customFolders.length === 0) {
        container.innerHTML = '';
        return;
    }

    container.innerHTML = AktMail.state.customFolders.map(folder => `
        <div class="custom-folder-item" data-folder-id="${folder.id}" data-folder-name="${escapeHtml(folder.name)}">
            <span class="folder-icon">${folder.icon || '📁'}</span>
            <span class="folder-name">${escapeHtml(folder.name)}</span>
            <span class="folder-color" style="background-color: ${folder.color}"></span>
        </div>
    `).join('');

    // Use event delegation - attach listener to container
    container.onclick = (e) => {
        const item = e.target.closest('.custom-folder-item');
        if (item) {
            const folderId = parseInt(item.dataset.folderId);
            const folderName = item.dataset.folderName;
            console.log('Custom folder clicked:', folderId, folderName);
            try {
                loadCustomFolder(folderId, folderName);
            } catch (error) {
                console.error('Error calling loadCustomFolder:', error);
            }
        }
    };
}

async function createFolder() {
    console.log('createFolder called - opening modal');
    openModal('create-folder-modal');
    // Focus input after modal opens
    setTimeout(() => {
        document.getElementById('folder-name-input')?.focus();
    }, 100);
}

async function handleCreateFolder(event) {
    event.preventDefault();

    const input = document.getElementById('folder-name-input');
    const name = input?.value?.trim();

    if (!name) {
        showToast('Klasör adı gerekli', 'warning');
        return;
    }

    const result = await apiCall(AktMail.api.folders + '?action=create', {
        method: 'POST',
        body: { name: name }
    });

    if (result?.success) {
        showToast('Klasör oluşturuldu', 'success');
        closeModal('create-folder-modal');
        input.value = ''; // Clear input
        loadCustomFolders();
    } else {
        showToast(result?.message || 'Klasör oluşturulamadı', 'error');
    }
}

async function loadCustomFolder(folderId, folderName) {
    console.log('loadCustomFolder called:', folderId, folderName);

    AktMail.state.currentFolder = 'custom';
    AktMail.state.currentCustomFolderId = folderId;
    AktMail.state.selectedEmail = null;
    AktMail.state.selectedEmails = [];

    updateFolderUI('custom', folderName);
    clearEmailView();

    // Load emails from this custom folder
    const container = document.getElementById('email-list-content');
    if (!container) return;

    showLoading(container);

    const result = await apiCall(`${AktMail.api.folders}?action=get_emails&folder_id=${folderId}`);

    if (result?.success) {
        AktMail.state.emails = result.emails || [];
        renderEmailList();
    } else {
        container.innerHTML = `
            <div class="empty-state">
                <div class="empty-state-title">E-postalar yüklenemedi</div>
                <p class="empty-state-description">${escapeHtml(result?.message || 'Bir hata oluştu')}</p>
            </div>
        `;
    }
}

async function deleteFolder(folderId) {
    if (!confirm('Bu klasörü silmek istediğinizden emin misiniz?')) return;

    const result = await apiCall(AktMail.api.folders + '?action=delete', {
        method: 'POST',
        body: { id: folderId }
    });

    if (result?.success) {
        showToast('Klasör silindi', 'success');
        loadCustomFolders();
    } else {
        showToast(result?.message || 'Silme hatası', 'error');
    }
}

async function loadCustomFolder(folderId, folderName) {
    // Bu fonksiyon özel klasördeki e-postaları yükler
    // Şimdilik IMAP klasör desteği yok, sadece UI gösterimi
    showToast(`"${folderName}" klasörü seçildi`, 'info');
}

// Folder dropdown
function toggleFolderDropdown() {
    const dropdown = document.getElementById('folder-dropdown');
    if (dropdown) {
        dropdown.classList.toggle('show');
    }
}

function closeFolderDropdown() {
    const dropdown = document.getElementById('folder-dropdown');
    if (dropdown) {
        dropdown.classList.remove('show');
    }
}

// ========================================
// Filter Rules
// ========================================

async function loadFilterRules() {
    const result = await apiCall(AktMail.api.filters + '?action=list');

    if (result?.success) {
        AktMail.state.filterRules = result.rules;
    }
}

async function createFilterFromSender(senderEmail) {
    // Önce klasör seç
    if (AktMail.state.customFolders.length === 0) {
        showToast('Önce bir klasör oluşturmalısınız', 'warning');
        return;
    }

    const folderNames = AktMail.state.customFolders.map(f => f.name).join(', ');
    const targetName = prompt(`${senderEmail} adresinden gelen mailler hangi klasöre taşınsın?\n\nMevcut klasörler: ${folderNames}`);

    if (!targetName) return;

    const targetFolder = AktMail.state.customFolders.find(f => f.name.toLowerCase() === targetName.toLowerCase());

    if (!targetFolder) {
        showToast('Klasör bulunamadı', 'error');
        return;
    }

    const result = await apiCall(AktMail.api.filters + '?action=add', {
        method: 'POST',
        body: {
            sender_email: senderEmail,
            target_folder_id: targetFolder.id,
            name: `${senderEmail} → ${targetFolder.name}`
        }
    });

    if (result?.success) {
        showToast('Filtre kuralı oluşturuldu', 'success');
        loadFilterRules();
    } else {
        showToast(result?.message || 'Kural oluşturulamadı', 'error');
    }
}

// Close dropdown on outside click
document.addEventListener('click', (e) => {
    if (!e.target.closest('.folder-dropdown') && !e.target.closest('.bulk-btn')) {
        closeFolderDropdown();
    }
});

// ========================================
// Login Form Handler
// ========================================

const loginForm = document.getElementById('login-form');
if (loginForm) {
    loginForm.addEventListener('submit', async (e) => {
        e.preventDefault();

        const formData = new FormData(loginForm);
        const username = formData.get('username');
        const password = formData.get('password');
        const remember = formData.get('remember') === 'on';

        const result = await apiCall('api/auth.php?action=login', {
            method: 'POST',
            body: { username, password, remember }
        });

        if (result?.success) {
            window.location.href = 'dashboard.php';
        } else {
            showToast(result?.message || 'Giriş başarısız', 'error');
        }
    });
}
