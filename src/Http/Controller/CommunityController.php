<?php
declare(strict_types=1);

namespace App\Http\Controller;

use App\Http\Request;
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
     *   input: array<string,string>,
     *   errors: array<string,string>
     * }
     */
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
}
