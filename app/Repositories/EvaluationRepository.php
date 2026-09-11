<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

class EvaluationRepository
{
    public static function paginate(array $filters, int $page, int $perPage): array
    {
        $pdo = Database::connection();

        $where = ['ev.id IS NOT NULL'];
        $params = [];

        if (!empty($filters['player_id'])) {
            $where[] = 'ev.player_id = :player_id';
            $params['player_id'] = $filters['player_id'];
        }

        if (!empty($filters['session_id'])) {
            $where[] = 'ev.session_id = :session_id';
            $params['session_id'] = $filters['session_id'];
        }

        if (!empty($filters['coach_id'])) {
            $where[] = 'ev.coach_id = :coach_id';
            $params['coach_id'] = $filters['coach_id'];
        }

        $whereSql = implode(' AND ', $where);

        $countStmt = $pdo->prepare("SELECT COUNT(*) AS total FROM football_evaluations ev WHERE {$whereSql}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetch()['total'];

        $offset = ($page - 1) * $perPage;

        $stmt = $pdo->prepare("
            SELECT ev.*, p.first_name, p.last_name, s.session_date,
                   c.title AS class_title
            FROM football_evaluations ev
            INNER JOIN football_players p ON p.id = ev.player_id
            LEFT JOIN football_sessions s ON s.id = ev.session_id
            LEFT JOIN football_classes c ON c.id = s.class_id
            WHERE {$whereSql}
            ORDER BY ev.id DESC
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
            SELECT ev.*, p.first_name, p.last_name, s.session_date,
                   c.title AS class_title
            FROM football_evaluations ev
            INNER JOIN football_players p ON p.id = ev.player_id
            LEFT JOIN football_sessions s ON s.id = ev.session_id
            LEFT JOIN football_classes c ON c.id = s.class_id
            WHERE ev.id = :id
            LIMIT 1
        ');

        $stmt->execute(['id' => $id]);
        $evaluation = $stmt->fetch();

        return $evaluation ?: null;
    }

    public static function create(array $data): int
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('
            INSERT INTO football_evaluations (
                session_id, player_id, coach_id, evaluation_type,
                technical_score, discipline_score, physical_score, teamwork_score,
                overall_score, strengths, weaknesses, notes, status, created_at
            ) VALUES (
                :session_id, :player_id, :coach_id, :evaluation_type,
                :technical_score, :discipline_score, :physical_score, :teamwork_score,
                :overall_score, :strengths, :weaknesses, :notes, :status, NOW()
            )
        ');

        $stmt->execute([
            'session_id' => $data['session_id'] ?? null,
            'player_id' => $data['player_id'],
            'coach_id' => $data['coach_id'],
            'evaluation_type' => $data['evaluation_type'] ?? 'session',
            'technical_score' => $data['technical_score'] ?? null,
            'discipline_score' => $data['discipline_score'] ?? null,
            'physical_score' => $data['physical_score'] ?? null,
            'teamwork_score' => $data['teamwork_score'] ?? null,
            'overall_score' => $data['overall_score'] ?? null,
            'strengths' => $data['strengths'] ?? null,
            'weaknesses' => $data['weaknesses'] ?? null,
            'notes' => $data['notes'] ?? null,
            'status' => $data['status'] ?? 'active',
        ]);

        return (int) $pdo->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $pdo = Database::connection();

        $sets = [];
        $params = ['id' => $id];

        $allowedFields = [
            'session_id', 'player_id', 'coach_id', 'evaluation_type',
            'technical_score', 'discipline_score', 'physical_score',
            'teamwork_score', 'overall_score', 'strengths', 'weaknesses',
            'notes', 'status',
        ];

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

        $stmt = $pdo->prepare("UPDATE football_evaluations SET " . implode(', ', $sets) . " WHERE id = :id");
        $stmt->execute($params);
    }
}