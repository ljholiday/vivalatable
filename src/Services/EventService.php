<?php
declare(strict_types=1);

namespace App\Services;

use App\Database\Database;
use PDO;
use RuntimeException;

/**
 * EventService
 * Thin data-access layer for events.
 *
 * Notes:
 * - Uses PDO with ERRMODE_EXCEPTION (set in Database).
 * - Returns associative arrays (no objects) to keep templates simple.
 */
final class EventService
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * List recent events, newest first.
     *
     * @param int $limit Max rows to return.
     * @return array<int, array<string, mixed>>
     */
    public function listRecent(int $limit = 20): array
    {
        $sql = "SELECT id, title, event_date, slug, description
                FROM vt_events
                ORDER BY event_date DESC
                LIMIT :lim";

        $stmt = $this->db->pdo()->prepare($sql);
        // bindValue with explicit type so MySQL accepts LIMIT as integer
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Fetch a single event by slug or numeric id.
     *
     * @param string $slugOrId Slug like "my-event" or numeric id like "42".
     * @return array<string, mixed>|null
     */
    public function getBySlugOrId(string $slugOrId): ?array
    {
        $pdo = $this->db->pdo();

        if (ctype_digit($slugOrId)) {
            $stmt = $pdo->prepare(
                "SELECT id, title, event_date, slug, description
                 FROM vt_events
                 WHERE id = :id
                 LIMIT 1"
            );
            $stmt->execute([':id' => (int)$slugOrId]);
        } else {
            $stmt = $pdo->prepare(
                "SELECT id, title, event_date, slug, description
                 FROM vt_events
                 WHERE slug = :slug
                 LIMIT 1"
            );
            $stmt->execute([':slug' => $slugOrId]);
        }

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    /**
     * @param array{title:string,description:string,event_date:?string} $data
     */
    public function create(array $data): string
    {
        $title = trim($data['title']);
        if ($title === '') {
            throw new RuntimeException('Title is required.');
        }

        $pdo = $this->db->pdo();
        $slug = $this->ensureUniqueSlug($pdo, $this->slugify($title));

        $createdAt = date('Y-m-d H:i:s');

        $stmt = $pdo->prepare(
            "INSERT INTO vt_events (
                title,
                slug,
                description,
                event_date,
                created_at,
                updated_at,
                created_by,
                author_id,
                post_id,
                event_status,
                status,
                visibility,
                privacy
            ) VALUES (
                :title,
                :slug,
                :description,
                :event_date,
                :created_at,
                :updated_at,
                :created_by,
                :author_id,
                :post_id,
                :event_status,
                :status,
                :visibility,
                :privacy
            )"
        );

        $stmt->execute([
            ':title' => $title,
            ':slug' => $slug,
            ':description' => $data['description'],
            ':event_date' => $data['event_date'],
            ':created_at' => $createdAt,
            ':updated_at' => $createdAt,
            ':created_by' => 1,
            ':author_id' => 1,
            ':post_id' => 0,
            ':event_status' => 'active',
            ':status' => 'active',
            ':visibility' => 'public',
            ':privacy' => 'public',
        ]);

        return $slug;
    }

    /**
     * @param array{title:string,description:string,event_date:?string} $data
     */
    public function update(string $slugOrId, array $data): string
    {
        $event = $this->getBySlugOrId($slugOrId);
        if ($event === null) {
            throw new RuntimeException('Event not found.');
        }

        $title = trim($data['title']);
        if ($title === '') {
            throw new RuntimeException('Title is required.');
        }

        $slug = (string)($event['slug'] ?? $slugOrId);
        $pdo = $this->db->pdo();
        $updatedAt = date('Y-m-d H:i:s');

        $stmt = $pdo->prepare(
            "UPDATE vt_events
             SET title = :title,
                 description = :description,
                 event_date = :event_date,
                 updated_at = :updated_at
             WHERE slug = :slug
             LIMIT 1"
        );

        $stmt->execute([
            ':title' => $title,
            ':description' => $data['description'],
            ':event_date' => $data['event_date'],
            ':updated_at' => $updatedAt,
            ':slug' => $slug,
        ]);

        return $slug;
    }

    private function slugify(string $title): string
    {
        $slug = strtolower($title);
        $slug = preg_replace('/[^a-z0-9]+/i', '-', $slug);
        $slug = trim($slug, '-');
        return $slug !== '' ? $slug : 'event';
    }

    private function ensureUniqueSlug(PDO $pdo, string $slug): string
    {
        $base = $slug;
        $i = 1;

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM vt_events WHERE slug = :slug');

        while (true) {
            $stmt->execute([':slug' => $slug]);
            if ((int)$stmt->fetchColumn() === 0) {
                return $slug;
            }
            $slug = $base . '-' . ++$i;
        }
    }
}
