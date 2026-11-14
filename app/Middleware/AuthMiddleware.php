<?php

declare(strict_types = 1);

namespace App\Middleware;

use App\Services\Auth\AuthService;
use App\Services\Session\SessionManager;
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

      // API endpoints must not redirect to an HTML login page.
      if ($request->getUri()->getPath() === '/auth/websocket-ticket') {
        $response = new Response(401);

        $response->getBody()->write(json_encode([
          'success' => false,
          'message' => 'Authentication required.'
        ], JSON_THROW_ON_ERROR));

        return $response
          ->withHeader('Content-Type', 'application/json; charset=UTF-8')
          ->withHeader('Cache-Control', 'no-store');
      }

      return $this->redirectToLogin();
    }

    // --- Continue Request ---
    return $handler->handle($request)
      ->withHeader('Cache-Control', 'no-store');
  }

  // ///////////////////////////////////////////////////////////////////////////

  /**
   * Redirect unauthenticated page requests to the login page.
   */
  private function redirectToLogin(): ResponseInterface {
    return (new Response(302))
      ->withHeader('Location', '/login')
      ->withHeader('Cache-Control', 'no-store');
  }

  // ///////////////////////////////////////////////////////////////////////////

}
