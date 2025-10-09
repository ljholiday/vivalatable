<?php
declare(strict_types=1);

// Dev-only error display; remove or disable in production.
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require __DIR__ . '/../src/bootstrap.php';

/** @var \App\Http\Request $request */
$request = vt_service('http.request');
$authService = vt_service('auth.service');

// Basic front controller & very simple router.
$path = $request->path();

// Normalize: remove trailing slash (except root)
if ($path !== '/' && str_ends_with($path, '/')) {
    $path = rtrim($path, '/');
}

if ($path === '/login' || $path === '/register') {
    header('Location: /auth');
    exit;
}

if ($path === '/') {
    if (!$authService->isLoggedIn()) {
        header('Location: /auth');
        exit;
    }

    $home = __DIR__ . '/../templates/home.php';
    if (!is_file($home)) {
        echo "<h1>Home</h1>";
        exit;
    }
    require $home;
    return;
}

if ($path === '/auth') {
    $view = vt_service('controller.auth')->landing();
    require __DIR__ . '/../templates/auth-landing.php';
    return;
}

if ($path === '/auth/login' && $request->method() === 'POST') {
    $view = vt_service('controller.auth')->login();
    if (isset($view['redirect'])) {
        header('Location: ' . $view['redirect']);
        exit;
    }
    require __DIR__ . '/../templates/auth-landing.php';
    return;
}

if ($path === '/auth/register' && $request->method() === 'POST') {
    $view = vt_service('controller.auth')->register();
    if (isset($view['redirect'])) {
        header('Location: ' . $view['redirect']);
        exit;
    }
    require __DIR__ . '/../templates/auth-landing.php';
    return;
}

if ($path === '/auth/logout') {
    if ($request->method() !== 'POST') {
        http_response_code(405);
        header('Allow: POST');
        echo 'Method Not Allowed';
        return;
    }
    $result = vt_service('controller.auth')->logout();
    header('Location: ' . ($result['redirect'] ?? '/auth'));
    exit;
}

if ($path === '/events') {
    $view = vt_service('controller.events')->index();
    $events = $view['events'];
    $filter = $view['filter'] ?? 'all';
    require __DIR__ . '/../templates/events-list.php';
    return;
}

if ($path === '/events/create') {
    $controller = vt_service('controller.events');

    if ($request->method() === 'POST') {
        $result = $controller->store();
        if (isset($result['redirect'])) {
            header('Location: ' . $result['redirect']);
            exit;
        }
        $errors = $result['errors'] ?? [];
        $input = $result['input'] ?? [];
    } else {
        $view = $controller->create();
        $errors = $view['errors'];
        $input = $view['input'];
    }

    require __DIR__ . '/../templates/event-create.php';
    return;
}

if (preg_match('#^/events/([^/]+)/edit$#', $path, $m)) {
    $slug = $m[1];
    $controller = vt_service('controller.events');

    if ($request->method() === 'POST') {
        $result = $controller->update($slug);
        if (isset($result['redirect'])) {
            header('Location: ' . $result['redirect']);
            exit;
        }
        if (!isset($result['event']) || $result['event'] === null) {
            http_response_code(404);
            echo 'Not Found';
            return;
        }
        $event = $result['event'];
        $errors = $result['errors'] ?? [];
        $input = $result['input'] ?? [];
    } else {
        $view = $controller->edit($slug);
        if ($view['event'] === null) {
            http_response_code(404);
            echo 'Not Found';
            return;
        }
        $event = $view['event'];
        $errors = $view['errors'];
        $input = $view['input'];
    }

    require __DIR__ . '/../templates/event-edit.php';
    return;
}

if (preg_match('#^/events/([^/]+)/delete$#', $path, $m)) {
    if ($request->method() !== 'POST') {
        http_response_code(405);
        header('Allow: POST');
        echo 'Method Not Allowed';
        return;
    }

    $slug = $m[1];
    $result = vt_service('controller.events')->destroy($slug);
    header('Location: ' . $result['redirect']);
    exit;
}

if (preg_match('#^/events/([^/]+)$#', $path, $m)) {
    $slug = $m[1];
    $view = vt_service('controller.events')->show($slug);
    $event = $view['event'];
    require __DIR__ . '/../templates/event-detail.php';
    return;
}

if ($path === '/communities/create') {
    $controller = vt_service('controller.communities');

    if ($request->method() === 'POST') {
        $result = $controller->store();
        if (isset($result['redirect'])) {
            header('Location: ' . $result['redirect']);
            exit;
        }
        $errors = $result['errors'] ?? [];
        $input = $result['input'] ?? [];
    } else {
        $view = $controller->create();
        $errors = $view['errors'];
        $input = $view['input'];
    }

    require __DIR__ . '/../templates/community-create.php';
    return;
}

if ($path === '/conversations/create') {
    $controller = vt_service('controller.conversations');

    if ($request->method() === 'POST') {
        $result = $controller->store();
        if (isset($result['redirect'])) {
            header('Location: ' . $result['redirect']);
            exit;
        }
        $errors = $result['errors'] ?? [];
        $input = $result['input'] ?? [];
    } else {
        $view = $controller->create();
        $errors = $view['errors'];
        $input = $view['input'];
    }

    require __DIR__ . '/../templates/conversation-create.php';
    return;
}

if ($path === '/conversations') {
    $view = vt_service('controller.conversations')->index();
    $conversations = $view['conversations'];
    $circle = $view['circle'] ?? 'all';
    $circle_context = $view['circle_context'] ?? ['inner' => ['communities' => [], 'creators' => []], 'trusted' => ['communities' => [], 'creators' => []], 'extended' => ['communities' => [], 'creators' => []]];
    $pagination = $view['pagination'] ?? ['page' => 1, 'per_page' => 20, 'has_more' => false, 'next_page' => null];
    require __DIR__ . '/../templates/conversations-list.php';
    return;
}

if ($path === '/communities') {
    $view = vt_service('controller.communities')->index();
    $communities = $view['communities'];
    $circle = $view['circle'] ?? 'all';
    $circle_context = $view['circle_context'] ?? ['inner' => ['communities' => [], 'creators' => []], 'trusted' => ['communities' => [], 'creators' => []], 'extended' => ['communities' => [], 'creators' => []]];
    require __DIR__ . '/../templates/communities-list.php';
    return;
}

if (preg_match('#^/communities/([^/]+)/edit$#', $path, $m)) {
    $slug = $m[1];
    $controller = vt_service('controller.communities');

    if ($request->method() === 'POST') {
        $result = $controller->update($slug);
        if (isset($result['redirect'])) {
            header('Location: ' . $result['redirect']);
            exit;
        }
        if (!isset($result['community']) || $result['community'] === null) {
            http_response_code(404);
            echo 'Not Found';
            return;
        }
        $community = $result['community'];
        $errors = $result['errors'] ?? [];
        $input = $result['input'] ?? [];
    } else {
        $view = $controller->edit($slug);
        if ($view['community'] === null) {
            http_response_code(404);
            echo 'Not Found';
            return;
        }
        $community = $view['community'];
        $errors = $view['errors'];
        $input = $view['input'];
    }

    require __DIR__ . '/../templates/community-edit.php';
    return;
}

if (preg_match('#^/communities/([^/]+)/delete$#', $path, $m)) {
    if ($request->method() !== 'POST') {
        http_response_code(405);
        header('Allow: POST');
        echo 'Method Not Allowed';
        return;
    }

    $slug = $m[1];
    $result = vt_service('controller.communities')->destroy($slug);
    header('Location: ' . $result['redirect']);
    exit;
}

if (preg_match('#^/conversations/([^/]+)/edit$#', $path, $m)) {
    $slug = $m[1];
    $controller = vt_service('controller.conversations');

    if ($request->method() === 'POST') {
        $result = $controller->update($slug);
        if (isset($result['redirect'])) {
            header('Location: ' . $result['redirect']);
            exit;
        }
        if (!isset($result['conversation']) || $result['conversation'] === null) {
            http_response_code(404);
            echo 'Not Found';
            return;
        }
        $conversation = $result['conversation'];
        $errors = $result['errors'] ?? [];
        $input = $result['input'] ?? [];
    } else {
        $view = $controller->edit($slug);
        if ($view['conversation'] === null) {
            http_response_code(404);
            echo 'Not Found';
            return;
        }
        $conversation = $view['conversation'];
        $errors = $view['errors'];
        $input = $view['input'];
    }

    require __DIR__ . '/../templates/conversation-edit.php';
    return;
}

if (preg_match('#^/conversations/([^/]+)/delete$#', $path, $m)) {
    if ($request->method() !== 'POST') {
        http_response_code(405);
        header('Allow: POST');
        echo 'Method Not Allowed';
        return;
    }

    $slug = $m[1];
    $result = vt_service('controller.conversations')->destroy($slug);
    header('Location: ' . $result['redirect']);
    exit;
}

if (preg_match('#^/conversations/([^/]+)/reply$#', $path, $m)) {
    if ($request->method() !== 'POST') {
        http_response_code(405);
        header('Allow: POST');
        echo 'Method Not Allowed';
        return;
    }

    $slug = $m[1];
    $controller = vt_service('controller.conversations');
    $result = $controller->reply($slug);
    if (isset($result['redirect'])) {
        header('Location: ' . $result['redirect']);
        exit;
    }
    $conversation = $result['conversation'] ?? null;
    $replies = $result['replies'] ?? [];
    $reply_errors = $result['reply_errors'] ?? [];
    $reply_input = $result['reply_input'] ?? ['content' => ''];

    require __DIR__ . '/../templates/conversation-detail.php';
    return;
}

if (preg_match('#^/conversations/([^/]+)$#', $path, $m)) {
    $slug = $m[1];
    $view = vt_service('controller.conversations')->show($slug);
    $conversation = $view['conversation'] ?? null;
    $replies = $view['replies'] ?? [];
    $reply_errors = $view['reply_errors'] ?? [];
    $reply_input = $view['reply_input'] ?? ['content' => ''];
    require __DIR__ . '/../templates/conversation-detail.php';
    return;
}

if (preg_match('#^/communities/([^/]+)$#', $path, $m)) {
    $slug = $m[1];
    $view = vt_service('controller.communities')->show($slug);
    $community = $view['community'];
    $status = (int)($view['status'] ?? ($community === null ? 404 : 200));
    if ($status !== 200) {
        http_response_code($status);
    }
    require __DIR__ . '/../templates/community-detail.php';
    return;
}

// Not implemented yet: e.g., /events/{slug}/edit, /communities/{slug}/manage
http_response_code(404);
echo 'Not Found';
