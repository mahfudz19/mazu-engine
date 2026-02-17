<?php

namespace Addon\Services;

use Google\Client;
use Google\Service\Directory;
use Exception;

class GoogleDirectoryService
{
  private $client;
  private $service;

  public function __construct()
  {
    $this->client = new Client();

    // Gunakan file credentials yang sama
    $authConfigPath = env('GOOGLE_AUTH_CONFIG', __DIR__ . '/../../storage/secrets/broadcast-agenda-kampus-597196c77bd7.json');

    if (!file_exists($authConfigPath)) {
      throw new Exception("Google Auth File not found at: " . $authConfigPath);
    }

    $this->client->setAuthConfig($authConfigPath);

    // Scope khusus untuk membaca data user
    // HANYA gunakan scope yang sudah didaftarkan di Google Admin Console (DWD)
    $this->client->addScope(Directory::ADMIN_DIRECTORY_USER_READONLY);
    // $this->client->addScope(Directory::ADMIN_DIRECTORY_GROUP_READONLY); // HAPUS INI karena tidak diizinkan di DWD
  }

  /**
   * Wajib impersonate sebagai Super Admin untuk baca Directory
   */
  public function impersonate(string $adminEmail): self
  {
    if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
      throw new Exception("Invalid admin email for impersonation.");
    }

    $this->client->setSubject($adminEmail);
    $this->service = new Directory($this->client);

    return $this;
  }

  /**
   * Ambil semua user di domain
   * @return array List of emails
   */
  public function getAllUsers(string $domain = 'inbitef.ac.id'): array
  {
    if (!$this->service) {
      throw new Exception("Please call impersonate() with Super Admin email first.");
    }

    $users = [];
    $pageToken = null;

    do {
      $params = [
        'domain' => $domain,
        'maxResults' => 100, // Max per page
        'pageToken' => $pageToken
      ];

      $results = $this->service->users->listUsers($params);

      foreach ($results->getUsers() as $user) {
        $users[] = [
          'email' => $user->getPrimaryEmail(),
          'name' => $user->getName()->getFullName(),
          'orgUnit' => $user->getOrgUnitPath() // Bisa dipakai untuk filter Dosen/Mhs
        ];
      }

      $pageToken = $results->getNextPageToken();
    } while ($pageToken);

    return $users;
  }

  /**
   * Ambil user berdasarkan Group (Misal: dosen@inbitef.ac.id)
   */
  public function getUsersByGroup(string $groupEmail): array
  {
    if (!$this->service) {
      throw new Exception("Please call impersonate() first.");
    }

    $members = [];
    $pageToken = null;

    do {
      $results = $this->service->members->listMembers($groupEmail, [
        'maxResults' => 200,
        'pageToken' => $pageToken
      ]);

      foreach ($results->getMembers() as $member) {
        if ($member->getType() === 'USER') {
          $members[] = $member->getEmail();
        }
      }
      $pageToken = $results->getNextPageToken();
    } while ($pageToken);

    return $members;
  }
}
