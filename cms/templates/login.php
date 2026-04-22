<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin Login</title>
<link rel="stylesheet" href="/cms/admin.css">
</head>
<body class="login-page">
<div class="login-card">
  <h1>Admin Login</h1>
  <?php if (!empty($error)): ?>
    <div class="login-error"><?= e($error) ?></div>
  <?php endif; ?>
  <form method="POST" action="/admin/login">
    <?= csrf_field() ?>
    <label for="username">Username</label>
    <input type="text" id="username" name="username"
           value="<?= e($_POST['username'] ?? '') ?>"
           autocomplete="username" required autofocus>
    <label for="password">Password</label>
    <input type="password" id="password" name="password"
           autocomplete="current-password" required>
    <button type="submit">Log in</button>
  </form>
</div>
</body>
</html>
