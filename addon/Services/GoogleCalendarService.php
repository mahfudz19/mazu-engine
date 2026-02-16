<?php

namespace Addon\Services;

use Google\Client;
use Google\Service\Calendar;
use Google\Service\Calendar\Event;
use App\Services\ConfigService;
use Exception;

class GoogleCalendarService
{
  private Client $client;
  private ?Calendar $service = null;

  /**
   * Constructor
   * Setup Google Client dasar saat class diinisialisasi.
   */
  public function __construct()
  {
    // 1. Setup Client
    $this->client = new Client();

    // Ambil path auth file dari Environment/Config agar tidak hardcode
    // Menggunakan helper env() dari framework Mazu Anda
    $authConfigPath = env('GOOGLE_AUTH_CONFIG', __DIR__ . '/../../storage/secrets/broadcast-agenda-kampus-597196c77bd7.json');

    if (!file_exists($authConfigPath)) {
      throw new Exception("Google Auth File not found at: " . $authConfigPath);
    }

    $this->client->setAuthConfig($authConfigPath);

    // Set Scopes (Wajib Calendar)
    $this->client->addScope(Calendar::CALENDAR);

    // Opsional: Set Access Type offline jika butuh refresh token jangka panjang (biasanya service account auto-refresh)
    $this->client->setAccessType('offline');
  }

  /**
   * Impersonate User (Domain-Wide Delegation)
   * Mengubah subjek akses menjadi email target.
   * * @param string $emailTarget Email user pemilik kalender (misal: dosen@inbitef.ac.id)
   * @return self
   */
  public function impersonate(string $emailTarget): self
  {
    // Validasi format email sederhana
    if (!filter_var($emailTarget, FILTER_VALIDATE_EMAIL)) {
      throw new Exception("Invalid target email for impersonation.");
    }

    // Logic Inti DWD: Set Subject
    $this->client->setSubject($emailTarget);

    // Re-inisialisasi Service Calendar dengan identitas baru
    $this->service = new Calendar($this->client);

    return $this; // Return self untuk method chaining
  }

  /**
   * Insert Event ke Kalender Primary User yang sedang di-impersonate
   * * @param array $agendaData Data agenda (title, start, end, location, description)
   * @return string ID Event yang berhasil dibuat
   */
  public function insertEvent(array $agendaData): string
  {
    if (!$this->service) {
      throw new Exception("Please call impersonate() before inserting an event.");
    }

    // Mapping data array ke Google Event Object
    // Menggunakan timezone 'Asia/Jakarta' sesuai kebutuhan kampus Indonesia
    $event = new Event([
      'summary' => $agendaData['title'] ?? 'Agenda Kampus',
      'location' => $agendaData['location'] ?? '',
      'description' => $agendaData['description'] ?? '',
      'start' => [
        'dateTime' => $agendaData['start_time'], // Format ISO 8601: 2026-02-15T09:00:00+07:00
        'timeZone' => 'Asia/Jakarta',
      ],
      'end' => [
        'dateTime' => $agendaData['end_time'],
        'timeZone' => 'Asia/Jakarta',
      ],
      'reminders' => [
        'useDefault' => false,
        'overrides' => [
          ['method' => 'popup', 'minutes' => 30],
        ],
      ],
    ]);

    // Eksekusi API ke Google
    // 'primary' merujuk ke kalender utama milik user yang di-impersonate
    $createdEvent = $this->service->events->insert('primary', $event);

    return $createdEvent->getId();
  }
}
