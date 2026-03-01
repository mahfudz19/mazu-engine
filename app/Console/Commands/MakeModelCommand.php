<?php

namespace App\Console\Commands;

use App\Core\Foundation\Application;
use App\Console\Contracts\CommandInterface;
use Doctrine\Inflector\Inflector;
use Doctrine\Inflector\InflectorFactory;

class MakeModelCommand implements CommandInterface
{
    private Inflector $inflector;

    public function __construct()
    {
        $this->inflector = InflectorFactory::create()->build();
    }

    public function getName(): string
    {
        return 'make:model';
    }

    public function getDescription(): string
    {
        return 'Membuat model baru di addon/Models';
    }

    public function handle(array $arguments): int
    {
        $name = $arguments[0] ?? null;
        if (!$name) {
            echo color("Error: Nama model harus diisi.\n", "red");
            return 1;
        }

        $name = ucfirst($name);

        if (!str_ends_with($name, 'Model')) {
            $name .= 'Model';
        }

        // Pastikan folder addon/Models ada
        $modelsDir = __DIR__ . '/../../../addon/Models';
        if (!is_dir($modelsDir)) {
            if (!mkdir($modelsDir, 0755, true)) {
                echo color("Error: Tidak dapat membuat folder addon/Models\n", "red");
                return 1;
            }
        }

        $path = $modelsDir . "/{$name}.php";

        if (file_exists($path)) {
            echo color("Error: Model sudah ada!\n", "red");
            return 1;
        }

        // SPECIAL TEMPLATES
        $baseName = str_replace(['Model', 'model'], '', $name);
        $template = $this->getSpecialTemplate($baseName, $name);

        if ($template === null) {
            // Default template untuk model biasa
            $tableName = $this->pluralize(strtolower($baseName));
            $template = $this->getDefaultTemplate($name, $tableName);
        }

        $content = $template;

        // Pastikan folder ada sebelum file_put_contents
        $dir = dirname($path);
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0755, true)) {
                echo color("Error: Tidak dapat membuat folder untuk model\n", "red");
                return 1;
            }
        }

        if (file_put_contents($path, $content) === false) {
            echo color("Error: Gagal membuat file model\n", "red");
            return 1;
        }

        echo color("SUCCESS:", "green") . " Model dibuat di " . color($path, "blue") . "\n";

        return 0;
    }

    private function getSpecialTemplate(string $baseName, string $name): ?string
    {
        $specialTemplates = [
            'Queue' => $this->getQueueModelTemplate($name),
            'User' => $this->getUserModelTemplate($name),
        ];

        $template = $specialTemplates[$baseName] ?? null;

        if ($template !== null) {
            // Lakukan placeholder replacement untuk special templates
            return str_replace('{{CLASS_NAME}}', $name, $template);
        }

        return null;
    }

    private function getQueueModelTemplate(string $name): string
    {
        return <<<'PHP'
<?php

namespace Addon\Models;

use App\Core\Database\Model;

class {{CLASS_NAME}} extends Model
{
    protected ?string $connection = null;
    protected string $table = 'queues';
    protected bool $timestamps = true;

    protected array $schema = [
        // Field wajib untuk queue system - TIDAK BOLEH DIHAPUS
        'id' => ['type' => 'id', 'primary' => true, 'auto_increment' => true],
        'queue' => ['type' => 'string', 'nullable' => false, 'default' => 'default'],
        'payload' => ['type' => 'longtext', 'nullable' => false],
        'attempts' => ['type' => 'int', 'nullable' => false, 'default' => 0],
        'reserved_at' => ['type' => 'bigint', 'nullable' => true],
        'available_at' => ['type' => 'bigint', 'nullable' => false],

        // Field opsional untuk progress tracking - BOLEH DIHAPUS jika tidak perlu
        'status' => ['type' => 'enum', 'values' => ['pending', 'processing', 'success', 'failed'], 'nullable' => false, 'default' => 'pending'],
        'progress' => ['type' => 'int', 'nullable' => false, 'default' => 0],
        'current_step' => ['type' => 'string', 'nullable' => true],
        'error_message' => ['type' => 'text', 'nullable' => true],
        'completed_at' => ['type' => 'bigint', 'nullable' => true],

        // Tambahkan custom fields untuk project Anda di sini
        // Contoh:
        // 'priority' => ['type' => 'enum', 'values' => ['low', 'medium', 'high'], 'default' => 'medium'],
        // 'retry_count' => ['type' => 'int', 'default' => 0],
        // 'duration' => ['type' => 'bigint', 'nullable' => true],
    ];


    protected array $seed = [];

    public function getPendingJobsCount(string $queue = 'default'): int
    {
        $stmt = $this->getDb()->prepare(
            "SELECT COUNT(*) FROM {$this->table} 
             WHERE queue = :queue AND reserved_at IS NULL AND available_at <= :now"
        );
        $stmt->execute(['queue' => $queue, 'now' => time()]);
        return (int)$stmt->fetchColumn();
    }

    public function getFailedJobs(): array
    {
        $stmt = $this->getDb()->prepare(
            "SELECT * FROM {$this->table} 
             WHERE attempts >= 3 ORDER BY created_at DESC"
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getQueueStats(): array
    {
        $sql = "
            SELECT 
                queue,
                COUNT(*) as total,
                SUM(CASE WHEN reserved_at IS NULL THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN reserved_at IS NOT NULL THEN 1 ELSE 0 END) as processing,
                SUM(CASE WHEN attempts >= 3 THEN 1 ELSE 0 END) as failed
            FROM {$this->table} 
            GROUP BY queue
        ";
        
        $stmt = $this->getDb()->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    // Basic CRUD methods
    public function all(): array
    {
        $stmt = $this->getDb()->prepare("SELECT * FROM {$this->table} ORDER BY created_at DESC");
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

    public function create(array $data): bool
    {
        if (empty($data)) return false;
        
        $columns = array_keys($data);
        $placeholders = array_map(fn($col) => ':' . $col, $columns);
        
        $sql = "INSERT INTO {$this->table} (" . implode(', ', $columns) . ")
                VALUES (" . implode(', ', $placeholders) . ")";
        
        return $this->getDb()->query($sql, $data);
    }

    public function updateById(string|int $id, array $data): bool
    {
        if (empty($data)) return false;
        
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
}
PHP;
    }

    private function getUserModelTemplate(string $name): string
    {
        return <<<'PHP'
<?php

namespace Addon\Models;

use App\Core\Database\Model;

class {{CLASS_NAME}} extends Model
{
    protected ?string $connection = null;
    protected string $table = 'users';
    protected bool $timestamps = true;

    protected array $schema = [
        'id' => ['type' => 'id', 'primary' => true, 'auto_increment' => true],
        'email' => ['type' => 'string', 'nullable' => false, 'unique' => true],
        'name' => ['type' => 'string', 'nullable' => false],
        'avatar' => ['type' => 'string', 'nullable' => true],
        'google_id' => ['type' => 'string', 'nullable' => true, 'unique' => true],
        'role' => ['type' => 'enum', 'values' => ['user', 'admin', 'super_admin'], 'default' => 'user'],
        'is_active' => ['type' => 'boolean', 'default' => true],
        'last_login_at' => ['type' => 'datetime', 'nullable' => true],
        'created_at' => ['type' => 'datetime', 'nullable' => false],
        'updated_at' => ['type' => 'datetime', 'nullable' => false],
    ];

    protected array $seed = [];

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->getDb()->prepare("SELECT * FROM {$this->table} WHERE email = :email LIMIT 1");
        $stmt->execute(['email' => $email]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function findByGoogleId(string $googleId): ?array
    {
        $stmt = $this->getDb()->prepare("SELECT * FROM {$this->table} WHERE google_id = :google_id LIMIT 1");
        $stmt->execute(['google_id' => $googleId]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function touchLogin(int $id, ?string $name = null, ?string $avatar = null, ?string $googleId = null): bool
    {
        $data = ['last_login_at' => date('Y-m-d H:i:s')];
        
        if ($name) $data['name'] = $name;
        if ($avatar) $data['avatar'] = $avatar;
        if ($googleId) $data['google_id'] = $googleId;
        
        return $this->updateById($id, $data);
    }

    public function createFromGoogle(array $userData): bool
    {
        return $this->create([
            'email' => $userData['email'],
            'name' => $userData['name'] ?? null,
            'avatar' => $userData['avatar'] ?? null,
            'google_id' => $userData['google_id'] ?? null,
            'role' => 'user',
            'is_active' => true,
        ]);
    }

    // Standard CRUD methods...
    public function all(): array
    {
        $stmt = $this->getDb()->prepare("SELECT * FROM {$this->table} ORDER BY created_at DESC");
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

    public function create(array $data): bool
    {
        if (empty($data)) return false;
        
        $columns = array_keys($data);
        $placeholders = array_map(fn($col) => ':' . $col, $columns);
        
        $sql = "INSERT INTO {$this->table} (" . implode(', ', $columns) . ")
                VALUES (" . implode(', ', $placeholders) . ")";
        
        return $this->getDb()->query($sql, $data);
    }

    public function updateById(string|int $id, array $data): bool
    {
        if (empty($data)) return false;
        
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
}
PHP;
    }

    private function getDefaultTemplate(string $name, string $tableName): string
    {
        // Template default yang sudah ada
        $template = <<<'PHP'
<?php

namespace Addon\Models;

use App\Core\Database\Model;

class {{CLASS_NAME}} extends Model
{
    protected ?string $connection = null;
    protected string $table = '{{TABLE_NAME}}';
    protected bool $timestamps = true;

    protected array $schema = [
        // 'id'   => ['type' => 'id', 'primary' => true],
        // 'name' => ['type' => 'string', 'nullable' => false],
    ];

    protected array $seed = [];

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

    public function create(array $data): bool
    {
        if (empty($data)) return false;
        
        $columns = array_keys($data);
        $placeholders = array_map(fn($col) => ':' . $col, $columns);
        
        $sql = "INSERT INTO {$this->table} (" . implode(', ', $columns) . ")
                VALUES (" . implode(', ', $placeholders) . ")";
        
        return $this->getDb()->query($sql, $data);
    }

    public function updateById(string|int $id, array $data): bool
    {
        if (empty($data)) return false;
        
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
}
PHP;

        return str_replace('{{CLASS_NAME}}', $name, $template);
    }

    /**
     * Delegate pluralization to Doctrine Inflector (same engine used by Laravel).
     */
    private function pluralize(string $singular): string
    {
        return strtolower($this->inflector->pluralize($singular));
    }
}
