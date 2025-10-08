<?php
declare(strict_types=1);

namespace App\Http\Controller;

use App\Http\Request;
use App\Services\EventService;
use App\Services\CircleService;

/**
 * Thin HTTP controller for event listings and detail views.
 * Controllers return view data arrays that templates consume directly.
 */
final class EventController
{
    private const VALID_CIRCLES = ['all', 'inner', 'trusted', 'extended'];

    public function __construct(
        private EventService $events,
        private CircleService $circles
    ) {
    }

    /**
     * @return array{events: array<int, array<string, mixed>>}
     */
    public function index(): array
    {
        $request = $this->request();
        $circle = $this->normalizeCircle($request->query('circle'));
        $viewerId = $this->viewerId();
        $context = $this->circles->buildContext($viewerId);
        $allowed = $this->circles->resolveCommunitiesForCircle($context, $circle);
        $memberCommunities = $this->circles->memberCommunities($context);

        $events = $this->events->listByCircle($allowed, $memberCommunities);

        return [
            'events' => $events,
            'circle' => $circle,
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
        $validated = $this->validateEventInput($this->request());

        if ($validated['errors']) {
            return [
                'errors' => $validated['errors'],
                'input' => $validated['input'],
            ];
        }

        $slug = $this->events->create([
            'title' => $validated['input']['title'],
            'description' => $validated['input']['description'],
            'event_date' => $validated['event_date_db'],
        ]);

        return [
            'redirect' => '/events/' . $slug,
        ];
    }

    /**
     * @return array{
     *   event: array<string,mixed>|null,
     *   errors: array<string,string>,
     *   input: array<string,string>
     * }
     */
    public function edit(string $slugOrId): array
    {
        $event = $this->events->getBySlugOrId($slugOrId);
        if ($event === null) {
            return [
                'event' => null,
                'errors' => [],
                'input' => [],
            ];
        }

        return [
            'event' => $event,
            'errors' => [],
            'input' => [
                'title' => $event['title'] ?? '',
                'description' => $event['description'] ?? '',
                'event_date' => $this->formatForInput($event['event_date'] ?? null),
            ],
        ];
    }

    /**
     * @return array{
     *   redirect?: string,
     *   event?: array<string,mixed>|null,
     *   errors?: array<string,string>,
     *   input?: array<string,string>
     * }
     */
    public function update(string $slugOrId): array
    {
        $event = $this->events->getBySlugOrId($slugOrId);
        if ($event === null) {
            return [
                'event' => null,
            ];
        }

        $validated = $this->validateEventInput($this->request());
        if ($validated['errors']) {
            return [
                'event' => $event,
                'errors' => $validated['errors'],
                'input' => $validated['input'],
            ];
        }

        $this->events->update($event['slug'], [
            'title' => $validated['input']['title'],
            'description' => $validated['input']['description'],
            'event_date' => $validated['event_date_db'],
        ]);

        return [
            'redirect' => '/events/' . $event['slug'],
        ];
    }

    /**
     * @return array{
     *   input: array<string,string>,
     *   errors: array<string,string>,
     *   event_date_db: ?string
     * }
     */
    private function validateEventInput(Request $request): array
    {
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

        return [
            'input' => $input,
            'errors' => $errors,
            'event_date_db' => $eventDateDb,
        ];
    }

    private function request(): Request
    {
        /** @var Request $request */
        $request = vt_service('http.request');
        return $request;
    }

    private function viewerId(): int
    {
        return 1;
    }

    private function normalizeCircle(?string $circle): string
    {
        $circle = strtolower((string) $circle);
        return in_array($circle, self::VALID_CIRCLES, true) ? $circle : 'all';
    }

    private function formatForInput(?string $dbDate): string
    {
        if (!$dbDate) {
            return '';
        }
        $timestamp = strtotime($dbDate);
        return $timestamp ? date('Y-m-d\TH:i', $timestamp) : '';
    }

    /**
     * @return array{redirect: string}
     */
    public function destroy(string $slugOrId): array
    {
        $this->events->delete($slugOrId);
        return [
            'redirect' => '/events',
        ];
    }
}
