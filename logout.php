<?php
/**
 * AktMail - Çıkış İşlemi
 */

require_once __DIR__ . '/includes/Database.php';
require_once __DIR__ . '/includes/Security.php';
require_once __DIR__ . '/includes/Auth.php';

use AktMail\Security;
use AktMail\Auth;

Security::startSecureSession();

$auth = Auth::getInstance();
$auth->logout();

header('Location: index.php');
exit;
