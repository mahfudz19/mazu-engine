<?php

namespace Addon\Models;

use App\Core\Database\Model;

class AgendaProposalsModel extends Model
{
    protected ?string $connection = null; // Nama koneksi database (opsional)
    protected string $table = 'agenda_proposals';
    protected bool $timestamps = true;

    /**
     * Schema untuk 'php mazu migrate'
     * Tipe: id|string|int|bigint|text|datetime|date|boolean|json|decimal
     */
    protected array $schema = [
        'id'             => ['type' => 'id', 'primary' => true],
        'proposer_email' => ['type' => 'string', 'nullable' => false],
        'title'          => ['type' => 'string', 'nullable' => false],
        'description'    => ['type' => 'text', 'nullable' => true],
        'location'       => ['type' => 'string', 'nullable' => true],
        'start_time'     => ['type' => 'datetime', 'nullable' => false],
        'end_time'       => ['type' => 'datetime', 'nullable' => false],
        'status'         => ['type' => 'enum', 'values' => ['PENDING', 'APPROVED', 'REJECTED'], 'default' => 'PENDING'],
        'approved_by'    => ['type' => 'string', 'nullable' => true],
        'google_event_id' => ['type' => 'string', 'nullable' => true],
        'rejection_note' => ['type' => 'text', 'nullable' => true],
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
