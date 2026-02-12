# 📅 Google Calendar Integration Project

Proyek ini adalah implementasi integrasi Google Calendar API menggunakan **Domain-Wide Delegation** untuk melakukan impersonasi user dan memasukkan agenda (event) secara otomatis ke kalender user. Proyek ini dibangun di atas **Mazu Framework**.

## 🚀 Fitur Utama

- **Google Calendar Impersonation**: Memungkinkan aplikasi (Service Account) untuk bertindak atas nama user dalam domain Google Workspace yang sama.
- **Auto Broadcast Agenda**: Endpoint API untuk mengirimkan data agenda ke kalender user target secara otomatis.
- **REST API Ready**: Dilengkapi dengan endpoint API yang siap dikonsumsi oleh aplikasi lain.
- **Hybrid SPA Engine**: Navigasi antarmuka yang cepat menggunakan engine Mazu.

## 🛠 Prasyarat & Instalasi

1. **Google Cloud Console**:
   - Buat Service Account.
   - Aktifkan Google Calendar API.
   - Aktifkan **Domain-Wide Delegation** pada Service Account tersebut.
   - Unduh file kredensial JSON dan simpan sebagai `service-account-auth.json` di root direktori proyek.

2. **Instalasi Proyek**:
   ```bash
   composer install
   cp .env.example .env
   ```

3. **Konfigurasi .env**:
   Sesuaikan `APP_URL` dan konfigurasi lainnya di file `.env`.

## 🔌 API Endpoints

### 1. Broadcast Agenda
- **URL**: `/api/broadcast-agenda`
- **Method**: `GET` (Saat ini diimplementasikan via GET untuk testing, namun mengambil data dari input body)
- **Payload (JSON)**:
  ```json
  {
    "target_email": "user@yourdomain.com",
    "title": "Rapat Koordinasi",
    "description": "Deskripsi agenda",
    "location": "Ruang Rapat A",
    "start_time": "2026-02-15T09:00:00+07:00",
    "end_time": "2026-02-15T10:00:00+07:00"
  }
  ```

## 💻 Menjalankan Proyek

Gunakan CLI Mazu untuk menjalankan server pengembangan:

```bash
php mazu serve
```

Akses aplikasi melalui `http://localhost:8000`.

## 🏗 Technical Stack (Mazu Framework)

Proyek ini menggunakan **Mazu Framework**, sebuah framework PHP personal dengan fitur:
- **CLI Tool**: `php mazu` untuk helper pengembangan.
- **Routing & Middleware**: Sistem routing yang fleksibel di `addon/Router/index.php`.
- **Service Layer**: Logika bisnis Google Calendar berada di `addon/Service/GoogleCalendarService.php`.

---
**Google Calendar Integration** - *Automating Schedule with Domain-Wide Delegation*
