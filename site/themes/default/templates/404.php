<?php
$page_title = 'Not found';
require __DIR__ . '/_header.php';
?>
<h1>404 — Not found</h1>
<p>No content at <code><?= htmlspecialchars($url) ?></code>.</p>
<p><a href="/">Go home</a></p>
<?php require __DIR__ . '/_footer.php'; ?>
