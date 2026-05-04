<div class="auth-container">
  <div class="auth-card">
    <h1 class="auth-title">Lupa Password</h1>

    <?php if (isset($message)): ?>
      <div class="auth-success">
        <?= htmlspecialchars($message) ?>
      </div>
    <?php endif; ?>

    <?php if (isset($error)): ?>
      <div class="auth-error">
        <?= htmlspecialchars($error) ?>
      </div>
    <?php endif; ?>

    <form method="POST" action="/password/forgot">
      <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">

      <div class="auth-form-group">
        <label for="email" class="auth-label">Email</label>
        <input
          type="email"
          id="email"
          name="email"
          class="auth-input"
          placeholder="Masukkan email Anda"
          required>
      </div>

      <button type="submit" class="auth-button">
        Kirim Link Reset
      </button>
    </form>

    <div class="auth-links">
      <a data-spa href="/login" class="auth-link">Kembali ke Login</a>
    </div>
  </div>
</div>