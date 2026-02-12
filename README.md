# 🌽 Mazu Framework

**Mazu Framework** adalah sebuah framework PHP personal yang dikembangkan oleh **Mahfudz Masyhur**. Framework ini dirancang dengan pendekatan modern, mendukung _hybrid rendering_ untuk pengalaman SPA (Single Page Application), serta memiliki sistem CLI yang kuat untuk mempermudah pengembangan.

## 🚀 Fitur Utama

- **Hybrid SPA Engine**: Navigasi antar halaman yang cepat tanpa reload penuh menggunakan Mazu SPA Router.
- **Mazu CLI**: Tool command-line (`php mazu`) untuk otomatisasi berbagai tugas pengembangan.
- **Queue System**: Mendukung background job menggunakan Redis.
- **Flexible Routing**: Sistem routing yang mendukung middleware dan caching.
- **Environment Driven**: Konfigurasi berbasis file `.env` yang aman.
- **Built-in Dev Server**: Server lokal siap pakai untuk pengembangan cepat.

## 🛠 Instalasi

1. Clone repositori ini ke direktori lokal Anda.
2. Pastikan Anda memiliki PHP 8.1+ dan Composer terinstal.
3. Jalankan perintah untuk menginstal dependensi:
   ```bash
   composer install
   ```
4. Salin file `.env.example` menjadi `.env` dan sesuaikan konfigurasinya:
   ```bash
   cp .env.example .env
   ```
5. Generate key atau sesuaikan konfigurasi database di dalam `.env`.

## 💻 Penggunaan CLI

Mazu dilengkapi dengan command-line interface yang disebut `mazu`. Gunakan perintah berikut untuk melihat daftar perintah yang tersedia:

```bash
php mazu list
```

### Perintah Populer:

- **Menjalankan Server Lokal**:
  ```bash
  php mazu serve
  ```
- **Membuat Controller Baru**:
  ```bash
  php mazu make:controller NamaController
  ```
- **Menjalankan Migrasi Database**:
  ```bash
  php mazu migrate
  ```
- **Menjalankan Queue Worker**:
  ```bash
  php mazu queue:work
  ```
- **Membangun Aset (Build)**:
  ```bash
  php mazu build
  ```

## 📁 Struktur Direktori

- `app/`: Logika inti aplikasi (Controller, Model, Middleware, Services).
- `addon/`: Tempat untuk modul atau ekstensi tambahan.
- `public/`: Direktori publik (entry point `index.php` dan aset statis).
- `config/`: File konfigurasi aplikasi.
- `vendor/`: Dependensi composer.
- `mazu`: Entry point untuk perintah CLI.

## 🤝 Kontribusi

Framework ini bersifat personal, namun jika Anda memiliki saran atau menemukan bug, silakan sampaikan kepada pengembang.

---

**Mazu Framework** - _Simplicity in Modern PHP Development_
