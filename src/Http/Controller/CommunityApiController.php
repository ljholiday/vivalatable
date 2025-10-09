<?php
declare(strict_types=1);

namespace App\Http\Controller;

use App\Http\Request;
use App\Services\AuthService;
use App\Services\CommunityService;

final class CommunityApiController
{
    public function __construct(
        private CommunityService $communities,
        private AuthService $auth
    ) {
    }

    /**
     * @return array{status:int, body:array<string,mixed>}
     */
    public function join(int $communityId): array
    {
        $request = $this->request();
        $nonce = (string)$request->input('nonce', '');

        if (!$this->verifyNonce($nonce, 'vt_nonce')) {
            return $this->error('Security verification failed', 403);
        }

        $viewerId = $this->auth->currentUserId();
        if ($viewerId === null || $viewerId <= 0) {
            return $this->error('You must be logged in to join a community', 401);
        }

        $community = $this->communities->getBySlugOrId((string)$communityId);
        if ($community === null) {
            return $this->error('Community not found', 404);
        }

        $actualId = (int)($community['id'] ?? 0);
        if ($actualId <= 0) {
            return $this->error('Invalid community', 400);
        }

        if ($this->communities->isMember($actualId, $viewerId)) {
            return $this->error('You are already a member of this community', 409);
        }

        $viewer = $this->auth->getCurrentUser();
        $memberData = [
            'user_id' => $viewerId,
            'email' => $viewer->email ?? '',
            'display_name' => $viewer->display_name ?? $viewer->username ?? '',
            'role' => 'member',
            'status' => 'active',
        ];

        try {
            $this->communities->addMember($actualId, $memberData);
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 500);
        }

        $slug = (string)($community['slug'] ?? $actualId);

        return $this->success([
            'message' => 'Welcome to ' . ($community['title'] ?? 'the community') . '!',
            'redirect_url' => '/communities/' . $slug,
            'community_slug' => $slug,
        ]);
    }

    private function request(): Request
    {
        /** @var Request $request */
        $request = vt_service('http.request');
        return $request;
    }

    private function verifyNonce(string $nonce, string $action): bool
    {
        if ($nonce === '') {
            return false;
        }

        $this->ensureLegacySecurityLoaded();

        if (class_exists('\VT_Security')) {
            try {
                return \VT_Security::verifyNonce($nonce, $action);
            } catch (\Throwable $e) {
                // fall through
            }
        }

        try {
            $security = vt_service('security.service');
            if (is_object($security) && method_exists($security, 'verifyNonce')) {
                return (bool)$security->verifyNonce($nonce, $action);
            }
        } catch (\Throwable $e) {
            // ignore
        }

        return true;
    }

    private function ensureLegacySecurityLoaded(): void
    {
        if (!class_exists('\VT_Security')) {
            $path = dirname(__DIR__, 3) . '/legacy/includes/includes/class-security.php';
            if (is_file($path)) {
                require_once $path;
            }
        }
    }

    /**
     * @return array{status:int, body:array<string,mixed>}
     */
    private function success(array $data, int $status = 200): array
    {
        return [
            'status' => $status,
            'body' => [
                'success' => true,
                'data' => $data,
            ],
        ];
    }

    /**
     * @return array{status:int, body:array<string,mixed>}
     */
    private function error(string $message, int $status): array
    {
        return [
            'status' => $status,
            'body' => [
                'success' => false,
                'message' => $message,
            ],
        ];
    }
}
