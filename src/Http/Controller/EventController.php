<?php
declare(strict_types=1);

namespace App\Http\Controller;

use App\Services\EventService;

/**
 * Thin HTTP controller for event listings and detail views.
 * Controllers return view data arrays that templates consume directly.
 */
final class EventController
{
    public function __construct(private EventService $events)
    {
    }

    /**
     * @return array{events: array<int, array<string, mixed>>}
     */
    public function index(): array
    {
        return [
            'events' => $this->events->listRecent(),
        ];
    }

    /**
     * @return array{event: array<string, mixed>|null}
     */
    public function show(string $slugOrId): array
    {
        return [
            'event' => $this->events->getBySlugOrId($slugOrId),
        ];
    }
}
