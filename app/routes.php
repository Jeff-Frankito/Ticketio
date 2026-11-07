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

    // GitHub authentication
    $group->get('/auth/github', [\App\Controllers\GitHubAuthController::class, 'start'])->setName('auth.github');
    $group->get('/auth/github/callback', [\App\Controllers\GitHubAuthController::class, 'callback'])->setName('auth.github.callback');

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

    // Logout
    $group->post('/logout', [\App\Controllers\AuthController::class, 'logout'])->setName('logout');

    // Dashboard
    $group->get('/dashboard', [\App\Controllers\AuthController::class, 'dashboard'])->setName('dashboard');

    // --- Account Settings ---
    $group->group('/settings', function (RouteCollectorProxy $group): void {
      $group->get('/password', [\App\Controllers\Settings\PasswordController::class, 'index'])->setName('settings.password');
      $group->post('/password', [\App\Controllers\Settings\PasswordController::class, 'setup'])->setName('settings.password.setup');
      $group->get('/github', [\App\Controllers\Settings\GitHubController::class, 'index'])->setName('settings.github');
      $group->post('/github/connect', [\App\Controllers\Settings\GitHubController::class, 'connect'])->setName('settings.github.connect');
      $group->post('/github/disconnect', [\App\Controllers\Settings\GitHubController::class, 'disconnect'])->setName('settings.github.disconnect');
      $group->get('/profile', [\App\Controllers\Settings\ProfileController::class, 'index'])->setName('settings.profile');
      $group->post('/profile', [\App\Controllers\Settings\ProfileController::class, 'update'])->setName('settings.profile.update');
    });

  })->add(AuthMiddleware::class);

};
