<?php
declare(strict_types=1);

namespace App\Services;

use App\Database\Database;
use PDO;

final class EventService {
    public function __construct(private Database $db) {}

    public function listRecent(): array {
        $sql = "SELECT id, title, event_date, slug, description
                FROM vt_events
                ORDER BY event_date DESC
                LIMIT 20";
        return $this->db->pdo()->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }
}

