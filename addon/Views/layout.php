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

  <!-- Bootstrap Icons (opsional) -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>

<body>
  <!-- Global Loading Progress Bar -->
  <div id="global-progress-bar" class="progress-bar-container">
    <div id="global-progress-bar-inner" class="progress-bar-fill"></div>
  </div>

  <!-- Header Sederhana -->
  <header class="bg-white shadow-sm py-4 px-6 mb-6 flex justify-between items-center">
    <a href="/" class="text-xl font-bold text-gray-800 flex items-center gap-2">
      <i class="bi bi-calendar-event text-blue-600"></i>
      Campus Agenda
    </a>

    <div>
      <?php
      if (session_status() === PHP_SESSION_NONE) session_start();
      $isLoggedIn = $_SESSION['is_logged_in'] ?? false;
      ?>

      <?php if ($isLoggedIn): ?>
        <a href="/dashboard" class="text-gray-600 hover:text-blue-600 font-medium mr-4">Dashboard</a>
        <a href="/logout" class="px-4 py-2 bg-red-50 text-red-600 rounded-lg hover:bg-red-100 transition">Logout</a>
      <?php else: ?>
        <a href="/login" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition flex items-center gap-2">
          <i class="bi bi-google"></i> Login
        </a>
      <?php endif; ?>
    </div>
  </header>

  <!-- Variabel ini akan berisi konten dari layout anak atau halaman  -->
  <div id="app-content" data-layout="layout.php">
    <?= $children; ?>
  </div>

  <!-- SPA Script -->
  <?= App\Core\View\View::renderScripts() ?>
</body>

</html>