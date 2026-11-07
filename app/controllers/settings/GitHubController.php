<?php

declare(strict_types = 1);

namespace App\Controllers\Settings;

use App\Models\GitHubConnection;
use App\Services\AuthRateLimiter;
use App\Services\AuthService;
use App\Services\BladeService;
use App\Services\GitHubAuthException;
use App\Services\GitHubAuthorization;
use App\Services\SessionManager;
use DI\Container;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Psr7\Response;
use Throwable;

/**
 * Connected GitHub account settings and password-confirmed changes.
 */
final class GitHubController {

  // ///////////////////////////////////////////////////////////////////////////

  private readonly AuthService $auth;
  private readonly GitHubAuthorization $authorization;
  private readonly GitHubConnection $connections;
  private readonly SessionManager $session;
  private readonly BladeService $blade;
  private readonly AuthRateLimiter $limiter;
  private readonly int $sessionTimeout;

  // ///////////////////////////////////////////////////////////////////////////

  public function __construct(Container $container) {
    $this->auth = $container->get(AuthService::class);
    $this->authorization = $container->get(GitHubAuthorization::class);
    $this->connections = $container->get(GitHubConnection::class);
    $this->session = $container->get(SessionManager::class);
    $this->blade = $container->get(BladeService::class);
    $this->limiter = $container->get(AuthRateLimiter::class);
    $this->sessionTimeout = (int) $container->get('settings')['auth']['session_timeout'];
  }

  // ///////////////////////////////////////////////////////////////////////////
  // Protected Settings Routes
  // ///////////////////////////////////////////////////////////////////////////

  /**
   * GET /settings/github - Show current GitHub connection.
   */
  public function index(
    ServerRequestInterface $request,
    ResponseInterface $response
  ): ResponseInterface {
    $connection = $this->connections->findByUserId($this->session->requireUserId());

    $response->getBody()->write($this->blade->run('settings.github', [
      'settingsPage' => 'github',
      'connection' => $connection,
      'csrf' => $this->session->csrfToken(),
      'success' => $this->session->pullFlash('github_success'),
      'error' => $this->session->pullFlash('github_error')
    ]));

    return $response
      ->withHeader('Content-Type', 'text/html; charset=UTF-8')
      ->withHeader('Cache-Control', 'no-store');
  }

  // ///////////////////////////////////////////////////////////////////////////

  /**
   * POST /settings/github/connect - Start linking after CSRF check.
   */
  public function connect(
    ServerRequestInterface $request,
    ResponseInterface $response
  ): ResponseInterface {
    if (!$this->session->validCsrf($request)) {
      return $response->withStatus(403);
    }

    if (!$this->auth->validateAuthentication($this->session, $this->sessionTimeout)) {
      return $this->redirect('/login');
    }

    if (!$this->limiter->allow($request, 'github_connect')) {
      $this->session->flash('github_error', 'Too many requests. Please try again later.');
      return $this->redirect('/settings/github');
    }

    $body = $request->getParsedBody();
    $password = is_array($body) ? ($body['password'] ?? null) : null;

    if (!is_string($password) ||
        !$this->auth->verifyPasswordForUser($this->session->requireUserId(), $password)) {
      $this->session->flash('github_error', 'Confirm your Ticketio password before connecting GitHub.');
      return $this->redirect('/settings/github');
    }

    if ($this->connections->findByUserId($this->session->requireUserId()) !== null) {
      $this->session->flash('github_error', 'A GitHub account is already connected.');
      return $this->redirect('/settings/github');
    }

    return $this->begin('link');
  }

  // ///////////////////////////////////////////////////////////////////////////

  /**
   * POST /settings/github/disconnect - Require a Ticketio password so a
   * GitHub-only account cannot accidentally remove its only login method.
   */
  public function disconnect(
    ServerRequestInterface $request,
    ResponseInterface $response
  ): ResponseInterface {
    if (!$this->session->validCsrf($request)) {
      return $response->withStatus(403);
    }

    $body = $request->getParsedBody();
    $password = is_array($body) ? ($body['password'] ?? null) : null;

    if (!$this->limiter->allow($request, 'github_disconnect')) {
      $this->session->flash('github_error', 'Too many attempts. Please try again later.');
    } elseif (!is_string($password) ||
        !$this->auth->verifyPasswordForUser($this->session->requireUserId(), $password)) {
      $this->session->flash('github_error', 'Enter your Ticketio password before disconnecting GitHub.');
    } else {
      try {
        $this->connections->disconnect($this->session->requireUserId());
        $this->session->remove('temporary.github_oauth');
        $this->session->flash('github_success', 'GitHub has been disconnected from Ticketio.');
      } catch (Throwable $exception) {
        error_log('GitHub disconnect failed: ' . $exception::class);
        $this->session->flash('github_error', 'Unable to disconnect GitHub. Please try again.');
      }
    }

    return $this->redirect('/settings/github');
  }

  // ///////////////////////////////////////////////////////////////////////////
  // Helpers
  // ///////////////////////////////////////////////////////////////////////////

  private function begin(string $mode): ResponseInterface {
    try {
      return $this->redirect($this->authorization->begin($mode));
    } catch (GitHubAuthException $exception) {
      $this->session->flash('github_error', $exception->getMessage());
    } catch (Throwable $exception) {
      error_log('GitHub authorization failed: ' . $exception::class);
      $this->session->flash('github_error', 'Unable to start GitHub authorization. Please try again.');
    }

    return $this->redirect($mode === 'link' ? '/settings/github' : '/login');
  }

  // ///////////////////////////////////////////////////////////////////////////

  private function redirect(string $url): ResponseInterface {
    return (new Response(303))
      ->withHeader('Location', $url)
      ->withHeader('Cache-Control', 'no-store');
  }

  // ///////////////////////////////////////////////////////////////////////////

}
