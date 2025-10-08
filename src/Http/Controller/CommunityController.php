<?php
declare(strict_types=1);

namespace App\Http\Controller;

use App\Services\CommunityService;

final class CommunityController
{
    public function __construct(private CommunityService $communities)
    {
    }

    /**
     * @return array{communities: array<int, array<string, mixed>>}
     */
    public function index(): array
    {
        return [
            'communities' => $this->communities->listRecent(),
        ];
    }

    /**
     * @return array{community: array<string, mixed>|null}
     */
    public function show(string $slugOrId): array
    {
        return [
            'community' => $this->communities->getBySlugOrId($slugOrId),
        ];
    }
}
