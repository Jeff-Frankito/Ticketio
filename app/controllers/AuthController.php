<?php

declare(strict_types = 1);

namespace App\Controllers;

use App\Services\AuthService;
use App\Services\BladeService;
use App\Services\SessionManager;
use App\Services\AccountEmailService;
use App\Services\AuthRateLimiter;
use App\Services\PasswordPolicy;
use Slim\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use DI\Container;

/**
 * Handles Ticketio authentication requests.
 */
final class AuthController {

  // ///////////////////////////////////////////////////////////////////////////

  /**
   * Authentication service.
   */
  private readonly AuthService $auth;

  /**
   * BladeOne template service.
   */
  private readonly BladeService $blade;
  private readonly SessionManager $session;
  private readonly AccountEmailService $accountEmail;
  private readonly AuthRateLimiter $limiter;
  private readonly int $sessionTimeout;

  // ///////////////////////////////////////////////////////////////////////////

  /**
   * Initialise the authentication controller.
   */
  public function __construct(Container $container) {
    $this->auth = $container->get('auth');
    $this->blade = $container->get('blade');
    $this->session = $container->get(SessionManager::class);
    $this->accountEmail = $container->get('accountEmail');
    $this->limiter = $container->get(AuthRateLimiter::class);
    $this->sessionTimeout = $container->get('settings')['auth']['session_timeout'];
  }

  // ///////////////////////////////////////////////////////////////////////////

  /**
   * Render a BladeOne view.
   *
   * @param array<string, mixed> $data
   */
  private function render(
    string $view,
    array $data = [],
    int $status = 200
  ): ResponseInterface {
    $response = new Response($status);

    $response->getBody()->write(
      $this->blade->run($view, $data)
    );

    return $response->withHeader('Cache-Control', 'no-store')->withHeader(
      'Content-Type',
      'text/html; charset=UTF-8'
    );
  }

  // ///////////////////////////////////////////////////////////////////////////

  /**
   * Redirect with HTTP 303 after a form submission.
   */
  private function redirect(string $path): ResponseInterface {
    return (new Response(303))->withHeader('Location', $path)->withHeader('Cache-Control', 'no-store');
  }

  // ///////////////////////////////////////////////////////////////////////////

  /**
   * Display the login form.
   */
  public function loginForm(
    ServerRequestInterface $request,
    ResponseInterface $response
  ): ResponseInterface {
    if ($this->auth->validateAuthentication($this->session, $this->sessionTimeout)) {
      return $this->redirect('/dashboard');
    }

    return $this->render('auth.login', [
      'csrf' => $this->session->csrfToken(),
      'error' => null,
      'githubError' => $this->session->pullFlash('github_error')
    ]);
  }

  // ///////////////////////////////////////////////////////////////////////////

  /**
   * Authenticate a submitted login.
   */
  public function login(
    ServerRequestInterface $request,
    ResponseInterface $response
  ): ResponseInterface {
    if (!$this->session->validCsrf($request)) {
      return $this->render('auth.login', [
        'csrf' => $this->session->csrfToken(),
        'error' => 'Your session has expired. Please try again.'
      ], 403);
    }

    $data = (array) $request->getParsedBody();

    $email = is_string($data['email'] ?? null) ? strtolower(trim($data['email'])) : '';
    $password = $data['password'] ?? '';

    if (!$this->limiter->allow($request, 'login', $email)) {
      return $this->render('auth.login', [
        'csrf' => $this->session->csrfToken(),
        'error' => 'Too many attempts. Please try again in 15 minutes.'
      ], 429)->withHeader('Retry-After', '900');
    }

    if (
      strlen($email) > 254 ||
      !filter_var($email, FILTER_VALIDATE_EMAIL) ||
      !PasswordPolicy::validLogin($password)
    ) {
      return $this->loginFailed();
    }

    $user = $this->auth->authenticate($email, $password);

    if ($user === false) {
      return $this->loginFailed();
    }

    $this->session->login($user);

    return $this->redirect('/dashboard');
  }

  // ///////////////////////////////////////////////////////////////////////////

  /**
   * Handle unsuccessful authentication.
   */
  private function loginFailed(): ResponseInterface {
    return $this->render('auth.login', [
      'csrf' => $this->session->csrfToken(),
      'error' => 'Unable to sign in. Check your credentials and make sure your email is verified.'
    ], 401);
  }
  
  // ///////////////////////////////////////////////////////////////////////////

  /**
   * Display the registration form.
   */
  public function registerForm(
    ServerRequestInterface $request,
    ResponseInterface $response
  ): ResponseInterface {
    return $this->render('auth.register', [
      'csrf' => $this->session->csrfToken(),
      'error' => null,
      'githubError' => $this->session->pullFlash('github_error')
    ]);
  }

  // ///////////////////////////////////////////////////////////////////////////

  /**
   * Register a new account.
   */
  public function register(
    ServerRequestInterface $request,
    ResponseInterface $response
  ): ResponseInterface {
    if (!$this->session->validCsrf($request)) {
      return $this->render('auth.register', [
        'csrf' => $this->session->csrfToken(),
        'error' => 'Your session has expired. Please try again.'
      ], 403);
    }

    $data = (array) $request->getParsedBody();

    $firstName = is_string($data['first_name'] ?? null) ? trim($data['first_name']) : '';
    $lastName = is_string($data['last_name'] ?? null) ? trim($data['last_name']) : '';
    $email = is_string($data['email'] ?? null) ? strtolower(trim($data['email'])) : '';
    $password = $data['password'] ?? '';
    $confirmation = $data['password_confirmation'] ?? '';

    if (!$this->limiter->allow($request, 'register', $email)) {
      return $this->render('auth.register', [
        'csrf' => $this->session->csrfToken(),
        'error' => 'Too many attempts. Please try again in 15 minutes.'
      ], 429)->withHeader('Retry-After', '900');
    }

    $valid = $firstName !== '' &&
      $lastName !== '' &&
      mb_strlen($firstName) <= 100 &&
      mb_strlen($lastName) <= 100 &&
      strlen($email) <= 254 &&
      filter_var($email, FILTER_VALIDATE_EMAIL) &&
      PasswordPolicy::valid($password) &&
      $password === $confirmation;

    if (!$valid) {
      return $this->render('auth.register', [
        'csrf' => $this->session->csrfToken(),
        'error' => 'Please check your details. ' . PasswordPolicy::MESSAGE
      ], 422);
    }

    if (!$this->auth->register($firstName, $lastName, $email, $password)) {
      return $this->render('auth.register', [
        'csrf' => $this->session->csrfToken(),
        'error' => 'Unable to create an account with those details.'
      ], 422);
    }

    $user = $this->auth->findByEmail($email);
    if ($user !== false) {
      $this->accountEmail->sendVerification((int) $user['ID']);
    }
    $this->session->set('temporary.verification_email', $email);
    $this->session->flash('registration_complete', true);

    return $this->redirect('/resend-verification');
  }

  // ///////////////////////////////////////////////////////////////////////////

  /**
   * End the current user's session.
   */
  public function logout(
    ServerRequestInterface $request,
    ResponseInterface $response
  ): ResponseInterface {
    if (!$this->session->validCsrf($request)) {
      return $response->withStatus(403);
    }

    $this->session->logout();

    return $this->redirect('/login');
  }

  // ///////////////////////////////////////////////////////////////////////////

  /**
   * Display the protected dashboard.
   */
  public function dashboard(
    ServerRequestInterface $request,
    ResponseInterface $response
  ): ResponseInterface {
    if (!$this->session->isAuthenticated()) {
      return $this->redirect('/login');
    }

    return $this->render('dashboard', [
      'name' => $this->session->get('user.display_name', ''),
      'csrf' => $this->session->csrfToken()
    ]);
  }

  // ///////////////////////////////////////////////////////////////////////////
}
