<?php
declare(strict_types=1);

// Dev-only error display; remove or disable in production.
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require __DIR__ . '/../src/bootstrap.php';

// Basic front controller & very simple router.
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

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
    $events = vt_service('event.service')->listRecent();
    require __DIR__ . '/../templates/events-list.php';
    return;
}

if (preg_match('#^/events/([^/]+)$#', $path, $m)) {
    $slug = $m[1];
    $event = vt_service('event.service')->getBySlugOrId($slug);
    require __DIR__ . '/../templates/event-detail.php';
    return;
}

if ($path === '/communities') {
    $communities = vt_service('community.service')->listRecent();
    require __DIR__ . '/../templates/communities-list.php';
    return;
}

if (preg_match('#^/communities/([^/]+)$#', $path, $m)) {
    $slug = $m[1];
    $community = vt_service('community.service')->getBySlugOrId($slug);
    require __DIR__ . '/../templates/community-detail.php';
    return;
}

// Not implemented yet: e.g., /events/{slug}/edit, /communities/{slug}/manage
http_response_code(404);
echo 'Not Found';
