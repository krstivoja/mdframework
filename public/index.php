<?php

declare(strict_types=1);
session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'httponly' => true, 'samesite' => 'Strict']);
session_start();

require __DIR__ . '/../bootstrap.php';

$GLOBALS['admin_logged_in'] = !empty($_SESSION['admin_user']);
$GLOBALS['admin_edit_path'] = null;

$url = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';

// ── robots.txt ────────────────────────────────────────────────────────────────

if ($url === '/robots.txt') {
    header('Content-Type: text/plain; charset=utf-8');
    echo "User-agent: *\nDisallow: /admin/\nSitemap: " . MD\Url::absolute('/sitemap.xml', $config, $_SERVER) . "\n";
    exit;
}

// ── sitemap.xml ───────────────────────────────────────────────────────────────

if ($url === '/sitemap.xml') {
    $allPages = $index->get();

    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    foreach ($allPages as $page) {
        if (!empty($page['draft'])) {
            continue;
        }
        $loc     = htmlspecialchars(MD\Url::forPage($page, $config, $_SERVER));
        $lastmod = !empty($page['date']) ? date('Y-m-d', strtotime((string)$page['date'])) : date('Y-m-d');
        $xml .= "  <url><loc>{$loc}</loc><lastmod>{$lastmod}</lastmod></url>\n";
    }
    $xml .= '</urlset>';

    header('Content-Type: application/xml; charset=utf-8');
    echo $xml;
    exit;
}

$route = $router->resolve($url);

switch ($route['type']) {
    case 'post':
    case 'page':
        $GLOBALS['admin_edit_path'] = $route['path'];
        $data                       = $content->load($route['path']);
        if ($data === null || !empty($data['meta']['draft'])) {
            not_found($url);
            break;
        }
        $template = $route['type'];
        if (!empty($data['meta']['template'])) {
            $override = $themes->resolveTemplate((string)$data['meta']['template']);
            if ($override) {
                $template = $override;
            }
        }
        render($template, [
            'meta'  => $data['meta'],
            'html'  => $data['html'],
            'route' => $route,
        ]);
        break;

    case 'feed':
        $siteName = $config->get('site', [])['name'] ?? 'Site';
        $folder   = $route['folder'];
        $all      = array_values($folder ? $index->filter(['folder' => $folder]) : $index->get());
        $items    = array_slice($all, 0, 20);
        $title    = $folder ? ($siteName . ' — ' . ucfirst($folder)) : $siteName;
        $feedUrl  = MD\Url::absolute($folder ? '/' . $folder . '/feed' : '/feed', $config, $_SERVER);
        $siteUrl  = MD\Url::absolute('/', $config, $_SERVER);
        $updated  = $items ? max(array_map(fn ($p) => (int)($p['mtime'] ?? 0), $items)) : time();
        // Resolve each item to an absolute URL up front so the template stays dumb.
        foreach ($items as &$it) {
            $it['absolute_url'] = MD\Url::forPage($it, $config, $_SERVER);
        }
        unset($it);
        header('Content-Type: application/atom+xml; charset=utf-8');
        render('feed', [
            'site_name' => $siteName,
            'title'     => $title,
            'site_url'  => $siteUrl,
            'feed_url'  => $feedUrl,
            'updated'   => $updated,
            'items'     => $items,
        ]);
        break;

    case 'taxonomy':
        $found = $index->findByTaxonomyTerm($route['taxonomy'], $route['term']);
        if (!$found['posts']) {
            not_found($url);
            break;
        }
        $perPage = (int)$config->get('posts_per_page', 10);
        if ($perPage < 1) {
            $perPage = 10;
        }
        $total = count($found['posts']);
        $pages = max(1, (int)ceil($total / $perPage));
        $page  = max(1, (int)($route['page'] ?? 1));
        if ($page > $pages) {
            not_found($url);
            break;
        }
        $items = array_slice($found['posts'], ($page - 1) * $perPage, $perPage);
        foreach ($items as &$it) {
            $it = array_merge($it['meta'] ?? [], $it);
        }
        unset($it);
        render('taxonomy', [
            'taxonomy'    => $route['taxonomy'],
            'term'        => $route['term'],
            'label'       => $found['label'] ?? $route['term'],
            'items'       => $items,
            'posts'       => $items, // alias — most theme conventions use `posts`
            'page'        => $page,
            'total_pages' => $pages,
            'per_page'    => $perPage,
        ]);
        break;

    case 'archive':
        $intro   = $content->load($route['folder'] . '/_index');
        $all     = array_values($index->filter(['folder' => $route['folder']]));
        $perPage = (int)($intro['meta']['posts_per_page'] ?? $config->get('posts_per_page', 10));
        if ($perPage < 1) {
            $perPage = 10;
        }
        $total = count($all);
        $pages = max(1, (int)ceil($total / $perPage));
        $page  = max(1, (int)($route['page'] ?? 1));
        if ($page > $pages) {
            not_found($url);
            break;
        }
        $items = array_slice($all, ($page - 1) * $perPage, $perPage);
        // Flatten meta into each post so themes can use `post.image`,
        // `post.excerpt`, etc. Canonical fields (title, url, date) win over
        // any same-named meta keys.
        foreach ($items as &$it) {
            $it = array_merge($it['meta'] ?? [], $it);
        }
        unset($it);
        // List of every content folder (for filter-tabs and similar).
        $folders = array_values(array_unique(array_filter(array_map(
            fn ($p) => $p['folder'] ?? null,
            $index->get(),
        ))));
        render('archive', [
            'folder'      => $route['folder'],
            'items'       => $items,
            'posts'       => $items, // alias — most theme conventions use `posts`
            'folders'     => $folders,
            'intro'       => $intro,
            'page'        => $page,
            'total_pages' => $pages,
            'per_page'    => $perPage,
        ]);
        break;

    default:
        not_found($url);
        break;
}
