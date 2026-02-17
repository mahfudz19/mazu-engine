<?php

namespace Addon\Controllers;

use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\View\View;
use Addon\Services\GoogleAuthService;
use Addon\Models\AppRolesModel;
use Exception;

class AuthController
{
  public function __construct(private AppRolesModel $roleModel) {}

    public function index(Request $request, Response $response): View
    {
        return $response->renderPage([]);
    }

    /**
     * Redirect user ke halaman login Google
     */
    public function login(Request $request, Response $response)
    {
        try {
            $service = new GoogleAuthService();
            return $response->redirect($service->getAuthUrl());
        } catch (Exception $e) {
            return $response->setContent("Error initializing login: " . $e->getMessage())->setStatusCode(500);
        }
    }

    /**
     * Handle callback dari Google setelah user login
     */
    public function callback(Request $request, Response $response)
    {
        $code = $request->query['code'] ?? null;
        
        if (!$code) {
             return $response->setContent("Error: Authorization code not found")->setStatusCode(400);
        }

        try {
            // 1. Tukar code dengan data user
            $service = new GoogleAuthService();
            $userData = $service->handleCallback($code);

            // 2. Cek Role user di database
            // Gunakan $this->roleModel yang sudah di-inject
            $userRole = $this->roleModel->findByEmail($userData['email']);

            // 3. Mulai Session (jika belum aktif)
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }

      // 4. Simpan data user ke session
      $_SESSION['user'] = $userData;

      // Jika email ada di tabel app_roles, gunakan role tersebut.
      // Jika tidak, set sebagai 'PROPOSER' (pengusul agenda biasa).
      $_SESSION['role'] = $userRole ? $userRole['role'] : 'PROPOSER';

      $_SESSION['is_logged_in'] = true;

      // 5. Redirect ke dashboard
      // Pastikan route /dashboard sudah ada
      return $response->redirect('/dashboard');
    } catch (Exception $e) {
      return $response->setContent("Login Failed: " . $e->getMessage())->setStatusCode(500);
    }
  }

  /**
   * Logout user
   */
  public function logout(Request $request, Response $response)
  {
    if (session_status() === PHP_SESSION_NONE) {
      session_start();
    }

    // Hapus semua session
    session_destroy();

    // Redirect ke halaman utama
    return $response->redirect('/');
  }
}
