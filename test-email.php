<?php

/**
 * Script testing Email Service
 * 
 * Jalankan: php test-email.php <email_test>
 * Example: php test-email.php user@example.com
 */

require_once __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Load environment variables from .env
function loadEnv($path)
{
    if (!file_exists($path)) {
        echo "❌ File .env tidak ditemukan: {$path}\n";
        return false;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '#') === 0) continue; // Skip comments
        if (strpos($line, '=') === false) continue;

        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value, '"\'');

        if (!getenv($key)) {
            putenv("{$key}={$value}");
        }
    }
    return true;
}

echo "===========================================\n";
echo "Testing Email Service - Gmail SMTP Relay\n";
echo "===========================================\n\n";

// Load .env
loadEnv(__DIR__ . '/.env');

// Get test email from argument
$testEmail = $argv[1] ?? null;

if (!$testEmail) {
    echo "❌ Email test tidak disediakan!\n";
    echo "Usage: php test-email.php <email_test>\n";
    echo "Example: php test-email.php user@example.com\n";
    exit(1);
}

echo "📧 Konfigurasi Email:\n";
echo "   MAIL_HOST: " . getenv('MAIL_HOST') . "\n";
echo "   MAIL_PORT: " . getenv('MAIL_PORT') . "\n";
echo "   MAIL_USERNAME: " . getenv('MAIL_USERNAME') . "\n";
echo "   MAIL_FROM_ADDRESS: " . getenv('MAIL_FROM_ADDRESS') . "\n";
echo "   MAIL_FROM_NAME: " . getenv('MAIL_FROM_NAME') . "\n";
echo "\n";

echo "📬 Mengirim email test ke: {$testEmail}\n";
echo "   (Check debug output below)\n\n";

$mail = new PHPMailer(true);

try {
    // Server settings
    $mail->isSMTP();
    $mail->Host = getenv('MAIL_HOST');
    $mail->SMTPAuth = true;
    $mail->Username = getenv('MAIL_USERNAME');
    $mail->Password = getenv('MAIL_PASSWORD');
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = (int) getenv('MAIL_PORT');

    // Debug output (level 3 = verbose)
    $mail->SMTPDebug = 3;
    $mail->Debugoutput = function ($str, $level) {
        echo "[SMTP Debug] {$str}\n";
    };

    // Recipients
    $mail->setFrom(getenv('MAIL_FROM_ADDRESS'), getenv('MAIL_FROM_NAME'));
    $mail->addAddress($testEmail, 'Test User');

    // Content
    $mail->isHTML(true);
    $mail->Subject = '🧪 Test Email - Mazu Framework';
    $mail->Body = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px; }
            .content { background: #f9f9f9; padding: 30px; margin-top: 20px; border-radius: 10px; }
            .success { color: #28a745; font-weight: bold; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>✅ Email Test Berhasil!</h1>
            </div>
            <div class="content">
                <p>Halo Test User,</p>
                <p class="success">Jika Anda menerima email ini, berarti konfigurasi Gmail SMTP Relay berfungsi dengan baik!</p>
                <p><strong>Detail Pengiriman:</strong></p>
                <ul>
                    <li>Waktu: ' . date('Y-m-d H:i:s') . '</li>
                    <li>Dari: ' . getenv('MAIL_FROM_ADDRESS') . '</li>
                    <li>SMTP Host: ' . getenv('MAIL_HOST') . ':' . getenv('MAIL_PORT') . '</li>
                </ul>
                <p>Test ini dikirim dari Mazu Framework - Hybrid Authentication System.</p>
            </div>
        </div>
    </body>
    </html>
    ';
    $mail->AltBody = "Halo Test User,\n\nJika Anda menerima email ini, berarti konfigurasi Gmail SMTP Relay berfungsi dengan baik!\n\nWaktu: " . date('Y-m-d H:i:s') . "\nDari: " . getenv('MAIL_FROM_ADDRESS');

    $result = $mail->send();

    echo "\n";
    echo "===========================================\n";
    if ($result) {
        echo "✅ Email BERHASIL dikirim!\n";
        echo "   Check inbox dan spam folder di: {$testEmail}\n";
    } else {
        echo "❌ Email GAGAL dikirim!\n";
    }
    echo "===========================================\n";
} catch (Exception $e) {
    echo "\n";
    echo "===========================================\n";
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "===========================================\n\n";

    echo "Kemungkinan masalah:\n";
    echo "  1. App Password salah atau expired\n";
    echo "  2. Gmail SMTP Relay belum enabled di Google Admin Console\n";
    echo "  3. Firewall/Antivirus memblok koneksi ke port 587\n";
    echo "  4. Koneksi internet bermasalah\n\n";

    echo "Solusi:\n";
    echo "  - Cek Google Admin Console: Apps → Gmail → SMTP relay service\n";
    echo "  - Pastikan 'Require SMTP Authentication' dicentang\n";
    echo "  - Generate ulang App Password di: https://myaccount.google.com/apppasswords\n";
}

echo "\nSelesai!\n";
