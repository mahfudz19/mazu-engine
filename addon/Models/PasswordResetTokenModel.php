<?php

namespace Addon\Models;

use App\Core\Database\Model;

/**
 * Password Reset Token Model
 * 
 * Mengelola token untuk password reset.
 * Token berlaku selama 1 jam dan hanya bisa dipakai sekali.
 */
class PasswordResetTokenModel extends Model
{
    protected ?string $connection = 'mysql';
    protected string $table = 'password_reset_tokens';
    protected bool $timestamps = true;

    protected array $schema = [
        'id' => ['type' => 'id', 'primary' => true, 'auto_increment' => true],
        'user_id' => ['type' => 'bigint', 'foreign' => 'users.id', 'unsigned' => true],
        'token' => ['type' => 'varchar', 'length' => 64],
        'expires_at' => ['type' => 'datetime'],
        'used_at' => ['type' => 'datetime', 'nullable' => true],
    ];

    protected array $seed = [];

    /**
     * Buat token reset password baru
     * 
     * @param int $userId User ID
     * @param string $token 64-character random token
     * @param int $expiresInMinutes Durasi kedaluwarsa (default: 60 menit)
     * @return int Last insert ID
     */
    public function createToken(int $userId, string $token, int $expiresInMinutes = 60): int
    {
        $expiresAt = date('Y-m-d H:i:s', strtotime("+{$expiresInMinutes} minutes"));

        $this->db->query(
            "INSERT INTO {$this->table} (user_id, token, expires_at) VALUES (:user_id, :token, :expires_at)",
            [
                ':user_id' => $userId,
                ':token' => $token,
                ':expires_at' => $expiresAt,
            ]
        );

        return (int) $this->db->lastInsertId();
    }

    /**
     * Temukan token yang valid
     * 
     * @param string $token Token yang dicari
     * @return array|null Data token jika valid, null jika tidak
     */
    public function findValidToken(string $token): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM {$this->table} 
             WHERE token = :token 
             AND used_at IS NULL 
             AND expires_at > NOW()
             ORDER BY created_at DESC LIMIT 1"
        );
        $stmt->execute([':token' => $token]);

        $result = $stmt->fetch();
        return $result === false ? null : $result;
    }

    /**
     * Tandai token sebagai sudah digunakan
     * 
     * @param int $id Token ID
     * @return bool Success status
     */
    public function markAsUsed(int $id): bool
    {
        return $this->db->query(
            "UPDATE {$this->table} SET used_at = NOW() WHERE id = :id",
            [':id' => $id]
        );
    }

    /**
     * Invalidate semua token untuk user (misal setelah reset password berhasil)
     * 
     * @param int $userId User ID
     * @return bool Success status
     */
    public function invalidateAll(int $userId): bool
    {
        return $this->db->query(
            "UPDATE {$this->table} SET used_at = NOW() WHERE user_id = :user_id AND used_at IS NULL",
            [':user_id' => $userId]
        );
    }

    /**
     * Hapus token yang sudah kedaluwarsa
     * 
     * @return int Jumlah token yang dihapus
     */
    public function deleteExpired(): int
    {
        $stmt = $this->db->prepare(
            "DELETE FROM {$this->table} WHERE expires_at < NOW()"
        );
        $stmt->execute();

        return $stmt->rowCount();
    }

    /**
     * Generate random token 64 karakter
     * 
     * @return string 64-character random hex string
     */
    public function generateToken(): string
    {
        return bin2hex(random_bytes(32));
    }
}
