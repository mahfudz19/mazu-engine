<?php

namespace App\Console\Contracts;

interface CommandInterface
{
  public function getName(): string;

  public function getDescription(): string;

  public function handle(array $arguments): int;
}
