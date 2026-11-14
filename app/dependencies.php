<?php

declare(strict_types = 1);

use App\Services\AuthService;
use App\Services\BladeService;
use App\Services\MailService;
use App\Services\AccountEmailService;
use App\Services\AccountTokenService;
use App\Services\SessionManager;
use App\Services\Redis\RedisService;
use App\Services\Events\EventPublisher;
use App\Services\WebSocket\WebSocketTicketService;
use DI\Container;

/**
 * Register Ticketio application dependencies.
 */
return static function (Container $container): void {

  $container->set(SessionManager::class, static fn (): SessionManager => new SessionManager());

  // --- Database Connection ---
  $container->set(PDO::class, static function (Container $container): PDO {
    $settings = $container->get('settings')['database'];

    return new PDO(
      "sqlsrv:Server={$settings['host']},{$settings['port']};Database={$settings['name']};Encrypt=yes;TrustServerCertificate=yes",
      $settings['username'],
      $settings['password'],
      [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
      ]
    );
  });

  // --- BladeOne Template Engine ---
  $container->set(BladeService::class, static function (Container $container): BladeService {
    $settings = $container->get('settings');

    return new BladeService(
      $settings['paths']['views'],
      $settings['paths']['cache']
    );
  });

  // --- Mail Service ---
  $container->set(MailService::class, static function (Container $container): MailService {
    $settings = $container->get('settings');

    return new MailService($settings['mail']);
  });

  // --- Account Tokens ---
  $container->set(AccountTokenService::class, static function (Container $container): AccountTokenService {
    return new AccountTokenService($container->get(PDO::class));
  });

  // --- Account Emails ---
  $container->set(AccountEmailService::class, static function (Container $container): AccountEmailService {
    return new AccountEmailService(
      $container->get(PDO::class),
      $container->get(AccountTokenService::class),
      $container->get(MailService::class),
      $container->get(BladeService::class),
      $container->get('settings')
    );
  });

  // --- Authentication Service ---
  $container->set(AuthService::class, static function (Container $container): AuthService {
    return new AuthService($container->get(PDO::class));
  });

  // --- Redis Service ---
  $container->set(RedisService::class, static function (Container $container): RedisService {
    return new RedisService();
  });

  // --- Event Publisher ---
  $container->set(EventPublisher::class, static function (Container $container): EventPublisher {
    return new EventPublisher(
      $container->get(RedisService::class)
    );
  });

  // --- WebSocket Authentication Tickets ---
  $container->set(WebSocketTicketService::class, static function (Container $container): WebSocketTicketService {
    return new WebSocketTicketService(
      $container->get(RedisService::class)
    );
  });

  // --- Service Aliases ---
  $container->set(\App\Middleware\AuthMiddleware::class, static function (Container $container): \App\Middleware\AuthMiddleware {
    return new \App\Middleware\AuthMiddleware(
      $container->get(AuthService::class),
      $container->get(SessionManager::class),
      $container->get('settings')['auth']['session_timeout']
    );
  });

  $container->set('blade', $container->get(BladeService::class));
  $container->set('mail', $container->get(MailService::class));
  $container->set('auth', $container->get(AuthService::class));
  $container->set('tokens', $container->get(AccountTokenService::class));
  $container->set('accountEmail', $container->get(AccountEmailService::class));

  $container->set('redis', $container->get(RedisService::class));
  $container->set('events', $container->get(EventPublisher::class));
  $container->set('websocketTickets', $container->get(WebSocketTicketService::class));

};
