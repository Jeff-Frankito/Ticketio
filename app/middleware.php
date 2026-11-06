<?php

declare(strict_types = 1);

use Slim\App;
use App\Middleware\SessionMiddleware;

/**
 * Register Ticketio application middleware.
 */
return static function (App $app): void {
  $container = $app->getContainer();
  $settings = $container->get('settings');

  // Register Slim routing middleware.
  $app->addRoutingMiddleware();

  // Slim executes application middleware last-in, first-out: start the session
  // before routing and route-level AuthMiddleware can access SessionManager.
  $app->add(new SessionMiddleware($settings['session']));

  // Register error middleware last to catch application exceptions.
  $app->addErrorMiddleware(
    $settings['error']['displayErrorDetails'],
    $settings['error']['logErrors'],
    $settings['error']['logErrorDetails']
  );
};
