<?php
declare(strict_types=1);

namespace App\Http\Controller;

use App\Services\CircleService;
use App\Services\ConversationService;

final class ConversationController
{
    private const VALID_CIRCLES = ['all', 'inner', 'trusted', 'extended'];

    public function __construct(
        private ConversationService $conversations,
        private CircleService $circles
    ) {
    }

    /**
     * @return array{
     *   conversations: array<int, array<string, mixed>>,
     *   circle: string,
     *   circle_context: array<string, array{communities: array<int>, creators: array<int>}>,
     *   pagination: array{page:int, per_page:int, has_more:bool, next_page:int|null}
     * }
     */
    public function index(): array
    {
        $request = $this->request();
        $circle = $this->normalizeCircle($request->query('circle'));

        $viewerId = $this->viewerId();
        $context = $this->circles->buildContext($viewerId);
        $allowedCommunities = $this->circles->resolveCommunitiesForCircle($context, $circle);
        $memberCommunities = $this->circles->memberCommunities($context);

        $options = [
            'page' => max(1, (int)$request->query('page', 1)),
            'per_page' => 20,
        ];

        $feed = $this->conversations->listByCircle(
            $viewerId,
            $circle,
            $allowedCommunities,
            $memberCommunities,
            $options
        );

        return [
            'conversations' => $feed['conversations'],
            'circle' => $circle,
            'circle_context' => $context,
            'pagination' => $feed['pagination'],
        ];
    }

    /**
     * @return array{
     *   conversation: array<string, mixed>|null,
     *   replies: array<int, array<string, mixed>>,
     *   reply_errors: array<string,string>,
     *   reply_input: array<string,string>
     * }
     */
    public function show(string $slugOrId): array
    {
        $conversation = $this->conversations->getBySlugOrId($slugOrId);
        $replies = [];
        if ($conversation !== null && isset($conversation['id'])) {
            $replies = $this->conversations->listReplies((int)$conversation['id']);
        }

        return [
            'conversation' => $conversation,
            'replies' => $replies,
            'reply_errors' => [],
            'reply_input' => ['content' => ''],
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
                'content' => '',
            ],
        ];
    }

    /**
     * @return array{
     *   redirect?: string,
     *   errors?: array<string,string>,
     *   input?: array<string,string>
     * }
     */
    public function store(): array
    {
        $request = $this->request();

        $input = [
            'title' => trim((string)$request->input('title', '')),
            'content' => trim((string)$request->input('content', '')),
        ];

        $errors = [];

        if ($input['title'] === '') {
            $errors['title'] = 'Title is required.';
        }
        if ($input['content'] === '') {
            $errors['content'] = 'Content is required.';
        }

        if ($errors) {
            return [
                'errors' => $errors,
                'input' => $input,
            ];
        }

        $slug = $this->conversations->create([
            'title' => $input['title'],
            'content' => $input['content'],
        ]);

        return [
            'redirect' => '/conversations/' . $slug,
        ];
    }

    /**
     * @return array{
     *   conversation: array<string,mixed>|null,
     *   errors: array<string,string>,
     *   input: array<string,string>
     * }
     */
    public function edit(string $slugOrId): array
    {
        $conversation = $this->conversations->getBySlugOrId($slugOrId);
        if ($conversation === null) {
            return [
                'conversation' => null,
                'errors' => [],
                'input' => [],
            ];
        }

        return [
            'conversation' => $conversation,
            'errors' => [],
            'input' => [
                'title' => $conversation['title'] ?? '',
                'content' => $conversation['content'] ?? '',
            ],
        ];
    }

    /**
     * @return array{
     *   redirect?: string,
     *   conversation?: array<string,mixed>|null,
     *   errors?: array<string,string>,
     *   input?: array<string,string>
     * }
     */
    public function update(string $slugOrId): array
    {
        $conversation = $this->conversations->getBySlugOrId($slugOrId);
        if ($conversation === null) {
            return [
                'conversation' => null,
            ];
        }

        $request = $this->request();
        $input = [
            'title' => trim((string)$request->input('title', '')),
            'content' => trim((string)$request->input('content', '')),
        ];

        $errors = [];
        if ($input['title'] === '') {
            $errors['title'] = 'Title is required.';
        }
        if ($input['content'] === '') {
            $errors['content'] = 'Content is required.';
        }

        if ($errors) {
            return [
                'conversation' => $conversation,
                'errors' => $errors,
                'input' => $input,
            ];
        }

        $this->conversations->update($conversation['slug'], [
            'title' => $input['title'],
            'content' => $input['content'],
        ]);

        return [
            'redirect' => '/conversations/' . $conversation['slug'],
        ];
    }

    /**
     * @return array{
     *   conversation: array<string,mixed>|null,
     *   replies: array<int,array<string,mixed>>,
     *   reply_errors: array<string,string>,
     *   reply_input: array<string,string>,
     *   redirect?: string
     * }
     */
    public function reply(string $slugOrId): array
    {
        $conversation = $this->conversations->getBySlugOrId($slugOrId);
        if ($conversation === null || !isset($conversation['id'])) {
            return [
                'conversation' => null,
                'replies' => [],
                'reply_errors' => ['conversation' => 'Conversation not found.'],
                'reply_input' => ['content' => ''],
            ];
        }

        $request = $this->request();
        $input = [
            'content' => trim((string)$request->input('content', '')),
        ];

        $errors = [];
        if ($input['content'] === '') {
            $errors['content'] = 'Reply content is required.';
        }

        if ($errors) {
            return [
                'conversation' => $conversation,
                'replies' => $this->conversations->listReplies((int)$conversation['id']),
                'reply_errors' => $errors,
                'reply_input' => $input,
            ];
        }

        $this->conversations->addReply((int)$conversation['id'], $input);

        $redirect = '/conversations/' . $conversation['slug'];
        $circleParam = $request->query('circle');
        if (is_string($circleParam) && $circleParam !== '') {
            $redirect .= '?circle=' . urlencode($circleParam);
        }

        return [
            'redirect' => $redirect,
            'conversation' => $conversation,
            'replies' => [],
            'reply_errors' => [],
            'reply_input' => ['content' => ''],
        ];
    }

    /**
     * @return array{redirect: string}
     */
    public function destroy(string $slugOrId): array
    {
        $this->conversations->delete($slugOrId);
        return [
            'redirect' => '/conversations',
        ];
    }

    private function request(): \App\Http\Request
    {
        /** @var \App\Http\Request $request */
        $request = vt_service('http.request');
        return $request;
    }

    private function viewerId(): int
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
    }

    private function normalizeCircle(?string $circle): string
    {
        $circle = strtolower((string)$circle);
        return in_array($circle, self::VALID_CIRCLES, true) ? $circle : 'all';
    }
}
