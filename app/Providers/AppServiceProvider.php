<?php

namespace App\Providers;

use App\Core\Database\Database;
use App\Core\Database\DatabaseManager;
use App\Core\Foundation\Container;
use App\Core\Foundation\ServiceProvider;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Queue\JobDispatcher;
use App\Services\ConfigService;
use App\Services\SeoService;
use App\Services\SessionService;


class AppServiceProvider extends ServiceProvider
{
  public function register(Container $container): void
  {
    // Response
    $container->bind(Response::class, function () use ($container) {
      return new Response($container);
    });

    // ConfigService (singleton)
    // Kita instansiasi di awal untuk menerapkan konfigurasi global (seperti Timezone)
    $configService = new ConfigService();

    if ($timezone = $configService->get('timezone')) {
      date_default_timezone_set($timezone);
    }

    $container->bind(ConfigService::class, function () use ($configService) {
      return $configService;
    });

    // SessionService (singleton)
    $container->bind(SessionService::class, function () {
      static $instance = null;
      if ($instance === null) {
        $instance = new SessionService();
      }
      return $instance;
    });

    // DatabaseManager (singleton)
    $container->bind(DatabaseManager::class, function () use ($container) {
      static $instance = null;
      if ($instance === null) {
        $instance = new DatabaseManager($container->resolve(ConfigService::class));
      }
      return $instance;
    });

    // Database (singleton) - Delegates to DatabaseManager
    $container->bind(Database::class, function () use ($container) {
      return $container->resolve(DatabaseManager::class)->connection('mysql');
    });


    // JobDispatcher (singleton)
    $container->singleton(JobDispatcher::class, function () use ($container) {
      return new JobDispatcher(
        $container->resolve(DatabaseManager::class),
        $container->resolve(ConfigService::class)
      );
    });



    $container->singleton(SeoService::class, function () use ($container) {
      return new SeoService(
        $container->resolve(ConfigService::class),
        $container->resolve(Request::class)
      );
    });
  }
}
