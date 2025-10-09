<?php
declare(strict_types=1);

namespace App\Http\Controller;

use App\Http\Request;
use App\Services\CommunityService;
use App\Services\CircleService;
use App\Services\AuthService;

final class CommunityController
{
    private const VALID_CIRCLES = ['all', 'inner', 'trusted', 'extended'];

    public function __construct(
        private CommunityService $communities,
        private CircleService $circles,
        private AuthService $auth
    ) {
    }

    /**
     * @return array{communities: array<int, array<string, mixed>>}
     */
    public function index(): array
    {
        $request = $this->request();
        $circle = $this->normalizeCircle($request->query('circle'));
        $viewerId = $this->auth->currentUserId() ?? 0;
        $context = $this->circles->buildContext($viewerId);
        $allowed = $this->circles->resolveCommunitiesForCircle($context, $circle);
        $memberCommunities = $this->circles->memberCommunities($context);

        $communities = $this->communities->listByCircle($allowed, $memberCommunities);

        return [
            'communities' => $communities,
            'circle' => $circle,
            'circle_context' => $context,
        ];
    }

    /**
     * @return array{
     *   community: array<string, mixed>|null,
     *   status: int,
     *   viewer?: array<string, mixed>,
     *   circle_context?: array<string, array{communities: array<int>, creators: array<int>}>
     * }
     */
    public function show(string $slugOrId): array
    {
        $viewerId = $this->auth->currentUserId() ?? 0;
        $context = $this->circles->buildContext($viewerId);
        $memberCommunities = $this->circles->memberCommunities($context);

        $community = $this->communities->getBySlugOrId($slugOrId);
        if ($community === null) {
            return [
                'community' => null,
                'status' => 404,
                'circle_context' => $context,
            ];
        }

        $communityId = (int)($community['id'] ?? 0);
        $privacy = strtolower((string)($community['privacy'] ?? 'public'));
        $isMember = $communityId > 0 && in_array($communityId, $memberCommunities, true);
        $isCreator = $viewerId > 0 && isset($community['creator_id']) && (int)$community['creator_id'] === $viewerId;

        if ($privacy === 'private' && !$isMember && !$isCreator) {
            return [
                'community' => null,
                'status' => 404,
                'circle_context' => $context,
            ];
        }

        return [
            'community' => $community,
            'status' => 200,
            'viewer' => [
                'id' => $viewerId,
                'is_member' => $isMember,
                'is_creator' => $isCreator,
            ],
            'circle_context' => $context,
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
                'name' => '',
                'description' => '',
                'privacy' => 'public',
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
        $validated = $this->validateCommunityInput($this->request());

        if ($validated['errors']) {
            return [
                'errors' => $validated['errors'],
                'input' => $validated['input'],
            ];
        }

        $slug = $this->communities->create([
            'name' => $validated['input']['name'],
            'description' => $validated['input']['description'],
            'privacy' => $validated['input']['privacy'],
        ]);

        return [
            'redirect' => '/communities/' . $slug,
        ];
    }

    /**
     * @return array{
     *   community: array<string,mixed>|null,
     *   errors: array<string,string>,
     *   input: array<string,string>
     * }
     */
    public function edit(string $slugOrId): array
    {
        $community = $this->communities->getBySlugOrId($slugOrId);
        if ($community === null) {
            return [
                'community' => null,
                'errors' => [],
                'input' => [],
            ];
        }

        return [
            'community' => $community,
            'errors' => [],
            'input' => [
                'name' => $community['title'] ?? '',
                'description' => $community['description'] ?? '',
                'privacy' => strtolower((string)($community['privacy'] ?? 'public')),
            ],
        ];
    }

    /**
     * @return array{
     *   redirect?: string,
     *   community?: array<string,mixed>|null,
     *   errors?: array<string,string>,
     *   input?: array<string,string>
     * }
     */
    public function update(string $slugOrId): array
    {
        $community = $this->communities->getBySlugOrId($slugOrId);
        if ($community === null) {
            return [
                'community' => null,
            ];
        }

        $validated = $this->validateCommunityInput($this->request());
        if ($validated['errors']) {
            return [
                'community' => $community,
                'errors' => $validated['errors'],
                'input' => $validated['input'],
            ];
        }

        $this->communities->update($community['slug'], [
            'name' => $validated['input']['name'],
            'description' => $validated['input']['description'],
            'privacy' => $validated['input']['privacy'],
        ]);

        return [
            'redirect' => '/communities/' . $community['slug'],
        ];
    }

    /**
     * @return array{
     *   input: array<string,string>,
     *   errors: array<string,string>
     * }
     */
    /**
     * @return array{redirect: string}
     */
    public function destroy(string $slugOrId): array
    {
        $this->communities->delete($slugOrId);
        return [
            'redirect' => '/communities',
        ];
    }

    private function validateCommunityInput(Request $request): array
    {
        $input = [
            'name' => trim((string)$request->input('name', '')),
            'description' => trim((string)$request->input('description', '')),
            'privacy' => strtolower(trim((string)$request->input('privacy', 'public'))),
        ];

        $errors = [];
        if ($input['name'] === '') {
            $errors['name'] = 'Name is required.';
        }

        if (!in_array($input['privacy'], ['public', 'private'], true)) {
            $errors['privacy'] = 'Privacy must be public or private.';
        }

        return [
            'input' => $input,
            'errors' => $errors,
        ];
    }

    private function request(): Request
    {
        /** @var Request $request */
        $request = vt_service('http.request');
        return $request;
    }

    private function normalizeCircle(?string $circle): string
    {
        $circle = strtolower((string) $circle);
        return in_array($circle, self::VALID_CIRCLES, true) ? $circle : 'all';
    }
}
