<!DOCTYPE html>
<html lang="en">

<head>
  <?= App\Core\View\View::renderMeta($meta) ?>

  <!-- Link ke file CSS yang sudah di-generate oleh Tailwind CLI -->
  <!-- Google Fonts - Pindahkan ke sini -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">

  <!-- Auto-Injected Styles -->
  <?= App\Core\View\View::renderStyles() ?>

</head>

<body>
  <!-- Global Loading Progress Bar -->
  <div id="global-progress-bar" class="progress-bar-container">
    <div id="global-progress-bar-inner" class="progress-bar-fill"></div>
  </div>

  <!-- Content Injection Point -->
  <div>
    <div class="mazu-container">
      <!-- Global Header -->
      <header class="mazu-header">
        <a data-spa href="/" class="mazu-brand">
          <img src="/logo_app/mazu-logo.svg" alt="Mazu Engine" height="40" />
        </a>
        <nav class="mazu-nav">
          <a href="https://github.com/mazu-framework/docs" target="_blank">Documentation</a>
          <a href="https://github.com/mazu-framework" target="_blank">GitHub</a>
        </nav>
      </header>

      <main id="app-content" data-layout="layout.php">
        <?= $children; ?>
      </main>

      <!-- Global Footer -->
      <footer class="mazu-footer">
        <div>
          &copy; <?= date('Y') ?> Mazu Framework. All rights reserved.
        </div>
        <div class="mazu-version">
          v1.0.0 (PHP <?= phpversion() ?>)
        </div>
      </footer>
    </div>
  </div>

  <!-- SPA Script -->
  <?= App\Core\View\View::renderScripts() ?>
</body>

</html>