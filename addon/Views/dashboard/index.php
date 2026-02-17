<div class="min-h-screen bg-gray-100 flex items-center justify-center p-6">
  <div class="bg-white rounded-xl shadow-lg p-8 w-full max-w-md text-center">
    <!-- Foto Profil -->
    <?php if (!empty($user['picture'])): ?>
      <img src="<?= htmlspecialchars($user['picture']) ?>"
        alt="Profile"
        class="w-24 h-24 rounded-full mx-auto border-4 border-blue-500 shadow-md mb-4">
    <?php else: ?>
      <div class="w-24 h-24 bg-gray-300 rounded-full mx-auto mb-4 flex items-center justify-center text-gray-500">
        <i class="bi bi-person-fill text-4xl"></i>
      </div>
    <?php endif; ?>

    <!-- Nama & Role -->
    <h1 class="text-2xl font-bold text-gray-800"><?= htmlspecialchars($user['name'] ?? 'User') ?></h1>
    <p class="text-gray-500 mb-2"><?= htmlspecialchars($user['email'] ?? '-') ?></p>

    <span class="inline-block px-3 py-1 rounded-full text-sm font-semibold 
            <?= $role === 'SUPER_ADMIN' ? 'bg-purple-100 text-purple-700' : ($role === 'APPROVER' ? 'bg-green-100 text-green-700' : 'bg-blue-100 text-blue-700') ?>">
      <?= htmlspecialchars($role) ?>
    </span>

    <!-- Tombol Aksi -->
    <div class="mt-8 space-y-3">
      <a href="/api/test-calendar" class="block w-full py-2 px-4 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition">
        Test Google Calendar
      </a>
      <a href="/logout" class="block w-full py-2 px-4 border border-red-500 text-red-500 hover:bg-red-50 rounded-lg transition">
        Logout
      </a>
    </div>
  </div>
</div>