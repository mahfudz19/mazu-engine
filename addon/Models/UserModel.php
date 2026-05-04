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
    protected ?string $connection = 'mysql';
    protected string $table = 'users';
    protected bool $timestamps = true;

    protected array $schema = [
        'id' => ['type' => 'id', 'primary' => true, 'auto_increment' => true],
        'email' => ['type' => 'string', 'nullable' => false, 'unique' => true],
        'password' => ['type' => 'string', 'nullable' => true],
        'name' => ['type' => 'string', 'nullable' => true],
        'avatar' => ['type' => 'string', 'nullable' => true],
        'is_active' => ['type' => 'boolean', 'nullable' => false, 'default' => true],
        'last_login_at' => ['type' => 'datetime', 'nullable' => true],
        'role' => ['type' => 'enum', 'values' => ['super-admin', 'admin', 'user'], 'nullable' => false, 'default' => 'user'],
        'google_id' => ['type' => 'string', 'nullable' => true, 'unique' => true],
        'avatar_url' => ['type' => 'string', 'nullable' => true]
    ];

    protected array $seed = [
        [
            'email' => 'superadmin@example.com',
            'password' => '$2y$10$XlyT7neGvzxYcZ5v.4gsP.QFqRq7UG8nNrJF1Bk4fiP/vQUCqXlDm', // password123
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
            'password' => '$2y$10$XlyT7neGvzxYcZ5v.4gsP.QFqRq7UG8nNrJF1Bk4fiP/vQUCqXlDm', // password123
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
            'password' => '$2y$10$XlyT7neGvzxYcZ5v.4gsP.QFqRq7UG8nNrJF1Bk4fiP/vQUCqXlDm', // password123
            'name' => 'Regular User',
            'avatar' => null,
            'is_active' => 1,
            'last_login_at' => null,
            'role' => 'user',
            'google_id' => null,
            'avatar_url' => null,
        ]
    ];

    /**
     * Get all users
     */
    public function all(): array
    {
        $stmt = $this->getDb()->prepare("SELECT * FROM {$this->table}");
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Find user by ID
     */
    public function find(string|int $id): ?array
    {
        $stmt = $this->getDb()->prepare("SELECT * FROM {$this->table} WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /**
     * Find user by email
     */
    public function findByEmail(string $email): ?array
    {
        $stmt = $this->getDb()->prepare("SELECT * FROM {$this->table} WHERE email = :email LIMIT 1");
        $stmt->execute(['email' => $email]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /**
     * Create new user
     *
     * @param array $data User data (email, password, name, avatar, role, etc.)
     * @return int Last insert ID on success
     * @throws \PDOException On database error
     * @throws \Exception On unique constraint violation (email already exists)
     */
    public function create(array $data): int
    {
        try {
            // Filter data based on schema
            $validData = [];
            foreach ($data as $key => $value) {
                if (isset($this->schema[$key]) && $key !== 'id') {
                    $validData[$key] = $value;
                }
            }

            // Build columns and placeholders
            $columns = implode(', ', array_keys($validData));
            $placeholders = ':' . implode(', :', array_keys($validData));

            // Build INSERT query
            $sql = "INSERT INTO {$this->table} ($columns) VALUES ($placeholders)";

            // Execute query
            if ($this->getDb()->query($sql, $validData)) {
                return (int) $this->getDb()->lastInsertId();
            }

            throw new \PDOException('Gagal membuat user baru');
        } catch (\PDOException $e) {
            // Check for duplicate entry (email already exists)
            if ($e->getCode() === '23000' || str_contains($e->getMessage(), 'Duplicate entry')) {
                throw new \Exception('Email sudah terdaftar');
            }
            throw $e;
        }
    }

    /**
     * Update user by ID
     */
    public function updateById(string|int $id, array $data): bool
    {
        if (empty($data)) {
            return false;
        }

        // Auto-update updated_at if not provided
        if (!isset($data['updated_at'])) {
            $data['updated_at'] = date('Y-m-d H:i:s');
        }

        $setParts = [];
        foreach ($data as $column => $value) {
            $setParts[] = "{$column} = :{$column}";
        }

        $sql = "UPDATE {$this->table} SET " . implode(', ', $setParts) . " WHERE id = :id";
        $data['id'] = $id;

        return $this->getDb()->query($sql, $data);
    }

    /**
     * Delete user by ID
     */
    public function deleteById(string|int $id): bool
    {
        $sql = "DELETE FROM {$this->table} WHERE id = :id";
        return $this->getDb()->query($sql, ['id' => $id]);
    }

    /**
     * Update last login timestamp
     */
    public function updateLastLogin(string|int $id): bool
    {
        return $this->updateById($id, ['last_login_at' => date('Y-m-d H:i:s')]);
    }

    /**
     * Check if user has specific role (if role system enabled)
     */
    public function hasRole(array $user, string $role): bool
    {
        if (isset($this->schema['role']) && isset($user['role'])) {
            return $user['role'] === $role;
        }
        return false;
    }
}
