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
            not_found($url);
            break;
        }
        $GLOBALS['admin_template_name'] = $route['type'];
        render($route['type'], [
            'meta'  => $data['meta'],
            'html'  => $data['html'],
            'route' => $route,
        ]);
        break;

    case 'archive':
        $intro = $content->load($route['folder'] . '/_index');
        $items = $index->filter(['folder' => $route['folder']]);
        render('archive', [
            'folder' => $route['folder'],
            'items'  => $items,
            'intro'  => $intro,
        ]);
        break;

    default:
        not_found($url);
        break;
}
