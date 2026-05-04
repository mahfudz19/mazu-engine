<?php

/**
 * @var \App\Core\View\PageMeta $meta
 */
?>
<h1 class="auth-title">Login</h1>

<?php if (isset($error)): ?>
  <div class="auth-error">
    <?= htmlspecialchars($error) ?>
  </div>
<?php endif; ?>

<form method="POST" action="/login">
  <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">

  <div class="auth-form-group">
    <label for="email" class="auth-label">Email</label>
    <input
      type="email"
      id="email"
      name="email"
      class="auth-input"
      value="<?= htmlspecialchars($_GET['email'] ?? '') ?>"
      required>
  </div>

  <div class="auth-form-group">
    <label for="password" class="auth-label">Password</label>
    <input
      type="password"
      id="password"
      name="password"
      class="auth-input"
      required>
  </div>

  <button type="submit" class="auth-button">
    Login
  </button>
</form>

<div class="auth-links">
  <a data-spa href="/password/forgot" class="auth-link">Lupa password?</a>
</div>

<div class="auth-divider">
  <span>Belum punya akun?</span>
  <a data-spa href="/register" class="auth-link">Register</a>
</div>