<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin Login</title>
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  body {
    font-family: system-ui, sans-serif; font-size: 15px;
    background: #f5f5f5; display: flex;
    align-items: center; justify-content: center; min-height: 100vh;
  }
  .login-card {
    background: #fff; border: 1px solid #e5e5e5;
    border-radius: 8px; padding: 2rem; width: 100%; max-width: 360px;
  }
  h1 { font-size: 20px; margin-bottom: 1.5rem; text-align: center; }
  label { display: block; font-size: 13px; font-weight: 500; margin-bottom: .3rem; }
  input[type=text], input[type=password] {
    display: block; width: 100%; padding: .5rem .75rem;
    border: 1px solid #d1d5db; border-radius: 5px;
    font-size: 14px; margin-bottom: 1rem;
  }
  input:focus { outline: 2px solid #2563eb; border-color: #2563eb; }
  button {
    width: 100%; padding: .6rem; background: #2563eb; color: #fff;
    border: none; border-radius: 5px; font-size: 14px;
    font-weight: 500; cursor: pointer;
  }
  button:hover { background: #1d4ed8; }
  .error {
    background: #fef2f2; border: 1px solid #fecaca; color: #dc2626;
    padding: .6rem .9rem; border-radius: 5px; margin-bottom: 1rem;
    font-size: 13px; text-align: center;
  }
</style>
</head>
<body>
<div class="login-card">
  <h1>Admin Login</h1>
  <?php if (!empty($error)): ?>
    <div class="error"><?= e($error) ?></div>
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
