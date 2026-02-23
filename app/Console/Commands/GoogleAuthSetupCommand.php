<?php

namespace App\Console\Commands;

use App\Console\Contracts\CommandInterface;
use App\Core\Foundation\Application;
use App\Services\ConfigService;

class GoogleAuthSetupCommand implements CommandInterface
{
  public function __construct(
    private Application $app,
  ) {}

  public function getName(): string
  {
    return 'auth:google-setup';
  }

  public function getDescription(): string
  {
    return 'Setup scaffold Google OAuth (model, service, controller, middleware, routes, env).';
  }

  public function handle(array $arguments): int
  {
    [$dbConnection, $withRole, $mode, $apiDriver] = $this->parseArguments($arguments);

    if (!$this->validateDbConnection($dbConnection)) {
      echo color("ERROR:", "red") . " Koneksi database '{$dbConnection}' tidak ditemukan di config.\n";
      return 1;
    }

    $profile = $this->resolveAuthProfile($mode, $apiDriver);

    $this->info("Mulai setup Google Auth dengan koneksi DB: {$dbConnection}" . ($withRole ? ' (with role)' : '') . " [profil: {$profile}]");

    $this->setupEnvPlaceholders();
    $this->setupUserModel($dbConnection, $withRole);
    $this->setupGoogleAuthService();
    $this->setupAuthProfile($profile, $withRole);

    $this->printNextSteps($profile, $withRole);

    return 0;
  }

  private function parseArguments(array $arguments): array
  {
    $dbConnection = null;
    $withRole = false;
    $mode = 'web';
    $apiDriver = null;

    foreach ($arguments as $arg) {
      if (str_starts_with($arg, '--db=')) {
        $dbConnection = substr($arg, 5);
      } elseif ($arg === '--with-role') {
        $withRole = true;
      } elseif (str_starts_with($arg, '--mode=')) {
        $value = substr($arg, 7);
        if (in_array($value, ['web', 'api', 'hybrid'], true)) {
          $mode = $value;
        }
      } elseif (str_starts_with($arg, '--api-driver=')) {
        $value = substr($arg, 13);
        if (in_array($value, ['jwt', 'token'], true)) {
          $apiDriver = $value;
        }
      }
    }

    if ($dbConnection === null || $dbConnection === '') {
      /** @var ConfigService $config */
      $config = $this->app->getContainer()->resolve(ConfigService::class);
      $dbConnection = $config->get('database.default', 'mysql');
    }

    if ($mode !== 'web' && $apiDriver === null) {
      $apiDriver = 'jwt';
    }

    return [$dbConnection, $withRole, $mode, $apiDriver];
  }

  private function validateDbConnection(string $connection): bool
  {
    /** @var ConfigService $config */
    $config = $this->app->getContainer()->resolve(ConfigService::class);
    $connections = $config->get('database.connections', []);

    return isset($connections[$connection]);
  }

  private function resolveAuthProfile(string $mode, ?string $apiDriver): string
  {
    if ($mode === 'api') {
      return $apiDriver === 'token' ? 'api-token' : 'api-jwt';
    }

    if ($mode === 'hybrid') {
      return $apiDriver === 'token' ? 'hybrid-token' : 'hybrid-jwt';
    }

    return 'web-session';
  }

  private function setupAuthProfile(string $profile, bool $withRole): void
  {
    switch ($profile) {
      case 'api-jwt':
        $this->setupApiJwtScaffold($withRole);
        break;
      case 'api-token':
        $this->setupApiTokenScaffold($withRole);
        break;
      case 'hybrid-jwt':
        $this->setupWebSessionScaffold($withRole);
        $this->setupApiJwtScaffold($withRole);
        break;
      case 'hybrid-token':
        $this->setupWebSessionScaffold($withRole);
        $this->setupApiTokenScaffold($withRole);
        break;
      case 'web-session':
      default:
        $this->setupWebSessionScaffold($withRole);
        break;
    }
  }

  private function setupWebSessionScaffold(bool $withRole): void
  {
    $this->setupAuthController($withRole);
    $this->setupAuthMiddleware($withRole);
    $this->setupRoutes($withRole);
    $this->setupDashboardView();
  }

  private function setupApiJwtScaffold(bool $withRole): void
  {
    $this->info('[Profile] api-jwt belum diimplementasikan. Tidak ada scaffold tambahan yang dibuat.');
  }

  private function setupApiTokenScaffold(bool $withRole): void
  {
    $this->info('[Profile] api-token belum diimplementasikan. Tidak ada scaffold tambahan yang dibuat.');
  }

  private function setupEnvPlaceholders(): void
  {
    $root = __DIR__ . '/../../..';
    $files = [
      $root . '/.env' => false,
      $root . '/.env.example' => true,
    ];
    $keys = [
      'GOOGLE_CLIENT_ID' => '',
      'GOOGLE_CLIENT_SECRET' => '',
      'GOOGLE_REDIRECT_URI' => 'http://localhost:9000/auth/callback',
      'GOOGLE_ALLOWED_DOMAIN' => '',
    ];

    foreach ($files as $file => $isExample) {
      if (!file_exists($file)) {
        continue;
      }

      $original = file_get_contents($file);
      $updated = $original;

      foreach ($keys as $key => $value) {
        if (preg_match('/^#?\s*' . preg_quote($key, '/') . '\s*=/m', $updated)) {
          continue;
        }

        $lineValue = $isExample && $value !== '' ? $value : '';
        $line = $key . '=' . $lineValue . PHP_EOL;

        if ($updated === '' || str_ends_with($updated, "\n")) {
          $updated .= $line;
        } else {
          $updated .= PHP_EOL . $line;
        }
      }

      if ($updated !== $original) {
        file_put_contents($file, $updated);
      }
    }

    $this->info('[ENV] GOOGLE_* keys ensured in .env dan .env.example.');
  }

  private function setupUserModel(string $dbConnection, bool $withRole): void
  {
    $root = __DIR__ . '/../../..';
    $dir = $root . '/addon/Models';
    $path = $dir . '/UserModel.php';

    if (!is_dir($dir)) {
      mkdir($dir, 0755, true);
    }

    if (file_exists($path)) {
      $this->info('[Model] UserModel sudah ada, SKIPPED.');
      return;
    }

    $schemaLines = [
      "        'id' => ['type' => 'id', 'primary' => true, 'auto_increment' => true],",
      "        'email' => ['type' => 'string', 'nullable' => false],",
      "        'google_id' => ['type' => 'string', 'nullable' => true],",
      "        'name' => ['type' => 'string', 'nullable' => true],",
      "        'avatar' => ['type' => 'string', 'nullable' => true],",
      "        'is_active' => ['type' => 'boolean', 'nullable' => false, 'default' => true],",
      "        'last_login_at' => ['type' => 'datetime', 'nullable' => true],",
    ];

    if ($withRole) {
      $schemaLines[] = "        'role' => ['type' => 'string', 'nullable' => false, 'default' => 'admin'],";
    }

    $schema = implode("\n", $schemaLines) . "\n";

    $seedLines = [
      "    [",
      "      'email' => 'user@example.com',",
      "      'name' => 'Default User',",
      "      'google_id' => null,",
      "      'avatar' => null,",
      "      'is_active' => 1,",
      "      'last_login_at' => null,",
    ];

    if ($withRole) {
      $seedLines[] = "      'role' => 'super_admin',";
    }

    $seedLines[] = "    ],";
    $seedLines[] = "    [";
    $seedLines[] = "      'email' => 'admin@example.com',";
    $seedLines[] = "      'name' => 'Default Admin',";
    $seedLines[] = "      'google_id' => null,";
    $seedLines[] = "      'avatar' => null,";
    $seedLines[] = "      'is_active' => 1,";
    $seedLines[] = "      'last_login_at' => null,";

    if ($withRole) {
      $seedLines[] = "      'role' => 'admin',";
    }

    $seedLines[] = "    ],";

    $seed = implode("\n", $seedLines) . "\n";

    $template = <<<'PHP'
<?php

namespace Addon\Models;

use App\Core\Database\Model;

class UserModel extends Model
{
  protected ?string $connection = '{{CONNECTION}}';
  protected string $table = 'users';
  protected bool $timestamps = true;

  protected array $schema = [
{{SCHEMA}}  ];

  protected array $seed = [
{{SEED}}  ];

  public function all(): array
  {
    $stmt = $this->getDb()->prepare("SELECT * FROM {$this->table}");
    $stmt->execute();
    return $stmt->fetchAll();
  }

  public function find(string|int $id): ?array
  {
    $stmt = $this->getDb()->prepare("SELECT * FROM {$this->table} WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch();

    return $row === false ? null : $row;
  }

  public function updateById(string|int $id, array $data): bool
  {
    if (empty($data)) {
      return false;
    }

    $setParts = [];
    foreach ($data as $column => $value) {
      $setParts[] = "{$column} = :{$column}";
    }

    $sql = "UPDATE {$this->table} SET " . implode(', ', $setParts) . " WHERE id = :id";
    $data['id'] = $id;

    return $this->getDb()->query($sql, $data);
  }

  public function deleteById(string|int $id): bool
  {
    $sql = "DELETE FROM {$this->table} WHERE id = :id";
    return $this->getDb()->query($sql, ['id' => $id]);
  }

  public function findByEmail(string $email): ?array
  {
    $stmt = $this->getDb()->prepare("SELECT * FROM {$this->table} WHERE email = :email LIMIT 1");
    $stmt->execute(['email' => $email]);
    $row = $stmt->fetch();

    return $row === false ? null : $row;
  }

  public function touchLogin(string|int $id, ?string $name, ?string $avatar, ?string $googleId): bool
  {
    $data = [
      'last_login_at' => date('Y-m-d H:i:s'),
    ];

    if ($name !== null) {
      $data['name'] = $name;
    }

    if ($avatar !== null) {
      $data['avatar'] = $avatar;
    }

    if ($googleId !== null) {
      $data['google_id'] = $googleId;
    }

    return $this->updateById($id, $data);
  }
}

PHP;

    $content = str_replace(
      ['{{CONNECTION}}', '{{SCHEMA}}', '{{SEED}}'],
      [$dbConnection, $schema, $seed],
      $template
    );

    file_put_contents($path, $content);
    $this->info('[Model] UserModel dibuat di ' . $path . '.');
  }

  private function setupGoogleAuthService(): void
  {
    $root = __DIR__ . '/../../..';
    $dir = $root . '/addon/Services';
    $path = $dir . '/GoogleAuthService.php';

    if (!is_dir($dir)) {
      mkdir($dir, 0755, true);
    }

    if (file_exists($path)) {
      $this->info('[Service] GoogleAuthService sudah ada, SKIPPED.');
      return;
    }

    $template = <<<'PHP'
<?php

namespace Addon\Services;

use Google\Client;
use Google\Service\Oauth2;
use Exception;

class GoogleAuthService
{
  private Client $client;

  public function __construct()
  {
    $this->client = new Client();

    $clientId = env('GOOGLE_CLIENT_ID');
    $clientSecret = env('GOOGLE_CLIENT_SECRET');
    $redirectUri = env('GOOGLE_REDIRECT_URI');

    if (!$clientId || !$clientSecret) {
      throw new Exception("Google OAuth Credentials not set in .env");
    }

    $this->client->setClientId($clientId);
    $this->client->setClientSecret($clientSecret);
    if ($redirectUri) {
      $this->client->setRedirectUri($redirectUri);
    }

    $this->client->addScope('email');
    $this->client->addScope('profile');
  }

  public function getAuthUrl(): string
  {
    return $this->client->createAuthUrl();
  }

  public function handleCallback(string $code): array
  {
    $token = $this->client->fetchAccessTokenWithAuthCode($code);
    if (isset($token['error'])) {
      throw new Exception('Error fetching token: ' . $token['error']);
    }

    $this->client->setAccessToken($token);

    $oauth2 = new Oauth2($this->client);
    $userInfo = $oauth2->userinfo->get();

    $email = $userInfo->email;
    $allowedDomain = env('GOOGLE_ALLOWED_DOMAIN', '@inbitef.ac.id');

    if ($allowedDomain && !str_ends_with($email, $allowedDomain)) {
      throw new Exception('Akses Ditolak: Hanya email ' . $allowedDomain . ' yang diizinkan.');
    }

    return [
      'email' => $email,
      'name' => $userInfo->name,
      'picture' => $userInfo->picture,
      'google_id' => $userInfo->id,
      'token' => $token,
    ];
  }
}

PHP;

    file_put_contents($path, $template);
    $this->info('[Service] GoogleAuthService dibuat di ' . $path . '.');
  }

  private function setupAuthController(bool $withRole): void
  {
    $root = __DIR__ . '/../../..';
    $dir = $root . '/addon/Controllers';
    $path = $dir . '/AuthController.php';

    if (!is_dir($dir)) {
      mkdir($dir, 0755, true);
    }

    if (file_exists($path)) {
      $this->info('[Controller] AuthController sudah ada, SKIPPED.');
      return;
    }

    $template = <<<'PHP'
<?php

namespace Addon\Controllers;

use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\View\View;
use Addon\Services\GoogleAuthService;
use Addon\Models\UserModel;
use App\Services\SessionService;
use Exception;

class AuthController
{
  public function __construct(
    private GoogleAuthService $googleAuth,
    private UserModel $users,
    private SessionService $session,
  ) {}

  public function login(Request $request, Response $response)
  {
    try {
      $url = $this->googleAuth->getAuthUrl();
      return $response->redirect($url);
    } catch (Exception $e) {
      return $response
        ->setStatusCode(500)
        ->setContent('Error initializing login: ' . $e->getMessage());
    }
  }

  public function callback(Request $request, Response $response)
  {
    $code = $request->query['code'] ?? null;

    if (!$code) {
      return $response
        ->setStatusCode(400)
        ->setContent('Error: Authorization code not found');
    }

    try {
      $userData = $this->googleAuth->handleCallback($code);

      // Cek user di database (untuk menentukan apakah dia Admin/Privileged)
      $user = $this->users->findByEmail($userData['email']);

      $role = 'user';
      $dbId = null;

      if ($user) {
        // User ditemukan di DB (Admin/Super Admin/Staff)
        if (isset($user['is_active']) && !$user['is_active']) {
           return $response->setStatusCode(403)->setContent('Akun dinonaktifkan.');
        }

        $this->users->touchLogin($user['id'], $userData['name'] ?? null, $userData['picture'] ?? null, $userData['google_id'] ?? null);
        $dbId = $user['id'];
        $role = $user['role'] ?? 'admin';
      }

      // Simpan ke session
      $this->session->set('user', [
        'id' => $dbId,
        'email' => $userData['email'],
        'name' => $userData['name'],
        'avatar' => $userData['picture'],
        'google_id' => $userData['google_id'] ?? null,
        'role' => $role,
      ]);
      $this->session->set('is_logged_in', true);

      return $response->redirect('/dashboard');
    } catch (Exception $e) {
      return $response
        ->setStatusCode(500)
        ->setContent('Login Failed: ' . $e->getMessage());
    }
  }

  public function logout(Request $request, Response $response)
  {
    $this->session->destroy();
    return $response->redirect('/');
  }

  public function dashboard(Request $request, Response $response): View
  {
    $user = $this->session->get('user', []);
    $role = $user['role'] ?? 'User';

    return $response->renderPage(
      ['user' => $user, 'role' => $role],
      ['meta' => ['title' => 'Dashboard']]
    );
  }
PHP;

    if ($withRole) {
      $template .= <<<'PHP'


  public function superAdminTest(Request $request, Response $response)
  {
    return $response->json([
      'ok' => true,
      'page' => 'super_admin',
    ]);
  }

  public function adminTest(Request $request, Response $response)
  {
    return $response->json([
      'ok' => true,
      'page' => 'admin',
    ]);
  }
PHP;
    }

    $template .= "\n}\n";

    file_put_contents($path, $template);
    $this->info('[Controller] AuthController dibuat di ' . $path . '.');
  }

  private function setupAuthMiddleware(bool $withRole): void
  {
    $root = __DIR__ . '/../../..';
    $dir = $root . '/addon/Middleware';
    $authPath = $dir . '/AuthMiddleware.php';
    $rolePath = $dir . '/RoleMiddleware.php';

    if (!is_dir($dir)) {
      mkdir($dir, 0755, true);
    }

    if (!file_exists($authPath)) {
      $template = <<<'PHP'
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
      throw new AuthenticationException('Unauthenticated');
    }

    return $next($request);
  }
}

PHP;

      file_put_contents($authPath, $template);
      $this->info('[Middleware] AuthMiddleware dibuat di ' . $authPath . '.');
    } else {
      $this->info('[Middleware] AuthMiddleware sudah ada, SKIPPED.');
    }

    if ($withRole) {
      if (!file_exists($rolePath)) {
        $roleTemplate = <<<'PHP'
<?php

namespace Addon\Middleware;

use App\Core\Interfaces\MiddlewareInterface;
use App\Exceptions\AuthenticationException;
use App\Exceptions\AuthorizationException;
use App\Services\SessionService;

class RoleMiddleware implements MiddlewareInterface
{
  public function __construct(private SessionService $session) {}

  public function handle($request, \Closure $next, array $params = [])
  {
    if ($this->session->get('is_logged_in') !== true) {
      throw new AuthenticationException('Unauthenticated');
    }

    $user = $this->session->get('user', []);
    $role = is_array($user) ? ($user['role'] ?? null) : null;

    if (!empty($params)) {
      if (!$role || !in_array($role, $params, true)) {
        throw new AuthorizationException('Forbidden');
      }
    }

    return $next($request);
  }
}

PHP;

        file_put_contents($rolePath, $roleTemplate);
        $this->info('[Middleware] RoleMiddleware dibuat di ' . $rolePath . '.');
      } else {
        $this->info('[Middleware] RoleMiddleware sudah ada, SKIPPED.');
      }
    }
  }

  private function setupRoutes(bool $withRole): void
  {
    $root = __DIR__ . '/../../..';
    $dir = $root . '/addon/Router';
    $path = $dir . '/index.php';

    if (!is_dir($dir)) {
      mkdir($dir, 0755, true);
    }

    if (!file_exists($path)) {
      $routes = <<<'PHP'
<?php

use Addon\Controllers\AuthController;

$router->get('/', function (\App\Core\Http\Request $req, \App\Core\Http\Response $res) {
  return $res->renderPage([]);
});

$router->get('/login', [AuthController::class, 'login']);
$router->get('/auth/callback', [AuthController::class, 'callback']);
$router->get('/logout', [AuthController::class, 'logout']);

$router->group(['middleware' => ['auth']], function ($router) {
  $router->get('/dashboard', [AuthController::class, 'dashboard'], ['auth']);
PHP;

      if ($withRole) {
        $routes .= "\n  " . '$router->get(\'/api/super-admin-test\', [AuthController::class, \'superAdminTest\'], [\'role:super_admin\']);';
        $routes .= "\n  " . '$router->get(\'/api/admin-test\', [AuthController::class, \'adminTest\'], [\'role:admin\']);';
      }

      $routes .= "\n});\n";

      file_put_contents($path, $routes);
      $this->info('[Routes] File router addon dibuat dan rute Google Auth + dashboard ditambahkan.');
      return;
    }

    $content = file_get_contents($path);
    $updated = $content;

    if (strpos($updated, "'/login'") === false) {
      $updated .= "\n" . "\$router->get('/login', [\\Addon\\Controllers\\AuthController::class, 'login']);";
    }

    if (strpos($updated, "'/auth/callback'") === false) {
      $updated .= "\n" . "\$router->get('/auth/callback', [\\Addon\\Controllers\\AuthController::class, 'callback']);";
    }

    if (strpos($updated, "'/logout'") === false) {
      $updated .= "\n" . "\$router->get('/logout', [\\Addon\\Controllers\\AuthController::class, 'logout']);" . "\n";
    }

    if (strpos($updated, "'/dashboard'") === false) {
      $updated .= "\n" . "\$router->get('/dashboard', [\\Addon\\Controllers\\AuthController::class, 'dashboard'], ['auth']);";
    }

    if ($withRole) {
      if (strpos($updated, "'/api/super-admin-test'") === false) {
        $updated .= "\n" . "\$router->get('/api/super-admin-test', [\\Addon\\Controllers\\AuthController::class, 'superAdminTest'], ['auth','role:super_admin']);";
      }
      if (strpos($updated, "'/api/admin-test'") === false) {
        $updated .= "\n" . "\$router->get('/api/admin-test', [\\Addon\\Controllers\\AuthController::class, 'adminTest'], ['auth', 'role:admin']);";
      }
    }

    if ($updated !== $content) {
      file_put_contents($path, $updated);
      $this->info('[Routes] Rute Google Auth ditambahkan ke router addon.');
    } else {
      $this->info('[Routes] Rute Google Auth sudah ada, SKIPPED.');
    }
  }

  private function setupDashboardView(): void
  {
    $root = __DIR__ . '/../../..';
    $dir = $root . '/addon/Views';
    $path = $dir . '/dashboard.php';

    if (!is_dir($dir)) {
      mkdir($dir, 0755, true);
    }

    if (file_exists($path)) {
      $this->info('[View] dashboard.php sudah ada, SKIPPED.');
      return;
    }

    $template = <<<'PHP'
<?php
/**
 * Dashboard view default untuk Mazu Google Auth scaffold.
 * Variabel tersedia:
 * - $user: array data user dari session
 * - $role: string role user
 */
?>
<div class="mazu-container">
  <section class="mazu-hero">
    <h1 class="mazu-title">Dashboard</h1>
    <p class="mazu-subtitle">
      Anda berhasil login dengan Google. Berikut informasi akun Anda.
    </p>
  </section>

  <div class="mazu-grid">
    <div class="mazu-card">
      <div class="mazu-card-icon">
        <i class="bi bi-person-circle"></i>
      </div>
      <h3 class="mazu-card-title">Profil Pengguna</h3>
      <p class="mazu-card-desc">
        <strong>Nama:</strong> <?= htmlspecialchars($user['name'] ?? 'Guest', ENT_QUOTES, 'UTF-8') ?><br>
        <strong>Email:</strong> <?= htmlspecialchars($user['email'] ?? '-', ENT_QUOTES, 'UTF-8') ?><br>
        <strong>Role:</strong> <?= htmlspecialchars($role ?? 'GUEST', ENT_QUOTES, 'UTF-8') ?>
      </p>
    </div>
  </div>
</div>
PHP;

    file_put_contents($path, $template);
    $this->info('[View] dashboard.php dibuat di ' . $path . '.');
  }

  private function printNextSteps(string $profile, bool $withRole): void
  {
    $this->info('Setup Google Auth selesai untuk profil: ' . $profile . ($withRole ? ' (with role)' : ''));
    echo " - Lengkapi nilai GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET, GOOGLE_REDIRECT_URI di .env.\n";
    echo " - (Opsional) Set GOOGLE_ALLOWED_DOMAIN jika ingin membatasi domain email.\n";
    echo " - Jalankan 'php mazu migrate' untuk membuat tabel users jika perlu.\n";
    echo " - Gunakan middleware 'auth' pada rute yang ingin dilindungi.\n";
  }

  private function info(string $message): void
  {
    echo color('INFO:', 'green') . ' ' . $message . "\n";
  }
}
