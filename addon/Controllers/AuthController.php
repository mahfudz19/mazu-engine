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
        private UserModel $users,
        private SessionService $session,
        private EmailVerificationModel $emailVerifications,
        private PasswordResetTokenModel $passwordResetTokens,
        private LoginNotificationModel $loginNotifications,
        private EmailService $emailService,
    ) {}

    /**
     * Minimum password length
     */
    private const MIN_PASSWORD_LENGTH = 8;

    /**
     * Hash password menggunakan bcrypt
     *
     * @param string $password Plain text password
     * @return string Hashed password
     */
    private function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
    }

    /**
     * Verify password against hash
     *
     * @param string $password Plain text password
     * @param string $hash Hashed password
     * @return bool True if password matches
     */
    private function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    /**
     * Validate password strength
     *
     * @param string $password Password to validate
     * @return array{valid: bool, errors: array<string>} Validation result
     */
    private function validatePassword(string $password): array
    {
        $errors = [];

        if (strlen($password) < self::MIN_PASSWORD_LENGTH) {
            $errors[] = "Password minimal " . self::MIN_PASSWORD_LENGTH . " karakter";
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Check if user is logged in
     */
    private function check(): bool
    {
        $userId = $this->session->get('auth.user_id');
        return $userId !== null;
    }

    /**
     * Get authenticated user (returns array)
     */
    private function user(): ?array
    {
        $userId = $this->session->get('auth.user_id');

        if ($userId === null) {
            return null;
        }

        return $this->users->find($userId);
    }

    /**
     * Login user dan simpan session
     */
    private function loginSession(array $user): void
    {
        $this->session->set('auth.user_id', $user['id']);
        $this->session->set('auth.user_email', $user['email']);
        $this->session->set('auth.user_name', $user['name']);
        $this->session->set('auth.user_avatar', $user['avatar'] ?? null);

        if (isset($user['role'])) {
            $this->session->set('auth.user_role', $user['role']);
        }
        $this->session->set('is_logged_in', true);
    }

    /**
     * Logout user
     */
    private function logoutSession(): void
    {
        $this->session->destroy();
    }

    /**
     * Show login form
     */
    public function showLogin(Request $request, Response $response): View | RedirectResponse
    {
        // If already logged in, redirect to dashboard
        if ($this->check()) {
            return $response->redirect('/dashboard');
        }

        return $response->renderPage([], ['path' => '/login', 'meta' => ['title' => 'Login | ' . env('APP_NAME')]]);
    }

    /**
     * Process login (Email/Password)
     */
    public function login(Request $request, Response $response): View | RedirectResponse
    {
        $email = $request->input('email');
        $password = $request->input('password');

        if (!$email || !$password) {
            return $response->redirect('/login?error=Email+dan+password+harus+diisi');
        }

        // Find user by email
        $user = $this->users->findByEmail($email);

        if (!$user) {
            return $response->redirect('/login?error=Email+tidak+ditemukan');
        }

        // If user has google_id, they registered via Google - no password set
        if (!empty($user['google_id'])) {
            return $response->redirect('/login?error=Akun+terdaftar+dengan+Google.+Silakan+login+menggunakan+Google');
        }

        // Verify password
        if (!$this->verifyPassword($password, $user['password'])) {
            return $response->redirect('/login?error=Password+salah');
        }

        // Check if user is active
        if (!$user['is_active']) {
            // User not active - resend OTP and redirect to verify
            $this->sendOtpToUser($user['id'], $user['email']);
            return $response->redirect('/verify-otp?email=' . urlencode($user['email']) . '&info=Akun+belum+terverifikasi.+Silakan+verifikasi+email+Anda');
        }

        // Update last login
        $this->users->updateLastLogin($user['id']);

        // Login successful - save session
        $this->loginSession($user);

        // Send login notification email
        $this->sendLoginNotification($user);

        return $response->redirect('/dashboard');
    }

    /**
     * Show register form
     */
    public function showRegister(Request $request, Response $response): View | RedirectResponse
    {
        // If already logged in, redirect to dashboard
        if ($this->check()) {
            return $response->redirect('/dashboard');
        }

        return $response->renderPage([], ['path' => '/register', 'meta' => ['title' => 'Register | ' . env('APP_NAME')]]);
    }

    /**
     * Process register (Manual with OTP verification)
     */
    public function register(Request $request, Response $response): View | RedirectResponse
    {
        $email = $request->input('email');
        $password = $request->input('password');
        $name = $request->input('name');
        $passwordConfirmation = $request->input('password_confirmation');

        // Validation
        if (!$email || !$password || !$name) {
            return $response->redirect('/register?error=Semua+field+harus+diisi');
        }

        if ($password !== $passwordConfirmation) {
            return $response->redirect('/register?error=Password+konfirmasi+tidak+cocok');
        }

        // Validate password strength
        $passwordValidation = $this->validatePassword($password);
        if (!$passwordValidation['valid']) {
            return $response->redirect('/register?error=' . urlencode(implode(', ', $passwordValidation['errors'])));
        }

        // Role handling
        $role = $request->input('role', 'user');
        if (!in_array($role, ['super-admin', 'admin', 'user'])) {
            $role = 'user';
        }

        // Check if email already exists
        $existingUser = $this->users->findByEmail($email);
        if ($existingUser) {
            return $response->redirect('/register?error=Email+sudah+terdaftar');
        }

        // Prepare user data (is_active = false, waiting for OTP verification)
        $userData = [
            'email' => $email,
            'password' => $this->hashPassword($password),
            'name' => $name,
            'avatar' => null,
            'is_active' => 0, // Not active until OTP verified
        ];

        // Add role if schema has role field
        if (isset($this->users->getSchema()['role'])) {
            $userData['role'] = $role;
        }

        // Create user with try-catch
        try {
            $userId = $this->users->create($userData);
            $newUser = $this->users->find($userId);

            if (!$newUser) {
                throw new Exception('Gagal membuat user');
            }

            // Send OTP to user's email
            $otpCode = OtpGenerator::generate();
            $this->emailVerifications->createOtp($userId, $email, $otpCode, 15);

            // Send email with OTP
            $this->emailService->sendOtpVerification($email, $name, $otpCode, 15);

            // Store user ID in session for OTP verification
            $this->session->set('auth.pending_user_id', $userId);
            $this->session->set('auth.pending_user_email', $email);

            // Redirect to OTP sent page
            return $response->redirect('/otp-sent?email=' . urlencode($email));
        } catch (\Exception $e) {
            return $response->redirect('/register?error=' . urlencode($e->getMessage()));
        }
    }

    /**
     * Logout
     */
    public function logout(Request $request, Response $response): View | RedirectResponse
    {
        $this->logoutSession();
        return $response->redirect('/login');
    }

    /**
     * Show OTP verification page
     */
    public function showVerifyOtp(Request $request, Response $response): View | RedirectResponse
    {
        $email = $request->query['email'] ?? null;
        $info = $request->query['info'] ?? null;

        if (!$email) {
            return $response->redirect('/register');
        }

        return $response->renderPage([
            'email' => $email,
            'info' => $info,
        ], ['path' => '/verify-otp', 'meta' => ['title' => 'Verifikasi Email | ' . env('APP_NAME')]]);
    }

    /**
     * Process OTP verification
     */
    public function verifyOtp(Request $request, Response $response): View | RedirectResponse
    {
        $email = $request->input('email');
        $otpCode = $request->input('otp_code');

        if (!$email || !$otpCode) {
            return $response->redirect('/verify-otp?email=' . urlencode($email ?? '') . '&error=Email+dan+kode+OTP+harus+diisi');
        }

        // Find user by email
        $user = $this->users->findByEmail($email);

        if (!$user) {
            return $response->redirect('/verify-otp?email=' . urlencode($email) . '&error=User+tidak+ditemukan');
        }

        // Verify OTP
        $result = $this->emailVerifications->verifyOtp($user['id'], $otpCode);

        if (!$result['valid']) {
            return $response->redirect('/verify-otp?email=' . urlencode($email) . '&error=' . urlencode($result['message']));
        }

        // Activate user account
        $this->users->updateById($user['id'], [
            'is_active' => 1,
        ]);

        // Invalidate all other OTPs
        $this->emailVerifications->invalidateAll($user['id']);

        // Auto-login after verification
        $this->loginSession($user);

        // Send login notification
        $this->sendLoginNotification($user);

        return $response->redirect('/dashboard?success=Email+berhasil+diverifikasi');
    }

    /**
     * Resend OTP
     */
    public function resendOtp(Request $request, Response $response): View | RedirectResponse
    {
        $email = $request->query['email'] ?? null;

        if (!$email) {
            return $response->redirect('/register');
        }

        $user = $this->users->findByEmail($email);

        if (!$user) {
            return $response->redirect('/register?error=User+tidak+ditemukan');
        }

        // Check if user already verified
        if ($user['is_active']) {
            return $response->redirect('/login?info=Akun+sudah+aktif.+Silakan+login');
        }

        // Send new OTP
        $this->sendOtpToUser($user['id'], $user['email']);

        return $response->redirect('/otp-sent?email=' . urlencode($email) . '&success=Kode+OTP+telah+dikirim+kembali');
    }

    /**
     * Show OTP sent page
     */
    public function showOtpSent(Request $request, Response $response): View | RedirectResponse
    {
        $email = $request->query['email'] ?? null;
        $success = $request->query['success'] ?? null;

        if (!$email) {
            return $response->redirect('/register');
        }

        return $response->renderPage([
            'email' => $email,
            'success' => $success,
        ], ['path' => '/otp-sent', 'meta' => ['title' => 'Email Terkirim | ' . env('APP_NAME')]]);
    }

    /**
     * Google OAuth callback
     */
    public function googleCallback(Request $request, Response $response): View | RedirectResponse
    {
        $code = $request->query['code'] ?? null;

        if (!$code) {
            return $response->redirect('/login?error=Authorization+code+tidak+ditemukan');
        }

        try {
            // Exchange code for token
            $client = new \Google_Client();
            $client->setClientId(env('GOOGLE_CLIENT_ID'));
            $client->setClientSecret(env('GOOGLE_CLIENT_SECRET'));
            $client->setRedirectUri(env('GOOGLE_REDIRECT_URI'));

            $token = $client->fetchAccessTokenWithAuthCode($code);

            if (isset($token['error'])) {
                throw new Exception($token['error']);
            }

            // Get user info from Google
            $client->setAccessToken($token);
            $oauth2 = new \Google_Service_Oauth2($client);
            $googleUser = $oauth2->userinfo->get();

            // Check if user exists by google_id
            $existingUser = $this->users->findByEmail($googleUser->email);

            if ($existingUser) {
                // User exists - check if already linked to Google
                if (empty($existingUser['google_id'])) {
                    // Link Google ID to existing user
                    $this->users->updateById($existingUser['id'], [
                        'google_id' => $googleUser->id,
                        'avatar_url' => $googleUser->picture,
                    ]);
                }

                // Login existing user
                $this->loginSession($existingUser);
                $this->sendLoginNotification($existingUser);
            } else {
                // Check domain restriction
                $allowedDomain = env('GOOGLE_ALLOWED_DOMAIN');
                if ($allowedDomain) {
                    $domain = substr(strrchr($googleUser->email, "@"), 1);
                    if ($domain !== ltrim($allowedDomain, '@')) {
                        return $response->redirect('/login?error=Domain+email+tidak+diizinkan.+Hanya+' . $allowedDomain);
                    }
                }

                // Create new user from Google
                $userData = [
                    'email' => $googleUser->email,
                    'password' => null, // No password for Google users
                    'name' => $googleUser->name,
                    'avatar' => $googleUser->picture,
                    'is_active' => 1, // Google verified email, so auto-activate
                    'google_id' => $googleUser->id,
                    'avatar_url' => $googleUser->picture,
                ];

                // Add role if schema has role field
                if (isset($this->users->getSchema()['role'])) {
                    $userData['role'] = 'user';
                }

                $userId = $this->users->create($userData);
                $newUser = $this->users->find($userId);

                // Auto-login
                $this->loginSession($newUser);
                $this->sendLoginNotification($newUser);
            }

            return $response->redirect('/dashboard?success=Login+berhasil+dengan+Google');
        } catch (Exception $e) {
            return $response->redirect('/login?error=' . urlencode('Google OAuth error: ' . $e->getMessage()));
        }
    }

    /**
     * Send OTP to user
     */
    private function sendOtpToUser(int $userId, string $email): void
    {
        // Invalidate old OTPs
        $this->emailVerifications->invalidateAll($userId);

        // Generate new OTP
        $otpCode = OtpGenerator::generate();

        // Create OTP record
        $this->emailVerifications->createOtp($userId, $email, $otpCode, 15);

        // Get user name
        $user = $this->users->find($userId);
        $name = $user['name'] ?? $email;

        // Send email
        $this->emailService->sendOtpVerification($email, $name, $otpCode, 15);
    }

    /**
     * Send login notification email
     */
    private function sendLoginNotification(array $user): void
    {
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        $loginAt = date('Y-m-d H:i:s');

        // Log to database
        $this->loginNotifications->logLogin(
            $user['id'],
            $user['email'],
            $ipAddress,
            $userAgent,
            $loginAt
        );

        // Send notification email
        $this->emailService->sendLoginNotification(
            $user['email'],
            $user['name'] ?? $user['email'],
            $ipAddress,
            $userAgent,
            $loginAt
        );
    }

    /**
     * Show forgot password form
     */
    public function showForgotPassword(Request $request, Response $response): View | RedirectResponse
    {
        return $response->renderPage([], ['path' => '/password/forgot', 'meta' => ['title' => 'Lupa Password | ' . env('APP_NAME')]]);
    }

    /**
     * Send password reset link
     */
    public function sendResetLink(Request $request, Response $response): View | RedirectResponse
    {
        $email = $request->input('email');

        if (!$email) {
            return $response->redirect('/password/forgot?Email+harus+diisi');
        }

        $user = $this->users->findByEmail($email);

        if (!$user) {
            // For security, show same success message even if email not found
            return $response->renderPage(
                ['message' => 'Jika email terdaftar, link reset password telah dikirim'],
                ['path' => '/password/forgot', 'meta' => ['title' => 'Lupa Password | ' . env('APP_NAME')]]
            );
        }

        // If user registered with Google, they don't have password
        if (!empty($user['google_id'])) {
            return $response->redirect('/password/forgot?Akun+Anda+terdaftar+dengan+Google.+Silakan+reset+password+melalui+Google');
        }

        // Generate reset token
        $token = $this->passwordResetTokens->generateToken();
        $this->passwordResetTokens->createToken($user['id'], $token, 60);

        // Build reset URL
        $resetUrl = rtrim(env('APP_URL', 'http://localhost:8000'), '/') . '/password/reset'  . '?email=' . urlencode($email) . '&token=' . $token;

        // Send email
        $this->emailService->sendPasswordReset(
            $user['email'],
            $user['name'] ?? $user['email'],
            $resetUrl,
            60
        );

        return $response->renderPage([
            'message' => 'Jika email terdaftar, link reset password telah dikirim',
        ], ['path' => '/password/forgot', 'meta' => ['title' => 'Lupa Password | ' . env('APP_NAME')]]);
    }

    /**
     * Show reset password form
     */
    public function showResetPassword(Request $request, Response $response): View | RedirectResponse
    {
        $token = $request->query['token'] ?? null;
        $email = $request->query['email'] ?? null;

        if (!$token) {
            return $response->redirect('/password/forgot');
        }

        // Validate token
        $tokenData = $this->passwordResetTokens->findValidToken($token);

        if (!$tokenData || $tokenData['user_id'] !== $this->users->findByEmail($email)['id']) {
            return $response->redirect('/password/reset?error=Link+reset+password+tidak+valid+atau+telah+kedaluwarsa');
        }

        return $response->renderPage(
            ['token' => $token, 'email' => $email,],
            ['meta' => ['title' => 'Reset Password | ' . env('APP_NAME')]]
        );
    }

    /**
     * Process reset password
     */
    public function resetPassword(Request $request, Response $response): View | RedirectResponse
    {
        $email = $request->input('email');
        $password = $request->input('password');
        $passwordConfirmation = $request->input('password_confirmation');
        $token = $request->input('token') ?? null;

        if ($password !== $passwordConfirmation) {
            return $response->redirect('/password/reset?Password+konfirmasi+tidak+cocok');
        }

        // Validate new password
        $passwordValidation = $this->validatePassword($password);
        if (!$passwordValidation['valid']) {
            return $response->redirect('/password/reset?' . urlencode(implode(', ', $passwordValidation['errors'])));
        }

        // Validate token
        $tokenData = $this->passwordResetTokens->findValidToken($token);

        if (!$tokenData) {
            return $response->redirect('/password/reset?error=Link+reset+password+tidak+valid+atau+telah+kedaluwarsa');
        }

        $user = $this->users->findByEmail($email);

        if (!$user || $user['id'] !== $tokenData['user_id']) {
            return $response->redirect('/password/reset?Email+tidak+valid');
        }

        // Update password
        $this->users->updateById($user['id'], ['password' => $this->hashPassword($password)]);

        // Invalidate all reset tokens
        $this->passwordResetTokens->invalidateAll($user['id']);

        return $response->renderPage(
            ['message' => 'Password berhasil direset. Silakan login dengan password baru',],
            ['path' => '/password/reset', 'meta' => ['title' => 'Password Direset | ' . env('APP_NAME')]]
        );
    }
}
