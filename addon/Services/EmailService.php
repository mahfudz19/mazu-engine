<?php

namespace Addon\Services;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Email Service
 * 
 * Layanan pengiriman email menggunakan Gmail SMTP Relay.
 * Mendukung berbagai jenis email: OTP, notifikasi login, reset password, dll.
 */
class EmailService
{
    private PHPMailer $mailer;
    private string $fromAddress;
    private string $fromName;

    /**
     * Constructor - Inisialisasi PHPMailer dengan konfigurasi Gmail SMTP Relay
     * 
     * @throws Exception Jika konfigurasi tidak lengkap
     */
    public function __construct()
    {
        $this->mailer = new PHPMailer(true);

        // Server configuration
        $this->mailer->isSMTP();
        $this->mailer->Host = env('MAIL_HOST', 'smtp-relay.gmail.com');
        $this->mailer->SMTPAuth = true;
        $this->mailer->Username = env('MAIL_USERNAME');
        $this->mailer->Password = env('MAIL_PASSWORD');
        $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $this->mailer->Port = (int) env('MAIL_PORT', 587);

        // Default sender
        $this->fromAddress = env('MAIL_FROM_ADDRESS', 'noreply@inbitef.ac.id');
        $this->fromName = env('MAIL_FROM_NAME', env('APP_NAME', 'Mazu Framework'));

        // Set defaults
        $this->mailer->setFrom($this->fromAddress, $this->fromName);
        $this->mailer->isHTML(true);
        $this->mailer->CharSet = 'UTF-8';
    }

    /**
     * Kirim email OTP verification
     * 
     * @param string $to Email tujuan
     * @param string $name Nama penerima
     * @param string $otpCode Kode OTP 6 digit
     * @param int $expiresInMinutes Durasi kedaluwarsa (menit)
     * @return bool True jika berhasil dikirim
     */
    public function sendOtpVerification(string $to, string $name, string $otpCode, int $expiresInMinutes = 15): bool
    {
        $subject = 'Kode Verifikasi Email - ' . $this->fromName;

        $html = $this->getOtpTemplate($name, $otpCode, $expiresInMinutes);
        $altBody = "Halo {$name},\n\nKode verifikasi email Anda adalah: {$otpCode}\n\nKode ini berlaku selama {$expiresInMinutes} menit.\n\nJika Anda tidak meminta kode ini, abaikan email ini.";

        return $this->send($to, $name, $subject, $html, $altBody);
    }

    /**
     * Kirim email notifikasi login
     * 
     * @param string $to Email tujuan
     * @param string $name Nama penerima
     * @param string $ipAddress IP address user
     * @param string $userAgent User agent browser
     * @param string $loginAt Waktu login
     * @return bool True jika berhasil dikirim
     */
    public function sendLoginNotification(
        string $to,
        string $name,
        string $ipAddress,
        string $userAgent,
        string $loginAt
    ): bool {
        $subject = 'Notifikasi Login Baru - ' . $this->fromName;

        $html = $this->getLoginNotificationTemplate($name, $ipAddress, $userAgent, $loginAt);
        $altBody = "Halo {$name},\n\nAkun Anda telah login pada {$loginAt}\n\nIP Address: {$ipAddress}\n\nJika ini bukan Anda, segera ubah password Anda.";

        return $this->send($to, $name, $subject, $html, $altBody);
    }

    /**
     * Kirim email reset password
     * 
     * @param string $to Email tujuan
     * @param string $name Nama penerima
     * @param string $resetUrl URL reset password
     * @param int $expiresInMinutes Durasi kedaluwarsa token (menit)
     * @return bool True jika berhasil dikirim
     */
    public function sendPasswordReset(string $to, string $name, string $resetUrl, int $expiresInMinutes = 60): bool
    {
        $subject = 'Reset Password - ' . $this->fromName;

        $html = $this->getPasswordResetTemplate($name, $resetUrl, $expiresInMinutes);
        $altBody = "Halo {$name},\n\nKlik link berikut untuk reset password: {$resetUrl}\n\nLink ini berlaku selama {$expiresInMinutes} menit.\n\nJika Anda tidak meminta reset password, abaikan email ini.";

        return $this->send($to, $name, $subject, $html, $altBody);
    }

    /**
     * Kirim email kustom
     * 
     * @param string $to Email tujuan
     * @param string $name Nama penerima
     * @param string $subject Subjek email
     * @param string $html HTML body email
     * @param string|null $altBody Plain text body (optional)
     * @return bool True jika berhasil dikirim
     */
    public function sendCustom(string $to, string $name, string $subject, string $html, ?string $altBody = null): bool
    {
        return $this->send($to, $name, $subject, $html, $altBody);
    }

    /**
     * Send email
     * 
     * @param string $to Email tujuan
     * @param string $name Nama penerima
     * @param string $subject Subjek email
     * @param string $html HTML body email
     * @param string|null $altBody Plain text body (optional)
     * @return bool True jika berhasil dikirim
     */
    private function send(string $to, string $name, string $subject, string $html, ?string $altBody = null): bool
    {
        try {
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($to, $name);
            $this->mailer->Subject = $subject;
            $this->mailer->Body = $html;

            if ($altBody !== null) {
                $this->mailer->AltBody = $altBody;
            }

            return $this->mailer->send();
        } catch (Exception $e) {
            // Log error
            error_log("Email sending failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Template email OTP
     */
    private function getOtpTemplate(string $name, string $otpCode, int $expiresInMinutes): string
    {
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
        .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
        .otp-code { background: white; border: 2px dashed #667eea; padding: 20px; text-align: center; font-size: 32px; font-weight: bold; letter-spacing: 8px; margin: 20px 0; border-radius: 8px; }
        .warning { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin-top: 20px; border-radius: 4px; }
        .footer { text-align: center; margin-top: 20px; color: #888; font-size: 12px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔐 Verifikasi Email</h1>
        </div>
        <div class="content">
            <p>Halo <strong>{$name}</strong>,</p>
            <p>Terima kasih telah mendaftar. Gunakan kode berikut untuk memverifikasi email Anda:</p>
            
            <div class="otp-code">{$otpCode}</div>
            
            <p>Kode ini berlaku selama <strong>{$expiresInMinutes} menit</strong>.</p>
            
            <div class="warning">
                <strong>⚠️ Penting:</strong> Jika Anda tidak meminta kode ini, abaikan email ini. Email Anda aman.
            </div>
            
            <div class="footer">
                <p>Email ini dikirim secara otomatis. Jangan membal email ini.</p>
                <p>&copy; {$this->fromName}</p>
            </div>
        </div>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Template email notifikasi login
     */
    private function getLoginNotificationTemplate(string $name, string $ipAddress, string $userAgent, string $loginAt): string
    {
        // Parse user agent untuk info browser
        $browserInfo = $this->parseUserAgent($userAgent);

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
        .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
        .info-box { background: white; border-left: 4px solid #11998e; padding: 20px; margin: 20px 0; border-radius: 4px; }
        .info-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #eee; }
        .info-row:last-child { border-bottom: none; }
        .info-label { font-weight: bold; color: #666; }
        .warning { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin-top: 20px; border-radius: 4px; }
        .footer { text-align: center; margin-top: 20px; color: #888; font-size: 12px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔔 Notifikasi Login Baru</h1>
        </div>
        <div class="content">
            <p>Halo <strong>{$name}</strong>,</p>
            <p>Akun Anda telah berhasil login dengan detail berikut:</p>
            
            <div class="info-box">
                <div class="info-row">
                    <span class="info-label">🕐 Waktu Login:</span>
                    <span>{$loginAt}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">🌐 IP Address:</span>
                    <span>{$ipAddress}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">💻 Browser:</span>
                    <span>{$browserInfo}</span>
                </div>
            </div>
            
            <div class="warning">
                <strong>⚠️ Bukan Anda?</strong> Jika Anda tidak mengenali aktivitas ini, segera ubah password Anda dan hubungi tim support.
            </div>
            
            <div class="footer">
                <p>Email ini dikirim untuk keamanan akun Anda.</p>
                <p>&copy; {$this->fromName}</p>
            </div>
        </div>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Template email reset password
     */
    private function getPasswordResetTemplate(string $name, string $resetUrl, int $expiresInMinutes): string
    {
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
        .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
        .button { display: inline-block; background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; padding: 15px 40px; text-decoration: none; border-radius: 30px; font-weight: bold; margin: 20px 0; }
        .warning { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin-top: 20px; border-radius: 4px; }
        .footer { text-align: center; margin-top: 20px; color: #888; font-size: 12px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔑 Reset Password</h1>
        </div>
        <div class="content">
            <p>Halo <strong>{$name}</strong>,</p>
            <p>Kami menerima permintaan untuk reset password akun Anda. Klik tombol di bawah untuk membuat password baru:</p>
            
            <p style="text-align: center;">
                <a href="{$resetUrl}" class="button">Reset Password</a>
            </p>
            
            <p>Atau salin link berikut ke browser Anda:</p>
            <p style="word-break: break-all; color: #666; font-size: 14px;">{$resetUrl}</p>
            
            <p>Link ini berlaku selama <strong>{$expiresInMinutes} menit</strong>.</p>
            
            <div class="warning">
                <strong>⚠️ Bukan Anda?</strong> Jika Anda tidak meminta reset password, abaikan email ini. Password Anda tidak akan berubah.
            </div>
            
            <div class="footer">
                <p>Email ini dikirim secara otomatis. Jangan membal email ini.</p>
                <p>&copy; {$this->fromName}</p>
            </div>
        </div>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Parse user agent string untuk info browser
     */
    private function parseUserAgent(string $userAgent): string
    {
        $info = [];

        // Detect browser
        if (preg_match('/Chrome\/([0-9.]+)/', $userAgent, $matches)) {
            $info[] = 'Chrome ' . $matches[1];
        } elseif (preg_match('/Firefox\/([0-9.]+)/', $userAgent, $matches)) {
            $info[] = 'Firefox ' . $matches[1];
        } elseif (preg_match('/Safari\/([0-9.]+)/', $userAgent, $matches)) {
            $info[] = 'Safari ' . $matches[1];
        }

        // Detect OS
        if (preg_match('/Windows NT ([0-9.]+)/', $userAgent, $matches)) {
            $info[] = 'Windows';
        } elseif (preg_match('/Mac OS X ([0-9_]+)/', $userAgent, $matches)) {
            $info[] = 'macOS';
        } elseif (preg_match('/Linux/', $userAgent)) {
            $info[] = 'Linux';
        } elseif (preg_match('/Android ([0-9.]+)/', $userAgent, $matches)) {
            $info[] = 'Android ' . $matches[1];
        } elseif (preg_match('/iPhone OS ([0-9_]+)/', $userAgent, $matches)) {
            $info[] = 'iOS ' . str_replace('_', '.', $matches[1]);
        }

        return implode(' on ', $info) ?: 'Unknown Browser';
    }
}
