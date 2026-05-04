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