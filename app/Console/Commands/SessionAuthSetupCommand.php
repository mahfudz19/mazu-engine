<?php

namespace App\Console\Commands;

use App\Console\Contracts\CommandInterface;
use App\Core\Foundation\Application;
use App\Services\ConfigService;

/**
 * Command untuk setup session-based authentication dengan email/password.
 * Secara default sudah termasuk: avatar field, password min 8.
 * Opsional: --with-role untuk menambahkan field role ke users table.
 */
class SessionAuthSetupCommand implements CommandInterface
{
    public function __construct(
        private Application $app,
    ) {}

    private function getConfig(): ConfigService
    {
        return $this->app->getContainer()->resolve(ConfigService::class);
    }

    public function getName(): string
    {
        return 'auth:session-setup';
    }

    public function getDescription(): string
    {
        return 'Setup session-based authentication (email/password) dengan avatar, dan password min 8';
    }

    public function handle(array $arguments): int
    {
        echo "\n🔐 Mazu Framework - Session Auth Setup\n\n";

        // Parse arguments
        [$dbConnection, $withRole] = $this->parseArguments($arguments);

        echo "📦 Konfigurasi:\n";
        echo "   - Database: {$dbConnection}\n";
        echo "   - Role System: " . ($withRole ? 'Ya' : 'Tidak') . "\n";
        echo "   - Avatar Field: Ya (default)\n";
        echo "   - Password Min Length: 8 (default)\n\n";

        // Validate database connection
        if (!$this->validateDbConnection($dbConnection)) {
            echo "❌ Database connection '{$dbConnection}' tidak tersedia.\n";
            echo "   Pastikan sudah dikonfigurasi di config/database.php\n\n";
            return 1;
        }

        // Start setup
        echo "🚀 Memulai setup session authentication...\n\n";

        $this->setupEnvPlaceholders();
        $this->setupUserModel($dbConnection, $withRole);
        $this->setupEmailVerificationModel($dbConnection);
        $this->setupPasswordResetTokenModel($dbConnection);
        $this->setupLoginNotificationModel($dbConnection);
        $this->setupOtpGenerator();
        $this->setupEmailService();
        $this->setupAuthController($withRole);
        $this->setupAuthMiddleware($withRole);
        $this->setupRoutes($withRole);
        $this->setupViews();

        echo "\n✅ Session authentication setup selesai!\n\n";
        $this->printNextSteps($withRole);

        return 0;
    }

    private function parseArguments(array $arguments): array
    {
        $dbConnection = 'mysql';
        $withRole = false;

        foreach ($arguments as $arg) {
            if (str_starts_with($arg, '--db=')) {
                $dbConnection = substr($arg, 5);
            } elseif ($arg === '--with-role') {
                $withRole = true;
            }
        }

        return [$dbConnection, $withRole];
    }

    private function validateDbConnection(string $connection): bool
    {
        $config = $this->getConfig();
        $dbConfig = $config->get('database.connections', []);

        return isset($dbConfig[$connection]);
    }

    private function setupEnvPlaceholders(): void
    {
        echo "📝 Setup environment placeholders...\n";

        $root = __DIR__ . '/../../..';
        $envPath = $root . '/.env';
        $envExamplePath = $root . '/.env.example';

        // Create .env.example if not exists
        if (!file_exists($envExamplePath)) {
            $exampleContent = <<<'ENV'
# =============================================================================
# MAZU FRAMEWORK - ENVIRONMENT CONFIGURATION
# =============================================================================
# Hybrid Authentication System: Google OAuth + Manual Registration with OTP
# =============================================================================

# -----------------------------------------------------------------------------
# APPLICATION CONFIGURATION
# -----------------------------------------------------------------------------
APP_NAME="Mazu Framework"
APP_TIMEZONE=Asia/Makassar
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost:8000

# -----------------------------------------------------------------------------
# DATABASE CONFIGURATION
# -----------------------------------------------------------------------------
# Default database connection for the application
# Supported: mysql, pgsql, sqlite
DB_CONNECTION=mysql

# MySQL connection settings
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=mazu_framework
DB_USERNAME=root
DB_PASSWORD=

# -----------------------------------------------------------------------------
# SESSION CONFIGURATION
# -----------------------------------------------------------------------------
# Session driver: file, database, redis
SESSION_DRIVER=file

# Session lifetime in minutes
SESSION_LIFETIME=120

# -----------------------------------------------------------------------------
# EMAIL CONFIGURATION (Gmail SMTP Relay)
# -----------------------------------------------------------------------------
# Required for: OTP verification, login notifications, password reset
#
# This system uses Gmail SMTP Relay for sending transactional emails.
# To configure email sending, you need a Google Workspace account with
# an institutional domain (e.g., @yourdomain.ac.id).
#
# STEP-BY-STEP: Get Gmail App Password
# -------------------------------------
# 1. Enable Two-Factor Authentication (2FA):
#    - Go to: https://myaccount.google.com/security
#    - Enable "2-Step Verification"
#
# 2. Generate App Password:
#    - Go to: https://myaccount.google.com/apppasswords
#    - Select "Mail" and your device
#    - Click "Generate"
#    - Copy the 16-digit password (e.g., "abcd efgh ijkl mnop")
#    - Remove spaces when using: "abcdefghijklmnop"
#
# 3. Update environment variables:
#    - MAIL_USERNAME: Your institutional email
#    - MAIL_PASSWORD: The 16-digit app password (no spaces)
#    - MAIL_FROM_ADDRESS: Same as MAIL_USERNAME
#
# NOTES:
# - Use STARTTLS encryption (port 587)
# - Do NOT use your regular Gmail password
# - App passwords are only shown once - save it securely
# -----------------------------------------------------------------------------
MAIL_HOST=smtp-relay.gmail.com
MAIL_PORT=587
MAIL_USERNAME=noreply@yourdomain.com
MAIL_PASSWORD=your-16-digit-app-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@yourdomain.com
MAIL_FROM_NAME="${APP_NAME}"

# -----------------------------------------------------------------------------
# GOOGLE OAUTH 2.0 CONFIGURATION
# -----------------------------------------------------------------------------
# Required for: Google OAuth login/registration (one-click signup)
#
# This system allows users to register/login using their Google account.
# Users with institutional domain emails (e.g., @yourdomain.ac.id) can
# skip OTP verification when signing up with Google.
#
# STEP-BY-STEP: Get Google OAuth Credentials
# ------------------------------------------
# 1. Go to Google Cloud Console:
#    https://console.cloud.google.com/apis/credentials
#
# 2. Create a new OAuth 2.0 Client ID:
#    - Click "Create Credentials" > "OAuth client ID"
#    - Application type: "Web application"
#    - Name: "Mazu Framework Auth"
#
# 3. Configure Authorized Redirect URIs:
#    - Add: http://localhost:8000/auth/callback (development)
#    - Add: https://yourdomain.com/auth/callback (production)
#    - Click "Create"
#
# 4. Copy your credentials:
#    - Client ID: A long string ending with .apps.googleusercontent.com
#    - Client Secret: Click the download icon or copy from console
#
# 5. Configure Google Workspace (if using institutional domain):
#    - Go to: https://admin.google.com/ac/owl/domainwidedelegation
#    - Enable domain-wide delegation for your OAuth client
#    - Add scope: https://www.googleapis.com/auth/userinfo.email
#
# 6. Update environment variables:
#    - GOOGLE_CLIENT_ID: Your OAuth Client ID
#    - GOOGLE_CLIENT_SECRET: Your OAuth Client Secret
#    - GOOGLE_REDIRECT_URI: Your callback URL
#    - GOOGLE_ALLOWED_DOMAIN: @yourdomain.ac.id (optional, for domain restriction)
#
# NOTES:
# - Keep your Client Secret secure - never commit to version control
# - Users outside GOOGLE_ALLOWED_DOMAIN can still register manually with OTP
# - Google OAuth registration does NOT require OTP verification
# -----------------------------------------------------------------------------
GOOGLE_CLIENT_ID=your-google-client-id.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=your-google-client-secret
GOOGLE_REDIRECT_URI=http://localhost:8000/auth/callback
GOOGLE_ALLOWED_DOMAIN=@yourdomain.com

# -----------------------------------------------------------------------------
# END OF CONFIGURATION
# -----------------------------------------------------------------------------
ENV;
            file_put_contents($envExamplePath, $exampleContent);
        }

        // Create .env if not exists
        if (!file_exists($envPath)) {
            copy($envExamplePath, $envPath);
        }

        echo "   ✓ Environment files ready\n";
    }

    private function setupUserModel(string $dbConnection, bool $withRole): void
    {
        echo "📦 Setup UserModel...\n";

        $root = __DIR__ . '/../../..';
        $modelDir = $root . '/addon/Models';
        $modelPath = $modelDir . '/UserModel.php';

        if (!is_dir($modelDir)) {
            mkdir($modelDir, 0755, true);
        }

        $roleSchema = $withRole ? "
        'role' => ['type' => 'enum', 'values' => ['super-admin', 'admin', 'user'], 'nullable' => false, 'default' => 'user']" : '';

        $seedContent = $withRole ? "
        [
            'email' => 'superadmin@example.com',
            'password' => '$2y$10\$XlyT7neGvzxYcZ5v.4gsP.QFqRq7UG8nNrJF1Bk4fiP/vQUCqXlDm', // password123
            'name' => 'Super Admin',
            'avatar' => null,
            'is_active' => 1,
            'last_login_at' => null,
            'role' => 'super-admin',
            'google_id' => null,
            'avatar_url' => null,
        ],
        [
            'email' => 'admin@example.com',
            'password' => '$2y$10\$XlyT7neGvzxYcZ5v.4gsP.QFqRq7UG8nNrJF1Bk4fiP/vQUCqXlDm', // password123
            'name' => 'Admin User',
            'avatar' => null,
            'is_active' => 1,
            'last_login_at' => null,
            'role' => 'admin',
            'google_id' => null,
            'avatar_url' => null,
        ],
        [
            'email' => 'user@example.com',
            'password' => '$2y$10\$XlyT7neGvzxYcZ5v.4gsP.QFqRq7UG8nNrJF1Bk4fiP/vQUCqXlDm', // password123
            'name' => 'Regular User',
            'avatar' => null,
            'is_active' => 1,
            'last_login_at' => null,
            'role' => 'user',
            'google_id' => null,
            'avatar_url' => null,
        ]" : "
        [
            'email' => 'user1@example.com',
            'password' => '$2y$10\$XlyT7neGvzxYcZ5v.4gsP.QFqRq7UG8nNrJF1Bk4fiP/vQUCqXlDm', // password123
            'name' => 'User 1',
            'avatar' => null,
            'is_active' => 1,
            'last_login_at' => null,
            'google_id' => null,
            'avatar_url' => null,
        ],
        [
            'email' => 'user2@example.com',
            'password' => '$2y$10\$XlyT7neGvzxYcZ5v.4gsP.QFqRq7UG8nNrJF1Bk4fiP/vQUCqXlDm', // password123
            'name' => 'User 2',
            'avatar' => null,
            'is_active' => 1,
            'last_login_at' => null,
            'google_id' => null,
            'avatar_url' => null,
        ]";

        $template = <<<PHP
<?php

namespace Addon\Models;

use App\Core\Database\Model;

/**
 * User Model - Session Authentication
 *
 * Fields:
 * - id: Primary key
 * - email: Unique email (for login)
 * - password: Hashed password (bcrypt)
 * - name: User full name
 * - avatar: Profile picture URL (nullable)
 * - is_active: Account status
 * - last_login_at: Last login timestamp
 * - role: User role (if enabled)
 */
class UserModel extends Model
{
    protected ?string \$connection = '{$dbConnection}';
    protected string \$table = 'users';
    protected bool \$timestamps = true;

    protected array \$schema = [
        'id' => ['type' => 'id', 'primary' => true, 'auto_increment' => true],
        'email' => ['type' => 'string', 'nullable' => false, 'unique' => true],
        'password' => ['type' => 'string', 'nullable' => true],
        'name' => ['type' => 'string', 'nullable' => true],
        'avatar' => ['type' => 'string', 'nullable' => true],
        'is_active' => ['type' => 'boolean', 'nullable' => false, 'default' => true],
        'google_id' => ['type' => 'string', 'nullable' => true, 'unique' => true],
        'avatar_url' => ['type' => 'string', 'nullable' => true],
        'last_login_at' => ['type' => 'datetime', 'nullable' => true],{$roleSchema}
    ];

    protected array \$seed = [{$seedContent}
    ];

    /**
     * Get all users
     */
    public function all(): array
    {
        \$stmt = \$this->getDb()->prepare("SELECT * FROM {\$this->table}");
        \$stmt->execute();
        return \$stmt->fetchAll();
    }

    /**
     * Find user by ID
     */
    public function find(string|int \$id): ?array
    {
        \$stmt = \$this->getDb()->prepare("SELECT * FROM {\$this->table} WHERE id = :id LIMIT 1");
        \$stmt->execute(['id' => \$id]);
        \$row = \$stmt->fetch();

        return \$row === false ? null : \$row;
    }

    /**
     * Find user by email
     */
    public function findByEmail(string \$email): ?array
    {
        \$stmt = \$this->getDb()->prepare("SELECT * FROM {\$this->table} WHERE email = :email LIMIT 1");
        \$stmt->execute(['email' => \$email]);
        \$row = \$stmt->fetch();

        return \$row === false ? null : \$row;
    }

    /**
     * Create new user
     *
     * @param array \$data User data (email, password, name, avatar, role, etc.)
     * @return int Last insert ID on success
     * @throws \PDOException On database error
     * @throws \Exception On unique constraint violation (email already exists)
     */
    public function create(array \$data): int
    {
        try {
            // Filter data based on schema
            \$validData = [];
            foreach (\$data as \$key => \$value) {
                if (isset(\$this->schema[\$key]) && \$key !== 'id') {
                    \$validData[\$key] = \$value;
                }
            }

            // Build columns and placeholders
            \$columns = implode(', ', array_keys(\$validData));
            \$placeholders = ':' . implode(', :', array_keys(\$validData));

            // Build INSERT query
            \$sql = "INSERT INTO {\$this->table} (\$columns) VALUES (\$placeholders)";

            // Execute query
            if (\$this->getDb()->query(\$sql, \$validData)) {
                return (int) \$this->getDb()->lastInsertId();
            }

            throw new \PDOException('Gagal membuat user baru');
        } catch (\PDOException \$e) {
            // Check for duplicate entry (email already exists)
            if (\$e->getCode() === '23000' || str_contains(\$e->getMessage(), 'Duplicate entry')) {
                throw new \Exception('Email sudah terdaftar');
            }
            throw \$e;
        }
    }

    /**
     * Update user by ID
     */
    public function updateById(string|int \$id, array \$data): bool
    {
        if (empty(\$data)) {
            return false;
        }

        // Auto-update updated_at if not provided
        if (!isset(\$data['updated_at'])) {
            \$data['updated_at'] = date('Y-m-d H:i:s');
        }

        \$setParts = [];
        foreach (\$data as \$column => \$value) {
            \$setParts[] = "{\$column} = :{\$column}";
        }

        \$sql = "UPDATE {\$this->table} SET " . implode(', ', \$setParts) . " WHERE id = :id";
        \$data['id'] = \$id;

        return \$this->getDb()->query(\$sql, \$data);
    }

    /**
     * Delete user by ID
     */
    public function deleteById(string|int \$id): bool
    {
        \$sql = "DELETE FROM {\$this->table} WHERE id = :id";
        return \$this->getDb()->query(\$sql, ['id' => \$id]);
    }

    /**
     * Update last login timestamp
     */
    public function updateLastLogin(string|int \$id): bool
    {
        return \$this->updateById(\$id, ['last_login_at' => date('Y-m-d H:i:s')]);
    }

    /**
     * Check if user has specific role (if role system enabled)
     */
    public function hasRole(array \$user, string \$role): bool
    {
        if (isset(\$this->schema['role']) && isset(\$user['role'])) {
            return \$user['role'] === \$role;
        }
        return false;
    }

}
PHP;

        file_put_contents($modelPath, $template);
        echo "   ✓ UserModel created\n";
    }

    private function setupOtpGenerator(): void
    {
        echo "🔢 Setup OtpGenerator...\n";

        $root = __DIR__ . '/../../..';
        $helpersDir = $root . '/addon/Helpers';
        $helperPath = $helpersDir . '/OtpGenerator.php';

        if (!is_dir($helpersDir)) {
            mkdir($helpersDir, 0755, true);
        }

        $template = <<<'PHP'
<?php

namespace Addon\Helpers;

/**
 * OTP Generator Helper
 *
 * Menghasilkan dan memvalidasi kode OTP (One-Time Password).
 * OTP berupa 6 digit angka acak.
 */
class OtpGenerator
{
    /**
     * Generate OTP 6 digit
     *
     * @return string 6-digit numeric code
     */
    public static function generate(): string
    {
        $otp = '';
        for ($i = 0; $i < 6; $i++) {
            $otp .= random_int(0, 9);
        }

        return $otp;
    }

    /**
     * Generate OTP dengan format tertentu (misal: tanpa angka 0 di depan)
     *
     * @param bool $noLeadingZero Hindari angka 0 di depan
     * @return string 6-digit numeric code
     */
    public static function generateFormatted(bool $noLeadingZero = true): string
    {
        if ($noLeadingZero) {
            $otp = (string) random_int(1, 9);
            for ($i = 1; $i < 6; $i++) {
                $otp .= random_int(0, 9);
            }
            return $otp;
        }

        return self::generate();
    }

    /**
     * Validasi format OTP (harus 6 digit angka)
     *
     * @param string $otp OTP yang divalidasi
     * @return bool True jika format valid
     */
    public static function isValidFormat(string $otp): bool
    {
        return preg_match('/^\d{6}$/', $otp) === 1;
    }

    /**
     * Mask OTP untuk ditampilkan (misal: 12***6)
     *
     * @param string $otp OTP yang akan di-mask
     * @param int $visibleAtStart Jumlah digit yang terlihat di awal
     * @param int $visibleAtEnd Jumlah digit yang terlihat di akhir
     * @return string Masked OTP
     */
    public static function mask(string $otp, int $visibleAtStart = 1, int $visibleAtEnd = 1): string
    {
        if (strlen($otp) !== 6) {
            return '******';
        }

        $masked = str_repeat('*', 6 - $visibleAtStart - $visibleAtEnd);

        return substr($otp, 0, $visibleAtStart) . $masked . substr($otp, -1, $visibleAtEnd);
    }
}
PHP;

        file_put_contents($helperPath, $template);
        echo "   ✓ OtpGenerator created\n";
    }

    private function setupEmailService(): void
    {
        echo "📧 Setup EmailService...\n";

        $root = __DIR__ . '/../../..';
        $servicesDir = $root . '/addon/Services';
        $servicePath = $servicesDir . '/EmailService.php';

        if (!is_dir($servicesDir)) {
            mkdir($servicesDir, 0755, true);
        }

        $template = <<<'PHP'
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
PHP;

        file_put_contents($servicePath, $template);
        echo "   ✓ EmailService created\n";
    }

    private function setupEmailVerificationModel(string $dbConnection): void
    {
        echo "📧 Setup EmailVerificationModel...\n";

        $root = __DIR__ . '/../../..';
        $modelsDir = $root . '/addon/Models';
        $modelPath = $modelsDir . '/EmailVerificationModel.php';

        if (!is_dir($modelsDir)) {
            mkdir($modelsDir, 0755, true);
        }

        $template = <<<'PHP'
<?php

namespace Addon\Models;

use App\Core\Database\Model;

class EmailVerificationModel extends Model
{
    protected ?string $connection = 'mysql';
    protected string $table = 'email_verifications';
    protected bool $timestamps = true;

    protected array $schema = [
        'id' => ['type' => 'id', 'primary' => true, 'auto_increment' => true],
        'user_id' => ['type' => 'bigint', 'foreign' => 'users.id', 'unsigned' => true],
        'email' => ['type' => 'varchar', 'length' => 255],
        'otp_code' => ['type' => 'varchar', 'length' => 6],
        'expires_at' => ['type' => 'datetime'],
        'used_at' => ['type' => 'datetime', 'nullable' => true],
    ];

    protected array $seed = [];

    /**
     * Buat OTP verification baru untuk user
     */
    public function createOtp(int $userId, string $email, string $otpCode, int $expiresInMinutes = 15): int
    {
        $expiresAt = date('Y-m-d H:i:s', strtotime("+{$expiresInMinutes} minutes"));

        $this->db->query(
            "INSERT INTO {$this->table} (user_id, email, otp_code, expires_at) VALUES (:user_id, :email, :otp_code, :expires_at)",
            [
                ':user_id' => $userId,
                ':email' => $email,
                ':otp_code' => $otpCode,
                ':expires_at' => $expiresAt,
            ]
        );

        return $this->db->lastInsertId();
    }

    /**
     * Verifikasi OTP code
     *
     * @return array ['valid' => bool, 'message' => string]
     */
    public function verifyOtp(int $userId, string $otpCode): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM {$this->table}
             WHERE user_id = :user_id
             AND otp_code = :otp_code
             AND used_at IS NULL
             AND expires_at > NOW()
             ORDER BY created_at DESC LIMIT 1"
        );
        $stmt->execute([
            ':user_id' => $userId,
            ':otp_code' => $otpCode,
        ]);

        $verification = $stmt->fetch();

        if (!$verification) {
            return ['valid' => false, 'message' => 'Kode OTP tidak valid atau telah kedaluwarsa'];
        }

        // Mark as used
        $this->markAsUsed($verification['id']);

        return ['valid' => true, 'message' => 'Email berhasil diverifikasi'];
    }

    /**
     * Tandai OTP sebagai sudah digunakan
     */
    private function markAsUsed(int $id): bool
    {
        return $this->db->query(
            "UPDATE {$this->table} SET used_at = NOW() WHERE id = :id",
            [':id' => $id]
        );
    }

    /**
     * Hapus OTP yang sudah kedaluwarsa untuk user
     */
    public function deleteExpired(int $userId): int
    {
        $stmt = $this->db->prepare(
            "DELETE FROM {$this->table} WHERE user_id = :user_id AND expires_at < NOW()"
        );
        $stmt->execute([':user_id' => $userId]);

        return $stmt->rowCount();
    }

    /**
     * Cek apakah user masih memiliki OTP yang aktif
     */
    public function hasActiveOtp(int $userId): bool
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) as count FROM {$this->table}
             WHERE user_id = :user_id
             AND used_at IS NULL
             AND expires_at > NOW()"
        );
        $stmt->execute([':user_id' => $userId]);

        $result = $stmt->fetch();
        return $result['count'] > 0;
    }

    /**
     * Invalidate semua OTP untuk user (misal setelah berhasil verifikasi)
     */
    public function invalidateAll(int $userId): bool
    {
        return $this->db->query(
            "UPDATE {$this->table} SET used_at = NOW() WHERE user_id = :user_id AND used_at IS NULL",
            [':user_id' => $userId]
        );
    }
}
PHP;

        file_put_contents($modelPath, $template);
        echo "   ✓ EmailVerificationModel created\n";
    }

    private function setupLoginNotificationModel(string $dbConnection): void
    {
        echo "🔔 Setup LoginNotificationModel...\n";

        $root = __DIR__ . '/../../..';
        $modelsDir = $root . '/addon/Models';
        $modelPath = $modelsDir . '/LoginNotificationModel.php';

        if (!is_dir($modelsDir)) {
            mkdir($modelsDir, 0755, true);
        }

        $template = <<<'PHP'
<?php

namespace Addon\Models;

use App\Core\Database\Model;

/**
 * Login Notification Model
 *
 * Mencatat setiap login user dan mengirim notifikasi email.
 * Berguna untuk keamanan - user akan tahu jika ada login mencurigakan.
 */
class LoginNotificationModel extends Model
{
    protected ?string $connection = 'mysql';
    protected string $table = 'login_notifications';
    protected bool $timestamps = true;

    protected array $schema = [
        'id' => ['type' => 'id', 'primary' => true, 'auto_increment' => true],
        'user_id' => ['type' => 'bigint', 'foreign' => 'users.id', 'unsigned' => true],
        'email_sent_to' => ['type' => 'varchar', 'length' => 255],
        'ip_address' => ['type' => 'varchar', 'length' => 45],
        'user_agent' => ['type' => 'text'],
        'login_at' => ['type' => 'datetime'],
    ];

    protected array $seed = [];

    /**
     * Catat login baru dan kirim notifikasi
     *
     * @param int $userId User ID
     * @param string $email Email tujuan notifikasi
     * @param string|null $ipAddress IP address user
     * @param string|null $userAgent User agent browser
     * @param string|null $loginAt Timestamp login (default: now)
     * @return int Last insert ID
     */
    public function logLogin(
        int $userId,
        string $email,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?string $loginAt = null
    ): int {
        $loginAt = $loginAt ?? date('Y-m-d H:i:s');

        $this->db->query(
            "INSERT INTO {$this->table} (user_id, email_sent_to, ip_address, user_agent, login_at)
             VALUES (:user_id, :email_sent_to, :ip_address, :user_agent, :login_at)",
            [
                ':user_id' => $userId,
                ':email_sent_to' => $email,
                ':ip_address' => $ipAddress,
                ':user_agent' => $userAgent,
                ':login_at' => $loginAt,
            ]
        );

        return (int) $this->db->lastInsertId();
    }

    /**
     * Dapatkan riwayat login user
     *
     * @param int $userId User ID
     * @param int $limit Jumlah record maksimal
     * @return array[] List of login records
     */
    public function getUserLogins(int $userId, int $limit = 10): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM {$this->table}
             WHERE user_id = :user_id
             ORDER BY login_at DESC
             LIMIT :limit"
        );
        $stmt->bindValue(':user_id', $userId, \PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Dapatkan login terakhir user
     *
     * @param int $userId User ID
     * @return array|null Last login record or null
     */
    public function getLastLogin(int $userId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM {$this->table}
             WHERE user_id = :user_id
             ORDER BY login_at DESC
             LIMIT 1"
        );
        $stmt->execute([':user_id' => $userId]);

        $result = $stmt->fetch();
        return $result === false ? null : $result;
    }

    /**
     * Dapatkan login mencurigakan (IP berbeda dari biasanya)
     *
     * @param int $userId User ID
     * @param int $limit Jumlah record maksimal
     * @return array[] List of suspicious login records
     */
    public function getSuspiciousLogins(int $userId, int $limit = 5): array
    {
        // Get most common IP for this user
        $stmt = $this->db->prepare(
            "SELECT ip_address, COUNT(*) as count FROM {$this->table}
             WHERE user_id = :user_id
             GROUP BY ip_address
             ORDER BY count DESC
             LIMIT 1"
        );
        $stmt->execute([':user_id' => $userId]);
        $commonIp = $stmt->fetch();

        if (!$commonIp || !$commonIp['ip_address']) {
            return [];
        }

        // Get logins from different IPs
        $stmt = $this->db->prepare(
            "SELECT * FROM {$this->table}
             WHERE user_id = :user_id
             AND ip_address != :ip_address
             ORDER BY login_at DESC
             LIMIT :limit"
        );
        $stmt->bindValue(':user_id', $userId, \PDO::PARAM_INT);
        $stmt->bindValue(':ip_address', $commonIp['ip_address']);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Hapus log lama (lebih dari 90 hari)
     *
     * @return int Jumlah record yang dihapus
     */
    public function deleteOldLogs(): int
    {
        $stmt = $this->db->prepare(
            "DELETE FROM {$this->table} WHERE login_at < DATE_SUB(NOW(), INTERVAL 90 DAY)"
        );
        $stmt->execute();

        return $stmt->rowCount();
    }
}
PHP;

        file_put_contents($modelPath, $template);
        echo "   ✓ LoginNotificationModel created\n";
    }

    private function setupPasswordResetTokenModel(string $dbConnection): void
    {
        echo "🔑 Setup PasswordResetTokenModel...\n";

        $root = __DIR__ . '/../../..';
        $modelsDir = $root . '/addon/Models';
        $modelPath = $modelsDir . '/PasswordResetTokenModel.php';

        if (!is_dir($modelsDir)) {
            mkdir($modelsDir, 0755, true);
        }

        $template = <<<'PHP'
<?php

namespace Addon\Models;

use App\Core\Database\Model;

/**
 * Password Reset Token Model
 *
 * Mengelola token untuk password reset.
 * Token berlaku selama 1 jam dan hanya bisa dipakai sekali.
 */
class PasswordResetTokenModel extends Model
{
    protected ?string $connection = 'mysql';
    protected string $table = 'password_reset_tokens';
    protected bool $timestamps = true;

    protected array $schema = [
        'id' => ['type' => 'id', 'primary' => true, 'auto_increment' => true],
        'user_id' => ['type' => 'bigint', 'foreign' => 'users.id', 'unsigned' => true],
        'token' => ['type' => 'varchar', 'length' => 64],
        'expires_at' => ['type' => 'datetime'],
        'used_at' => ['type' => 'datetime', 'nullable' => true],
    ];

    protected array $seed = [];

    /**
     * Buat token reset password baru
     *
     * @param int $userId User ID
     * @param string $token 64-character random token
     * @param int $expiresInMinutes Durasi kedaluwarsa (default: 60 menit)
     * @return int Last insert ID
     */
    public function createToken(int $userId, string $token, int $expiresInMinutes = 60): int
    {
        $expiresAt = date('Y-m-d H:i:s', strtotime("+{$expiresInMinutes} minutes"));

        $this->db->query(
            "INSERT INTO {$this->table} (user_id, token, expires_at) VALUES (:user_id, :token, :expires_at)",
            [
                ':user_id' => $userId,
                ':token' => $token,
                ':expires_at' => $expiresAt,
            ]
        );

        return (int) $this->db->lastInsertId();
    }

    /**
     * Temukan token yang valid
     *
     * @param string $token Token yang dicari
     * @return array|null Data token jika valid, null jika tidak
     */
    public function findValidToken(string $token): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM {$this->table}
             WHERE token = :token
             AND used_at IS NULL
             AND expires_at > NOW()
             ORDER BY created_at DESC LIMIT 1"
        );
        $stmt->execute([':token' => $token]);

        $result = $stmt->fetch();
        return $result === false ? null : $result;
    }

    /**
     * Tandai token sebagai sudah digunakan
     *
     * @param int $id Token ID
     * @return bool Success status
     */
    public function markAsUsed(int $id): bool
    {
        return $this->db->query(
            "UPDATE {$this->table} SET used_at = NOW() WHERE id = :id",
            [':id' => $id]
        );
    }

    /**
     * Invalidate semua token untuk user (misal setelah reset password berhasil)
     *
     * @param int $userId User ID
     * @return bool Success status
     */
    public function invalidateAll(int $userId): bool
    {
        return $this->db->query(
            "UPDATE {$this->table} SET used_at = NOW() WHERE user_id = :user_id AND used_at IS NULL",
            [':user_id' => $userId]
        );
    }

    /**
     * Hapus token yang sudah kedaluwarsa
     *
     * @return int Jumlah token yang dihapus
     */
    public function deleteExpired(): int
    {
        $stmt = $this->db->prepare(
            "DELETE FROM {$this->table} WHERE expires_at < NOW()"
        );
        $stmt->execute();

        return $stmt->rowCount();
    }

    /**
     * Generate random token 64 karakter
     *
     * @return string 64-character random hex string
     */
    public function generateToken(): string
    {
        return bin2hex(random_bytes(32));
    }
}
PHP;

        file_put_contents($modelPath, $template);
        echo "   ✓ PasswordResetTokenModel created\n";
    }

    private function setupAuthController(bool $withRole): void
    {
        echo "🎮 Setup AuthController...\n";

        $root = __DIR__ . '/../../..';
        $controllerDir = $root . '/addon/Controllers';
        $controllerPath = $controllerDir . '/AuthController.php';

        if (!is_dir($controllerDir)) {
            mkdir($controllerDir, 0755, true);
        }

        $roleHandling = $withRole ? <<<'PHP'
// Role handling
        $role = $request->input('role', 'user');
        if (!in_array($role, ['super-admin', 'admin', 'user'])) {
            $role = 'user';
        }
PHP
            : <<<'PHP'
PHP;

        $roleUserData = $withRole ? <<<'PHP'
// Add role to user data
        $userData['role'] = $role;
PHP
            : <<<'PHP'
PHP;

        $roleGoogleUserData = $withRole ? <<<'PHP'
// Add role to Google OAuth user data
        $userData['role'] = 'user';
PHP
            : <<<'PHP'
PHP;

        $template = <<<PHP
<?php

namespace Addon\Controllers;

use Addon\Models\UserModel;
use Addon\Models\EmailVerificationModel;
use Addon\Models\PasswordResetTokenModel;
use Addon\Models\LoginNotificationModel;
use Addon\Services\EmailService;
use Addon\Helpers\OtpGenerator;
use App\Services\SessionService;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\View\View;
use App\Core\Http\RedirectResponse;
use App\Exceptions\HttpException;
use Exception;

/**
 * Authentication Controller - Hybrid Auth System
 *
 * Handles:
 * - Login (Email/Password + Google OAuth)
 * - Register (Manual + Google OAuth)
 * - OTP Verification
 * - Logout
 * - Password reset via email
 * - Login notifications
 */
class AuthController
{
    public function __construct(
        private UserModel \$users,
        private SessionService \$session,
        private EmailVerificationModel \$emailVerifications,
        private PasswordResetTokenModel \$passwordResetTokens,
        private LoginNotificationModel \$loginNotifications,
        private EmailService \$emailService,
    ) {}

    /**
     * Minimum password length
     */
    private const MIN_PASSWORD_LENGTH = 8;

    /**
     * Hash password menggunakan bcrypt
     *
     * @param string \$password Plain text password
     * @return string Hashed password
     */
    private function hashPassword(string \$password): string
    {
        return password_hash(\$password, PASSWORD_BCRYPT, ['cost' => 10]);
    }

    /**
     * Verify password against hash
     *
     * @param string \$password Plain text password
     * @param string \$hash Hashed password
     * @return bool True if password matches
     */
    private function verifyPassword(string \$password, string \$hash): bool
    {
        return password_verify(\$password, \$hash);
    }

    /**
     * Validate password strength
     *
     * @param string \$password Password to validate
     * @return array{valid: bool, errors: array<string>} Validation result
     */
    private function validatePassword(string \$password): array
    {
        \$errors = [];

        if (strlen(\$password) < self::MIN_PASSWORD_LENGTH) {
            \$errors[] = "Password minimal " . self::MIN_PASSWORD_LENGTH . " karakter";
        }

        return [
            'valid' => empty(\$errors),
            'errors' => \$errors,
        ];
    }

    /**
     * Check if user is logged in
     */
    private function check(): bool
    {
        \$userId = \$this->session->get('auth.user_id');
        return \$userId !== null;
    }

    /**
     * Get authenticated user (returns array)
     */
    private function user(): ?array
    {
        \$userId = \$this->session->get('auth.user_id');

        if (\$userId === null) {
            return null;
        }

        return \$this->users->find(\$userId);
    }

    /**
     * Login user dan simpan session
     */
    private function loginSession(array \$user): void
    {
        \$this->session->set('auth.user_id', \$user['id']);
        \$this->session->set('auth.user_email', \$user['email']);
        \$this->session->set('auth.user_name', \$user['name']);
        \$this->session->set('auth.user_avatar', \$user['avatar'] ?? null);

        if (isset(\$user['role'])) {
            \$this->session->set('auth.user_role', \$user['role']);
        }
        \$this->session->set('is_logged_in', true);
    }

    /**
     * Logout user
     */
    private function logoutSession(): void
    {
        \$this->session->destroy();
    }

    /**
     * Show login form
     */
    public function showLogin(Request \$request, Response \$response): View | RedirectResponse
    {
        // If already logged in, redirect to dashboard
        if (\$this->check()) {
            return \$response->redirect('/dashboard');
        }

        return \$response->renderPage([], ['path' => '/login', 'meta' => ['title' => 'Login | ' . env('APP_NAME')]]);
    }

    /**
     * Process login (Email/Password)
     */
    public function login(Request \$request, Response \$response): View | RedirectResponse
    {
        \$email = \$request->input('email');
        \$password = \$request->input('password');

        if (!\$email || !\$password) {
            return \$response->redirect('/login?error=Email+dan+password+harus+diisi');
        }

        // Find user by email
        \$user = \$this->users->findByEmail(\$email);

        if (!\$user) {
            return \$response->redirect('/login?error=Email+tidak+ditemukan');
        }

        // If user has google_id, they registered via Google - no password set
        if (!empty(\$user['google_id'])) {
            return \$response->redirect('/login?error=Akun+terdaftar+dengan+Google.+Silakan+login+menggunakan+Google');
        }

        // Verify password
        if (!\$this->verifyPassword(\$password, \$user['password'])) {
            return \$response->redirect('/login?error=Password+salah');
        }

        // Check if user is active
        if (!\$user['is_active']) {
            // User not active - resend OTP and redirect to verify
            \$this->sendOtpToUser(\$user['id'], \$user['email']);
            return \$response->redirect('/verify-otp?email=' . urlencode(\$user['email']) . '&info=Akun+belum+terverifikasi.+Silakan+verifikasi+email+Anda');
        }

        // Update last login
        \$this->users->updateLastLogin(\$user['id']);

        // Login successful - save session
        \$this->loginSession(\$user);

        // Send login notification email
        \$this->sendLoginNotification(\$user);

        return \$response->redirect('/dashboard');
    }

    /**
     * Show register form
     */
    public function showRegister(Request \$request, Response \$response): View | RedirectResponse
    {
        // If already logged in, redirect to dashboard
        if (\$this->check()) {
            return \$response->redirect('/dashboard');
        }

        return \$response->renderPage([], ['path' => '/register', 'meta' => ['title' => 'Register | ' . env('APP_NAME')]]);
    }

    /**
     * Process register (Manual with OTP verification)
     */
    public function register(Request \$request, Response \$response): View | RedirectResponse
    {
        \$email = \$request->input('email');
        \$password = \$request->input('password');
        \$name = \$request->input('name');
        \$passwordConfirmation = \$request->input('password_confirmation');

        // Validation
        if (!\$email || !\$password || !\$name) {
            return \$response->redirect('/register?error=Semua+field+harus+diisi');
        }

        if (\$password !== \$passwordConfirmation) {
            return \$response->redirect('/register?error=Password+konfirmasi+tidak+cocok');
        }

        // Validate password strength
        \$passwordValidation = \$this->validatePassword(\$password);
        if (!\$passwordValidation['valid']) {
            return \$response->redirect('/register?error=' . urlencode(implode(', ', \$passwordValidation['errors'])));
        }

        {$roleHandling}

        // Check if email already exists
        \$existingUser = \$this->users->findByEmail(\$email);
        if (\$existingUser) {
            return \$response->redirect('/register?error=Email+sudah+terdaftar');
        }

        // Prepare user data (is_active = false, waiting for OTP verification)
        \$userData = [
            'email' => \$email,
            'password' => \$this->hashPassword(\$password),
            'name' => \$name,
            'avatar' => null,
            'is_active' => 0, // Not active until OTP verified
        ];

        {$roleUserData}

        // Create user with try-catch
        try {
            \$userId = \$this->users->create(\$userData);
            \$newUser = \$this->users->find(\$userId);

            if (!\$newUser) {
                throw new Exception('Gagal membuat user');
            }

            // Send OTP to user's email
            \$otpCode = OtpGenerator::generate();
            \$this->emailVerifications->createOtp(\$userId, \$email, \$otpCode, 15);

            // Send email with OTP
            \$this->emailService->sendOtpVerification(\$email, \$name, \$otpCode, 15);

            // Store user ID in session for OTP verification
            \$this->session->set('auth.pending_user_id', \$userId);
            \$this->session->set('auth.pending_user_email', \$email);

            // Redirect to OTP sent page
            return \$response->redirect('/otp-sent?email=' . urlencode(\$email));
        } catch (\\Exception \$e) {
            return \$response->redirect('/register?error=' . urlencode(\$e->getMessage()));
        }
    }

    /**
     * Logout
     */
    public function logout(Request \$request, Response \$response): View | RedirectResponse
    {
        \$this->logoutSession();
        return \$response->redirect('/login');
    }

    /**
     * Show OTP verification page
     */
    public function showVerifyOtp(Request \$request, Response \$response): View | RedirectResponse
    {
        \$email = \$request->query['email'] ?? null;
        \$info = \$request->query['info'] ?? null;

        if (!\$email) {
            return \$response->redirect('/register');
        }

        return \$response->renderPage([
            'email' => \$email,
            'info' => \$info,
        ], ['path' => '/verify-otp', 'meta' => ['title' => 'Verifikasi Email | ' . env('APP_NAME')]]);
    }

    /**
     * Process OTP verification
     */
    public function verifyOtp(Request \$request, Response \$response): View | RedirectResponse
    {
        \$email = \$request->input('email');
        \$otpCode = \$request->input('otp_code');

        if (!\$email || !\$otpCode) {
            return \$response->redirect('/verify-otp?email=' . urlencode(\$email ?? '') . '&error=Email+dan+kode+OTP+harus+diisi');
        }

        // Find user by email
        \$user = \$this->users->findByEmail(\$email);

        if (!\$user) {
            return \$response->redirect('/verify-otp?email=' . urlencode(\$email) . '&error=User+tidak+ditemukan');
        }

        // Verify OTP
        \$result = \$this->emailVerifications->verifyOtp(\$user['id'], \$otpCode);

        if (!\$result['valid']) {
            return \$response->redirect('/verify-otp?email=' . urlencode(\$email) . '&error=' . urlencode(\$result['message']));
        }

        // Activate user account
        \$this->users->updateById(\$user['id'], [
            'is_active' => 1,
        ]);

        // Invalidate all other OTPs
        \$this->emailVerifications->invalidateAll(\$user['id']);

        // Auto-login after verification
        \$this->loginSession(\$user);

        // Send login notification
        \$this->sendLoginNotification(\$user);

        return \$response->redirect('/dashboard?success=Email+berhasil+diverifikasi');
    }

    /**
     * Resend OTP
     */
    public function resendOtp(Request \$request, Response \$response): View | RedirectResponse
    {
        \$email = \$request->query['email'] ?? null;

        if (!\$email) {
            return \$response->redirect('/register');
        }

        \$user = \$this->users->findByEmail(\$email);

        if (!\$user) {
            return \$response->redirect('/register?error=User+tidak+ditemukan');
        }

        // Check if user already verified
        if (\$user['is_active']) {
            return \$response->redirect('/login?info=Akun+sudah+aktif.+Silakan+login');
        }

        // Send new OTP
        \$this->sendOtpToUser(\$user['id'], \$user['email']);

        return \$response->redirect('/otp-sent?email=' . urlencode(\$email) . '&success=Kode+OTP+telah+dikirim+kembali');
    }

    /**
     * Show OTP sent page
     */
    public function showOtpSent(Request \$request, Response \$response): View | RedirectResponse
    {
        \$email = \$request->query['email'] ?? null;
        \$success = \$request->query['success'] ?? null;

        if (!\$email) {
            return \$response->redirect('/register');
        }

        return \$response->renderPage([
            'email' => \$email,
            'success' => \$success,
        ], ['path' => '/otp-sent', 'meta' => ['title' => 'Email Terkirim | ' . env('APP_NAME')]]);
    }

    /**
     * Google OAuth callback
     */
    public function googleCallback(Request \$request, Response \$response): View | RedirectResponse
    {
        \$code = \$request->query['code'] ?? null;

        if (!\$code) {
            return \$response->redirect('/login?error=Authorization+code+tidak+ditemukan');
        }

        try {
            // Exchange code for token
            \$client = new \\Google_Client();
            \$client->setClientId(env('GOOGLE_CLIENT_ID'));
            \$client->setClientSecret(env('GOOGLE_CLIENT_SECRET'));
            \$client->setRedirectUri(env('GOOGLE_REDIRECT_URI'));

            \$token = \$client->fetchAccessTokenWithAuthCode(\$code);

            if (isset(\$token['error'])) {
                throw new Exception(\$token['error']);
            }

            // Get user info from Google
            \$client->setAccessToken(\$token);
            \$oauth2 = new \\Google_Service_Oauth2(\$client);
            \$googleUser = \$oauth2->userinfo->get();

            // Check if user exists by google_id
            \$existingUser = \$this->users->findByEmail(\$googleUser->email);

            if (\$existingUser) {
                // User exists - check if already linked to Google
                if (empty(\$existingUser['google_id'])) {
                    // Link Google ID to existing user
                    \$this->users->updateById(\$existingUser['id'], [
                        'google_id' => \$googleUser->id,
                        'avatar_url' => \$googleUser->picture,
                    ]);
                }

                // Login existing user
                \$this->loginSession(\$existingUser);
                \$this->sendLoginNotification(\$existingUser);
            } else {
                // Create new user from Google (open to all domains)
                // Note: Users registered via Google OAuth cannot use password login
                // unless they set a password via password reset feature
                \$userData = [
                    'email' => \$googleUser->email,
                    'password' => null, // No password for Google users
                    'name' => \$googleUser->name,
                    'avatar' => \$googleUser->picture,
                    'is_active' => 1, // Google verified email, so auto-activate
                    'google_id' => \$googleUser->id,
                    'avatar_url' => \$googleUser->picture,
                ];

                {$roleGoogleUserData}

                \$userId = \$this->users->create(\$userData);
                \$newUser = \$this->users->find(\$userId);

                // Auto-login
                \$this->loginSession(\$newUser);
                \$this->sendLoginNotification(\$newUser);
            }

            return \$response->redirect('/dashboard?success=Login+berhasil+dengan+Google');
        } catch (Exception \$e) {
            return \$response->redirect('/login?error=' . urlencode('Google OAuth error: ' . \$e->getMessage()));
        }
    }

    /**
     * Send OTP to user
     */
    private function sendOtpToUser(int \$userId, string \$email): void
    {
        // Invalidate old OTPs
        \$this->emailVerifications->invalidateAll(\$userId);

        // Generate new OTP
        \$otpCode = OtpGenerator::generate();

        // Create OTP record
        \$this->emailVerifications->createOtp(\$userId, \$email, \$otpCode, 15);

        // Get user name
        \$user = \$this->users->find(\$userId);
        \$name = \$user['name'] ?? \$email;

        // Send email
        \$this->emailService->sendOtpVerification(\$email, \$name, \$otpCode, 15);
    }

    /**
     * Send login notification email
     */
    private function sendLoginNotification(array \$user): void
    {
        \$ipAddress = \$_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        \$userAgent = \$_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        \$loginAt = date('Y-m-d H:i:s');

        // Log to database
        \$this->loginNotifications->logLogin(
            \$user['id'],
            \$user['email'],
            \$ipAddress,
            \$userAgent,
            \$loginAt
        );

        // Send notification email
        \$this->emailService->sendLoginNotification(
            \$user['email'],
            \$user['name'] ?? \$user['email'],
            \$ipAddress,
            \$userAgent,
            \$loginAt
        );
    }

    /**
     * Show forgot password form
     */
    public function showForgotPassword(Request \$request, Response \$response): View | RedirectResponse
    {
        return \$response->renderPage([], ['path' => '/password/forgot', 'meta' => ['title' => 'Lupa Password | ' . env('APP_NAME')]]);
    }

    /**
     * Send password reset link
     */
    public function sendResetLink(Request \$request, Response \$response): View | RedirectResponse
    {
        \$email = \$request->input('email');

        if (!\$email) {
            return \$response->redirect('/password/forgot?Email+harus+diisi');
        }

        \$user = \$this->users->findByEmail(\$email);

        if (!\$user) {
            // For security, show same success message even if email not found
            return \$response->renderPage(
                ['message' => 'Jika email terdaftar, link reset password telah dikirim'],
                ['path' => '/password/forgot', 'meta' => ['title' => 'Lupa Password | ' . env('APP_NAME')]]
            );
        }

        // If user registered with Google, they don't have password
        if (!empty(\$user['google_id'])) {
            return \$response->redirect('/password/forgot?Akun+Anda+terdaftar+dengan+Google.+Silakan+reset+password+melalui+Google');
        }

        // Generate reset token
        \$token = \$this->passwordResetTokens->generateToken();
        \$this->passwordResetTokens->createToken(\$user['id'], \$token, 60);

        // Build reset URL
        \$resetUrl = rtrim(env('APP_URL', 'http://localhost:8000'), '/') . '/password/reset'  . '?email=' . urlencode(\$email) . '&token=' . \$token;

        // Send email
        \$this->emailService->sendPasswordReset(
            \$user['email'],
            \$user['name'] ?? \$user['email'],
            \$resetUrl,
            60
        );

        return \$response->renderPage([
            'message' => 'Jika email terdaftar, link reset password telah dikirim',
        ], ['path' => '/password/forgot', 'meta' => ['title' => 'Lupa Password | ' . env('APP_NAME')]]);
    }

    /**
     * Show reset password form
     */
    public function showResetPassword(Request \$request, Response \$response): View | RedirectResponse
    {
        \$token = \$request->query['token'] ?? null;
        \$email = \$request->query['email'] ?? null;

        if (!\$token) {
            return \$response->redirect('/password/forgot');
        }

        // Validate token
        \$tokenData = \$this->passwordResetTokens->findValidToken(\$token);

        if (!\$tokenData || \$tokenData['user_id'] !== \$this->users->findByEmail(\$email)['id']) {
            return \$response->redirect('/password/reset?error=Link+reset+password+tidak+valid+atau+telah+kedaluwarsa');
        }

        return \$response->renderPage(
            ['token' => \$token, 'email' => \$email,],
            ['meta' => ['title' => 'Reset Password | ' . env('APP_NAME')]]
        );
    }

    /**
     * Process reset password
     */
    public function resetPassword(Request \$request, Response \$response): View | RedirectResponse
    {
        \$email = \$request->input('email');
        \$password = \$request->input('password');
        \$passwordConfirmation = \$request->input('password_confirmation');
        \$token = \$request->input('token') ?? null;

        if (\$password !== \$passwordConfirmation) {
            return \$response->redirect('/password/reset?Password+konfirmasi+tidak+cocok');
        }

        // Validate new password
        \$passwordValidation = \$this->validatePassword(\$password);
        if (!\$passwordValidation['valid']) {
            return \$response->redirect('/password/reset?' . urlencode(implode(', ', \$passwordValidation['errors'])));
        }

        // Validate token
        \$tokenData = \$this->passwordResetTokens->findValidToken(\$token);

        if (!\$tokenData) {
            return \$response->redirect('/password/reset?error=Link+reset+password+tidak+valid+atau+telah+kedaluwarsa');
        }

        \$user = \$this->users->findByEmail(\$email);

        if (!\$user || \$user['id'] !== \$tokenData['user_id']) {
            return \$response->redirect('/password/reset?Email+tidak+valid');
        }

        // Update password
        \$this->users->updateById(\$user['id'], ['password' => \$this->hashPassword(\$password)]);

        // Invalidate all reset tokens
        \$this->passwordResetTokens->invalidateAll(\$user['id']);

        return \$response->renderPage(
            ['message' => 'Password berhasil direset. Silakan login dengan password baru',],
            ['path' => '/password/reset', 'meta' => ['title' => 'Password Direset | ' . env('APP_NAME')]]
        );
    }
}
PHP;

        file_put_contents($controllerPath, $template);
        echo "   ✓ AuthController created\n";
    }

    private function setupAuthMiddleware(bool $withRole): void
    {
        echo "🛡️ Setup Auth Middleware...\n";

        $root = __DIR__ . '/../../..';
        $middlewareDir = $root . '/addon/Middleware';

        if (!is_dir($middlewareDir)) {
            mkdir($middlewareDir, 0755, true);
        }

        // AuthMiddleware - Check if user is logged in
        $authMiddlewarePath = $middlewareDir . '/AuthMiddleware.php';

        $authTemplate = <<<'PHP'
<?php

namespace Addon\Middleware;

use App\Core\Interfaces\MiddlewareInterface;
use App\Exceptions\AuthenticationException;
use App\Services\SessionService;

class AuthMiddleware implements MiddlewareInterface
{
    public function __construct(private SessionService $session) {}

    public function handle($request, \Closure $next, array $params = [])
    {
        if ($this->session->get('is_logged_in') !== true) {
            $e = new AuthenticationException('Unauthenticated');
            $e->hardRedirect();
            throw $e;
        }

        return $next($request);
    }
}
PHP;

        file_put_contents($authMiddlewarePath, $authTemplate);
        echo "   ✓ AuthMiddleware created\n";

        // GuestMiddleware - Redirect logged-in users from guest pages (alias: guest)
        $guestMiddlewarePath = $root . '/addon/Middleware/GuestMiddleware.php';

        $guestTemplate = <<<'PHP'
<?php

namespace Addon\Middleware;

use App\Core\Interfaces\MiddlewareInterface;
use App\Exceptions\AuthorizationException;
use App\Services\SessionService;

class GuestMiddleware implements MiddlewareInterface
{
    public function __construct(private SessionService $session) {}

    public function handle($request, \Closure $next, array $params = [])
    {
        if ($this->session->get('is_logged_in') === true) {
            $e = new AuthorizationException('RedirectIfAuthenticated');
            $e->hardRedirect();
            throw $e;
        }

        return $next($request);
    }
}
PHP;

        file_put_contents($guestMiddlewarePath, $guestTemplate);
        echo "   ✓ GuestMiddleware created\n";

        // RoleMiddleware - Only if --with-role is specified
        if ($withRole) {
            $roleMiddlewarePath = $root . '/addon/Middleware/RoleMiddleware.php';

            $roleTemplate = <<<'PHP'
<?php

namespace Addon\Middleware;

use App\Core\Interfaces\MiddlewareInterface;
use App\Exceptions\AuthorizationException;
use App\Services\SessionService;

class RoleMiddleware implements MiddlewareInterface
{
    public function __construct(private SessionService $session) {}

    public function handle($request, \Closure $next, array $params = [])
    {
        $allowedRoles = $params;
        $userRole = $this->session->get('role');

        if (!$userRole || !in_array($userRole, $allowedRoles)) {
            $e = new AuthorizationException("Forbidden. Anda tidak memiliki izin untuk mengakses halaman ini.");
            $e->hardRedirect();
            throw $e;
        }

        return $next($request);
    }
}
PHP;

            file_put_contents($roleMiddlewarePath, $roleTemplate);
            echo "   ✓ RoleMiddleware created\n";
        }
    }

    private function setupRoutes(bool $withRole): void
    {
        echo "🛣️ Setup Routes...\n";

        $root = __DIR__ . '/../../..';
        $routerPath = $root . '/addon/Router/index.php';

        // Routes untuk Hybrid Auth (Google OAuth + Manual OTP)
        $routes = <<<'PHP'
<?php

use App\Core\Http\Request;
use App\Core\Http\Response;
use Addon\Controllers\AuthController;
use Addon\Models\UserModel;
use App\Services\SessionService;

/** @var \App\Core\Routing\Router $router */

// Guest routes (login, register, password reset, OTP verification)
$router->group(['middleware' => ['guest']], function () use ($router) {
    // Login
    $router->get('/login', [AuthController::class, 'showLogin']);
    $router->post('/login', [AuthController::class, 'login']);
    
    // Register
    $router->get('/register', [AuthController::class, 'showRegister']);
    $router->post('/register', [AuthController::class, 'register']);
    
    // OTP Verification
    $router->get('/verify-otp', [AuthController::class, 'showVerifyOtp']);
    $router->post('/verify-otp', [AuthController::class, 'verifyOtp']);
    $router->get('/resend-otp', [AuthController::class, 'resendOtp']);
    $router->get('/otp-sent', [AuthController::class, 'showOtpSent']);
    
    // Password reset
    $router->get('/password/forgot', [AuthController::class, 'showForgotPassword']);
    $router->post('/password/forgot', [AuthController::class, 'sendResetLink']);
    $router->get('/password/reset', [AuthController::class, 'showResetPassword']);
    $router->post('/password/reset', [AuthController::class, 'resetPassword']);
    
    // Google OAuth
    $router->get('/auth/google', function (Request $request, Response $response) {
        $client = new \Google_Client();
        $client->setClientId(env('GOOGLE_CLIENT_ID'));
        $client->setClientSecret(env('GOOGLE_CLIENT_SECRET'));
        $client->setRedirectUri(env('GOOGLE_REDIRECT_URI'));
        $client->addScope('email');
        $client->addScope('profile');
        
        $authUrl = $client->createAuthUrl();
        return $response->redirect($authUrl);
    });
    $router->get('/auth/callback', [AuthController::class, 'googleCallback']);
});

// Auth routes (require login)
$router->group(['middleware' => ['auth']], function () use ($router) {
    // Dashboard
    $router->get('/dashboard', function (Request $request, Response $response) {
        return $response->renderPage([], ['path' => '/dashboard', 'meta' => ['title' => 'Dashboard | ' . env('APP_NAME')]]);
    });
    
    // Logout
    $router->post('/logout', [AuthController::class, 'logout']);
});

// Home route
$router->get('/', function (Request $request, Response $response) {
    return $response->redirect('/dashboard');
});
PHP;

        file_put_contents($routerPath, $routes);
        echo "   ✓ Routes configured\n";
    }

    private function setupViews(): void
    {
        echo "🎨 Setup Views...\n";

        $root = __DIR__ . '/../../..';
        $viewsPath = $root . '/addon/Views';

        // Create directories
        $authDir = $viewsPath . '/(auth)';
        $passwordDir = $viewsPath . '/password';
        $dashboardDir = $viewsPath . '/dashboard';

        if (!is_dir($authDir)) {
            mkdir($authDir, 0755, true);
        }

        if (!is_dir($passwordDir)) {
            mkdir($passwordDir, 0755, true);
        }

        if (!is_dir($dashboardDir)) {
            mkdir($dashboardDir, 0755, true);
        }

        // Auth layout
        $authLayout = <<<'PHP'
<?php

/**
 * @var \App\Core\View\PageMeta $meta
 * @var string $children
 */
?>
<div class="auth-container">
    <div class="auth-card" data-layout="(auth)/layout.php">
        <?= $children; ?>
    </div>
</div>
PHP;

        file_put_contents("$authDir/layout.php", $authLayout);

        // Login view
        $loginView = <<<'PHP'
<?php

/**
 * @var \App\Core\View\PageMeta $meta
 */
?>
<h1 class="auth-title">Login</h1>

<?php if (isset($error)): ?>
  <div class="auth-error">
    <?= htmlspecialchars($error) ?>
  </div>
<?php endif; ?>

<form method="POST" action="/login">
  <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">

  <div class="auth-form-group">
    <label for="email" class="auth-label">Email</label>
    <input
      type="email"
      id="email"
      name="email"
      class="auth-input"
      value="<?= htmlspecialchars($_GET['email'] ?? '') ?>"
      required>
  </div>

  <div class="auth-form-group">
    <label for="password" class="auth-label">Password</label>
    <input
      type="password"
      id="password"
      name="password"
      class="auth-input"
      required>
  </div>

  <button type="submit" class="auth-button">
    Login
  </button>
</form>

<div class="auth-divider">
  <span>atau</span>
</div>

<a href="/auth/google" class="google-button">
  <svg class="google-icon" viewBox="0 0 24 24" width="20" height="20">
    <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
    <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
    <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
    <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
  </svg>
  Login with Google
</a>

<div class="auth-links">
  <a data-spa href="/password/forgot" class="auth-link">Lupa password?</a>
</div>

<div class="auth-divider">
  <span>Belum punya akun?</span>
  <a data-spa href="/register" class="auth-link">Register</a>
</div>
PHP;

        file_put_contents("$authDir/login.php", $loginView);

        // Register view
        $registerView = <<<'PHP'
<?php

/**
 * @var \App\Core\View\PageMeta $meta
 */
?>
<h1 class="auth-title">Register</h1>

<?php if (isset($error)): ?>
  <div class="auth-error">
    <?= htmlspecialchars($error) ?>
  </div>
<?php endif; ?>

<form method="POST" action="/register">
  <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">

  <div class="auth-form-group">
    <label for="name" class="auth-label">Nama Lengkap</label>
    <input
      type="text"
      id="name"
      name="name"
      class="auth-input"
      value="<?= htmlspecialchars($_GET['name'] ?? '') ?>"
      required>
  </div>

  <div class="auth-form-group">
    <label for="email" class="auth-label">Email</label>
    <input
      type="email"
      id="email"
      name="email"
      class="auth-input"
      value="<?= htmlspecialchars($_GET['email'] ?? '') ?>"
      required>
  </div>

  <div class="auth-form-group">
    <label for="password" class="auth-label">Password (min. 8 karakter)</label>
    <input
      type="password"
      id="password"
      name="password"
      class="auth-input"
      minlength="8"
      required>
  </div>

  <div class="auth-form-group">
    <label for="password_confirmation" class="auth-label">Konfirmasi Password</label>
    <input
      type="password"
      id="password_confirmation"
      name="password_confirmation"
      class="auth-input"
      minlength="8"
      required>
  </div>

  <button type="submit" class="auth-button">
    Register
  </button>
</form>

<div class="auth-divider">
  <span>atau</span>
</div>

<a href="/auth/google" class="google-button">
  <svg class="google-icon" viewBox="0 0 24 24" width="20" height="20">
    <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
    <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
    <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
    <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
  </svg>
  Register with Google
</a>

<div class="auth-divider">
  <span>Sudah punya akun?</span>
  <a data-spa href="/login" class="auth-link">Login</a>
</div>
PHP;

        file_put_contents("$authDir/register.php", $registerView);

        // Auth style
        $authStyle = <<<'CSS'
.auth-container {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background-color: var(--md-sys-color-background);
  padding: 24px;
}
.auth-card {
  width: 100%;
  max-width: 420px;
  background-color: var(--md-surface-1);
  border-radius: 28px;
  padding: 40px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}
.auth-title {
  font-family: "Poppins", sans-serif;
  font-weight: 600;
  font-size: 1.75rem;
  text-align: center;
  margin-bottom: 24px;
  color: var(--md-sys-color-on-surface);
}
.auth-error {
  background-color: var(--md-sys-color-error-container);
  border: 1px solid var(--md-sys-color-error);
  color: var(--md-sys-color-on-error-container);
  padding: 12px 16px;
  border-radius: 12px;
  margin-bottom: 20px;
  font-size: 0.9rem;
}
.auth-form-group {
  margin-bottom: 20px;
}
.auth-label {
  display: block;
  font-weight: 500;
  font-size: 0.9rem;
  color: var(--md-sys-color-on-surface);
  margin-bottom: 8px;
}
.auth-input {
  width: 100%;
  padding: 12px 16px;
  border: 1px solid var(--md-sys-color-outline-variant);
  border-radius: 12px;
  font-size: 1rem;
  box-sizing: border-box;
  transition: border-color 0.2s;
  background-color: var(--md-sys-color-surface);
  color: var(--md-sys-color-on-surface);
}
.auth-input:focus {
  outline: none;
  border-color: var(--md-sys-color-primary);
}
.auth-button {
  width: 100%;
  height: 48px;
  background-color: var(--md-sys-color-primary);
  color: var(--md-sys-color-on-primary);
  border: none;
  border-radius: 24px;
  font-weight: 600;
  font-size: 1rem;
  cursor: pointer;
  transition: all 0.2s;
}
.auth-button:hover {
  background-color: var(--md-sys-color-on-primary-container);
  box-shadow: 0 4px 12px rgba(0, 104, 116, 0.3);
}
.auth-links {
  margin-top: 20px;
  text-align: center;
}
.auth-link {
  color: var(--md-sys-color-primary);
  font-size: 0.9rem;
  text-decoration: none;
}
.auth-link:hover {
  color: var(--md-sys-color-on-primary-container);
}
.auth-divider {
  margin: 8px 0;
  text-align: center;
  color: var(--md-sys-color-on-surface-variant);
  font-size: 0.875rem;
}
.google-button {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 12px;
  width: 100%;
  height: 48px;
  background-color: #fff;
  color: #3c4043;
  border: 1px solid #dadce0;
  border-radius: 24px;
  font-weight: 500;
  font-size: 0.95rem;
  cursor: pointer;
  text-decoration: none;
  transition: all 0.2s;
  box-sizing: border-box;
}
.google-button:hover {
  background-color: #f7f8f8;
  border-color: #d2e3fc;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}
.google-icon {
  flex-shrink: 0;
}
CSS;

        file_put_contents("$authDir/style.css", $authStyle);

        // Dashboard view
        $dashboardView = <<<'PHP'
<main class="dashboard-main">
  <div class="dashboard-card">
    <h2 class="dashboard-card-title">Selamat Datang di Dashboard</h2>
    <p class="dashboard-card-desc">Anda berhasil login dengan session authentication.</p>
    <form method="POST" data-spa action="/logout">
      <button class="dashboard-logout">Logout</button>
    </form>
  </div>
</main>
PHP;

        file_put_contents("$dashboardDir/index.php", $dashboardView);

        // Dashboard style
        $dashboardStyle = <<<'CSS'

.dashboard-logout {
    color: var(--md-sys-color-error);
    text-decoration: none;
    font-weight: 500;
    font-size: 0.9rem;
    transition: color 0.2s;
}
.dashboard-logout:hover {
    color: var(--md-sys-color-on-error-container);
}
.dashboard-main {
    max-width: 1200px;
    margin: 0 auto;
    padding: 32px 24px;
}
.dashboard-card {
    background-color: var(--md-surface-1);
    border-radius: 28px;
    padding: 32px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}
.dashboard-card-title {
    font-family: "Poppins", sans-serif;
    font-weight: 600;
    font-size: 1.25rem;
    color: var(--md-sys-color-on-surface);
    margin: 0 0 16px 0;
}
.dashboard-card-desc {
    color: var(--md-sys-color-on-surface-variant);
    font-size: 1rem;
    line-height: 1.6;
    margin: 0;
}
CSS;

        file_put_contents("$dashboardDir/style.css", $dashboardStyle);

        // Forgot password view
        $forgotView = <<<'PHP'
<div class="auth-container">
  <div class="auth-card">
    <h1 class="auth-title">Lupa Password</h1>

    <?php if (isset($message)): ?>
      <div class="auth-success">
        <?= htmlspecialchars($message) ?>
      </div>
    <?php endif; ?>

    <?php if (isset($error)): ?>
      <div class="auth-error">
        <?= htmlspecialchars($error) ?>
      </div>
    <?php endif; ?>

    <form method="POST" action="/password/forgot">
      <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">

      <div class="auth-form-group">
        <label for="email" class="auth-label">Email</label>
        <input
          type="email"
          id="email"
          name="email"
          class="auth-input"
          placeholder="Masukkan email Anda"
          required>
      </div>

      <button type="submit" class="auth-button">
        Kirim Link Reset
      </button>
    </form>

    <div class="auth-links">
      <a data-spa href="/login" class="auth-link">Kembali ke Login</a>
    </div>
  </div>
</div>
PHP;

        file_put_contents("$passwordDir/forgot.php", $forgotView);

        // Reset password view
        $resetView = <<<'PHP'
<div class="auth-container">
  <div class="auth-card">
    <h1 class="auth-title">Reset Password</h1>

    <?php if (isset($error)): ?>
      <div class="auth-error">
        <?= htmlspecialchars($error) ?>
      </div>
    <?php endif; ?>

    <form method="POST" action="/password/reset">
      <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
      <?php if (isset($token)): ?>
        <input type="hidden" name="token" value="<?= $token ?>">
      <?php endif; ?>

      <div class="auth-form-group">
        <label for="email" class="auth-label">Email</label>
        <input
          type="email"
          id="email"
          name="email"
          class="auth-input"
          required>
      </div>

      <div class="auth-form-group">
        <label for="password" class="auth-label">Password Baru</label>
        <input
          type="password"
          id="password"
          name="password"
          class="auth-input"
          minlength="8"
          required>
      </div>

      <div class="auth-form-group">
        <label for="password_confirmation" class="auth-label">Konfirmasi Password</label>
        <input
          type="password"
          id="password_confirmation"
          name="password_confirmation"
          class="auth-input"
          minlength="8"
          required>
      </div>

      <button type="submit" class="auth-button">
        Reset Password
      </button>
    </form>

    <div class="auth-links">
      <a data-spa href="/login" class="auth-link">Kembali ke Login</a>
    </div>
  </div>
</div>
PHP;

        file_put_contents("$passwordDir/reset.php", $resetView);

        // Password style
        $passwordStyle = <<<'CSS'

.auth-container {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background-color: var(--md-sys-color-background);
  padding: 24px;
}
.auth-card {
  width: 100%;
  max-width: 420px;
  background-color: var(--md-surface-1);
  border-radius: 28px;
  padding: 40px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}
.auth-title {
  font-family: "Poppins", sans-serif;
  font-weight: 600;
  font-size: 1.75rem;
  text-align: center;
  margin-bottom: 24px;
  color: var(--md-sys-color-on-surface);
}
.auth-success {
  background-color: var(--md-sys-color-secondary-container);
  border: 1px solid var(--md-sys-color-secondary);
  color: var(--md-sys-color-on-secondary-container);
  padding: 12px 16px;
  border-radius: 12px;
  margin-bottom: 20px;
  font-size: 0.9rem;
}
.auth-error {
  background-color: var(--md-sys-color-error-container);
  border: 1px solid var(--md-sys-color-error);
  color: var(--md-sys-color-on-error-container);
  padding: 12px 16px;
  border-radius: 12px;
  margin-bottom: 20px;
  font-size: 0.9rem;
}
.auth-form-group {
  margin-bottom: 20px;
}
.auth-label {
  display: block;
  font-weight: 500;
  font-size: 0.9rem;
  color: var(--md-sys-color-on-surface);
  margin-bottom: 8px;
}
.auth-input {
  width: 100%;
  padding: 12px 16px;
  border: 1px solid var(--md-sys-color-outline-variant);
  border-radius: 12px;
  font-size: 1rem;
  box-sizing: border-box;
  transition: border-color 0.2s;
  background-color: var(--md-sys-color-surface);
  color: var(--md-sys-color-on-surface);
}
.auth-input:focus {
  outline: none;
  border-color: var(--md-sys-color-primary);
}
.auth-input::placeholder {
  color: var(--md-sys-color-on-surface-variant);
}
.auth-button {
  width: 100%;
  height: 48px;
  background-color: var(--md-sys-color-primary);
  color: var(--md-sys-color-on-primary);
  border: none;
  border-radius: 24px;
  font-weight: 600;
  font-size: 1rem;
  cursor: pointer;
  transition: all 0.2s;
}
.auth-button:hover {
  background-color: var(--md-sys-color-on-primary-container);
  box-shadow: 0 4px 12px rgba(0, 104, 116, 0.3);
}
.auth-links {
  margin-top: 20px;
  text-align: center;
}
.auth-link {
  color: var(--md-sys-color-primary);
  font-size: 0.9rem;
  text-decoration: none;
}
.auth-link:hover {
  color: var(--md-sys-color-on-primary-container);
}
CSS;

        file_put_contents("$passwordDir/style.css", $passwordStyle);

        // OTP Sent view
        $otpSentView = <<<'PHP'
<?php

/**
 * @var \App\Core\View\PageMeta $meta
 * @var string $email Email tujuan OTP
 */
?>

<div class="otp-sent-container">
    <div class="otp-sent-header">
        <div class="otp-sent-icon">📧</div>
        <h1 class="otp-sent-title">Email Terkirim!</h1>
        <p class="otp-sent-description">
            Kami telah mengirim kode verifikasi ke<br>
            <strong><?= htmlspecialchars($email ?? '') ?></strong>
        </p>
    </div>

    <div class="otp-sent-instructions">
        <div class="instruction-step">
            <span class="step-number">1</span>
            <span class="step-text">Buka inbox email Anda</span>
        </div>
        <div class="instruction-step">
            <span class="step-number">2</span>
            <span class="step-text">Cari email dari <?= htmlspecialchars(env('MAIL_FROM_NAME', 'Mazu Framework')) ?></span>
        </div>
        <div class="instruction-step">
            <span class="step-number">3</span>
            <span class="step-text">Salin kode 6 digit dari email</span>
        </div>
        <div class="instruction-step">
            <span class="step-number">4</span>
            <span class="step-text">Masukkan kode di halaman verifikasi</span>
        </div>
    </div>

    <div class="otp-sent-actions">
        <a
            href="/verify-otp?email=<?= urlencode($email ?? '') ?>"
            class="otp-sent-button primary"
            data-spa
        >
            Buka Halaman Verifikasi
        </a>

        <button
            type="button"
            class="otp-sent-button secondary"
            id="resend-from-sent"
            disabled>
            <span class="button-text">Kirim Ulang Email</span>
            <span class="button-countdown">(60s)</span>
        </button>
    </div>

    <div class="otp-sent-tips">
        <p class="tips-title">💡 Tips:</p>
        <ul class="tips-list">
            <li>Periksa folder spam/junk jika email tidak muncul di inbox</li>
            <li>Pastikan alamat email yang Anda masukkan sudah benar</li>
            <li>Kode verifikasi berlaku selama 15 menit</li>
        </ul>
    </div>

    <a href="/register" class="otp-sent-back" data-spa>
        ← Kembali ke Register
    </a>
</div>

<script>
    (function() {
        const resendButton = document.getElementById('resend-from-sent');
        const countdownSpan = resendButton.querySelector('.button-countdown');
        let cooldown = 60;

        function startCooldown() {
            const interval = setInterval(() => {
                if (cooldown <= 0) {
                    clearInterval(interval);
                    resendButton.disabled = false;
                    countdownSpan.textContent = '';
                    return;
                }

                cooldown--;
                countdownSpan.textContent = `(${cooldown}s)`;
            }, 1000);
        }

        resendButton.addEventListener('click', () => {
            if (!resendButton.disabled) {
                window.location.href = '/resend-otp?email=' + encodeURIComponent('<?= htmlspecialchars($email ?? '') ?>');
            }
        });

        startCooldown();
    })();
</script>

<style>
    .otp-sent-container {
        max-width: 420px;
        margin: 0 auto;
        padding: 20px;
    }

    .otp-sent-header {
        text-align: center;
        margin-bottom: 30px;
    }

    .otp-sent-icon {
        font-size: 64px;
        margin-bottom: 15px;
        animation: bounce 1s ease infinite;
    }

    @keyframes bounce {

        0%,
        100% {
            transform: translateY(0);
        }

        50% {
            transform: translateY(-10px);
        }
    }

    .otp-sent-title {
        font-size: 24px;
        font-weight: 700;
        color: #1a1a1a;
        margin: 0 0 10px 0;
    }

    .otp-sent-description {
        color: #666;
        font-size: 14px;
        line-height: 1.6;
    }

    .otp-sent-description strong {
        color: #333;
        word-break: break-all;
    }

    .otp-sent-instructions {
        background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 25px;
    }

    .instruction-step {
        display: flex;
        align-items: center;
        gap: 15px;
        padding: 12px 0;
        border-bottom: 1px solid #e2e8f0;
    }

    .instruction-step:last-child {
        border-bottom: none;
    }

    .step-number {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 32px;
        height: 32px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-radius: 50%;
        font-weight: 700;
        font-size: 14px;
        flex-shrink: 0;
    }

    .step-text {
        color: #333;
        font-size: 14px;
    }

    .otp-sent-actions {
        display: flex;
        flex-direction: column;
        gap: 12px;
        margin-bottom: 25px;
    }

    .otp-sent-button {
        width: 100%;
        padding: 16px;
        border-radius: 10px;
        font-size: 16px;
        font-weight: 600;
        text-align: center;
        text-decoration: none;
        cursor: pointer;
        transition: all 0.3s;
        border: none;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }

    .otp-sent-button.primary {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
    }

    .otp-sent-button.primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
    }

    .otp-sent-button.secondary {
        background: white;
        color: #667eea;
        border: 2px solid #667eea;
    }

    .otp-sent-button.secondary:hover:not(:disabled) {
        background: #667eea;
        color: white;
    }

    .otp-sent-button:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    .otp-sent-tips {
        background: #fffbeb;
        border: 1px solid #fde68a;
        border-radius: 10px;
        padding: 16px;
        margin-bottom: 20px;
    }

    .tips-title {
        font-weight: 600;
        color: #92400e;
        margin: 0 0 10px 0;
        font-size: 14px;
    }

    .tips-list {
        margin: 0;
        padding-left: 20px;
        color: #78350f;
        font-size: 13px;
        line-height: 1.8;
    }

    .tips-list li {
        margin-bottom: 5px;
    }

    .otp-sent-back {
        display: inline-block;
        color: #64748b;
        text-decoration: none;
        font-size: 14px;
        transition: color 0.2s;
    }

    .otp-sent-back:hover {
        color: #667eea;
    }
</style>
PHP;

        file_put_contents("$authDir/otp-sent.php", $otpSentView);

        // Verify OTP view
        $verifyOtpView = <<<'PHP'
<?php

/**
 * @var \App\Core\View\PageMeta $meta
 * @var string $email Email user yang akan diverifikasi
 * @var string|null $error Error message (jika ada)
 */
?>

<div class="otp-verification-container">
    <div class="otp-header">
        <div class="otp-icon">🔐</div>
        <h1 class="otp-title">Verifikasi Email</h1>
        <p class="otp-description">
            Kami telah mengirim kode 6-digit ke<br>
            <strong><?= htmlspecialchars($email ?? '') ?></strong>
        </p>
    </div>

    <?php if (isset($error)): ?>
        <div class="otp-error" role="alert">
            <span class="otp-error-icon">⚠️</span>
            <span><?= htmlspecialchars($error) ?></span>
        </div>
    <?php endif; ?>

    <?php if (isset($success)): ?>
        <div class="otp-success" role="alert">
            <span class="otp-success-icon">✅</span>
            <span><?= htmlspecialchars($success) ?></span>
        </div>
    <?php endif; ?>

    <form method="POST" action="/verify-otp" id="otp-form">
        <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
        <input type="hidden" name="email" value="<?= htmlspecialchars($email ?? '') ?>">

        <div class="otp-inputs" role="group" aria-label="Kode verifikasi 6 digit">
            <input
                type="text"
                maxlength="1"
                pattern="[0-9]"
                inputmode="numeric"
                autocomplete="one-time-code"
                aria-label="Digit pertama"
                required
                class="otp-digit"
                data-index="0">
            <input
                type="text"
                maxlength="1"
                pattern="[0-9]"
                inputmode="numeric"
                autocomplete="off"
                aria-label="Digit kedua"
                required
                class="otp-digit"
                data-index="1">
            <input
                type="text"
                maxlength="1"
                pattern="[0-9]"
                inputmode="numeric"
                autocomplete="off"
                aria-label="Digit ketiga"
                required
                class="otp-digit"
                data-index="2">
            <input
                type="text"
                maxlength="1"
                pattern="[0-9]"
                inputmode="numeric"
                autocomplete="off"
                aria-label="Digit keempat"
                required
                class="otp-digit"
                data-index="3">
            <input
                type="text"
                maxlength="1"
                pattern="[0-9]"
                inputmode="numeric"
                autocomplete="off"
                aria-label="Digit kelima"
                required
                class="otp-digit"
                data-index="4">
            <input
                type="text"
                maxlength="1"
                pattern="[0-9]"
                inputmode="numeric"
                autocomplete="off"
                aria-label="Digit keenam"
                required
                class="otp-digit"
                data-index="5">
        </div>

        <input type="hidden" name="otp_code" id="otp-code-hidden" required>

        <button type="submit" class="otp-button" id="verify-button" disabled>
            <span class="button-text">Verifikasi</span>
            <span class="button-loading" style="display: none;">
                <span class="spinner"></span>
                Memverifikasi...
            </span>
        </button>
    </form>

    <div class="otp-footer">
        <div class="otp-timer" id="otp-timer">
            <span class="timer-icon">⏱️</span>
            <span class="timer-text" id="timer-text">Kode berlaku 15:00</span>
        </div>

        <button
            type="button"
            class="otp-resend"
            id="resend-button"
            disabled
            data-spa>
            <span class="resend-text">Kirim Ulang OTP</span>
            <span class="resend-countdown">(60s)</span>
        </button>

        <a href="/register" class="otp-back-link" data-spa>
            ← Kembali ke Register
        </a>
    </div>
</div>

<script>
    (function() {
        const inputs = document.querySelectorAll('.otp-digit');
        const form = document.getElementById('otp-form');
        const verifyButton = document.getElementById('verify-button');
        const otpHidden = document.getElementById('otp-code-hidden');
        const timerText = document.getElementById('timer-text');
        const resendButton = document.getElementById('resend-button');
        const resendCountdown = resendButton.querySelector('.resend-countdown');

        let timeLeft = 900; // 15 minutes
        let resendCooldown = 60; // 60 seconds

        // Auto-focus first input
        inputs[0].focus();

        // Handle input
        inputs.forEach((input, index) => {
            input.addEventListener('input', (e) => {
                const value = e.target.value;

                // Only allow numbers
                if (!/^\d*$/.test(value)) {
                    e.target.value = '';
                    return;
                }

                // Auto-focus next
                if (value.length === 1 && index < inputs.length - 1) {
                    inputs[index + 1].focus();
                }

                // Check if all filled
                checkAllFilled();
            });

            // Handle backspace
            input.addEventListener('keydown', (e) => {
                if (e.key === 'Backspace' && input.value === '' && index > 0) {
                    inputs[index - 1].focus();
                }
            });

            // Handle paste
            input.addEventListener('paste', (e) => {
                e.preventDefault();
                const pasted = e.clipboardData.getData('text').slice(0, 6);

                if (/^\d{6}$/.test(pasted)) {
                    inputs.forEach((inp, i) => {
                        inp.value = pasted[i];
                        if (i < 5) inputs[i + 1].focus();
                    });
                    checkAllFilled();
                }
            });
        });

        function checkAllFilled() {
            const allFilled = Array.from(inputs).every(i => i.value.length === 1);
            if (allFilled) {
                verifyButton.disabled = false;
                otpHidden.value = Array.from(inputs).map(i => i.value).join('');
            } else {
                verifyButton.disabled = true;
                otpHidden.value = '';
            }
        }

        // Handle form submit
        form.addEventListener('submit', (e) => {
            verifyButton.disabled = true;
            verifyButton.querySelector('.button-text').style.display = 'none';
            verifyButton.querySelector('.button-loading').style.display = 'inline-flex';
        });

        // Timer countdown
        function startTimer() {
            const interval = setInterval(() => {
                if (timeLeft <= 0) {
                    clearInterval(interval);
                    timerText.textContent = 'Kode telah kedaluwarsa';
                    timerText.closest('.otp-timer').classList.add('expired');
                    return;
                }

                timeLeft--;
                const minutes = Math.floor(timeLeft / 60);
                const seconds = timeLeft % 60;
                timerText.textContent = `Kode berlaku ${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;

                if (timeLeft < 60) {
                    timerText.closest('.otp-timer').classList.add('urgent');
                }
            }, 1000);
        }

        // Resend cooldown
        function startResendCooldown() {
            resendButton.disabled = true;
            let cooldown = resendCooldown;

            const interval = setInterval(() => {
                if (cooldown <= 0) {
                    clearInterval(interval);
                    resendButton.disabled = false;
                    resendCountdown.textContent = '';
                    return;
                }

                cooldown--;
                resendCountdown.textContent = `(${cooldown}s)`;
            }, 1000);
        }

        // Handle resend click
        resendButton.addEventListener('click', () => {
            if (!resendButton.disabled) {
                window.location.href = '/resend-otp?email=' + encodeURIComponent('<?= htmlspecialchars($email ?? '') ?>');
            }
        });

        // Start timers
        startTimer();
        startResendCooldown();
    })();
</script>

<style>
    .otp-verification-container {
        max-width: 420px;
        margin: 0 auto;
        padding: 20px;
    }

    .otp-header {
        text-align: center;
        margin-bottom: 30px;
    }

    .otp-icon {
        font-size: 48px;
        margin-bottom: 15px;
    }

    .otp-title {
        font-size: 24px;
        font-weight: 700;
        color: #1a1a1a;
        margin: 0 0 10px 0;
    }

    .otp-description {
        color: #666;
        font-size: 14px;
        line-height: 1.6;
    }

    .otp-description strong {
        color: #333;
        word-break: break-all;
    }

    .otp-error,
    .otp-success {
        padding: 12px 16px;
        border-radius: 8px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 14px;
    }

    .otp-error {
        background: #fef2f2;
        border: 1px solid #fecaca;
        color: #dc2626;
    }

    .otp-success {
        background: #ecfdf5;
        border: 1px solid #a7f3d0;
        color: #059669;
    }

    .otp-inputs {
        display: flex;
        gap: 10px;
        justify-content: center;
        margin: 30px 0;
    }

    .otp-digit {
        width: 50px;
        height: 60px;
        font-size: 24px;
        font-weight: 600;
        text-align: center;
        border: 2px solid #e2e8f0;
        border-radius: 10px;
        transition: all 0.2s;
        background: white;
    }

    .otp-digit:focus {
        border-color: #667eea;
        box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
        outline: none;
    }

    .otp-digit.filled {
        border-color: #10b981;
        background: #ecfdf5;
    }

    .otp-button {
        width: 100%;
        padding: 16px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border: none;
        border-radius: 10px;
        font-size: 16px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
    }

    .otp-button:hover:not(:disabled) {
        transform: translateY(-2px);
        box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
    }

    .otp-button:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    .button-loading {
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    .spinner {
        width: 18px;
        height: 18px;
        border: 2px solid rgba(255, 255, 255, 0.3);
        border-top-color: white;
        border-radius: 50%;
        animation: spin 0.8s linear infinite;
    }

    @keyframes spin {
        to {
            transform: rotate(360deg);
        }
    }

    .otp-footer {
        margin-top: 30px;
        text-align: center;
    }

    .otp-timer {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 16px;
        background: #f8fafc;
        border-radius: 8px;
        font-size: 14px;
        color: #64748b;
        margin-bottom: 15px;
        transition: all 0.3s;
    }

    .otp-timer.urgent {
        background: #fef2f2;
        color: #dc2626;
        animation: pulse 1s infinite;
    }

    .otp-timer.expired {
        background: #fef2f2;
        color: #dc2626;
    }

    @keyframes pulse {

        0%,
        100% {
            opacity: 1;
        }

        50% {
            opacity: 0.6;
        }
    }

    .otp-resend {
        display: block;
        width: 100%;
        padding: 12px;
        background: none;
        border: 2px solid #667eea;
        color: #667eea;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
        margin-bottom: 15px;
    }

    .otp-resend:hover:not(:disabled) {
        background: #667eea;
        color: white;
    }

    .otp-resend:disabled {
        border-color: #cbd5e1;
        color: #94a3b8;
        cursor: not-allowed;
    }

    .otp-back-link {
        display: inline-block;
        color: #64748b;
        text-decoration: none;
        font-size: 14px;
        transition: color 0.2s;
    }

    .otp-back-link:hover {
        color: #667eea;
    }
</style>
PHP;

        file_put_contents("$authDir/verify-otp.php", $verifyOtpView);

        echo "   ✓ Views created (auth/layout, auth/login, auth/register, auth/otp-sent, auth/verify-otp, auth/style, password/forgot, password/reset, password/style, dashboard/index, dashboard/style)\n";
    }

    private function printNextSteps(bool $withRole): void
    {
        echo "📋 Langkah Selanjutnya:\n\n";

        echo "1. Jalankan migration untuk membuat tabel users:\n";
        echo "   php mazu migrate\n\n";

        echo "2. (Opsional) Seed data user:\n";
        if ($withRole) {
            echo "   - Super Admin: superadmin@example.com / password123\n";
            echo "   - Admin: admin@example.com / password123\n";
            echo "   - User: user@example.com / password123\n\n";
        } else {
            echo "   - User 1: user1@example.com / password123\n";
            echo "   - User 2: user2@example.com / password123\n\n";
        }

        if ($withRole) {
            echo "3. Role system aktif. Middleware tersedia:\n";
            echo "   - auth: Check if user is logged in\n";
            echo "   - guest: Redirect if logged in\n";
            echo "   - role:admin,role:super_admin: Check user role\n\n";
        } else {
            echo "3. Middleware tersedia:\n";
            echo "   - auth: Check if user is logged in\n";
            echo "   - guest: Redirect if logged in\n\n";
        }

        echo "4. Start server:\n";
        echo "   php mazu serve\n\n";

        echo "5. Akses aplikasi:\n";
        echo "   http://localhost:8000/login\n";
        echo "   http://localhost:8000/register\n\n";
    }

    private function info(string $message): void
    {
        echo "   ℹ️  {$message}\n";
    }
}
