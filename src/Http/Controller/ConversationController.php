<?php
declare(strict_types=1);

namespace App\Http\Controller;

use App\Services\ConversationService;

final class ConversationController
{
    public function __construct(private ConversationService $conversations)
    {
    }

    /**
     * @return array{conversations: array<int, array<string, mixed>>}
     */
    public function index(): array
    {
        return [
            'conversations' => $this->conversations->listRecent(),
        ];
    }

    /**
     * @return array{conversation: array<string, mixed>|null}
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

        return [
            'redirect' => '/conversations/' . $conversation['slug'],
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
}
