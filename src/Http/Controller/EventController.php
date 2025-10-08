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

    /**
     * @return array{
     *   errors: array<string,string>,
     *   input: array<string,string>
     * }
     */
    public function create(): array
    {
        return [
            'errors' => [],
            'input' => [
                'title' => '',
                'description' => '',
                'event_date' => '',
            ],
        ];
    }

    /**
     * @return array{
     *   errors?: array<string,string>,
     *   input?: array<string,string>,
     *   redirect?: string
     * }
     */
    public function store(): array
    {
        /** @var \App\Http\Request $request */
        $request = vt_service('http.request');

        $input = [
            'title' => trim((string)$request->input('title', '')),
            'description' => trim((string)$request->input('description', '')),
            'event_date' => trim((string)$request->input('event_date', '')),
        ];

        $errors = [];

        if ($input['title'] === '') {
            $errors['title'] = 'Title is required.';
        }

        $eventDateDb = null;
        if ($input['event_date'] !== '') {
            $timestamp = strtotime($input['event_date']);
            if ($timestamp === false) {
                $errors['event_date'] = 'Provide a valid date/time.';
            } else {
                $eventDateDb = date('Y-m-d H:i:s', $timestamp);
            }
        }

        if ($errors) {
            return [
                'errors' => $errors,
                'input' => $input,
            ];
        }

        $slug = $this->events->create([
            'title' => $input['title'],
            'description' => $input['description'],
            'event_date' => $eventDateDb,
        ]);

        return [
            'redirect' => '/events/' . $slug,
        ];
    }
}
