<?php

namespace App\Exceptions;

class AuthorizationException extends \Exception
{
  protected ?string $redirectTo;

  public function __construct(string $message = "", ?string $redirectTo = null, int $code = 0, \Throwable $previous = null)
  {
    parent::__construct($message, $code, $previous);
    $this->redirectTo = $redirectTo;
  }

  public function getRedirectTo(): ?string
  {
    return $this->redirectTo;
  }
}
