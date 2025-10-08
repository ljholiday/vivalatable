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
        return [
            'conversation' => $this->conversations->getBySlugOrId($slugOrId),
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

    private function request(): \App\Http\Request
    {
        /** @var \App\Http\Request $request */
        $request = vt_service('http.request');
        return $request;
    }
}
