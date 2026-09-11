<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

class MatchRepository
{
    public static function paginate(array $filters, int $page, int $perPage): array
    {
        $pdo = Database::connection();

        $where = ['m.id IS NOT NULL'];
        $params = [];

        if (!empty($filters['status'])) {
            $where[] = 'm.status = :status';
            $params['status'] = $filters['status'];
        }

        if (!empty($filters['class_id'])) {
            $where[] = 'm.class_id = :class_id';
            $params['class_id'] = $filters['class_id'];
        }

        if (!empty($filters['age_group_id'])) {
            $where[] = 'm.age_group_id = :age_group_id';
            $params['age_group_id'] = $filters['age_group_id'];
        }

        if (!empty($filters['date_from'])) {
            $where[] = 'm.match_date >= :date_from';
            $params['date_from'] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[] = 'm.match_date <= :date_to';
            $params['date_to'] = $filters['date_to'];
        }

        if (!empty($filters['q'])) {
            $where[] = '(m.title LIKE :q1 OR m.opponent_team LIKE :q2)';
            $like = '%' . $filters['q'] . '%';
            $params['q1'] = $like;
            $params['q2'] = $like;
        }

        $whereSql = implode(' AND ', $where);

        $countStmt = $pdo->prepare("SELECT COUNT(*) AS total FROM football_matches m WHERE {$whereSql}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetch()['total'];

        $offset = ($page - 1) * $perPage;

        $stmt = $pdo->prepare("
            SELECT m.*, c.title AS class_title, ag.title AS age_group_title
            FROM football_matches m
            LEFT JOIN football_classes c ON c.id = m.class_id
            LEFT JOIN football_age_groups ag ON ag.id = m.age_group_id
            WHERE {$whereSql}
            ORDER BY m.match_date DESC, m.id DESC
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
            SELECT m.*, c.title AS class_title, ag.title AS age_group_title
            FROM football_matches m
            LEFT JOIN football_classes c ON c.id = m.class_id
            LEFT JOIN football_age_groups ag ON ag.id = m.age_group_id
            WHERE m.id = :id
            LIMIT 1
        ');

        $stmt->execute(['id' => $id]);
        $match = $stmt->fetch();

        return $match ?: null;
    }

    public static function create(array $data): int
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('
            INSERT INTO football_matches (
                title, match_type, class_id, age_group_id, opponent_team,
                match_date, match_time, location, status, home_score, away_score,
                result, notes, created_by, created_at
            ) VALUES (
                :title, :match_type, :class_id, :age_group_id, :opponent_team,
                :match_date, :match_time, :location, :status, :home_score, :away_score,
                :result, :notes, :created_by, NOW()
            )
        ');

        $stmt->execute([
            'title' => $data['title'],
            'match_type' => $data['match_type'],
            'class_id' => $data['class_id'] ?? null,
            'age_group_id' => $data['age_group_id'] ?? null,
            'opponent_team' => $data['opponent_team'] ?? null,
            'match_date' => $data['match_date'],
            'match_time' => $data['match_time'],
            'location' => $data['location'] ?? null,
            'status' => $data['status'] ?? 'planned',
            'home_score' => $data['home_score'] ?? null,
            'away_score' => $data['away_score'] ?? null,
            'result' => $data['result'] ?? null,
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

        $allowedFields = [
            'title', 'match_type', 'class_id', 'age_group_id', 'opponent_team',
            'match_date', 'match_time', 'location', 'status', 'home_score',
            'away_score', 'result', 'notes',
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

        $stmt = $pdo->prepare("UPDATE football_matches SET " . implode(', ', $sets) . " WHERE id = :id");
        $stmt->execute($params);
    }

    public static function players(int $matchId): array
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('
            SELECT mp.*, p.first_name, p.last_name, p.birth_date,
                   TIMESTAMPDIFF(YEAR, p.birth_date, CURDATE()) AS age
            FROM football_match_players mp
            INNER JOIN football_players p ON p.id = mp.player_id
            WHERE mp.match_id = :match_id AND p.deleted_at IS NULL
            ORDER BY mp.id ASC
        ');

        $stmt->execute(['match_id' => $matchId]);

        return $stmt->fetchAll();
    }

    public static function findMatchPlayerById(int $id): ?array
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('
            SELECT mp.*, p.first_name, p.last_name
            FROM football_match_players mp
            INNER JOIN football_players p ON p.id = mp.player_id
            WHERE mp.id = :id
            LIMIT 1
        ');

        $stmt->execute(['id' => $id]);
        $matchPlayer = $stmt->fetch();

        return $matchPlayer ?: null;
    }

    public static function findMatchPlayer(int $matchId, int $playerId): ?array
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('
            SELECT * FROM football_match_players
            WHERE match_id = :match_id AND player_id = :player_id
            LIMIT 1
        ');

        $stmt->execute(['match_id' => $matchId, 'player_id' => $playerId]);
        $matchPlayer = $stmt->fetch();

        return $matchPlayer ?: null;
    }

    public static function createMatchPlayer(array $data): int
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('
            INSERT INTO football_match_players (
                match_id, player_id, invitation_status, attendance_status,
                jersey_number, position, goals, assists, yellow_cards, red_cards,
                minutes_played, rating, notes, created_at
            ) VALUES (
                :match_id, :player_id, :invitation_status, :attendance_status,
                :jersey_number, :position, :goals, :assists, :yellow_cards, :red_cards,
                :minutes_played, :rating, :notes, NOW()
            )
        ');

        $stmt->execute([
            'match_id' => $data['match_id'],
            'player_id' => $data['player_id'],
            'invitation_status' => $data['invitation_status'] ?? 'invited',
            'attendance_status' => $data['attendance_status'] ?? null,
            'jersey_number' => $data['jersey_number'] ?? null,
            'position' => $data['position'] ?? null,
            'goals' => $data['goals'] ?? 0,
            'assists' => $data['assists'] ?? 0,
            'yellow_cards' => $data['yellow_cards'] ?? 0,
            'red_cards' => $data['red_cards'] ?? 0,
            'minutes_played' => $data['minutes_played'] ?? null,
            'rating' => $data['rating'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        return (int) $pdo->lastInsertId();
    }

    public static function updateMatchPlayer(int $id, array $data): void
    {
        $pdo = Database::connection();

        $sets = [];
        $params = ['id' => $id];

        $allowedFields = [
            'invitation_status', 'attendance_status', 'jersey_number', 'position',
            'goals', 'assists', 'yellow_cards', 'red_cards', 'minutes_played', 'rating', 'notes',
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

        $stmt = $pdo->prepare("UPDATE football_match_players SET " . implode(', ', $sets) . " WHERE id = :id");
        $stmt->execute($params);
    }

    public static function deleteMatchPlayer(int $id): void
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('DELETE FROM football_match_players WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }
}