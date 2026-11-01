<?php

declare(strict_types = 1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;

/**
 * Starts and manages the PHP session for HTTP requests.
 */
final class SessionMiddleware implements MiddlewareInterface {

  /**
   * Session configuration.
   *
   * @var array<string, mixed>
   */
  private readonly array $settings;

  /**
   * Initialise the session middleware.
   *
   * @param array<string, mixed> $settings
   */
  public function __construct(array $settings) {
    $this->settings = $settings;
  }

  /**
   * Start the session before passing the request to the next handler.
   */
  public function process(
    ServerRequestInterface $request,
    RequestHandlerInterface $handler
  ): ResponseInterface {
    if (session_status() === PHP_SESSION_NONE) {
      session_name($this->settings['name']);

      ini_set('session.use_strict_mode', '1');
      ini_set('session.use_only_cookies', '1');
      ini_set('session.use_trans_sid', '0');
      ini_set('session.gc_maxlifetime', (string) $this->settings['gc_maxlifetime']);

      session_set_cookie_params([
        'lifetime' => $this->settings['lifetime'],
        'path' => '/',
        'secure' => $this->settings['secure'],
        'httponly' => true,
        'samesite' => $this->settings['samesite']
      ]);

      if (!session_start()) {
        throw new RuntimeException('Unable to start PHP session.');
      }
    }

    try {
      return $handler->handle($request);
    } finally {
      if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
      }
    }
  }
}
