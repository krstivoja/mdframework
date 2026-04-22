<?php
$page_title = 'Not found';
ob_start();
?>
<h1>404 — Not found</h1>
<p>No content at <code><?= htmlspecialchars($url) ?></code>.</p>
<p><a href="/">Go home</a></p>
<?php
$content_body = ob_get_clean();
require __DIR__ . '/_layout.php';
