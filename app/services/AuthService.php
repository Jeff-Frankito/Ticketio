<?php

declare(strict_types = 1);

namespace App\Services;

use PDO;
use PDOException;

/**
 * Handles user authentication and account registration.
 */
final class AuthService {

  /**
   * Create the authentication service.
   */
  public function __construct(
    private readonly PDO $database
  ) {}

  /**
   * Find an active user by their email address.
   *
   * @return array<string, mixed>|false
   */
  public function findByEmail(string $email): array|false {
    $query = $this->database->prepare(
      'SELECT
        [ID],
        [FIRST_NAME],
        [LAST_NAME],
        [EMAIL],
        [PASSWORD_HASH],
        [IS_ACTIVE],
        [EMAIL_VERIFIED_AT],
        [SESSION_VERSION]
      FROM [dbo].[USERS]
      WHERE [EMAIL] = :email'
    );

    $query->execute(['email' => $email]);

    return $query->fetch(PDO::FETCH_ASSOC);
  }

  /**
   * Register a user with a securely hashed password.
   *
   * Returns false if the email is already registered.
   */
  public function register(
    string $firstName,
    string $lastName,
    string $email,
    string $password
  ): bool {
    if (!PasswordPolicy::valid($password)) { return false; }
    if ($this->findByEmail($email) !== false) {
      return false;
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);

    $query = $this->database->prepare(
      'INSERT INTO [dbo].[USERS] (
        [FIRST_NAME],
        [LAST_NAME],
        [EMAIL],
        [PASSWORD_HASH]
      ) OUTPUT INSERTED.[ID] VALUES (
        :first_name,
        :last_name,
        :email,
        :password_hash
      )'
    );

    try {
      $this->database->beginTransaction();
      $query->execute([
        'first_name' => $firstName,
        'last_name' => $lastName,
        'email' => $email,
        'password_hash' => $hash
      ]);
      $userId = (int) $query->fetchColumn();
      $query->closeCursor();

      $details = $this->database->prepare(
        'INSERT INTO [dbo].[USER_DETAILS] ([USER_ID]) VALUES (:user_id)'
      );
      $details->execute(['user_id' => $userId]);
      $this->database->commit();

      return true;
    } catch (\Throwable $exception) {
      if ($this->database->inTransaction()) {
        $this->database->rollBack();
      }

      // SQL Server duplicate key / unique constraint violation.
      if ($exception instanceof PDOException &&
          (str_contains($exception->getMessage(), '2601') ||
           str_contains($exception->getMessage(), '2627'))) {
        return false;
      }

      throw $exception;
    }
  }

  /**
   * Authenticate an active account using email and password.
   *
   * @return array<string, mixed>|false
   */
  public function authenticate(string $email, string $password): array|false {
    if (!PasswordPolicy::validLogin($password)) { return false; }
    $user = $this->findByEmail($email);

    // Keep a password-hash check for unknown accounts as well.
    $hash = $user === false
      ? '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi'
      : $user['PASSWORD_HASH'];
    $passwordMatches = password_verify($password, $hash);

    if (
      $user === false ||
      !$passwordMatches ||
      !(bool) $user['IS_ACTIVE'] ||
      $user['EMAIL_VERIFIED_AT'] === null
    ) {
      return false;
    }

    if (password_needs_rehash($user['PASSWORD_HASH'], PASSWORD_DEFAULT)) {
      $newHash = password_hash($password, PASSWORD_DEFAULT);

      $query = $this->database->prepare(
        'UPDATE [dbo].[USERS]
         SET [PASSWORD_HASH] = :hash,
             [UPDATED_AT] = SYSUTCDATETIME()
         WHERE [ID] = :id AND [PASSWORD_HASH] = :old_hash AND [SESSION_VERSION] = :version'
      );

      $query->execute([
        'hash' => $newHash,
        'id' => $user['ID'],
        'old_hash' => $user['PASSWORD_HASH'],
        'version' => $user['SESSION_VERSION']
      ]);
      if ($query->rowCount() !== 1) { return false; }
    }

    $query = $this->database->prepare(
      'UPDATE [dbo].[USERS]
       SET [LAST_LOGIN_AT] = SYSUTCDATETIME()
       WHERE [ID] = :id'
    );

    $query->execute(['id' => $user['ID']]);

    unset($user['PASSWORD_HASH']);

    return $user;
  }

  public function validSession(int $userId, int $version): bool {
    $query = $this->database->prepare(
      'SELECT [ID] FROM [dbo].[USERS]
       WHERE [ID] = ? AND [SESSION_VERSION] = ? AND [IS_ACTIVE] = 1 AND [EMAIL_VERIFIED_AT] IS NOT NULL'
    );
    $query->execute([$userId, $version]);
    return $query->fetchColumn() !== false;
  }
}
