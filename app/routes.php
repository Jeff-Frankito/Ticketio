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

    // Home
    $group->get('/', function ($request, $response) {
      return $response
        ->withHeader('Location', '/login')
        ->withStatus(302);
    })->setName('home');

    // Authentication
    $group->get('/login', [\App\Controllers\AuthController::class, 'loginForm'])->setName('login');
    $group->post('/login', [\App\Controllers\AuthController::class, 'login'])->setName('login.submit');

    $group->get('/register', [\App\Controllers\AuthController::class, 'registerForm'])->setName('register');
    $group->post('/register', [\App\Controllers\AuthController::class, 'register'])->setName('register.submit');

    // Password Recovery
    $group->get('/forgot-password', [\App\Controllers\AccountRecoveryController::class, 'forgotForm'])->setName('password.forgot');
    $group->post('/forgot-password', [\App\Controllers\AccountRecoveryController::class, 'forgot'])->setName('password.forgot.submit');

    $group->get('/reset-password', [\App\Controllers\AccountRecoveryController::class, 'resetForm'])->setName('password.reset');
    $group->post('/reset-password', [\App\Controllers\AccountRecoveryController::class, 'reset'])->setName('password.reset.submit');

    // Email Verification
    $group->get('/verify-email', [\App\Controllers\AccountRecoveryController::class, 'verifyForm'])->setName('email.verify');
    $group->post('/verify-email', [\App\Controllers\AccountRecoveryController::class, 'verify'])->setName('email.verify.submit');

    $group->get('/resend-verification', [\App\Controllers\AccountRecoveryController::class, 'resendForm']);
    $group->post('/resend-verification', [\App\Controllers\AccountRecoveryController::class, 'resend']);

  });

  // --- Protected Routes ---
  $app->group('', function (RouteCollectorProxy $group): void {

    // Dashboard
    $group->get('/dashboard', [\App\Controllers\AuthController::class, 'dashboard'])->setName('dashboard');

    // Logout
    $group->post('/logout', [\App\Controllers\AuthController::class, 'logout'])->setName('logout');

  })->add(AuthMiddleware::class);

};
