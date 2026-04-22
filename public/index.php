<?php
session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'httponly' => true, 'samesite' => 'Strict']);
session_start();

require __DIR__ . '/../bootstrap.php';

$GLOBALS['admin_logged_in'] = !empty($_SESSION['admin_user']);
$GLOBALS['admin_edit_path'] = null;

$url = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
$route = $router->resolve($url);

switch ($route['type']) {
    case 'post':
    case 'page':
        $GLOBALS['admin_edit_path'] = $route['path'];
        $data = $content->load($route['path']);
        if ($data === null || !empty($data['meta']['draft'])) {
            goto notfound;
        }
        render($route['type'], [
            'meta' => $data['meta'],
            'html' => $data['html'],
            'route' => $route,
        ]);
        break;

    case 'archive':
        // Load optional _index.md for archive customization
        $intro = $content->load($route['folder'] . '/_index');
        $items = $index->filter(['folder' => $route['folder']]);
        render('archive', [
            'folder' => $route['folder'],
            'items' => $items,
            'intro' => $intro,
        ]);
        break;

    notfound:
    case 'notfound':
    default:
        http_response_code(404);
        render('404', ['url' => $url]);
        break;
}
