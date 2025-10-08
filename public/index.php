<?php
declare(strict_types=1);

// Dev-only error display; remove or disable in production.
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require __DIR__ . '/../src/bootstrap.php';

/** @var \App\Http\Request $request */
$request = vt_service('http.request');

// Basic front controller & very simple router.
$path = $request->path();

// Normalize: remove trailing slash (except root)
if ($path !== '/' && str_ends_with($path, '/')) {
    $path = rtrim($path, '/');
}

if ($path === '/') {
    // Minimal home template to prove render path works
    $home = __DIR__ . '/../templates/home.php';
    if (!is_file($home)) {
        echo "<h1>Home</h1>";
        exit;
    }
    require $home;
    return;
}

if ($path === '/events') {
    $view = vt_service('controller.events')->index();
    $events = $view['events'];
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

if ($path === '/communities') {
    $view = vt_service('controller.communities')->index();
    $communities = $view['communities'];
    require __DIR__ . '/../templates/communities-list.php';
    return;
}

if (preg_match('#^/communities/([^/]+)$#', $path, $m)) {
    $slug = $m[1];
    $view = vt_service('controller.communities')->show($slug);
    $community = $view['community'];
    require __DIR__ . '/../templates/community-detail.php';
    return;
}

// Not implemented yet: e.g., /events/{slug}/edit, /communities/{slug}/manage
http_response_code(404);
echo 'Not Found';
