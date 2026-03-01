<?php

namespace App\Exceptions;

class AuthenticationException extends \Exception
{
  private bool $hardRedirect = false;
  private ?string $redirectTo = null;

  public function __construct(string $message, int $code = 0, ?\Throwable $previous = null)
  {
    parent::__construct($message, $code, $previous);
  }

  public function hardRedirect(bool $hard = true): self
  {
    $this->hardRedirect = $hard;
    return $this;
  }

  public function redirectTo(string $url): self
  {
    $this->redirectTo = $url;
    return $this;
  }

  public function shouldHardRedirect(): bool
  {
    return $this->hardRedirect;
  }

  public function getRedirectTo(): ?string
  {
    return $this->redirectTo;
  }
}
