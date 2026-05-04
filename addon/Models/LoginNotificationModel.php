<?php

namespace Addon\Models;

use App\Core\Database\Model;

/**
 * Login Notification Model
 * 
 * Mencatat setiap login user dan mengirim notifikasi email.
 * Berguna untuk keamanan - user akan tahu jika ada login mencurigakan.
 */
class LoginNotificationModel extends Model
{
    protected ?string $connection = 'mysql';
    protected string $table = 'login_notifications';
    protected bool $timestamps = true;

    protected array $schema = [
        'id' => ['type' => 'id', 'primary' => true, 'auto_increment' => true],
        'user_id' => ['type' => 'bigint', 'foreign' => 'users.id', 'unsigned' => true],
        'email_sent_to' => ['type' => 'varchar', 'length' => 255],
        'ip_address' => ['type' => 'varchar', 'length' => 45],
        'user_agent' => ['type' => 'text'],
        'login_at' => ['type' => 'datetime'],
    ];

    protected array $seed = [];

    /**
     * Catat login baru dan kirim notifikasi
     * 
     * @param int $userId User ID
     * @param string $email Email tujuan notifikasi
     * @param string|null $ipAddress IP address user
     * @param string|null $userAgent User agent browser
     * @param string|null $loginAt Timestamp login (default: now)
     * @return int Last insert ID
     */
    public function logLogin(
        int $userId,
        string $email,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?string $loginAt = null
    ): int {
        $loginAt = $loginAt ?? date('Y-m-d H:i:s');

        $this->db->query(
            "INSERT INTO {$this->table} (user_id, email_sent_to, ip_address, user_agent, login_at) 
             VALUES (:user_id, :email_sent_to, :ip_address, :user_agent, :login_at)",
            [
                ':user_id' => $userId,
                ':email_sent_to' => $email,
                ':ip_address' => $ipAddress,
                ':user_agent' => $userAgent,
                ':login_at' => $loginAt,
            ]
        );

        return (int) $this->db->lastInsertId();
    }

    /**
     * Dapatkan riwayat login user
     * 
     * @param int $userId User ID
     * @param int $limit Jumlah record maksimal
     * @return array[] List of login records
     */
    public function getUserLogins(int $userId, int $limit = 10): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM {$this->table} 
             WHERE user_id = :user_id 
             ORDER BY login_at DESC 
             LIMIT :limit"
        );
        $stmt->bindValue(':user_id', $userId, \PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Dapatkan login terakhir user
     * 
     * @param int $userId User ID
     * @return array|null Last login record or null
     */
    public function getLastLogin(int $userId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM {$this->table} 
             WHERE user_id = :user_id 
             ORDER BY login_at DESC 
             LIMIT 1"
        );
        $stmt->execute([':user_id' => $userId]);

        $result = $stmt->fetch();
        return $result === false ? null : $result;
    }

    /**
     * Dapatkan login mencurigakan (IP berbeda dari biasanya)
     * 
     * @param int $userId User ID
     * @param int $limit Jumlah record maksimal
     * @return array[] List of suspicious login records
     */
    public function getSuspiciousLogins(int $userId, int $limit = 5): array
    {
        // Get most common IP for this user
        $stmt = $this->db->prepare(
            "SELECT ip_address, COUNT(*) as count FROM {$this->table} 
             WHERE user_id = :user_id 
             GROUP BY ip_address 
             ORDER BY count DESC 
             LIMIT 1"
        );
        $stmt->execute([':user_id' => $userId]);
        $commonIp = $stmt->fetch();

        if (!$commonIp || !$commonIp['ip_address']) {
            return [];
        }

        // Get logins from different IPs
        $stmt = $this->db->prepare(
            "SELECT * FROM {$this->table} 
             WHERE user_id = :user_id 
             AND ip_address != :ip_address 
             ORDER BY login_at DESC 
             LIMIT :limit"
        );
        $stmt->bindValue(':user_id', $userId, \PDO::PARAM_INT);
        $stmt->bindValue(':ip_address', $commonIp['ip_address']);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Hapus log lama (lebih dari 90 hari)
     * 
     * @return int Jumlah record yang dihapus
     */
    public function deleteOldLogs(): int
    {
        $stmt = $this->db->prepare(
            "DELETE FROM {$this->table} WHERE login_at < DATE_SUB(NOW(), INTERVAL 90 DAY)"
        );
        $stmt->execute();

        return $stmt->rowCount();
    }
}
