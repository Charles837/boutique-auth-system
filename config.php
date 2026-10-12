<?php
declare(strict_types=1);
session_start();

/*
 * Boutique Login System configuration.
 * Update these values to match your local XAMPP/MySQL setup.
 */
const DB_HOST = '127.0.0.1';
const DB_NAME = 'boutique_auth';
const DB_USER = 'root';
const DB_PASS = '';

const APP_NAME = 'ÉLAN Boutique';
const APP_ENV = 'development'; // Change to 'production' after configuring SMTP.
const OTP_VALID_MINUTES = 5;

// Optional SMTP settings. If SMTP_HOST is empty, development mode shows the OTP on screen.
// For real email delivery, install PHPMailer and configure these values.
const SMTP_HOST = '';
const SMTP_PORT = 587;
const SMTP_USER = '';
const SMTP_PASS = '';
const SMTP_FROM = 'no-reply@example.com';
const SMTP_FROM_NAME = 'ÉLAN Boutique';

function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $pdo;
}

function e(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf(): void {
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(419);
        exit('Your session token expired. Go back, refresh the page, and try again.');
    }
}

function set_flash(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function take_flash(): ?array {
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return is_array($flash) ? $flash : null;
}

function clear_session_state(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
    session_start();
}

function require_login(): void {
    if (empty($_SESSION['user'])) {
        header('Location: index.php');
        exit;
    }
}

function require_role(array $roles): void {
    require_login();
    if (!in_array($_SESSION['user']['role'], $roles, true)) {
        http_response_code(403);
        include __DIR__ . '/forbidden.php';
        exit;
    }
}

function send_otp_email(string $email, string $code): bool {
    if (SMTP_HOST === '') return false;
    $subject = APP_NAME . ' sign-in verification code';
    $message = "Your sign-in verification code is: {$code}\n\nIt expires in " . OTP_VALID_MINUTES . " minutes. If you did not request this, ignore this email.";
    $headers = "From: " . SMTP_FROM_NAME . " <" . SMTP_FROM . ">\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    // PHP mail() requires a working mail transport configured on the server.
    return @mail($email, $subject, $message, $headers);
}
