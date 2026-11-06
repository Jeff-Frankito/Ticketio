<?php

declare(strict_types = 1);

namespace App\Models;

use InvalidArgumentException;
use PDO;
use RuntimeException;
use Throwable;

/**
 * User account model.
 */
final class User {

  private readonly UserDetails $details;

  // ///////////////////////////////////////////////////////////////////////////
  // Constructor
  // ///////////////////////////////////////////////////////////////////////////

  public function __construct(
    private readonly PDO $db
  ) {
    $this->details = new UserDetails($db);
  }

  // ///////////////////////////////////////////////////////////////////////////
  // Read
  // ///////////////////////////////////////////////////////////////////////////

  /**
   * Find a user by ID.
   *
   * @return array<string, mixed>|null
   */
  public function findById(int $userId): ?array {
    $query = $this->db->prepare(
      'SELECT
         [ID],
         [FIRST_NAME],
         [LAST_NAME],
         [EMAIL]
       FROM [dbo].[USERS]
       WHERE [ID] = :user_id'
    );

    $query->execute([
      'user_id' => $userId
    ]);

    $user = $query->fetch(PDO::FETCH_ASSOC);

    return $user === false ? null : $user;
  }

  /**
   * Retrieve account and profile information.
   *
   * @return array<string, mixed>|null
   */
  public function getProfile(int $userId): ?array {
    $query = $this->db->prepare(
      'SELECT
         U.[ID],
         U.[FIRST_NAME],
         U.[LAST_NAME],
         U.[EMAIL],

         D.[DISPLAY_NAME],
         D.[JOB_TITLE],
         D.[COMPANY],
         D.[BIO],
         D.[AVATAR_PATH],
         D.[LOCATION],
         D.[WEBSITE],
         D.[GITHUB_USERNAME],

         D.[TIMEZONE],
         D.[LANGUAGE],
         D.[DATE_FORMAT],
         D.[TIME_FORMAT],
         D.[WEEK_START],
         D.[SHOW_LOCAL_TIME],
         D.[THEME],
         D.[EMAIL_NOTIFICATIONS]

       FROM [dbo].[USERS] AS U
       LEFT JOIN [dbo].[USER_DETAILS] AS D
         ON D.[USER_ID] = U.[ID]
       WHERE U.[ID] = :user_id'
    );

    $query->execute([
      'user_id' => $userId
    ]);

    $profile = $query->fetch(PDO::FETCH_ASSOC);

    return $profile === false ? null : $profile;
  }

  // ///////////////////////////////////////////////////////////////////////////
  // Validation
  // ///////////////////////////////////////////////////////////////////////////

  /**
   * Validate editable profile fields.
   *
   * @param array<string, string|null> $data
   * @return list<string>
   */
  public function validateProfile(array $data): array {
    $errors = [];

    $fields = [
      'FIRST_NAME' => ['First name', 1, 100],
      'LAST_NAME' => ['Last name', 1, 100],
      'DISPLAY_NAME' => ['Display name', 0, 150],
      'JOB_TITLE' => ['Job title', 0, 150],
      'COMPANY' => ['Company', 0, 150],
      'BIO' => ['Bio', 0, 1000],
      'WEBSITE' => ['Website', 0, 500],
      'GITHUB_USERNAME' => ['GitHub username', 0, 39],
      'LOCATION' => ['Location', 0, 150]
    ];

    foreach ($fields as $column => [$label, $minimum, $maximum]) {
      $value = $data[$column] ?? '';

      if (!is_string($value)) {
        $errors[] = "{$label} must be valid text.";
        continue;
      }

      $length = $this->utf16Length($value);

      if ($length === null) {
        $errors[] = "{$label} contains invalid text.";
      } elseif ($length < $minimum) {
        $errors[] = "{$label} is required.";
      } elseif ($length > $maximum) {
        $errors[] = "{$label} must not exceed {$maximum} characters.";
      }
    }

    // Validate website URL.
    $website = $data['WEBSITE'] ?? '';

    if (is_string($website) && $website !== '') {
      $scheme = parse_url($website, PHP_URL_SCHEME);

      if (
        !filter_var($website, FILTER_VALIDATE_URL) ||
        !is_string($scheme) ||
        !in_array(strtolower($scheme), ['http', 'https'], true)
      ) {
        $errors[] = 'Please enter a valid HTTP or HTTPS website URL.';
      }
    }

    // Validate GitHub username.
    $github = $data['GITHUB_USERNAME'] ?? '';

    if (
      is_string($github) &&
      $github !== '' &&
      !preg_match('/^[A-Za-z0-9]+(?:-[A-Za-z0-9]+)*$/D', $github)
    ) {
      $errors[] = 'Please enter a valid GitHub username.';
    }

    return $errors;
  }

  /**
   * Count UTF-16 code units, matching SQL Server NVARCHAR limits.
   */
  private function utf16Length(string $value): ?int {
    $characters = preg_match_all('/./us', $value);

    if ($characters === false) {
      return null;
    }

    $supplementary = preg_match_all(
      '/[\x{10000}-\x{10FFFF}]/u',
      $value
    );

    if ($supplementary === false) {
      return null;
    }

    return $characters + $supplementary;
  }

  // ///////////////////////////////////////////////////////////////////////////
  // Update
  // ///////////////////////////////////////////////////////////////////////////

  /**
   * Validate and update a user's profile.
   *
   * @param array<string, string|null> $data
   */
  public function updateProfile(int $userId, array $data): void {
    if ($userId <= 0) {
      throw new InvalidArgumentException('Invalid user ID.');
    }

    // Normalise accepted profile fields.
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
        throw new InvalidArgumentException("Invalid {$field} value.");
      }

      $values[$field] = trim($value);
    }

    // Enforce validation even if this method is called elsewhere.
    $errors = $this->validateProfile($values);

    if ($errors !== []) {
      throw new InvalidArgumentException(implode(' ', $errors));
    }

    // Store empty optional fields as NULL.
    foreach ($fields as $field) {
      if (
        !in_array($field, ['FIRST_NAME', 'LAST_NAME'], true) &&
        $values[$field] === ''
      ) {
        $values[$field] = null;
      }
    }

    // Update both tables atomically.
    $this->db->beginTransaction();

    try {
      $query = $this->db->prepare(
        'UPDATE [dbo].[USERS]
         SET
           [FIRST_NAME] = :first_name,
           [LAST_NAME] = :last_name
         WHERE [ID] = :user_id'
      );

      $query->execute([
        'first_name' => $values['FIRST_NAME'],
        'last_name' => $values['LAST_NAME'],
        'user_id' => $userId
      ]);

      $query = $this->db->prepare(
        'SELECT [ID]
         FROM [dbo].[USERS]
         WHERE [ID] = :user_id'
      );

      $query->execute([
        'user_id' => $userId
      ]);

      $exists = $query->fetchColumn() !== false;
      $query->closeCursor();

      if (!$exists) {
        throw new RuntimeException('User not found.');
      }

      $this->details->saveProfile($userId, $values);

      $this->db->commit();
    } catch (Throwable $exception) {
      if ($this->db->inTransaction()) {
        $this->db->rollBack();
      }

      throw $exception;
    }
  }

}
