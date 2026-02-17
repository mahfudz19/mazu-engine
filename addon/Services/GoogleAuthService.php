<?php

namespace Addon\Services;

use Google\Client;
use Google\Service\Oauth2;
use Exception;

class GoogleAuthService
{
    private $client;
    private $service;

    public function __construct()
    {
        $this->client = new Client();
        
        // Ambil konfigurasi dari .env
        $clientId = env('GOOGLE_CLIENT_ID');
        $clientSecret = env('GOOGLE_CLIENT_SECRET');
        $redirectUri = env('GOOGLE_REDIRECT_URI');

        if (!$clientId || !$clientSecret) {
            throw new Exception("Google OAuth Credentials not set in .env");
        }

        $this->client->setClientId($clientId);
        $this->client->setClientSecret($clientSecret);
        $this->client->setRedirectUri($redirectUri);
        
        // Scope Wajib: Email & Profile
        $this->client->addScope("email");
        $this->client->addScope("profile");
    }

    /**
     * Generate URL Login Google
     */
    public function getAuthUrl(): string
    {
        return $this->client->createAuthUrl();
    }

    /**
     * Tukar Authorization Code dengan Data User
     */
    public function handleCallback(string $code): array
    {
        // 1. Tukar Code -> Access Token
        $token = $this->client->fetchAccessTokenWithAuthCode($code);
        
        if (isset($token['error'])) {
            throw new Exception("Error fetching token: " . $token['error']);
        }

        // 2. Set Token ke Client
        $this->client->setAccessToken($token);

        // 3. Ambil Data User via Oauth2 Service
        $oauth2 = new Oauth2($this->client);
        $userInfo = $oauth2->userinfo->get();

        // 4. Validasi Domain (Wajib @inbitef.ac.id)
        $email = $userInfo->email;
        if (!str_ends_with($email, '@inbitef.ac.id')) {
             throw new Exception("Akses Ditolak: Hanya email @inbitef.ac.id yang diizinkan.");
        }

        return [
            'email' => $email,
            'name' => $userInfo->name,
            'picture' => $userInfo->picture,
            'google_id' => $userInfo->id,
            'token' => $token // Simpan jika perlu akses API lain nanti
        ];
    }
}