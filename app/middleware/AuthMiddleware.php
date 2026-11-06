<?php

declare(strict_types = 1);

namespace App\Middleware;

use App\Services\AuthService;
use App\Services\SessionManager;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

/**
 * Middleware responsible for protecting authenticated routes.
 */
final class AuthMiddleware implements MiddlewareInterface {

  // ///////////////////////////////////////////////////////////////////////////

  /**
   * Maximum session inactivity period in seconds.
   */
  public function __construct(
    private readonly AuthService $auth,
    private readonly SessionManager $session,
    private readonly int $sessionTimeout = 7200
  ) {}

  // ///////////////////////////////////////////////////////////////////////////

  /**
   * Validate the authenticated session before processing the request.
   */
  public function process(
    ServerRequestInterface $request,
    RequestHandlerInterface $handler
  ): ResponseInterface {
    if (!$this->auth->validateAuthentication($this->session, $this->sessionTimeout)) {
      return $this->redirectToLogin();
    }

    // --- Continue Request ---
    return $handler->handle($request)->withHeader('Cache-Control', 'no-store');
  }

  // ///////////////////////////////////////////////////////////////////////////

  /**
   * Redirect unauthenticated requests to the login page.
   */
  private function redirectToLogin(): ResponseInterface {
    return (new Response(302))
      ->withHeader('Location', '/login')
      ->withHeader('Cache-Control', 'no-store');
  }

  // ///////////////////////////////////////////////////////////////////////////

}
