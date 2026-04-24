    <?php if (!empty($GLOBALS['admin_logged_in'])): ?>
    <div class="admin-front-bar">
        <?php if (!empty($GLOBALS['admin_edit_path'])): ?>
        <a href="/admin/edit/<?= htmlspecialchars($GLOBALS['admin_edit_path'], ENT_QUOTES) ?>">Edit page</a>
        <?php endif; ?>
        <a href="/admin/">Dashboard</a>
    </div>
    <?php endif; ?>
</body>
</html>
