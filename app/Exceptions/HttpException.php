<?php

namespace App\Exceptions;

class HttpException extends \Exception
{
  public function __construct(
    protected int $statusCode,
    string $message = ""
  ) {
    parent::__construct($message);
  }

  public function getStatusCode(): int
  {
    return $this->statusCode;
  }
}
