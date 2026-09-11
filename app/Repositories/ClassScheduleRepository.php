<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

class ClassScheduleRepository
{
    public static function forClass(int $classId): array
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('
            SELECT * FROM football_class_schedules
            WHERE class_id = :class_id
            ORDER BY weekday ASC, start_time ASC
        ');

        $stmt->execute(['class_id' => $classId]);

        return $stmt->fetchAll();
    }

    public static function findById(int $id): ?array
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('SELECT * FROM football_class_schedules WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $schedule = $stmt->fetch();

        return $schedule ?: null;
    }

    public static function create(array $data): int
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('
            INSERT INTO football_class_schedules (
                class_id, weekday, start_time, end_time, location, status, created_at
            ) VALUES (
                :class_id, :weekday, :start_time, :end_time, :location, :status, NOW()
            )
        ');

        $stmt->execute([
            'class_id' => $data['class_id'],
            'weekday' => $data['weekday'],
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
            'location' => $data['location'] ?? null,
            'status' => $data['status'] ?? 'active',
        ]);

        return (int) $pdo->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $pdo = Database::connection();

        $sets = [];
        $params = ['id' => $id];

        $allowedFields = ['weekday', 'start_time', 'end_time', 'location', 'status'];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $sets[] = "{$field} = :{$field}";
                $params[$field] = $data[$field];
            }
        }

        if (empty($sets)) {
            return;
        }

        $sets[] = 'updated_at = NOW()';

        $stmt = $pdo->prepare("UPDATE football_class_schedules SET " . implode(', ', $sets) . " WHERE id = :id");
        $stmt->execute($params);
    }

    public static function setStatus(int $id, string $status): void
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('UPDATE football_class_schedules SET status = :status, updated_at = NOW() WHERE id = :id');
        $stmt->execute(['id' => $id, 'status' => $status]);
    }

    public static function existsDuplicate(int $classId, int $weekday, string $startTime, ?int $exceptId = null): bool
    {
        $pdo = Database::connection();

        $sql = '
            SELECT id FROM football_class_schedules
            WHERE class_id = :class_id AND weekday = :weekday AND start_time = :start_time
        ';

        $params = ['class_id' => $classId, 'weekday' => $weekday, 'start_time' => $startTime];

        if ($exceptId !== null) {
            $sql .= ' AND id <> :except_id';
            $params['except_id'] = $exceptId;
        }

        $sql .= ' LIMIT 1';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return (bool) $stmt->fetch();
    }
}