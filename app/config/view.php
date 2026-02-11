<?php

return [
  /*
  |--------------------------------------------------------------------------
  | Layout Suffix Generator
  |--------------------------------------------------------------------------
  |
  | Callback ini digunakan untuk menentukan suffix dinamis pada ID layout.
  | Berguna untuk aplikasi yang memiliki layout berbeda berdasarkan role atau state user.
  | 
  | Contoh: Jika user adalah 'mahasiswa', layout ID akan menjadi 'layout-mahasiswa'.
  | Jika callback mengembalikan string kosong, tidak ada suffix yang ditambahkan.
  |
  */
  'layout_suffix_generator' => function () {
    return $_SESSION['role'] ?? '';
  },

  /*
  |--------------------------------------------------------------------------
  | SEO Defaults for Views
  |--------------------------------------------------------------------------
  |
  | Konfigurasi ini menyediakan nilai default untuk metadata SEO yang dapat
  | digunakan di seluruh aplikasi. SeoService akan membaca nilai-nilai ini
  | ketika opsi tidak diberikan secara eksplisit di controller.
  |
  */
  'seo' => [
    'default_locale' => 'id_ID',
    'default_og_image' => null,
    'twitter_site' => null,
    'twitter_card' => 'summary_large_image',
    'noindex' => true,
  ],
];
