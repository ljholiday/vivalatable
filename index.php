<?php
/**
 * VivalaTable Main Index
 * Entry point for all requests
 */

// Define root path
define('VT_ROOT', __DIR__);

// Load bootstrap
require_once __DIR__ . '/includes/bootstrap.php';

// Get request URI and method
$request_uri = $_SERVER['REQUEST_URI'] ?? '/';
$request_method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Remove query string from URI
$uri = strtok($request_uri, '?');
// Remove the base path from URI for routing
$script_dir = dirname($_SERVER['SCRIPT_NAME']);
if ($script_dir !== '/') {
    $uri = preg_replace('#^' . preg_quote($script_dir, '#') . '#', '', $uri);
}
$uri = rtrim($uri, '/') ?: '/';

// Basic routing
switch ($uri) {
    case '/':
        include __DIR__ . '/public/home.php';
        break;

    case '/login':
        include __DIR__ . '/public/login.php';
        break;

    case '/register':
        include __DIR__ . '/public/register.php';
        break;

    case '/logout':
        vt_logout_user();
        vt_redirect(vt_base_url('/'));
        break;

    case '/events':
        include __DIR__ . '/public/events/index.php';
        break;

    case '/communities':
        include __DIR__ . '/public/communities/index.php';
        break;

    case '/conversations':
        include __DIR__ . '/public/conversations/index.php';
        break;

    case '/profile':
        vt_require_login();
        include __DIR__ . '/public/profile.php';
        break;

    // API endpoints
    case '/api/test':
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'success',
            'message' => 'VivalaTable API is working',
            'version' => VT_VERSION,
            'timestamp' => date('c')
        ]);
        break;

    default:
        // Try to handle dynamic routes (events/slug, communities/slug, etc.)
        if (preg_match('#^/events/([a-z0-9-]+)$#', $uri, $matches)) {
            $_GET['event_slug'] = $matches[1];
            include __DIR__ . '/public/events/single.php';
        } elseif (preg_match('#^/communities/([a-z0-9-]+)$#', $uri, $matches)) {
            $_GET['community_slug'] = $matches[1];
            include __DIR__ . '/public/communities/single.php';
        } elseif (preg_match('#^/conversations/([a-z0-9-]+)$#', $uri, $matches)) {
            $_GET['conversation_slug'] = $matches[1];
            include __DIR__ . '/public/conversations/single.php';
        } else {
            // 404 Not Found
            http_response_code(404);
            include __DIR__ . '/public/404.php';
        }
        break;
}