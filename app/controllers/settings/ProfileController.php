<?php

declare(strict_types = 1);

namespace App\Controllers\Settings;

use App\Models\User;
use App\Services\BladeService;
use DI\Container;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

/**
 * Handles user profile settings.
 */
final class ProfileController {

  private readonly User $users;
  private readonly BladeService $blade;

  // ///////////////////////////////////////////////////////////////////////////
  // Constructor
  // ///////////////////////////////////////////////////////////////////////////

  public function __construct(Container $container) {
    $this->users = $container->get(User::class);
    $this->blade = $container->get('blade');
  }

  // ///////////////////////////////////////////////////////////////////////////
  // GET /settings/profile
  // ///////////////////////////////////////////////////////////////////////////

  /**
   * Display the authenticated user's profile.
   */
  public function index(
    ServerRequestInterface $request,
    ResponseInterface $response
  ): ResponseInterface {
    $profile = $this->users->getProfile($this->userId());

    if ($profile === null) {
      throw new RuntimeException('User profile not found.');
    }

    $success = (bool) ($_SESSION['profile_saved'] ?? false);
    unset($_SESSION['profile_saved']);

    return $this->render($response, $profile, [], $success);
  }

  // ///////////////////////////////////////////////////////////////////////////
  // POST /settings/profile
  // ///////////////////////////////////////////////////////////////////////////

  /**
   * Validate the request and update the authenticated user's profile.
   */
  public function update(
    ServerRequestInterface $request,
    ResponseInterface $response
  ): ResponseInterface {
    $userId = $this->userId();
    $data = $request->getParsedBody();

    if (!is_array($data)) {
      return $response->withStatus(400);
    }

    // Validate CSRF token.
    $csrf = $_SESSION['csrf_token'] ?? null;
    $submittedToken = $data['_token'] ?? null;

    if (
      !is_string($csrf) ||
      $csrf === '' ||
      !is_string($submittedToken) ||
      !hash_equals($csrf, $submittedToken)
    ) {
      return $response->withStatus(403);
    }

    // Only accept editable profile fields.
    $fields = [
      'FIRST_NAME',
      'LAST_NAME',
      'DISPLAY_NAME',
      'JOB_TITLE',
      'COMPANY',
      'BIO',
      'WEBSITE',
      'GITHUB_USERNAME',
      'LOCATION'
    ];

    $values = [];

    foreach ($fields as $field) {
      $value = $data[$field] ?? '';

      if (!is_string($value)) {
        return $response->withStatus(400);
      }

      $values[$field] = trim($value);
    }

    // Apply the model's validation rules.
    $errors = $this->users->validateProfile($values);

    if ($errors !== []) {
      $profile = $this->users->getProfile($userId);

      if ($profile === null) {
        throw new RuntimeException('User profile not found.');
      }

      return $this->render(
        $response->withStatus(422),
        array_replace($profile, $values),
        $errors
      );
    }

    // Save the validated profile.
    $this->users->updateProfile($userId, $values);

    $_SESSION['profile_saved'] = true;

    return $response
      ->withHeader('Location', '/settings/profile')
      ->withStatus(303);
  }

  // ///////////////////////////////////////////////////////////////////////////
  // Helpers
  // ///////////////////////////////////////////////////////////////////////////

  /**
   * Retrieve the authenticated user ID.
   */
  private function userId(): int {
    $userId = (int) ($_SESSION['user_id'] ?? 0);

    if ($userId <= 0) {
      throw new RuntimeException('Authentication required.');
    }

    return $userId;
  }

  /**
   * Render the profile settings page.
   *
   * @param array<string, mixed> $profile
   * @param list<string> $errors
   */
  private function render(
    ResponseInterface $response,
    array $profile,
    array $errors = [],
    bool $success = false
  ): ResponseInterface {
    $response->getBody()->write(
      $this->blade->run('settings.profile', [
        'settingsPage' => 'profile',
        'profile' => $profile,
        'csrf' => $_SESSION['csrf_token'] ?? '',
        'success' => $success,
        'errors' => $errors
      ])
    );

    return $response
      ->withHeader('Content-Type', 'text/html; charset=UTF-8')
      ->withHeader('Cache-Control', 'no-store');
  }

}
