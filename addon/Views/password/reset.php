<div class="auth-container">
  <div class="auth-card">
    <h1 class="auth-title">Reset Password</h1>

    <?php if (isset($error)): ?>
      <div class="auth-error">
        <?= htmlspecialchars($error) ?>
      </div>
    <?php endif; ?>

    <form method="POST" action="/password/reset">
      <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">

      <div class="auth-form-group">
        <label for="email" class="auth-label">Email</label>
        <input
          type="email"
          id="email"
          name="email"
          class="auth-input"
          required>
      </div>

      <div class="auth-form-group">
        <label for="password" class="auth-label">Password Baru</label>
        <input
          type="password"
          id="password"
          name="password"
          class="auth-input"
          minlength="8"
          required>
      </div>

      <div class="auth-form-group">
        <label for="password_confirmation" class="auth-label">Konfirmasi Password</label>
        <input
          type="password"
          id="password_confirmation"
          name="password_confirmation"
          class="auth-input"
          minlength="8"
          required>
      </div>

      <button type="submit" class="auth-button">
        Reset Password
      </button>
    </form>

    <div class="auth-links">
      <a data-spa href="/login" class="auth-link">Kembali ke Login</a>
    </div>
  </div>
</div>