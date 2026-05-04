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
        $this->setupAuthController($withRole);
        $this->setupAuthMiddleware($withRole);
        $this->setupRoutes($withRole);
        $this->setupViews();
        $this->setupOtpGenerator();

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
APP_NAME="Mazu Framework"
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=mazu_framework
DB_USERNAME=root
DB_PASSWORD=

SESSION_DRIVER=file
SESSION_LIFETIME=120

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
        ],
        [
            'email' => 'admin@example.com',
            'password' => '$2y$10\$XlyT7neGvzxYcZ5v.4gsP.QFqRq7UG8nNrJF1Bk4fiP/vQUCqXlDm', // password123
            'name' => 'Admin User',
            'avatar' => null,
            'is_active' => 1,
            'last_login_at' => null,
            'role' => 'admin',
        ],
        [
            'email' => 'user@example.com',
            'password' => '$2y$10\$XlyT7neGvzxYcZ5v.4gsP.QFqRq7UG8nNrJF1Bk4fiP/vQUCqXlDm', // password123
            'name' => 'Regular User',
            'avatar' => null,
            'is_active' => 1,
            'last_login_at' => null,
            'role' => 'user',
        ]" : "
        [
            'email' => 'user1@example.com',
            'password' => '$2y$10\$XlyT7neGvzxYcZ5v.4gsP.QFqRq7UG8nNrJF1Bk4fiP/vQUCqXlDm', // password123
            'name' => 'User 1',
            'avatar' => null,
            'is_active' => 1,
            'last_login_at' => null,
        ],
        [
            'email' => 'user2@example.com',
            'password' => '$2y$10\$XlyT7neGvzxYcZ5v.4gsP.QFqRq7UG8nNrJF1Bk4fiP/vQUCqXlDm', // password123
            'name' => 'User 2',
            'avatar' => null,
            'is_active' => 1,
            'last_login_at' => null,
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
        'password' => ['type' => 'string', 'nullable' => false],
        'name' => ['type' => 'string', 'nullable' => true],
        'avatar' => ['type' => 'string', 'nullable' => true],
        'is_active' => ['type' => 'boolean', 'nullable' => false, 'default' => true],
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

        // Add role if schema has role field
        if (isset(\$this->users->getSchema()['role'])) {
            \$userData['role'] = \$role;
        }

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
        // Catatan: Email verification status dilacak melalui tabel email_verifications (used_at)
        // bukan melalui kolom di tabel users
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
                // Check domain restriction
                \$allowedDomain = env('GOOGLE_ALLOWED_DOMAIN');
                if (\$allowedDomain) {
                    \$domain = substr(strrchr(\$googleUser->email, "@"), 1);
                    if (\$domain !== ltrim(\$allowedDomain, '@')) {
                        return \$response->redirect('/login?error=Domain+email+tidak+diizinkan.+Hanya+' . \$allowedDomain);
                    }
                }

                // Create new user from Google
                \$userData = [
                    'email' => \$googleUser->email,
                    'password' => null, // No password for Google users
                    'name' => \$googleUser->name,
                    'avatar' => \$googleUser->picture,
                    'is_active' => 1, // Google verified email, so auto-activate
                    'google_id' => \$googleUser->id,
                    'avatar_url' => \$googleUser->picture,
                ];

                // Add role if schema has role field
                if (isset(\$this->users->getSchema()['role'])) {
                    \$userData['role'] = 'user';
                }

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
            <span class="step-text">Cari email dari Mazu Framework</span>
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
    </div>

    <a href="/register" class="otp-sent-back" data-spa>
        ← Kembali ke Register
    </a>
</div>
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

    <form method="POST" action="/verify-otp" id="otp-form">
        <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
        <input type="hidden" name="email" value="<?= htmlspecialchars($email ?? '') ?>">

        <div class="otp-inputs" role="group" aria-label="Kode verifikasi 6 digit">
            <input type="text" maxlength="1" pattern="[0-9]" inputmode="numeric" autocomplete="one-time-code" required class="otp-digit" data-index="0">
            <input type="text" maxlength="1" pattern="[0-9]" inputmode="numeric" autocomplete="off" required class="otp-digit" data-index="1">
            <input type="text" maxlength="1" pattern="[0-9]" inputmode="numeric" autocomplete="off" required class="otp-digit" data-index="2">
            <input type="text" maxlength="1" pattern="[0-9]" inputmode="numeric" autocomplete="off" required class="otp-digit" data-index="3">
            <input type="text" maxlength="1" pattern="[0-9]" inputmode="numeric" autocomplete="off" required class="otp-digit" data-index="4">
            <input type="text" maxlength="1" pattern="[0-9]" inputmode="numeric" autocomplete="off" required class="otp-digit" data-index="5">
        </div>

        <input type="hidden" name="otp_code" id="otp-code-hidden" required>

        <button type="submit" class="otp-button" id="verify-button" disabled>
            <span class="button-text">Verifikasi</span>
        </button>
    </form>

    <div class="otp-footer">
        <div class="otp-timer" id="otp-timer">
            <span class="timer-icon">⏱️</span>
            <span class="timer-text" id="timer-text">Kode berlaku 15:00</span>
        </div>

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

    let timeLeft = 900; // 15 minutes

    inputs[0].focus();

    inputs.forEach((input, index) => {
        input.addEventListener('input', (e) => {
            const value = e.target.value;
            if (!/^\d*$/.test(value)) {
                e.target.value = '';
                return;
            }
            if (value.length === 1 && index < inputs.length - 1) {
                inputs[index + 1].focus();
            }
            checkAllFilled();
        });

        input.addEventListener('keydown', (e) => {
            if (e.key === 'Backspace' && input.value === '' && index > 0) {
                inputs[index - 1].focus();
            }
        });

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

    function startTimer() {
        const interval = setInterval(() => {
            if (timeLeft <= 0) {
                clearInterval(interval);
                timerText.textContent = 'Kode telah kedaluwarsa';
                return;
            }
            timeLeft--;
            const minutes = Math.floor(timeLeft / 60);
            const seconds = timeLeft % 60;
            timerText.textContent = `Kode berlaku ${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
        }, 1000);
    }

    startTimer();
})();
</script>
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
