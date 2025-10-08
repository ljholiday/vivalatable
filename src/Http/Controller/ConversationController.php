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
}
