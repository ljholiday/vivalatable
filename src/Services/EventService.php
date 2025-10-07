<?php
declare(strict_types=1);

namespace App\Services;

use App\Database\Database;
use PDO;

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
}

