<?php

declare(strict_types = 1);

namespace App\Controllers;

use App\Models\GitHubConnection;
use App\Services\AuthRateLimiter;
use App\Services\AuthService;
use App\Services\BladeService;
use App\Services\GitHubAuthException;
use App\Services\GitHubOAuth;
use App\Services\SessionManager;
use DI\Container;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Psr7\Response;
use Throwable;

/**
 * GitHub OAuth login, registration and explicit account linking.
 */
final class GitHubAuthController {

  // ///////////////////////////////////////////////////////////////////////////

  private readonly AuthService $auth;
  private readonly GitHubOAuth $github;
  private readonly GitHubConnection $connections;
  private readonly SessionManager $session;
  private readonly BladeService $blade;
  private readonly AuthRateLimiter $limiter;
  private readonly int $sessionTimeout;

  // ///////////////////////////////////////////////////////////////////////////

  public function __construct(Container $container) {
    $this->auth = $container->get(AuthService::class);
    $this->github = $container->get(GitHubOAuth::class);
    $this->connections = $container->get(GitHubConnection::class);
    $this->session = $container->get(SessionManager::class);
    $this->blade = $container->get(BladeService::class);
    $this->limiter = $container->get(AuthRateLimiter::class);
    $this->sessionTimeout = (int) $container->get('settings')['auth']['session_timeout'];
  }

  // ///////////////////////////////////////////////////////////////////////////
  // Public Routes
  // ///////////////////////////////////////////////////////////////////////////

  /**
   * GET /auth/github - GitHub login or automatic registration.
   */
  public function start(
    ServerRequestInterface $request,
    ResponseInterface $response
  ): ResponseInterface {
    if ($this->auth->validateAuthentication($this->session, $this->sessionTimeout)) {
      return $this->redirect('/dashboard');
    }

    if (!$this->limiter->allow($request, 'github_oauth')) {
      $this->session->flash('github_error', 'Too many sign-in attempts. Please try again later.');
      return $this->redirect('/login');
    }

    return $this->begin('login');
  }

  /**
   * GET /auth/github/callback - Consume state and finish the OAuth operation.
   */
  public function callback(
    ServerRequestInterface $request,
    ResponseInterface $response
  ): ResponseInterface {
    $query = $request->getQueryParams();
    $pending = $this->session->pull('temporary.github_oauth');
    $state = $query['state'] ?? null;

    if (
      !is_array($pending) ||
      !is_string($state) ||
      !is_string($pending['state'] ?? null) ||
      !hash_equals($pending['state'], $state) ||
      !is_int($pending['created_at'] ?? null) ||
      $pending['created_at'] > time() ||
      time() - $pending['created_at'] > 600 ||
      !in_array($pending['mode'] ?? null, ['login', 'link'], true) ||
      !is_string($pending['verifier'] ?? null)
    ) {
      $this->session->flash('github_error', 'GitHub authorization expired or could not be verified. Please try again.');
      return $this->redirect('/login');
    }

    $mode = $pending['mode'];
    $errorPath = $mode === 'link' ? '/settings/github' : '/login';

    if (isset($query['error'])) {
      $this->session->flash('github_error', 'GitHub authorization was cancelled or denied.');
      return $this->redirect($errorPath);
    }

    $code = $query['code'] ?? null;

    if (!is_string($code) || $code === '') {
      $this->session->flash('github_error', 'GitHub did not return an authorization code.');
      return $this->redirect($errorPath);
    }

    if ($mode === 'link' && (
      !$this->auth->validateAuthentication($this->session, $this->sessionTimeout) ||
      !is_int($pending['user_id'] ?? null) ||
      !is_int($pending['session_version'] ?? null) ||
      $pending['user_id'] !== $this->session->userIdOrNull() ||
      $pending['session_version'] !== $this->session->sessionVersion()
    )) {
      $this->session->flash('github_error', 'Sign in again before connecting GitHub.');
      return $this->redirect('/login');
    }

    try {
      $github = $this->github->identity($code, $pending['verifier']);

      if ($mode === 'link') {
        $this->connections->link(
          $this->session->requireUserId(),
          $github['id'],
          $github['login']
        );

        $this->session->flash('github_success', 'Your GitHub account is now connected.');
        return $this->redirect('/settings/github');
      }

      $userId = $this->connections->findUserId($github['id']);

      if ($userId === null) {
        // New account ONLY. Never merge accounts based solely on email.
        $github['email'] = $this->github->verifiedPrimaryEmail($github['access_token']);
        $userId = $this->connections->createAccount($github);
      }

      // The GitHub token is never put in SessionManager or SQL Server.
      unset($github['access_token']);

      $user = $this->auth->findByIdForSession($userId);

      if ($user === false) {
        throw new GitHubAuthException('This Ticketio account cannot sign in. Please contact support.');
      }

      $this->session->login($user);
      return $this->redirect('/dashboard');
    } catch (GitHubAuthException $exception) {
      // Only carefully authored, user-safe messages are shown to the browser.
      $this->session->flash('github_error', $exception->getMessage());
    } catch (Throwable $exception) {
      // Never show or log raw SQL/Guzzle/OAuth exception messages or tokens.
      error_log('GitHub OAuth operation failed: ' . $exception::class);
      $this->session->flash('github_error', 'Unable to complete GitHub authentication. Please try again.');
    }

    return $this->redirect($errorPath);
  }

  // ///////////////////////////////////////////////////////////////////////////
  // Protected Settings Routes
  // ///////////////////////////////////////////////////////////////////////////

  /**
   * GET /settings/github - Show current GitHub connection.
   */
  public function settings(
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
      $this->connections->disconnect($this->session->requireUserId());
      $this->session->flash('github_success', 'GitHub has been disconnected from Ticketio.');
    }

    return $this->redirect('/settings/github');
  }

  // ///////////////////////////////////////////////////////////////////////////
  // Helpers
  // ///////////////////////////////////////////////////////////////////////////

  private function begin(string $mode): ResponseInterface {
    try {
      $authorization = $this->github->begin();
    } catch (GitHubAuthException $exception) {
      $this->session->flash('github_error', $exception->getMessage());
      return $this->redirect($mode === 'link' ? '/settings/github' : '/login');
    }

    $pending = [
      'state' => $authorization['state'],
      'verifier' => $authorization['verifier'],
      'mode' => $mode,
      'created_at' => time()
    ];

    if ($mode === 'link') {
      $pending['user_id'] = $this->session->requireUserId();
      $pending['session_version'] = $this->session->sessionVersion();
    }

    $this->session->set('temporary.github_oauth', $pending);
    return $this->redirect($authorization['url']);
  }

  private function redirect(string $url): ResponseInterface {
    return (new Response(303))
      ->withHeader('Location', $url)
      ->withHeader('Cache-Control', 'no-store');
  }

}
