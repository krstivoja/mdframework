<?php
require __DIR__ . '/../bootstrap.php';

$url = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
$route = $router->resolve($url);

switch ($route['type']) {
    case 'post':
    case 'page':
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
