<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

class PlayerRepository
{
    public static function paginate(array $filters, int $page, int $perPage): array
    {
        $pdo = Database::connection();
        $where = ['p.deleted_at IS NULL'];
        $params = [];

        if (!empty($filters['status'])) {
            $where[] = 'p.status = :status';
            $params['status'] = $filters['status'];
        }
        if (!empty($filters['q'])) {
            $where[] = '(p.first_name LIKE :q1 OR p.last_name LIKE :q2 OR p.national_code LIKE :q3)';
            $like = '%' . $filters['q'] . '%';
            $params['q1'] = $like;
            $params['q2'] = $like;
            $params['q3'] = $like;
        }

        $whereSql = implode(' AND ', $where);
        $countStmt = $pdo->prepare("SELECT COUNT(*) AS total FROM football_players p WHERE {$whereSql}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetch()['total'];

        $offset = ($page - 1) * $perPage;
        $stmt = $pdo->prepare("
            SELECT p.*, TIMESTAMPDIFF(YEAR, p.birth_date, CURDATE()) AS age
            FROM football_players p
            WHERE {$whereSql}
            ORDER BY p.id DESC
            LIMIT {$perPage} OFFSET {$offset}
        ");
        $stmt->execute($params);

        return [
            'items' => $stmt->fetchAll(),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    public static function findById(int $id): ?array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('
            SELECT p.*, TIMESTAMPDIFF(YEAR, p.birth_date, CURDATE()) AS age
            FROM football_players p
            WHERE p.id = :id AND p.deleted_at IS NULL
            LIMIT 1
        ');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public static function existsByNationalCode(string $nationalCode, ?int $exceptPlayerId = null): bool
    {
        $pdo = Database::connection();
        $sql = 'SELECT id FROM football_players WHERE national_code = :national_code AND deleted_at IS NULL';
        $params = ['national_code' => $nationalCode];
        if ($exceptPlayerId !== null) {
            $sql .= ' AND id <> :except_id';
            $params['except_id'] = $exceptPlayerId;
        }
        $stmt = $pdo->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);
        return (bool) $stmt->fetch();
    }

    public static function create(array $data): int
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('
            INSERT INTO football_players (
                first_name, last_name, national_code, birth_date, gender,
                avatar_path, medical_notes, status, notes, created_by, created_at
            ) VALUES (
                :first_name, :last_name, :national_code, :birth_date, :gender,
                :avatar_path, :medical_notes, :status, :notes, :created_by, NOW()
            )
        ');
        $stmt->execute([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'national_code' => $data['national_code'] ?? null,
            'birth_date' => $data['birth_date'],
            'gender' => $data['gender'] ?? null,
            // رشته خالی به‌جای null — برخی دیتابیس‌ها (هاست) این ستون را NOT NULL تعریف کرده‌اند
            'avatar_path' => $data['avatar_path'] ?? '', // ← پشتیبانی از آواتار
            'medical_notes' => $data['medical_notes'] ?? null,
            'status' => $data['status'] ?? 'active',
            'notes' => $data['notes'] ?? null,
            'created_by' => $data['created_by'] ?? null,
        ]);
        return (int) $pdo->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $pdo = Database::connection();
        $sets = [];
        $params = ['id' => $id];
        
        // اضافه شدن avatar_path به لیست فیلدهای مجاز
        $allowedFields = [
            'first_name', 'last_name', 'national_code', 'birth_date', 
            'avatar_path', 'gender', 'medical_notes', 'status', 'notes', 'user_id', 'password_hash'
        ];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $sets[] = "{$field} = :{$field}";
                $params[$field] = $data[$field];
            }
        }

        if (empty($sets)) return;
        $sets[] = 'updated_at = NOW()';

        $stmt = $pdo->prepare("UPDATE football_players SET " . implode(', ', $sets) . " WHERE id = :id AND deleted_at IS NULL");
        $stmt->execute($params);
    }

    /** بازیکنِ متصل به این حساب کاربری (برای ورود و رمز) */
    public static function findByUserId(int $userId): ?array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM football_players WHERE user_id = :user_id AND deleted_at IS NULL LIMIT 1');
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetch() ?: null;
    }

    /** همگام‌سازی رمز بازیکن (تغییر رمز از داخل اپ) */
    public static function updatePasswordByUserId(int $userId, string $passwordHash): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('UPDATE football_players SET password_hash = :hash, updated_at = NOW() WHERE user_id = :user_id');
        $stmt->execute(['hash' => $passwordHash, 'user_id' => $userId]);
    }

    public static function updateAvatar(int $id, ?string $avatarPath): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('UPDATE football_players SET avatar_path = :avatar_path, updated_at = NOW() WHERE id = :id AND deleted_at IS NULL');
        // رشته خالی به‌جای null (سازگاری با ستون NOT NULL در برخی هاست‌ها)
        $stmt->execute(['id' => $id, 'avatar_path' => $avatarPath ?? '']);
    }
}