<?php

namespace Addon\Models;

use App\Core\Database\Model;

class EmailVerificationModel extends Model
{
    protected ?string $connection = 'mysql';
    protected string $table = 'email_verifications';
    protected bool $timestamps = true;

    protected array $schema = [
        'id' => ['type' => 'id', 'primary' => true, 'auto_increment' => true],
        'user_id' => ['type' => 'bigint', 'foreign' => 'users.id', 'unsigned' => true],
        'email' => ['type' => 'varchar', 'length' => 255],
        'otp_code' => ['type' => 'varchar', 'length' => 6],
        'expires_at' => ['type' => 'datetime'],
        'used_at' => ['type' => 'datetime', 'nullable' => true],
    ];

    protected array $seed = [];

    /**
     * Buat OTP verification baru untuk user
     */
    public function createOtp(int $userId, string $email, string $otpCode, int $expiresInMinutes = 15): int
    {
        $expiresAt = date('Y-m-d H:i:s', strtotime("+{$expiresInMinutes} minutes"));

        $this->db->query(
            "INSERT INTO {$this->table} (user_id, email, otp_code, expires_at) VALUES (:user_id, :email, :otp_code, :expires_at)",
            [
                ':user_id' => $userId,
                ':email' => $email,
                ':otp_code' => $otpCode,
                ':expires_at' => $expiresAt,
            ]
        );

        return $this->db->lastInsertId();
    }

    /**
     * Verifikasi OTP code
     *
     * @return array ['valid' => bool, 'message' => string]
     */
    public function verifyOtp(int $userId, string $otpCode): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM {$this->table}
             WHERE user_id = :user_id
             AND otp_code = :otp_code
             AND used_at IS NULL
             AND expires_at > NOW()
             ORDER BY created_at DESC LIMIT 1"
        );
        $stmt->execute([
            ':user_id' => $userId,
            ':otp_code' => $otpCode,
        ]);

        $verification = $stmt->fetch();

        if (!$verification) {
            return ['valid' => false, 'message' => 'Kode OTP tidak valid atau telah kedaluwarsa'];
        }

        // Mark as used
        $this->markAsUsed($verification['id']);

        return ['valid' => true, 'message' => 'Email berhasil diverifikasi'];
    }

    /**
     * Tandai OTP sebagai sudah digunakan
     */
    private function markAsUsed(int $id): bool
    {
        return $this->db->query(
            "UPDATE {$this->table} SET used_at = NOW() WHERE id = :id",
            [':id' => $id]
        );
    }

    /**
     * Hapus OTP yang sudah kedaluwarsa untuk user
     */
    public function deleteExpired(int $userId): int
    {
        $stmt = $this->db->prepare(
            "DELETE FROM {$this->table} WHERE user_id = :user_id AND expires_at < NOW()"
        );
        $stmt->execute([':user_id' => $userId]);

        return $stmt->rowCount();
    }

    /**
     * Cek apakah user masih memiliki OTP yang aktif
     */
    public function hasActiveOtp(int $userId): bool
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) as count FROM {$this->table}
             WHERE user_id = :user_id
             AND used_at IS NULL
             AND expires_at > NOW()"
        );
        $stmt->execute([':user_id' => $userId]);

        $result = $stmt->fetch();
        return $result['count'] > 0;
    }

    /**
     * Invalidate semua OTP untuk user (misal setelah berhasil verifikasi)
     */
    public function invalidateAll(int $userId): bool
    {
        return $this->db->query(
            "UPDATE {$this->table} SET used_at = NOW() WHERE user_id = :user_id AND used_at IS NULL",
            [':user_id' => $userId]
        );
    }
}
