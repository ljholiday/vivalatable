<?php
declare(strict_types=1);

namespace App\Services;

use App\Database\Database;
use PDO;

final class CommunityService
{
    public function __construct(private Database $db) {}

    /** @return array<int,array<string,mixed>> */
    public function listRecent(int $limit = 20): array
    {
        $sql = "SELECT
                    id,
                    name AS title,
                    slug,
                    description,
                    created_at,
                    privacy,
                    member_count,
                    event_count
                FROM vt_communities
                ORDER BY COALESCE(created_at, id) DESC
                LIMIT :lim";
        $stmt = $this->db->pdo()->prepare($sql);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<string,mixed>|null */
    public function getBySlugOrId(string $slugOrId): ?array
    {
        $pdo = $this->db->pdo();

        if (ctype_digit($slugOrId)) {
            $stmt = $pdo->prepare(
                "SELECT id, name AS title, slug, description, created_at, privacy, member_count, event_count
                 FROM vt_communities
                 WHERE id = :id
                 LIMIT 1"
            );
            $stmt->execute([':id' => (int)$slugOrId]);
        } else {
            $stmt = $pdo->prepare(
                "SELECT id, name AS title, slug, description, created_at, privacy, member_count, event_count
                 FROM vt_communities
                 WHERE slug = :slug
                 LIMIT 1"
            );
            $stmt->execute([':slug' => $slugOrId]);
        }

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }
}
