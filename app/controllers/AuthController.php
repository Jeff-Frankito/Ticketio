<?php

declare(strict_types = 1);

namespace App\Controllers;

use App\Services\AuthService;
use App\Services\BladeService;
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

  /**
   * Authentication service.
   */
  private readonly AuthService $auth;

  /**
   * BladeOne template service.
   */
  private readonly BladeService $blade;
  private readonly AccountEmailService $accountEmail;
  private readonly AuthRateLimiter $limiter;
  private readonly int $sessionTimeout;

  /**
   * Initialise the authentication controller.
   */
  public function __construct(Container $container) {
    $this->auth = $container->get('auth');
    $this->blade = $container->get('blade');
    $this->accountEmail = $container->get('accountEmail');
    $this->limiter = $container->get(AuthRateLimiter::class);
    $this->sessionTimeout = $container->get('settings')['auth']['session_timeout'];
  }

  /**
   * Generate or retrieve the current CSRF token.
   */
  private function csrfToken(): string {
    if (!isset($_SESSION['csrf_token'])) {
      $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
  }

  /**
   * Validate the submitted CSRF token.
   */
  private function validCsrf(ServerRequestInterface $request): bool {
    $data = (array) $request->getParsedBody();
    $token = $data['_token'] ?? null;

    return is_string($token) &&
      hash_equals($this->csrfToken(), $token);
  }

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

  /**
   * Redirect with HTTP 303 after a form submission.
   */
  private function redirect(string $path): ResponseInterface {
    return (new Response(303))->withHeader('Location', $path)->withHeader('Cache-Control', 'no-store');
  }

  /**
   * Display the login form.
   */
  public function loginForm(
    ServerRequestInterface $request,
    ResponseInterface $response
  ): ResponseInterface {
    if (is_int($_SESSION['user_id'] ?? null) &&
        is_int($_SESSION['session_version'] ?? null) &&
        time() - (int) ($_SESSION['last_activity'] ?? 0) < $this->sessionTimeout &&
        $this->auth->validSession($_SESSION['user_id'], $_SESSION['session_version'])) {
      return $this->redirect('/dashboard');
    }

    return $this->render('auth.login', [
      'csrf' => $this->csrfToken(),
      'error' => null
    ]);
  }

  /**
   * Authenticate a submitted login.
   */
  public function login(
    ServerRequestInterface $request,
    ResponseInterface $response
  ): ResponseInterface {
    if (!$this->validCsrf($request)) {
      return $this->render('auth.login', [
        'csrf' => $this->csrfToken(),
        'error' => 'Your session has expired. Please try again.'
      ], 403);
    }

    $data = (array) $request->getParsedBody();

    $email = is_string($data['email'] ?? null) ? strtolower(trim($data['email'])) : '';
    $password = $data['password'] ?? '';

    if (!$this->limiter->allow($request, 'login', $email)) {
      return $this->render('auth.login', [
        'csrf' => $this->csrfToken(),
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

    session_regenerate_id(true);

    $_SESSION = [
      'user_id' => (int) $user['ID'],
      'user_name' => $user['FIRST_NAME'],
      'session_version' => (int) $user['SESSION_VERSION'],
      'csrf_token' => bin2hex(random_bytes(32)),
      'authenticated_at' => time(),
      'last_activity' => time()
    ];

    return $this->redirect('/dashboard');
  }

  /**
   * Handle unsuccessful authentication.
   */
  private function loginFailed(): ResponseInterface {
    return $this->render('auth.login', [
      'csrf' => $this->csrfToken(),
      'error' => 'Unable to sign in. Check your credentials and make sure your email is verified.'
    ], 401);
  }

  /**
   * Display the registration form.
   */
  public function registerForm(
    ServerRequestInterface $request,
    ResponseInterface $response
  ): ResponseInterface {
    return $this->render('auth.register', [
      'csrf' => $this->csrfToken(),
      'error' => null
    ]);
  }

  /**
   * Register a new account.
   */
  public function register(
    ServerRequestInterface $request,
    ResponseInterface $response
  ): ResponseInterface {
    if (!$this->validCsrf($request)) {
      return $this->render('auth.register', [
        'csrf' => $this->csrfToken(),
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
        'csrf' => $this->csrfToken(),
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
        'csrf' => $this->csrfToken(),
        'error' => 'Please check your details. ' . PasswordPolicy::MESSAGE
      ], 422);
    }

    if (!$this->auth->register($firstName, $lastName, $email, $password)) {
      return $this->render('auth.register', [
        'csrf' => $this->csrfToken(),
        'error' => 'Unable to create an account with those details.'
      ], 422);
    }

    $user = $this->auth->findByEmail($email);
    if ($user !== false) {
      $this->accountEmail->sendVerification((int) $user['ID']);
    }
    return $this->redirect('/login?registered=1');
  }

  /**
   * End the current user's session.
   */
  public function logout(
    ServerRequestInterface $request,
    ResponseInterface $response
  ): ResponseInterface {
    if (!$this->validCsrf($request)) {
      return $response->withStatus(403);
    }

    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
      $params = session_get_cookie_params();

      setcookie(session_name(), '', [
        'expires' => time() - 3600,
        'path' => $params['path'],
        'domain' => $params['domain'],
        'secure' => $params['secure'],
        'httponly' => $params['httponly'],
        'samesite' => $params['samesite']
      ]);
    }

    session_destroy();

    return $this->redirect('/login');
  }

  /**
   * Display the protected dashboard.
   */
  public function dashboard(
    ServerRequestInterface $request,
    ResponseInterface $response
  ): ResponseInterface {
    if (!isset($_SESSION['user_id'])) {
      return $this->redirect('/login');
    }

    return $this->render('dashboard', [
      'name' => $_SESSION['user_name'],
      'csrf' => $this->csrfToken()
    ]);
  }
}
