<?php

declare(strict_types = 1);

namespace App\Services;

use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

/**
 * Manages Ticketio session data, authentication state and CSRF protection.
 *
 * SessionMiddleware is responsible for starting and closing the PHP session.
 */
final class SessionManager {

  // ///////////////////////////////////////////////////////////////////////////
  // Session Data
  // ///////////////////////////////////////////////////////////////////////////

  /**
   * Retrieve a nested session value using dot notation.
   */
  public function get(string $key, mixed $default = null): mixed {
    $this->requireActive();

    $value = $_SESSION;

    foreach (explode('.', $key) as $part) {
      if (!is_array($value) || !array_key_exists($part, $value)) {
        return $default;
      }

      $value = $value[$part];
    }

    return $value;
  }

  // ///////////////////////////////////////////////////////////////////////////

  /**
   * Store a temporary session value using dot notation.
   *
   * Authentication, user, security and flash data are managed separately.
   */
  public function set(string $key, mixed $value): void {
    $this->requireTemporaryKey($key);

    $parts = explode('.', $key);
    $target = &$_SESSION;

    foreach ($parts as $part) {
      if (!isset($target[$part]) || !is_array($target[$part])) {
        $target[$part] = [];
      }

      $target = &$target[$part];
    }

    $target = $value;
  }

  // ///////////////////////////////////////////////////////////////////////////

  /**
   * Remove a temporary session value.
   */
  public function remove(string $key): void {
    $this->requireTemporaryKey($key);

    $parts = explode('.', $key);
    $last = array_pop($parts);
    $target = &$_SESSION;

    foreach ($parts as $part) {
      if (!isset($target[$part]) || !is_array($target[$part])) {
        return;
      }

      $target = &$target[$part];
    }

    unset($target[$last]);
  }

  // ///////////////////////////////////////////////////////////////////////////

  /**
   * Retrieve and remove a temporary session value.
   */
  public function pull(string $key, mixed $default = null): mixed {
    $this->requireTemporaryKey($key);

    $value = $this->get($key, $default);
    $this->remove($key);

    return $value;
  }

  // ///////////////////////////////////////////////////////////////////////////
  // Authentication
  // ///////////////////////////////////////////////////////////////////////////

  /**
   * Establish a new authenticated session.
   *
   * @param array<string, mixed> $user Authenticated database user record.
   */
  public function login(array $user): void {
    $this->requireActive();

    $userId = (int) ($user['ID'] ?? 0);
    $version = (int) ($user['SESSION_VERSION'] ?? 0);

    if ($userId <= 0 || $version <= 0) {
      throw new RuntimeException('Invalid authenticated user data.');
    }

    if (!session_regenerate_id(true)) {
      throw new RuntimeException('Unable to regenerate session ID.');
    }

    $now = time();

    $_SESSION = [
      'auth' => [
        'user_id' => $userId,
        'session_version' => $version,
        'authenticated_at' => $now,
        'last_activity' => $now
      ],

      'user' => $this->userData($user),

      'security' => [
        'csrf_token' => bin2hex(random_bytes(32))
      ],

      'flash' => [],
      'temporary' => []
    ];
  }

  // ///////////////////////////////////////////////////////////////////////////

  /**
   * Retrieve the authenticated user's ID, if available.
   */
  public function userIdOrNull(): ?int {
    $userId = $this->get('auth.user_id');

    return is_int($userId) && $userId > 0
      ? $userId
      : null;
  }

  // ///////////////////////////////////////////////////////////////////////////

  /**
   * Require an authenticated user ID.
   */
  public function requireUserId(): int {
    return $this->userIdOrNull()
      ?? throw new RuntimeException('Authentication required.');
  }

  // ///////////////////////////////////////////////////////////////////////////

  /**
   * Check whether the session contains authenticated user information.
   *
   * This does not replace AuthMiddleware's database validation.
   */
  public function isAuthenticated(): bool {
    return $this->userIdOrNull() !== null &&
      $this->sessionVersion() !== null;
  }

  // ///////////////////////////////////////////////////////////////////////////

  /**
   * Retrieve the session version.
   */
  public function sessionVersion(): ?int {
    $version = $this->get('auth.session_version');

    return is_int($version) && $version > 0
      ? $version
      : null;
  }

  // ///////////////////////////////////////////////////////////////////////////

  /**
   * Determine whether the authenticated session has expired.
   */
  public function isExpired(int $timeout): bool {
    $lastActivity = $this->get('auth.last_activity');

    return $timeout <= 0 ||
      !is_int($lastActivity) ||
      $lastActivity > time() ||
      time() - $lastActivity >= $timeout;
  }

  // ///////////////////////////////////////////////////////////////////////////

  /**
   * Update the authenticated session's activity timestamp.
   */
  public function touch(): void {
    $this->requireActive();

    if ($this->isAuthenticated()) {
      $_SESSION['auth']['last_activity'] = time();
    }
  }

  // ///////////////////////////////////////////////////////////////////////////

  /**
   * Invalidate authentication and rotate the session ID.
   *
   * Used when a session expires or is revoked.
   */
  public function invalidate(): void {
    $this->requireActive();

    $_SESSION = [];

    if (!session_regenerate_id(true)) {
      throw new RuntimeException('Unable to regenerate session ID.');
    }

    $_SESSION = [
      'security' => ['csrf_token' => bin2hex(random_bytes(32))],
      'flash' => [],
      'temporary' => []
    ];
  }

  // ///////////////////////////////////////////////////////////////////////////

  /**
   * End the PHP session and expire its browser cookie.
   */
  public function logout(): void {
    $this->requireActive();

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

    if (!session_destroy()) {
      throw new RuntimeException('Unable to destroy PHP session.');
    }
  }

  // ///////////////////////////////////////////////////////////////////////////
  // User Information
  // ///////////////////////////////////////////////////////////////////////////

  /**
   * Refresh cached user information without modifying authentication state.
   *
   * @param array<string, mixed> $user
   */
  public function updateUser(array $user): void {
    $this->requireActive();

    if (!$this->isAuthenticated()) {
      throw new RuntimeException('Authentication required.');
    }

    if (
      !isset($user['ID']) ||
      (int) $user['ID'] !== $this->requireUserId()
    ) {
      throw new RuntimeException('Invalid session user.');
    }

    $_SESSION['user'] = $this->userData($user);
  }

  // ///////////////////////////////////////////////////////////////////////////

  /**
   * Build the small set of user information cached in the session.
   *
   * @param array<string, mixed> $user
   * @return array<string, string|null>
   */
  private function userData(array $user): array {
    $firstName = (string) ($user['FIRST_NAME'] ?? '');
    $lastName = (string) ($user['LAST_NAME'] ?? '');

    $displayName = trim((string) ($user['DISPLAY_NAME'] ?? ''));

    if ($displayName === '') {
      $displayName = trim("{$firstName} {$lastName}");
    }

    $avatarPath = $user['AVATAR_PATH'] ?? null;

    return [
      'first_name' => $firstName,
      'last_name' => $lastName,
      'display_name' => $displayName,
      'avatar_path' => is_string($avatarPath) && $avatarPath !== ''
        ? $avatarPath
        : null
    ];
  }

  // ///////////////////////////////////////////////////////////////////////////
  // CSRF
  // ///////////////////////////////////////////////////////////////////////////

  /**
   * Generate or retrieve the current CSRF token.
   */
  public function csrfToken(): string {
    $this->requireActive();

    $token = $this->get('security.csrf_token');

    if (!is_string($token) || $token === '') {
      $token = bin2hex(random_bytes(32));
      $_SESSION['security']['csrf_token'] = $token;
    }

    return $token;
  }

  // ///////////////////////////////////////////////////////////////////////////

  /**
   * Validate a submitted CSRF token.
   */
  public function validCsrf(ServerRequestInterface $request): bool {
    $data = $request->getParsedBody();

    if (!is_array($data)) {
      return false;
    }

    $submitted = $data['_token'] ?? null;
    $expected = $this->get('security.csrf_token');

    return is_string($submitted) &&
      is_string($expected) &&
      $expected !== '' &&
      hash_equals($expected, $submitted);
  }

  // ///////////////////////////////////////////////////////////////////////////
  // Flash Messages
  // ///////////////////////////////////////////////////////////////////////////

  /**
   * Store a message to be retrieved once.
   */
  public function flash(string $key, mixed $value): void {
    $this->requireActive();
    $this->requireSimpleKey($key);

    $_SESSION['flash'][$key] = $value;
  }

  // ///////////////////////////////////////////////////////////////////////////

  /**
   * Retrieve and remove a flash message.
   */
  public function pullFlash(string $key, mixed $default = null): mixed {
    $this->requireActive();
    $this->requireSimpleKey($key);

    $value = $_SESSION['flash'][$key] ?? $default;

    unset($_SESSION['flash'][$key]);

    return $value;
  }

  // ///////////////////////////////////////////////////////////////////////////
  // Internal Helpers
  // ///////////////////////////////////////////////////////////////////////////

  /**
   * Ensure SessionMiddleware has started the PHP session.
   */
  private function requireActive(): void {
    if (session_status() !== PHP_SESSION_ACTIVE) {
      throw new RuntimeException('PHP session is not active.');
    }
  }

  // ///////////////////////////////////////////////////////////////////////////

  /**
   * Restrict generic writes to the temporary namespace.
   */
  private function requireTemporaryKey(string $key): void {
    $this->requireActive();

    if (
      !str_starts_with($key, 'temporary.') ||
      $key === 'temporary.'
    ) {
      throw new RuntimeException('Session key must belong to temporary.');
    }

    foreach (explode('.', $key) as $part) {
      $this->requireSimpleKey($part);
    }
  }

  // ///////////////////////////////////////////////////////////////////////////

  /**
   * Validate a single session key component.
   */
  private function requireSimpleKey(string $key): void {
    if (
      $key === '' ||
      preg_match('/^[a-zA-Z0-9_]+$/D', $key) !== 1
    ) {
      throw new RuntimeException('Invalid session key.');
    }
  }

  // ///////////////////////////////////////////////////////////////////////////
  
}
