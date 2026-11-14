<?php

declare(strict_types = 1);

use App\Middleware\AuthMiddleware;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;

/**
 * Register Ticketio application routes.
 */
return static function (App $app): void {

  // --- Public Routes ---
  $app->group('', function (RouteCollectorProxy $group): void {

    // Home redirect
    $group->get('/', function($request, $response){
      return $response
        ->withHeader('Location', '/login')
        ->withStatus(302);
    })->setName('home');

    // Authentication
    $group->get('/login', [\App\Controllers\Auth\AuthController::class, 'loginForm'])->setName('login');
    $group->post('/login', [\App\Controllers\Auth\AuthController::class, 'login'])->setName('login.submit');

    $group->get('/register', [\App\Controllers\Auth\AuthController::class, 'registerForm'])->setName('register');
    $group->post('/register', [\App\Controllers\Auth\AuthController::class, 'register'])->setName('register.submit');

    // Password Recovery
    $group->get('/forgot-password', [\App\Controllers\Auth\AccountRecoveryController::class, 'forgotForm'])->setName('password.forgot');
    $group->post('/forgot-password', [\App\Controllers\Auth\AccountRecoveryController::class, 'forgot'])->setName('password.forgot.submit');

    $group->get('/reset-password', [\App\Controllers\Auth\AccountRecoveryController::class, 'resetForm'])->setName('password.reset');
    $group->post('/reset-password', [\App\Controllers\Auth\AccountRecoveryController::class, 'reset'])->setName('password.reset.submit');

    // Email Verification
    $group->get('/verify-email', [\App\Controllers\Auth\AccountRecoveryController::class, 'verifyForm'])->setName('email.verify');
    $group->post('/verify-email', [\App\Controllers\Auth\AccountRecoveryController::class, 'verify'])->setName('email.verify.submit');

    $group->get('/resend-verification', [\App\Controllers\Auth\AccountRecoveryController::class, 'resendForm']);
    $group->post('/resend-verification', [\App\Controllers\Auth\AccountRecoveryController::class, 'resend']);

  });

  // --- Protected Routes ---
  $app->group('', function (RouteCollectorProxy $group): void {

    // Logout
    $group->post('/logout', [\App\Controllers\Auth\AuthController::class, 'logout'])->setName('logout');

    // Dashboard
    $group->get('/dashboard', [\App\Controllers\Auth\AuthController::class, 'dashboard'])->setName('dashboard');

    // WebSocket Authentication
    $group->post(
      '/auth/websocket-ticket',
      [\App\Controllers\WebSocket\WebSocketController::class, 'ticket']
    )->setName('websocket.ticket');

    // --- Account Settings ---
    $group->group('/settings', function (RouteCollectorProxy $group): void {
      $group->get('/profile', [\App\Controllers\Settings\ProfileController::class, 'index'])->setName('settings.profile');
      $group->post('/profile', [\App\Controllers\Settings\ProfileController::class, 'update'])->setName('settings.profile.update');
    });

  })->add(AuthMiddleware::class);

};
