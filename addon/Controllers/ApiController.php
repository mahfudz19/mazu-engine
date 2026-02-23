<?php

namespace Addon\Controllers;

use App\Core\Http\JsonResponse;
use App\Core\Http\Request;
use App\Core\Http\Response;
use Addon\Services\GoogleCalendarService;
use Addon\Services\GoogleDirectoryService;

class ApiController
{
  public function index(Request $request, Response $response)
  {
    return $response->setContent('Rest API Connection Success!');
  }

  public function testDirectory(Request $request, Response $response)
  {
    try {
      // 1. Inisialisasi Service
      $directory = new GoogleDirectoryService();

      // 2. Impersonate sebagai SUPER ADMIN (Wajib!)
      // Menggunakan email Pak Mahfudz (Super Admin)
      $adminEmail = 'mahfudz@inbitef.ac.id';

      // 3. Ambil Semua User
      $users = $directory->impersonate($adminEmail)->getAllUsers();

      return $response->json([
        'status' => 'success',
        'total_users' => count($users),
        'data' => $users
      ]);
    } catch (\Exception $e) {
      return $response->json([
        'status' => 'error',
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
      ], 500);
    }
  }

  public function checkSchedule(Request $request, Response $response)
  {
    try {
      $gcal = new GoogleCalendarService();

      // Target User yang mau diintip jadwalnya
      $targetEmail = 'sultan.syahabana@inbitef.ac.id';

      // Ambil 10 event ke depan
      $events = $gcal->impersonate($targetEmail)->listEvents();

      return $response->json([
        'status' => 'success',
        'target' => $targetEmail,
        'upcoming_events' => $events
      ]);
    } catch (\Exception $e) {
      return $response->json(['status' => 'error', 'message' => $e->getMessage()], 500);
    }
  }

  public function test(Request $request, Response $response): JsonResponse
  {
    return $response->json([
      'message' => 'Hello World!',
    ]);
  }

  public function broadcastAgenda(Request $request, Response $response)
  {
    // 1. Ambil data dari JSON Body
    $payload = $request->input();

    // Validasi input minimal
    if (empty($payload['target_email']) || empty($payload['start_time'])) {
      return $response->json(['status' => 'error', 'message' => 'Data tidak lengkap'], 400);
    }

    try {
      // 2. Inisialisasi Service
      $gcal = new GoogleCalendarService();

      // 3. Eksekusi (Chaining Method)
      $eventId = $gcal->impersonate($payload['target_email'])
        ->insertEvent($payload);

      return $response->json([
        'status' => 'success',
        'message' => 'Agenda berhasil dijadwalkan',
        'data' => ['event_id' => $eventId]
      ]);
    } catch (\Exception $e) {
      // Log error untuk debugging developer
      // logger()->error($e->getMessage()); 

      return $response->json([
        'status' => 'error',
        'message' => 'Gagal koneksi ke Google Calendar: ' . $e->getMessage()
      ], 500);
    }
  }

  public function testBroadcast(Request $request, Response $response)
  {
    try {
      // 1. Inisialisasi Service
      $gcal = new GoogleCalendarService();

      // 2. DATA DUMMY (Ganti email ini dengan email asli di domain @inbitef.ac.id)
      $targetEmail = 'sultan.syahabana@inbitef.ac.id';

      // 3. Setup Data Event Sederhana
      $eventData = [
        'title'       => '[TEST SYSTEM] Rapat Percobaan',
        'description' => 'Ini adalah tes broadcast otomatis dari sistem Mazu.',
        'location'    => 'Ruang Rapat Virtual',
        // Mulai besok jam 09:00 pagi
        'start_time'  => date('c', strtotime('tomorrow 09:00')),
        // Selesai besok jam 10:00 pagi
        'end_time'    => date('c', strtotime('tomorrow 10:00')),
      ];

      // 4. Eksekusi
      $eventId = $gcal->impersonate($targetEmail)
        ->insertEvent($eventData);

      return $response->json([
        'status' => 'success',
        'message' => 'Berhasil insert ke kalender ' . $targetEmail,
        'event_id' => $eventId,
        'link' => "https://calendar.google.com/calendar/r/eventedit/" . $eventId
      ]);
    } catch (\Exception $e) {
      // Tampilkan error lengkap biar ketahuan salahnya dimana
      return $response->json([
        'status' => 'error',
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
      ], 500);
    }
  }
}
