<?php

declare(strict_types = 1);

namespace App\Controllers\Settings;

use App\Services\AccountEmailService;
use App\Services\AuthRateLimiter;
use App\Services\BladeService;
use App\Services\SessionManager;
use DI\Container;
use PDO;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

/** Email-proven password setup for both GitHub-first and password accounts. */
final class PasswordController {

  // ///////////////////////////////////////////////////////////////////////////

  private readonly PDO $database;
  private readonly SessionManager $session;
  private readonly BladeService $blade;
  private readonly AuthRateLimiter $limiter;
  private readonly AccountEmailService $email;

  // ///////////////////////////////////////////////////////////////////////////

  public function __construct(Container $container) {
    $this->database = $container->get(PDO::class);
    $this->session = $container->get(SessionManager::class);
    $this->blade = $container->get(BladeService::class);
    $this->limiter = $container->get(AuthRateLimiter::class);
    $this->email = $container->get(AccountEmailService::class);
  }

  // ///////////////////////////////////////////////////////////////////////////

  public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface {
    return $this->render($response);
  }

  // ///////////////////////////////////////////////////////////////////////////

  public function setup(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface {
    if (!$this->session->validCsrf($request)) {
      return $response->withStatus(403);
    }

    // Resolve the recipient from the authenticated account, never the form body.
    $query = $this->database->prepare(
      'SELECT [EMAIL] FROM [dbo].[USERS]
       WHERE [ID] = ? AND [IS_ACTIVE] = 1 AND [EMAIL_VERIFIED_AT] IS NOT NULL'
    );
    $query->execute([$this->session->requireUserId()]);
    $email = $query->fetchColumn();
    $query->closeCursor();
    if (!is_string($email)) {
      return $response->withStatus(403);
    }

    // Share the recovery bucket so using settings cannot bypass its email limit.
    if (!$this->limiter->allow($request, 'password_reset', $email)) {
      return $this->render($response->withStatus(429)->withHeader('Retry-After', '900'),
        'Too many requests. Please try again in 15 minutes.');
    }

    try {
      $this->email->requestPasswordReset($email);
    } catch (Throwable $exception) {
      error_log('Password setup failed: ' . $exception::class);
      return $this->render($response->withStatus(503), 'Unable to send the password link. Please try again.');
    }

    // POST/redirect/GET avoids sending another email when the page is refreshed.
    $this->session->flash('password_setup_sent', true);
    return $response->withStatus(303)->withHeader('Location', '/settings/password')
      ->withHeader('Cache-Control', 'no-store');
  }

  // ///////////////////////////////////////////////////////////////////////////

  private function render(ResponseInterface $response, ?string $error = null): ResponseInterface {
    $response->getBody()->write($this->blade->run('settings.password', [
      'settingsPage' => 'password',
      'csrf' => $this->session->csrfToken(),
      'sent' => $this->session->pullFlash('password_setup_sent', false),
      'error' => $error
    ]));

    return $response->withHeader('Content-Type', 'text/html; charset=UTF-8')
      ->withHeader('Cache-Control', 'no-store');
  }

  // ///////////////////////////////////////////////////////////////////////////
  
}
