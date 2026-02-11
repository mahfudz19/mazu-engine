<?php

namespace App\Console\Commands;

use App\Core\Foundation\Application;
use App\Console\Contracts\CommandInterface;
use Doctrine\Inflector\Inflector;
use Doctrine\Inflector\InflectorFactory;

class MakeModelCommand implements CommandInterface
{
  private Inflector $inflector;

  public function __construct(
    private Application $app,
  ) {
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

    $root = __DIR__ . '/../../..';
    $path = $root . "/addon/Models/{$name}.php";

    if (file_exists($path)) {
      echo color("Error: Model sudah ada!\n", "red");
      return 1;
    }

    $baseName = str_replace('Model', '', $name);
    $tableName = $this->pluralize(strtolower($baseName));

    $template = <<<'PHP'
<?php

namespace Addon\Models;

use App\Core\Database\Model;

class {{CLASS_NAME}} extends Model
{
    protected ?string $connection = null; // Nama koneksi database (opsional)
    protected string $table = '{{TABLE_NAME}}';
    protected bool $timestamps = true;

    // Kolom timestamp (opsional untuk diubah)
    protected string $createdAtColumn = 'created_at';
    protected string $updatedAtColumn = 'updated_at';

    /**
     * Schema untuk 'php mazu migrate'
     * Tipe: id|string|int|bigint|text|datetime|date|boolean|json|decimal
     */
    protected array $schema = [
        // 'id'   => ['type' => 'id', 'primary' => true],
        // 'name' => ['type' => 'string', 'nullable' => false],
    ];

    protected array $seed = []; // Data awal untuk seeder

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
        if (empty($data)) {
            return false;
        }

        $columns = array_keys($data);
        $placeholders = array_map(fn($col) => ':' . $col, $columns);

        $sql = "INSERT INTO {$this->table} (" . implode(', ', $columns) . ")
                VALUES (" . implode(', ', $placeholders) . ")";

        return $this->getDb()->query($sql, $data);
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
}

PHP;

    $content = str_replace(
      ['{{CLASS_NAME}}', '{{TABLE_NAME}}'],
      [$name, $tableName],
      $template
    );

    file_put_contents($path, $content);
    echo color("SUCCESS:", "green") . " Model dibuat di " . color($path, "blue") . "\n";

    return 0;
  }

  /**
   * Delegate pluralization to Doctrine Inflector (same engine used by Laravel).
   */
  private function pluralize(string $singular): string
  {
    return strtolower($this->inflector->pluralize($singular));
  }
}
