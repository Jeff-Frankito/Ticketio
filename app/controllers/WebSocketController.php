<?php

declare(strict_types = 1);

namespace App\Controllers;

use App\Services\SessionManager;
use App\Services\WebSocket\WebSocketTicketService;
use DI\Container;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Handles WebSocket authentication for signed-in Ticketio users.
 */
final class WebSocketController {

  // ///////////////////////////////////////////////////////////////////////////

  private readonly SessionManager $session;
  private readonly WebSocketTicketService $tickets;
  private readonly string $appUrl;

  // ///////////////////////////////////////////////////////////////////////////

  /**
   * Initialise the WebSocket controller.
   */
  public function __construct(Container $container) {
    $this->session = $container->get(SessionManager::class);
    $this->tickets = $container->get(WebSocketTicketService::class);
    $this->appUrl = $container->get('settings')['url']['base'];
  }

  // ///////////////////////////////////////////////////////////////////////////
  // Authentication Tickets
  // ///////////////////////////////////////////////////////////////////////////

  /**
   * Issue a short-lived, one-time WebSocket authentication ticket.
   *
   * AuthMiddleware validates the authenticated session before this
   * controller executes.
   */
  public function ticket(
    ServerRequestInterface $request,
    ResponseInterface $response
  ): ResponseInterface {

    // Only permit requests from Ticketio's configured origin.
    $origin = rtrim($request->getHeaderLine('Origin'), '/');

    if ($origin !== rtrim($this->appUrl, '/')) {
      return $this->json($response, [
        'success' => false,
        'message' => 'Invalid request origin.'
      ], 403);
    }

    // Validate against the current authenticated PHP session.
    $submitted = $request->getHeaderLine('X-CSRF-Token');
    $expected = $this->session->csrfToken();

    if ($submitted === '' || !hash_equals($expected, $submitted)) {
      return $this->json($response, [
        'success' => false,
        'message' => 'Invalid CSRF token.'
      ], 403);
    }

    // Authentication identity is owned by SessionManager.
    $userId = $this->session->requireUserId();

    // Redis stores the ticket for 60 seconds. Workerman consumes it
    // atomically when the browser authenticates.
    $ticket = $this->tickets->issue($userId);

    return $this->json($response, [
      'success' => true,
      'ticket' => $ticket,
      'expires_in' => 60
    ]);
  }

  // ///////////////////////////////////////////////////////////////////////////
  // Helpers
  // ///////////////////////////////////////////////////////////////////////////

  /**
   * Build a JSON response with caching disabled.
   *
   * @param array<string, mixed> $payload
   */
  private function json(
    ResponseInterface $response,
    array $payload,
    int $status = 200
  ): ResponseInterface {
    $response->getBody()->write(
      json_encode($payload, JSON_THROW_ON_ERROR)
    );

    return $response
      ->withStatus($status)
      ->withHeader('Content-Type', 'application/json; charset=UTF-8')
      ->withHeader('Cache-Control', 'no-store')
      ->withHeader('Pragma', 'no-cache')
      ->withHeader('X-Content-Type-Options', 'nosniff');
  }

  // ///////////////////////////////////////////////////////////////////////////

}
