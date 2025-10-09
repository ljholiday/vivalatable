<?php
declare(strict_types=1);

use App\Http\Router;
use App\Http\Request;

/**
 * Application routes
 *
 * @param Router $router
 * @return void
 */
return static function (Router $router): void {
    // Auth redirects
    $router->any('/login', static function (Request $request) {
        header('Location: /auth');
        exit;
    });

    $router->any('/register', static function (Request $request) {
        header('Location: /auth');
        exit;
    });

    // Home
    $router->get('/', static function (Request $request) {
        $authService = vt_service('auth.service');
        if (!$authService->isLoggedIn()) {
            header('Location: /auth');
            exit;
        }

        $home = dirname(__DIR__, 2) . '/templates/home.php';
        if (!is_file($home)) {
            echo "<h1>Home</h1>";
            return null;
        }
        require $home;
        return null;
    });

    // Auth routes
    $router->get('/auth', static function (Request $request) {
        $view = vt_service('controller.auth')->landing();
        require dirname(__DIR__, 2) . '/templates/auth-landing.php';
        return null;
    });

    $router->post('/auth/login', static function (Request $request) {
        $view = vt_service('controller.auth')->login();
        if (isset($view['redirect'])) {
            header('Location: ' . $view['redirect']);
            exit;
        }
        require dirname(__DIR__, 2) . '/templates/auth-landing.php';
        return null;
    });

    $router->post('/auth/register', static function (Request $request) {
        $view = vt_service('controller.auth')->register();
        if (isset($view['redirect'])) {
            header('Location: ' . $view['redirect']);
            exit;
        }
        require dirname(__DIR__, 2) . '/templates/auth-landing.php';
        return null;
    });

    $router->post('/auth/logout', static function (Request $request) {
        $result = vt_service('controller.auth')->logout();
        header('Location: ' . ($result['redirect'] ?? '/auth'));
        exit;
    });

    // API: Conversations
    $router->post('/api/conversations', static function (Request $request) {
        $response = vt_service('controller.conversations.api')->list();
        http_response_code($response['status'] ?? 200);
        header('Content-Type: application/json');
        echo json_encode($response['body']);
        return null;
    });

    $router->post('/api/conversations/{slug}/replies', static function (Request $request, string $slug) {
        $response = vt_service('controller.conversations.api')->reply($slug);
        http_response_code($response['status'] ?? 200);
        header('Content-Type: application/json');
        echo json_encode($response['body']);
        return null;
    });

    // API: Communities
    $router->post('/api/communities/{id}/join', static function (Request $request, string $id) {
        $response = vt_service('controller.communities.api')->join((int)$id);
        http_response_code($response['status'] ?? 200);
        header('Content-Type: application/json');
        echo json_encode($response['body']);
        return null;
    });

    // API: Invitations
    $router->post('/api/invitations/accept', static function (Request $request) {
        $response = vt_service('controller.invitations')->accept();
        http_response_code($response['status'] ?? 200);
        header('Content-Type: application/json');
        echo json_encode($response['body']);
        return null;
    });

    $router->post('/api/{type}/{id}/invitations', static function (Request $request, string $type, string $id) {
        $entityId = (int)$id;
        $controller = vt_service('controller.invitations');
        $response = $type === 'communities'
            ? $controller->sendCommunity($entityId)
            : $controller->sendEvent($entityId);
        http_response_code($response['status'] ?? 200);
        header('Content-Type: application/json');
        echo json_encode($response['body']);
        return null;
    });

    $router->get('/api/{type}/{id}/invitations', static function (Request $request, string $type, string $id) {
        $entityId = (int)$id;
        $controller = vt_service('controller.invitations');
        $response = $type === 'communities'
            ? $controller->listCommunity($entityId)
            : $controller->listEvent($entityId);
        http_response_code($response['status'] ?? 200);
        header('Content-Type: application/json');
        echo json_encode($response['body']);
        return null;
    });

    $router->post('/api/events/{eventId}/invitations/{invitationId}/resend', static function (Request $request, string $eventId, string $invitationId) {
        $response = vt_service('controller.invitations')->resendEvent((int)$eventId, (int)$invitationId);
        http_response_code($response['status'] ?? 200);
        header('Content-Type: application/json');
        echo json_encode($response['body']);
        return null;
    });

    $router->delete('/api/{type}/{entityId}/invitations/{invitationId}', static function (Request $request, string $type, string $entityId, string $invitationId) {
        $controller = vt_service('controller.invitations');
        $response = $type === 'communities'
            ? $controller->deleteCommunity((int)$entityId, (int)$invitationId)
            : $controller->deleteEvent((int)$entityId, (int)$invitationId);
        http_response_code($response['status'] ?? 200);
        header('Content-Type: application/json');
        echo json_encode($response['body']);
        return null;
    });

    $router->get('/api/communities/{id}/members', static function (Request $request, string $id) {
        $response = vt_service('controller.invitations')->listCommunityMembers((int)$id);
        http_response_code($response['status'] ?? 200);
        header('Content-Type: application/json');
        echo json_encode($response['body']);
        return null;
    });

    $router->post('/api/communities/{communityId}/members/{memberId}/role', static function (Request $request, string $communityId, string $memberId) {
        $response = vt_service('controller.invitations')->updateCommunityMemberRole((int)$communityId, (int)$memberId);
        http_response_code($response['status'] ?? 200);
        header('Content-Type: application/json');
        echo json_encode($response['body']);
        return null;
    });

    $router->delete('/api/communities/{communityId}/members/{memberId}', static function (Request $request, string $communityId, string $memberId) {
        $response = vt_service('controller.invitations')->removeCommunityMember((int)$communityId, (int)$memberId);
        http_response_code($response['status'] ?? 200);
        header('Content-Type: application/json');
        echo json_encode($response['body']);
        return null;
    });

    // Events
    $router->get('/events', static function (Request $request) {
        $view = vt_service('controller.events')->index();
        $events = $view['events'];
        $filter = $view['filter'] ?? 'all';
        require dirname(__DIR__, 2) . '/templates/events-list.php';
        return null;
    });

    $router->get('/events/create', static function (Request $request) {
        $view = vt_service('controller.events')->create();
        $errors = $view['errors'];
        $input = $view['input'];
        require dirname(__DIR__, 2) . '/templates/event-create.php';
        return null;
    });

    $router->post('/events/create', static function (Request $request) {
        $result = vt_service('controller.events')->store();
        if (isset($result['redirect'])) {
            header('Location: ' . $result['redirect']);
            exit;
        }
        $errors = $result['errors'] ?? [];
        $input = $result['input'] ?? [];
        require dirname(__DIR__, 2) . '/templates/event-create.php';
        return null;
    });

    $router->get('/events/{slug}/edit', static function (Request $request, string $slug) {
        $view = vt_service('controller.events')->edit($slug);
        if ($view['event'] === null) {
            http_response_code(404);
            echo 'Not Found';
            return null;
        }
        $event = $view['event'];
        $errors = $view['errors'];
        $input = $view['input'];
        require dirname(__DIR__, 2) . '/templates/event-edit.php';
        return null;
    });

    $router->post('/events/{slug}/edit', static function (Request $request, string $slug) {
        $result = vt_service('controller.events')->update($slug);
        if (isset($result['redirect'])) {
            header('Location: ' . $result['redirect']);
            exit;
        }
        if (!isset($result['event']) || $result['event'] === null) {
            http_response_code(404);
            echo 'Not Found';
            return null;
        }
        $event = $result['event'];
        $errors = $result['errors'] ?? [];
        $input = $result['input'] ?? [];
        require dirname(__DIR__, 2) . '/templates/event-edit.php';
        return null;
    });

    $router->post('/events/{slug}/delete', static function (Request $request, string $slug) {
        $result = vt_service('controller.events')->destroy($slug);
        header('Location: ' . $result['redirect']);
        exit;
    });

    $router->get('/events/{slug}', static function (Request $request, string $slug) {
        $view = vt_service('controller.events')->show($slug);
        $event = $view['event'];
        require dirname(__DIR__, 2) . '/templates/event-detail.php';
        return null;
    });

    // Communities
    $router->get('/communities', static function (Request $request) {
        $view = vt_service('controller.communities')->index();
        $communities = $view['communities'];
        $circle = $view['circle'] ?? 'all';
        $circle_context = $view['circle_context'] ?? ['inner' => ['communities' => [], 'creators' => []], 'trusted' => ['communities' => [], 'creators' => []], 'extended' => ['communities' => [], 'creators' => []]];
        require dirname(__DIR__, 2) . '/templates/communities-list.php';
        return null;
    });

    $router->get('/communities/create', static function (Request $request) {
        $view = vt_service('controller.communities')->create();
        $errors = $view['errors'];
        $input = $view['input'];
        require dirname(__DIR__, 2) . '/templates/community-create.php';
        return null;
    });

    $router->post('/communities/create', static function (Request $request) {
        $result = vt_service('controller.communities')->store();
        if (isset($result['redirect'])) {
            header('Location: ' . $result['redirect']);
            exit;
        }
        $errors = $result['errors'] ?? [];
        $input = $result['input'] ?? [];
        require dirname(__DIR__, 2) . '/templates/community-create.php';
        return null;
    });

    $router->get('/communities/{slug}/edit', static function (Request $request, string $slug) {
        $view = vt_service('controller.communities')->edit($slug);
        if ($view['community'] === null) {
            http_response_code(404);
            echo 'Not Found';
            return null;
        }
        $community = $view['community'];
        $errors = $view['errors'];
        $input = $view['input'];
        require dirname(__DIR__, 2) . '/templates/community-edit.php';
        return null;
    });

    $router->post('/communities/{slug}/edit', static function (Request $request, string $slug) {
        $result = vt_service('controller.communities')->update($slug);
        if (isset($result['redirect'])) {
            header('Location: ' . $result['redirect']);
            exit;
        }
        if (!isset($result['community']) || $result['community'] === null) {
            http_response_code(404);
            echo 'Not Found';
            return null;
        }
        $community = $result['community'];
        $errors = $result['errors'] ?? [];
        $input = $result['input'] ?? [];
        require dirname(__DIR__, 2) . '/templates/community-edit.php';
        return null;
    });

    $router->post('/communities/{slug}/delete', static function (Request $request, string $slug) {
        $result = vt_service('controller.communities')->destroy($slug);
        header('Location: ' . $result['redirect']);
        exit;
    });

    $router->get('/communities/{slug}', static function (Request $request, string $slug) {
        $view = vt_service('controller.communities')->show($slug);
        $community = $view['community'];
        $status = (int)($view['status'] ?? ($community === null ? 404 : 200));
        if ($status !== 200) {
            http_response_code($status);
        }
        require dirname(__DIR__, 2) . '/templates/community-detail.php';
        return null;
    });

    // Conversations
    $router->get('/conversations', static function (Request $request) {
        $view = vt_service('controller.conversations')->index();
        $conversations = $view['conversations'];
        $circle = $view['circle'] ?? 'all';
        $circle_context = $view['circle_context'] ?? ['inner' => ['communities' => [], 'creators' => []], 'trusted' => ['communities' => [], 'creators' => []], 'extended' => ['communities' => [], 'creators' => []]];
        $pagination = $view['pagination'] ?? ['page' => 1, 'per_page' => 20, 'has_more' => false, 'next_page' => null];
        require dirname(__DIR__, 2) . '/templates/conversations-list.php';
        return null;
    });

    $router->get('/conversations/create', static function (Request $request) {
        $view = vt_service('controller.conversations')->create();
        $errors = $view['errors'];
        $input = $view['input'];
        require dirname(__DIR__, 2) . '/templates/conversation-create.php';
        return null;
    });

    $router->post('/conversations/create', static function (Request $request) {
        $result = vt_service('controller.conversations')->store();
        if (isset($result['redirect'])) {
            header('Location: ' . $result['redirect']);
            exit;
        }
        $errors = $result['errors'] ?? [];
        $input = $result['input'] ?? [];
        require dirname(__DIR__, 2) . '/templates/conversation-create.php';
        return null;
    });

    $router->get('/conversations/{slug}/edit', static function (Request $request, string $slug) {
        $view = vt_service('controller.conversations')->edit($slug);
        if ($view['conversation'] === null) {
            http_response_code(404);
            echo 'Not Found';
            return null;
        }
        $conversation = $view['conversation'];
        $errors = $view['errors'];
        $input = $view['input'];
        require dirname(__DIR__, 2) . '/templates/conversation-edit.php';
        return null;
    });

    $router->post('/conversations/{slug}/edit', static function (Request $request, string $slug) {
        $result = vt_service('controller.conversations')->update($slug);
        if (isset($result['redirect'])) {
            header('Location: ' . $result['redirect']);
            exit;
        }
        if (!isset($result['conversation']) || $result['conversation'] === null) {
            http_response_code(404);
            echo 'Not Found';
            return null;
        }
        $conversation = $result['conversation'];
        $errors = $result['errors'] ?? [];
        $input = $result['input'] ?? [];
        require dirname(__DIR__, 2) . '/templates/conversation-edit.php';
        return null;
    });

    $router->post('/conversations/{slug}/delete', static function (Request $request, string $slug) {
        $result = vt_service('controller.conversations')->destroy($slug);
        header('Location: ' . $result['redirect']);
        exit;
    });

    $router->post('/conversations/{slug}/reply', static function (Request $request, string $slug) {
        $result = vt_service('controller.conversations')->reply($slug);
        if (isset($result['redirect'])) {
            header('Location: ' . $result['redirect']);
            exit;
        }
        $conversation = $result['conversation'] ?? null;
        $replies = $result['replies'] ?? [];
        $reply_errors = $result['reply_errors'] ?? [];
        $reply_input = $result['reply_input'] ?? ['content' => ''];
        require dirname(__DIR__, 2) . '/templates/conversation-detail.php';
        return null;
    });

    $router->get('/conversations/{slug}', static function (Request $request, string $slug) {
        $view = vt_service('controller.conversations')->show($slug);
        $conversation = $view['conversation'] ?? null;
        $replies = $view['replies'] ?? [];
        $reply_errors = $view['reply_errors'] ?? [];
        $reply_input = $view['reply_input'] ?? ['content' => ''];
        require dirname(__DIR__, 2) . '/templates/conversation-detail.php';
        return null;
    });
};
