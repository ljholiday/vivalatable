<?php
declare(strict_types=1);

namespace App\Services;

use App\Database\Database;
use PDO;
use RuntimeException;

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

    /**
     * @param array{name:string,description:string,privacy:string} $data
     */
    public function create(array $data): string
    {
        $name = trim($data['name']);
        if ($name === '') {
            throw new RuntimeException('Name is required.');
        }

        $privacy = $data['privacy'] !== '' ? $data['privacy'] : 'public';
        if (!in_array($privacy, ['public', 'private'], true)) {
            throw new RuntimeException('Invalid privacy value.');
        }

        $pdo = $this->db->pdo();
        $slug = $this->ensureUniqueSlug($pdo, $this->slugify($name));
        $now = date('Y-m-d H:i:s');

        $stmt = $pdo->prepare(
            "INSERT INTO vt_communities (
                name,
                slug,
                description,
                privacy,
                creator_id,
                creator_email,
                created_by,
                created_at,
                updated_at,
                member_count,
                event_count,
                is_active
            ) VALUES (
                :name,
                :slug,
                :description,
                :privacy,
                :creator_id,
                :creator_email,
                :created_by,
                :created_at,
                :updated_at,
                :member_count,
                :event_count,
                :is_active
            )"
        );

        $stmt->execute([
            ':name' => $name,
            ':slug' => $slug,
            ':description' => $data['description'],
            ':privacy' => $privacy,
            ':creator_id' => 1,
            ':creator_email' => 'demo@example.com',
            ':created_by' => 1,
            ':created_at' => $now,
            ':updated_at' => $now,
            ':member_count' => 1,
            ':event_count' => 0,
            ':is_active' => 1,
        ]);

        return $slug;
    }

    private function slugify(string $name): string
    {
        $slug = strtolower($name);
        $slug = preg_replace('/[^a-z0-9]+/i', '-', $slug);
        $slug = trim($slug, '-');
        return $slug !== '' ? $slug : 'community';
    }

    private function ensureUniqueSlug(PDO $pdo, string $slug): string
    {
        $base = $slug;
        $i = 1;

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM vt_communities WHERE slug = :slug');

        while (true) {
            $stmt->execute([':slug' => $slug]);
            if ((int)$stmt->fetchColumn() === 0) {
                return $slug;
            }
            $slug = $base . '-' . ++$i;
        }
    }
}
