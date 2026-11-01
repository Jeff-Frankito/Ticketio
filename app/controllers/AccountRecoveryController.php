<?php

declare(strict_types = 1);

namespace App\Controllers;

use App\Services\AccountEmailService;
use App\Services\AccountTokenService;
use App\Services\BladeService;
use App\Services\AuthRateLimiter;
use App\Services\PasswordPolicy;
use DI\Container;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Psr7\Response;

/**
 * Handles forgotten passwords and email verification.
 */
final class AccountRecoveryController {

  /**
   * Account token service.
   */
  private readonly AccountTokenService $tokens;

  /**
   * Account email service.
   */
  private readonly AccountEmailService $accountEmail;

  /**
   * BladeOne template service.
   */
  private readonly BladeService $blade;
  private readonly AuthRateLimiter $limiter;

  /**
   * Initialise the controller using the application container.
   */
  public function __construct(Container $container) {
    $this->tokens = $container->get('tokens');
    $this->accountEmail = $container->get('accountEmail');
    $this->blade = $container->get('blade');
    $this->limiter = $container->get(AuthRateLimiter::class);
  }

  /**
   * Retrieve the current session CSRF token.
   */
  private function csrfToken(): string {
    if (!isset($_SESSION['csrf_token'])) {
      $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
  }

  /**
   * Validate a submitted CSRF token.
   */
  private function validCsrf(ServerRequestInterface $request): bool {
    $data = $request->getParsedBody();

    return is_array($data)
      && is_string($data['_token'] ?? null)
      && hash_equals($this->csrfToken(), $data['_token']);
  }

  /**
   * Render an authentication Blade view.
   *
   * @param array<string, mixed> $data
   */
  private function render(
    string $view,
    array $data = [],
    int $status = 200
  ): ResponseInterface {
    $response = new Response($status);

    $response->getBody()->write($this->blade->run($view, $data));

    return $response
      ->withHeader('Content-Type', 'text/html; charset=UTF-8')
      ->withHeader('Cache-Control', 'no-store')
      ->withHeader('Referrer-Policy', 'no-referrer')
      ->withHeader('X-Robots-Tag', 'noindex, nofollow');
  }

  /**
   * Display the forgotten-password form.
   */
  public function forgotForm(
    ServerRequestInterface $request,
    ResponseInterface $response
  ): ResponseInterface {
    return $this->render('auth.forgot-password', [
      'csrf' => $this->csrfToken()
    ]);
  }

  /**
   * Submit a forgotten-password request.
   */
  public function forgot(
    ServerRequestInterface $request,
    ResponseInterface $response
  ): ResponseInterface {
    if (!$this->validCsrf($request)) {
      return $response->withStatus(403);
    }

    $data = (array) $request->getParsedBody();
    $email = is_string($data['email'] ?? null) ? strtolower(trim($data['email'])) : '';

    if (!$this->limiter->allow($request, 'password_reset', $email)) {
      return $this->rateLimited();
    }

    if (strlen($email) <= 254 && filter_var($email, FILTER_VALIDATE_EMAIL)) {
      $this->accountEmail->requestPasswordReset($email);
    }

    return $this->render('auth.forgot-password', [
      'csrf' => $this->csrfToken(),
      'success' => true
    ]);
  }

  /**
   * Display the password reset form.
   */
  public function resetForm(
    ServerRequestInterface $request,
    ResponseInterface $response
  ): ResponseInterface {
    $token = $request->getQueryParams()['token'] ?? '';

    if (
      !is_string($token) ||
      !$this->tokens->valid($token, 'password_reset')
    ) {
      return $this->render('auth.token-result', [
        'message' => 'This reset link is invalid or has expired.'
      ], 400);
    }

    return $this->render('auth.reset-password', [
      'csrf' => $this->csrfToken(),
      'token' => $token,
      'error' => null
    ]);
  }

  /**
   * Change the password using a valid reset token.
   */
  public function reset(
    ServerRequestInterface $request,
    ResponseInterface $response
  ): ResponseInterface {
    if (!$this->validCsrf($request)) {
      return $response->withStatus(403);
    }

    if (!$this->limiter->allow($request, 'consume_password_reset')) { return $this->rateLimited(); }

    $data = (array) $request->getParsedBody();

    $token = $data['token'] ?? '';
    $password = $data['password'] ?? '';
    $confirmation = $data['password_confirmation'] ?? '';

    if (!is_string($token) || !is_string($password) || !is_string($confirmation)) {
      return $response->withStatus(422);
    }

    if (
      !PasswordPolicy::valid($password) ||
      $password !== $confirmation
    ) {
      return $this->render('auth.reset-password', [
        'csrf' => $this->csrfToken(),
        'token' => $token,
        'error' => PasswordPolicy::MESSAGE
      ], 422);
    }

    if (!$this->tokens->consume($token, 'password_reset', $password)) {
      return $this->render('auth.token-result', [
        'message' => 'This reset link is invalid or has expired.'
      ], 400);
    }

    return $this->render('auth.token-result', [
      'message' => 'Your password has been changed. You can now sign in.'
    ]);
  }

  /**
   * Display the email verification confirmation.
   */
  public function verifyForm(
    ServerRequestInterface $request,
    ResponseInterface $response
  ): ResponseInterface {
    $token = $request->getQueryParams()['token'] ?? '';

    if (
      !is_string($token) ||
      !$this->tokens->valid($token, 'email_verification')
    ) {
      return $this->render('auth.token-result', [
        'message' => 'This verification link is invalid or has expired.'
      ], 400);
    }

    return $this->render('auth.verify-email', [
      'csrf' => $this->csrfToken(),
      'token' => $token
    ]);
  }

  /**
   * Verify the email address using a single-use token.
   */
  public function verify(
    ServerRequestInterface $request,
    ResponseInterface $response
  ): ResponseInterface {
    if (!$this->validCsrf($request)) {
      return $response->withStatus(403);
    }

    if (!$this->limiter->allow($request, 'consume_email_verification')) { return $this->rateLimited(); }

    $data = (array) $request->getParsedBody();
    $token = $data['token'] ?? '';

    $verified = is_string($token) &&
      $this->tokens->consume($token, 'email_verification');

    return $this->render('auth.token-result', [
      'message' => $verified
        ? 'Your email is verified. You can now sign in.'
        : 'This verification link is invalid or has expired.'
    ], $verified ? 200 : 400);
  }

  public function resendForm(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface {
    return $this->render('auth.resend-verification', ['csrf' => $this->csrfToken()]);
  }

  public function resend(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface {
    if (!$this->validCsrf($request)) { return $response->withStatus(403); }
    $data = (array) $request->getParsedBody();
    $email = is_string($data['email'] ?? null) ? strtolower(trim($data['email'])) : '';
    if (!$this->limiter->allow($request, 'email_verification', $email)) {
      return $this->rateLimited();
    }
    if (strlen($email) <= 254 && filter_var($email, FILTER_VALIDATE_EMAIL)) {
      $this->accountEmail->requestVerification($email);
    }
    return $this->render('auth.resend-verification', ['csrf' => $this->csrfToken(), 'success' => true]);
  }

  private function rateLimited(): ResponseInterface {
    return $this->render('auth.token-result', [
      'message' => 'Too many requests. Please try again in 15 minutes.'
    ], 429)->withHeader('Retry-After', '900');
  }
}
