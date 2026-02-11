<?php

namespace App\Console;

use App\Console\Contracts\CommandInterface;
use App\Console\Commands\MakeControllerCommand;
use App\Console\Commands\MakeModelCommand;
use App\Console\Commands\MakeMiddlewareCommand;
use App\Console\Commands\MigrateCommand;
use App\Console\Commands\RouteCacheCommand;
use App\Console\Commands\BuildCommand;
use App\Console\Commands\ServeCommand;
use App\Console\Commands\QueueWorkCommand;
use App\Console\Commands\MakeJobCommand;
use App\Console\Commands\AboutCommand;
use App\Core\Foundation\Application;

class ConsoleKernel
{
  public function __construct(
    private Application $app,
  ) {
    $this->registerCommands();
  }

  /**
   * @var array<string, CommandInterface>
   */
  private array $commands = [];

  private function registerCommands(): void
  {
    $this->add(new MakeControllerCommand($this->app));
    $this->add(new MakeModelCommand($this->app));
    $this->add(new MakeMiddlewareCommand($this->app));
    $this->add(new MigrateCommand($this->app));
    $this->add(new RouteCacheCommand($this->app));
    $this->add(new BuildCommand($this->app));
    $this->add(new ServeCommand($this->app));
    $this->add(new QueueWorkCommand($this->app));
    $this->add(new MakeJobCommand($this->app));
    $this->add(new AboutCommand($this->app));
  }

  private function add(CommandInterface $command): void
  {
    $this->commands[$command->getName()] = $command;
  }

  public function handle(string $command, array $arguments): int
  {
    if ($command === '' || $command === 'list') {
      $this->printList();
      return 0;
    }

    if (!isset($this->commands[$command])) {
      echo color("Perintah tidak dikenal: {$command}\n", "red");
      echo "Gunakan 'php mazu list' untuk melihat daftar perintah.\n";
      return 1;
    }

    $instance = $this->commands[$command];

    return $instance->handle($arguments);
  }

  private function printList(): void
  {
    echo color("Usage:", "yellow") . "\n  php mazu [command] [arguments]\n\n";
    echo color("Available commands:", "yellow") . "\n";

    foreach ($this->commands as $cmd) {
      $name = $cmd->getName();
      $description = $cmd->getDescription();
      echo "  " . color($name, "green") . "  {$description}\n";
    }
  }
}
