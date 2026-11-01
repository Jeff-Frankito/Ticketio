<?php

declare(strict_types = 1);

use DI\Container;

/**
 * Register Ticketio application settings.
 */
return static function (Container $container): void {
  $basePath = dirname(__DIR__);
  $environment = getenv('APP_ENV') ?: 'development';

  $container->set('settings', [

    // --- Application Settings ---
    'app' => [
      'name' => 'Ticketio',
      'environment' => $environment
    ],
    
    // --- Authentication Settings ---
    'auth' => [
      'session_timeout' => 7200,
      'password_reset_lifetime' => 1800,
      'email_verification_lifetime' => 86400
    ],

    // --- Application URL Settings ---
    'url' => [
      'base' => rtrim(getenv('APP_URL') ?: 'http://localhost:8080', '/')
    ],

    // --- Error Handling ---
    'error' => [
      'displayErrorDetails' => $environment === 'development',
      'logErrors' => true,
      'logErrorDetails' => true
    ],

    // --- Application Paths ---
    'paths' => [
      'base' => $basePath,
      'views' => "{$basePath}/app/views",
      'cache' => "{$basePath}/cache"
    ],

    // --- Database Connection ---
    'database' => [
      'host' => getenv('DB_HOST') ?: 'mssql',
      'port' => (int) (getenv('DB_PORT') ?: 1433),
      'name' => getenv('DB_NAME') ?: 'Ticketio',
      'username' => getenv('DB_USER') ?: 'sa',
      'password' => getenv('DB_PASSWORD') ?: ''
    ],

    // --- Mail Settings ---
    'mail' => [
      'host' => getenv('MAIL_HOST') ?: 'mailpit',
      'port' => (int) (getenv('MAIL_PORT') ?: 1025),
      'auth' => false,
      'username' => '',
      'password' => '',
      'encryption' => '',
      'from' => [
        'email' => getenv('MAIL_FROM') ?: 'noreply@ticketio.test',
        'name' => getenv('MAIL_FROM_NAME') ?: 'Ticketio'
      ]
    ],

    // --- Session Settings ---
    'session' => [
      'name' => 'TICKETIOSESSID',
      'lifetime' => 0,           // Browser-session cookie.
      'gc_maxlifetime' => 7200,  // Session data eligible for cleanup after 2 hours.
      'secure' => $environment === 'production',
      'samesite' => 'Lax'
    ]

  ]);
};
