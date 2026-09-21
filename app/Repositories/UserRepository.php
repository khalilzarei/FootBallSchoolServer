<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

class UserRepository
{
    public static function findByIdentifier(string $identifier): ?array
    {
        $pdo = Database::connection();
        
        // جستجو بر اساس موبایل
        $stmt = $pdo->prepare('SELECT * FROM football_users WHERE mobile = :identifier AND deleted_at IS NULL LIMIT 1');
        $stmt->execute(['identifier' => $identifier]);
        $user = $stmt->fetch();
        if ($user) return $user;

        // جستجو بر اساس کد ملی
        $stmt = $pdo->prepare('SELECT * FROM football_users WHERE national_code = :identifier AND deleted_at IS NULL LIMIT 1');
        $stmt->execute(['identifier' => $identifier]);
        return $stmt->fetch() ?: null;
    }

    public static function findById(int $id): ?array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM football_users WHERE id = :id AND deleted_at IS NULL LIMIT 1');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public static function paginate(array $filters, int $page, int $perPage): array
    {
        $pdo = Database::connection();
        $where = ['u.deleted_at IS NULL'];
        $params = [];

        if (!empty($filters['role'])) {
            $where[] = 'u.role = :role';
            $params['role'] = $filters['role'];
        }
        if (!empty($filters['exclude_role'])) {
            $where[] = 'u.role <> :exclude_role';
            $params['exclude_role'] = $filters['exclude_role'];
        }
        if (!empty($filters['status'])) {
            $where[] = 'u.status = :status';
            $params['status'] = $filters['status'];
        }
        if (!empty($filters['q'])) {
            $where[] = '(u.full_name LIKE :q1 OR u.mobile LIKE :q2 OR u.national_code LIKE :q3)';
            $like = '%' . $filters['q'] . '%';
            $params['q1'] = $like;
            $params['q2'] = $like;
            $params['q3'] = $like;
        }

        $whereSql = implode(' AND ', $where);
        $countStmt = $pdo->prepare("SELECT COUNT(*) AS total FROM football_users u WHERE {$whereSql}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetch()['total'];

        $offset = ($page - 1) * $perPage;
        $stmt = $pdo->prepare("
            SELECT u.* FROM football_users u 
            WHERE {$whereSql} 
            ORDER BY u.id DESC 
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

    public static function existsByMobile(string $mobile, ?int $exceptUserId = null): bool
    {
        $pdo = Database::connection();
        $sql = 'SELECT id FROM football_users WHERE mobile = :mobile AND deleted_at IS NULL';
        $params = ['mobile' => $mobile];
        if ($exceptUserId !== null) {
            $sql .= ' AND id <> :except_id';
            $params['except_id'] = $exceptUserId;
        }
        $stmt = $pdo->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);
        return (bool) $stmt->fetch();
    }

    public static function existsByNationalCode(string $nationalCode, ?int $exceptUserId = null): bool
    {
        $pdo = Database::connection();
        $sql = 'SELECT id FROM football_users WHERE national_code = :national_code AND deleted_at IS NULL';
        $params = ['national_code' => $nationalCode];
        if ($exceptUserId !== null) {
            $sql .= ' AND id <> :except_id';
            $params['except_id'] = $exceptUserId;
        }
        $stmt = $pdo->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);
        return (bool) $stmt->fetch();
    }

    public static function create(array $data): int
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('
            INSERT INTO football_users (
                full_name, mobile, national_code, avatar_path, password_hash, role, status,
                must_change_password, created_by, created_at
            ) VALUES (
                :full_name, :mobile, :national_code, :avatar_path, :password_hash, :role, :status,
                :must_change_password, :created_by, NOW()
            )
        ');
        $stmt->execute([
            'full_name' => $data['full_name'],
            'mobile' => $data['mobile'] ?? null,
            'national_code' => $data['national_code'] ?? null,
            // رشته خالی به‌جای null — برخی دیتابیس‌ها (هاست) این ستون را NOT NULL تعریف کرده‌اند
            'avatar_path' => $data['avatar_path'] ?? '', // پشتیبانی از آواتار
            'password_hash' => $data['password_hash'],
            'role' => $data['role'],
            'status' => $data['status'] ?? 'active',
            'must_change_password' => 1,
            'created_by' => $data['created_by'] ?? null,
        ]);
        return (int) $pdo->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $pdo = Database::connection();
        $sets = [];
        $params = ['id' => $id];
        $allowedFields = ['full_name', 'mobile', 'national_code', 'avatar_path', 'role', 'status'];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $sets[] = "{$field} = :{$field}";
                $params[$field] = $data[$field];
            }
        }

        if (empty($sets)) return;
        $sets[] = 'updated_at = NOW()';

        $stmt = $pdo->prepare("UPDATE football_users SET " . implode(', ', $sets) . " WHERE id = :id AND deleted_at IS NULL");
        $stmt->execute($params);
    }

    public static function updateAvatar(int $id, ?string $avatarPath): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('UPDATE football_users SET avatar_path = :avatar_path, updated_at = NOW() WHERE id = :id AND deleted_at IS NULL');
        // رشته خالی به‌جای null (سازگاری با ستون NOT NULL در برخی هاست‌ها)
        $stmt->execute(['id' => $id, 'avatar_path' => $avatarPath ?? '']);
    }

    public static function resetPassword(int $id, string $passwordHash): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('UPDATE football_users SET password_hash = :password_hash, must_change_password = 1, updated_at = NOW() WHERE id = :id AND deleted_at IS NULL');
        $stmt->execute(['id' => $id, 'password_hash' => $passwordHash]);
    }

    public static function setStatus(int $id, string $status): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('UPDATE football_users SET status = :status, updated_at = NOW() WHERE id = :id AND deleted_at IS NULL');
        $stmt->execute(['id' => $id, 'status' => $status]);
    }

    public static function updateLastLogin(int $id): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('UPDATE football_users SET last_login_at = NOW() WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public static function updatePassword(int $id, string $passwordHash): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('UPDATE football_users SET password_hash = :password_hash WHERE id = :id');
        $stmt->execute(['id' => $id, 'password_hash' => $passwordHash]);
    }

    public static function clearMustChangePassword(int $id): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('UPDATE football_users SET must_change_password = 0 WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }
}