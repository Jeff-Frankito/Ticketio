<?php

declare(strict_types = 1);

namespace App\Middleware;

use App\Services\AuthService;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

/**
 * Middleware responsible for protecting authenticated routes.
 */
final class AuthMiddleware implements MiddlewareInterface {

  /**
   * Maximum session inactivity period in seconds.
   */
  public function __construct(
    private readonly AuthService $auth,
    private readonly int $sessionTimeout = 7200
  ) {}

  /**
   * Validate the authenticated session before processing the request.
   */
  public function process(
    ServerRequestInterface $request,
    RequestHandlerInterface $handler
  ): ResponseInterface {
    $userId = $_SESSION['user_id'] ?? null;
    $lastActivity = $_SESSION['last_activity'] ?? null;

    // --- Authentication Check ---
    if (!is_int($userId) || $userId <= 0 || !is_int($lastActivity)) {
      return $this->redirectToLogin();
    }

    // --- Session Expiration ---
    if (time() - $lastActivity >= $this->sessionTimeout ||
        !is_int($_SESSION['session_version'] ?? null) ||
        !$this->auth->validSession($userId, $_SESSION['session_version'])) {
      $_SESSION = [];

      session_regenerate_id(true);

      return $this->redirectToLogin();
    }

    // --- Update Session Activity ---
    $_SESSION['last_activity'] = time();

    // --- Continue Request ---
    return $handler->handle($request)->withHeader('Cache-Control', 'no-store');
  }

  /**
   * Redirect unauthenticated requests to the login page.
   */
  private function redirectToLogin(): ResponseInterface {
    return (new Response(302))
      ->withHeader('Location', '/login')
      ->withHeader('Cache-Control', 'no-store');
  }

}
