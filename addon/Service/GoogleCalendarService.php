<?php

namespace Addon\Services;

use Google\Client;
use Google\Service\Calendar;
use Google\Service\Calendar\Event;

class GoogleCalendarService
{
  private Client $client;
  private Calendar $service;
  private string $credentialsPath;

  public function __construct()
  {
    // 1. Load Credential JSON path dari config/env
    // Pastikan file JSON diletakkan di tempat aman, misal di folder storage atau root (jangan di public)
    $this->credentialsPath = __DIR__ . '/../../service-account-auth.json';

    $this->setupClient();
  }

  /**
   * Inisialisasi Google Client dengan Scope Calendar
   */
  private function setupClient(): void
  {
    $this->client = new Client();
    $this->client->setAuthConfig($this->credentialsPath);
    $this->client->addScope(Calendar::CALENDAR);

    // Default service (sebagai bot itu sendiri sebelum impersonate)
    $this->service = new Calendar($this->client);
  }

  /**
   * Fungsi Impersonate (Berubah identitas menjadi user target)
   * Ini adalah kunci dari Domain-Wide Delegation
   */
  public function impersonate(string $userEmail): self
  {
    $this->client->setSubject($userEmail);

    // Refresh service dengan identitas baru
    $this->service = new Calendar($this->client);

    return $this; // Method chaining support
  }

  /**
   * Fungsi Insert Event
   * @param array $data Agenda dalam bentuk array/object
   */
  public function insertEvent(array $data): Event
  {
    // $data akan berisi: summary, location, description, start, end, dll.
    $event = new Event([
      'summary'     => $data['title'],
      'location'    => $data['location'] ?? 'Kampus INBITEF',
      'description' => $data['description'] ?? '',
      'start' => [
        'dateTime' => $data['start_time'], // Format: '2023-10-28T09:00:00-07:00'
        'timeZone' => 'Asia/Jakarta',
      ],
      'end' => [
        'dateTime' => $data['end_time'],
        'timeZone' => 'Asia/Jakarta',
      ],
      // 'attendees' bisa dikosongkan karena kita langsung naruh di kalender ybs
    ]);

    // 'primary' merujuk pada kalender utama user yang sedang di-impersonate
    return $this->service->events->insert('primary', $event);
  }
}
