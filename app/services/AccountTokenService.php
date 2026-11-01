<?php

declare(strict_types = 1);

namespace App\Services;

use PDO;
use RuntimeException;

/**
 * Manages password reset and email verification tokens.
 */
final class AccountTokenService {

  /**
   * Initialise the token service.
   */
  public function __construct(
    private readonly PDO $database
  ) {}

  /**
   * Create a single-use account token.
   */
  public function create(int $userId, string $type, int $lifetime): string {
    if (!in_array($type, ['password_reset', 'email_verification'], true)) {
      throw new RuntimeException('Unsupported account token type.');
    }

    $token = bin2hex(random_bytes(32));
    $hash = hash('sha256', $token);

    $this->database->beginTransaction();

    try {
      // Remove any earlier tokens for this user and purpose.
      $query = $this->database->prepare(
        'DELETE FROM [dbo].[USER_TOKENS] WITH (UPDLOCK, HOLDLOCK)
         WHERE [USER_ID] = ? AND [TOKEN_TYPE] = ?'
      );

      $query->execute([$userId, $type]);

      $query = $this->database->prepare(
        'INSERT INTO [dbo].[USER_TOKENS]
          ([USER_ID], [TOKEN_HASH], [TOKEN_TYPE], [EXPIRES_AT])
         VALUES (?, ?, ?, DATEADD(SECOND, CAST(? AS INT), SYSUTCDATETIME()))'
      );

      $query->execute([$userId, $hash, $type, $lifetime]);

      $this->database->commit();

      return $token;
    } catch (\Throwable $exception) {
      $this->database->rollBack();

      throw $exception;
    }
  }

  /**
   * Check whether a token exists and has not expired.
   */
  public function valid(string $token, string $type): bool {
    if (!preg_match('/\A[a-f0-9]{64}\z/D', $token)) {
      return false;
    }

    $query = $this->database->prepare(
      'SELECT TOP (1) [ID]
       FROM [dbo].[USER_TOKENS]
       WHERE [TOKEN_HASH] = ?
         AND [TOKEN_TYPE] = ?
         AND [EXPIRES_AT] > SYSUTCDATETIME()'
    );

    $query->execute([hash('sha256', $token), $type]);

    return $query->fetchColumn() !== false;
  }

  /**
   * Consume a token and perform the corresponding account update atomically.
   */
  public function consume(
    string $token,
    string $type,
    ?string $password = null
  ): bool {
    if (!preg_match('/\A[a-f0-9]{64}\z/D', $token)) {
      return false;
    }

    if (!in_array($type, ['password_reset', 'email_verification'], true)) {
      return false;
    }

    if ($type === 'password_reset' && !PasswordPolicy::valid($password)) {
      return false;
    }

    // Hash the password before entering the database transaction.
    $passwordHash = $type === 'password_reset'
      ? password_hash($password, PASSWORD_DEFAULT)
      : null;

    $this->database->beginTransaction();

    try {
      // Lock the token row so concurrent requests cannot use it twice.
      $query = $this->database->prepare(
        'SELECT TOP (1) [ID], [USER_ID]
         FROM [dbo].[USER_TOKENS] WITH (UPDLOCK, HOLDLOCK)
         WHERE [TOKEN_HASH] = ?
           AND [TOKEN_TYPE] = ?
           AND [EXPIRES_AT] > SYSUTCDATETIME()'
      );

      $query->execute([hash('sha256', $token), $type]);
      $record = $query->fetch(PDO::FETCH_ASSOC);
      $query->closeCursor();

      if ($record === false) {
        $this->database->rollBack();

        return false;
      }

      if ($type === 'password_reset') {
        $query = $this->database->prepare(
          'UPDATE [dbo].[USERS]
           SET [PASSWORD_HASH] = ?,
               [UPDATED_AT] = SYSUTCDATETIME(),
               [SESSION_VERSION] = [SESSION_VERSION] + 1
           WHERE [ID] = ? AND [IS_ACTIVE] = 1'
        );

        $query->execute([$passwordHash, $record['USER_ID']]);
      } else {
        $query = $this->database->prepare(
          'UPDATE [dbo].[USERS]
           SET [EMAIL_VERIFIED_AT] = SYSUTCDATETIME(),
               [UPDATED_AT] = SYSUTCDATETIME()
           WHERE [ID] = ? AND [IS_ACTIVE] = 1'
        );

        $query->execute([$record['USER_ID']]);
      }

      if ($query->rowCount() !== 1) {
        $this->database->rollBack();
        return false;
      }

      // Invalidate every token of this type for the account.
      $query = $this->database->prepare(
        'DELETE FROM [dbo].[USER_TOKENS]
         WHERE [USER_ID] = ? AND [TOKEN_TYPE] = ?'
      );

      $query->execute([$record['USER_ID'], $type]);

      $this->database->commit();

      return true;
    } catch (\Throwable $exception) {
      $this->database->rollBack();

      throw $exception;
    }
  }
}
