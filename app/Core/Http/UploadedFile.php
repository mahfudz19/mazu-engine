<?php

namespace App\Core\Http;

class UploadedFile
{
  public string $name;
  public string $type;
  public string $tmpName;
  public int $error;
  public int $size;

  public function __construct(array $file)
  {
    $this->name = $file['name'];
    $this->type = $file['type'];
    $this->tmpName = $file['tmp_name'];
    $this->error = $file['error'];
    $this->size = $file['size'];
  }

  /**
   * Pindahkan file yang diunggah ke lokasi baru.
   *
   * @param string $directory Direktori tujuan.
   * @param string|null $newName Nama file baru (jika null, akan menggunakan nama asli).
   * @return bool True jika berhasil, false jika gagal.
   */
  public function move(string $directory, ?string $newName = null): bool
  {
    $targetFileName = $newName ?? $this->name; // Gunakan $newName jika disediakan, jika tidak gunakan nama asli
    $targetPath = rtrim($directory, '/') . '/' . $targetFileName;

    // Log untuk debugging di dalam UploadedFile
    error_log("DEBUG_UPLOADEDFILE: Moving from " . $this->tmpName . " to " . $targetPath);

    return @move_uploaded_file($this->tmpName, $targetPath);
  }

  public function getError(): int
  {
    return $this->error;
  }

  public function getClientOriginalName(): string
  {
    return $this->name;
  }

  public function getClientMimeType(): string
  {
    return $this->type;
  }

  public function getSize(): int
  {
    return $this->size;
  }
}
