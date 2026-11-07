<?php

declare(strict_types = 1);

namespace App\Models;

use App\Services\GitHubAuthException;
use PDO;
use PDOException;
use RuntimeException;
use Throwable;

/**
 * Persistent one-to-one mapping between a Ticketio user and GitHub identity.
 */
final class GitHubConnection {

  // ///////////////////////////////////////////////////////////////////////////

  public function __construct(private readonly PDO $db) {}

  // ///////////////////////////////////////////////////////////////////////////
  // Read
  // ///////////////////////////////////////////////////////////////////////////

  /**
   * Find the Ticketio account linked to a permanent GitHub user ID.
   */
  public function findUserId(int $githubId): ?int {
    $query = $this->db->prepare(
      'SELECT [USER_ID]
       FROM [dbo].[USER_GITHUB_CONNECTIONS]
       WHERE [GITHUB_USER_ID] = :github_id'
    );

    $query->execute(['github_id' => $githubId]);
    $userId = $query->fetchColumn();
    $query->closeCursor();

    return $userId === false ? null : (int) $userId;
  }

  /**
   * Find connection details for a Ticketio user's settings page.
   *
   * @return array<string, mixed>|null
   */
  public function findByUserId(int $userId): ?array {
    $query = $this->db->prepare(
      'SELECT [GITHUB_USER_ID], [GITHUB_USERNAME], [CONNECTED_AT]
       FROM [dbo].[USER_GITHUB_CONNECTIONS]
       WHERE [USER_ID] = :user_id'
    );

    $query->execute(['user_id' => $userId]);
    $connection = $query->fetch(PDO::FETCH_ASSOC);
    $query->closeCursor();

    return $connection === false ? null : $connection;
  }

  // ///////////////////////////////////////////////////////////////////////////
  // Link
  // ///////////////////////////////////////////////////////////////////////////

  /**
   * Bind GitHub to an account that already has a valid Ticketio session.
   */
  public function link(int $userId, int $githubId, string $username): void {
    $this->validateIdentity($userId, $githubId, $username);
    $this->db->beginTransaction();

    try {
      $query = $this->db->prepare(
        'SELECT [GITHUB_USER_ID]
         FROM [dbo].[USER_GITHUB_CONNECTIONS] WITH (UPDLOCK, HOLDLOCK)
         WHERE [USER_ID] = :user_id'
      );

      $query->execute(['user_id' => $userId]);
      $existing = $query->fetchColumn();
      $query->closeCursor();

      if ($existing !== false && (int) $existing !== $githubId) {
        throw new GitHubAuthException(
          'This Ticketio account is already linked to a different GitHub account.'
        );
      }

      if ($existing !== false) {
        $query = $this->db->prepare(
          'UPDATE [dbo].[USER_GITHUB_CONNECTIONS]
           SET [GITHUB_USERNAME] = :username,
               [UPDATED_AT] = SYSUTCDATETIME()
           WHERE [USER_ID] = :user_id'
        );

        $query->execute(['username' => $username, 'user_id' => $userId]);
      } else {
        $query = $this->db->prepare(
          'INSERT INTO [dbo].[USER_GITHUB_CONNECTIONS]
             ([USER_ID], [GITHUB_USER_ID], [GITHUB_USERNAME])
           VALUES (:user_id, :github_id, :username)'
        );

        $query->execute([
          'user_id' => $userId,
          'github_id' => $githubId,
          'username' => $username
        ]);
      }

      $this->db->commit();
    } catch (Throwable $exception) {
      if ($this->db->inTransaction()) {
        $this->db->rollBack();
      }

      if ($this->duplicateKey($exception)) {
        throw new GitHubAuthException(
          'This GitHub account is already connected to another Ticketio account.'
        );
      }

      throw $exception;
    }
  }

  // ///////////////////////////////////////////////////////////////////////////
  // Register
  // ///////////////////////////////////////////////////////////////////////////

  /**
   * Atomically create a new, already-email-verified Ticketio user and link GitHub.
   * Never merge an existing account based solely on matching email.
   *
   * @param array{id: int, login: string, name: ?string, email: string} $github
   * @return int Newly created Ticketio user ID
   */
  public function createAccount(array $github): int {
    $this->validateIdentity(1, $github['id'], $github['login']);
    $email = strtolower(trim($github['email']));

    if (strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
      throw new GitHubAuthException('GitHub did not supply a valid verified email address.');
    }

    $parts = preg_split('/\s+/u', trim((string) ($github['name'] ?? '')), 2) ?: [];
    // 50 Unicode codepoints will fit NVARCHAR(100) even for supplementary chars.
    $firstName = mb_substr((string) ($parts[0] ?? ''), 0, 50);
    $lastName = mb_substr((string) ($parts[1] ?? ''), 0, 50);

    if ($firstName === '') {
      $firstName = $github['login'];
    }

    if ($lastName === '') {
      $lastName = '-';
    }

    // Password login remains unavailable until the user sets a password via
    // Ticketio's existing verified-email password-reset flow.
    $passwordHash = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);
    $this->db->beginTransaction();

    try {
      $query = $this->db->prepare(
        'SELECT [ID] FROM [dbo].[USERS] WITH (UPDLOCK, HOLDLOCK)
         WHERE [EMAIL] = :email'
      );

      $query->execute(['email' => $email]);
      $registered = $query->fetchColumn() !== false;
      $query->closeCursor();

      if ($registered) {
        throw new GitHubAuthException(
          'This email already has a Ticketio account. Sign in with your password and connect GitHub in Account Settings.'
        );
      }

      $query = $this->db->prepare(
        'INSERT INTO [dbo].[USERS]
           ([FIRST_NAME], [LAST_NAME], [EMAIL], [PASSWORD_HASH], [EMAIL_VERIFIED_AT])
         OUTPUT INSERTED.[ID]
         VALUES (:first_name, :last_name, :email, :password_hash, SYSUTCDATETIME())'
      );

      $query->execute([
        'first_name' => $firstName,
        'last_name' => $lastName,
        'email' => $email,
        'password_hash' => $passwordHash
      ]);

      $userId = (int) $query->fetchColumn();
      $query->closeCursor();

      if ($userId <= 0) {
        throw new RuntimeException('Could not create GitHub user record.');
      }

      $query = $this->db->prepare(
        'INSERT INTO [dbo].[USER_DETAILS] ([USER_ID], [GITHUB_USERNAME])
         VALUES (:user_id, :username)'
      );

      $query->execute(['user_id' => $userId, 'username' => $github['login']]);

      $query = $this->db->prepare(
        'INSERT INTO [dbo].[USER_GITHUB_CONNECTIONS]
           ([USER_ID], [GITHUB_USER_ID], [GITHUB_USERNAME])
         VALUES (:user_id, :github_id, :username)'
      );

      $query->execute([
        'user_id' => $userId,
        'github_id' => $github['id'],
        'username' => $github['login']
      ]);

      $this->db->commit();

      return $userId;
    } catch (Throwable $exception) {
      if ($this->db->inTransaction()) {
        $this->db->rollBack();
      }

      if ($this->duplicateKey($exception)) {
        throw new GitHubAuthException(
          'This GitHub account or email is already registered. Sign in to Ticketio first to link an existing account.'
        );
      }

      throw $exception;
    }
  }

  // ///////////////////////////////////////////////////////////////////////////
  // Disconnect
  // ///////////////////////////////////////////////////////////////////////////

  /**
   * Remove an existing link. Controller requires current password first.
   */
  public function disconnect(int $userId): void {
    $query = $this->db->prepare(
      'DELETE FROM [dbo].[USER_GITHUB_CONNECTIONS] WHERE [USER_ID] = :user_id'
    );

    $query->execute(['user_id' => $userId]);
  }

  // ///////////////////////////////////////////////////////////////////////////
  // Helpers
  // ///////////////////////////////////////////////////////////////////////////

  private function validateIdentity(int $userId, int $githubId, string $username): void {
    if ($userId <= 0 || $githubId <= 0 ||
        preg_match('/^[A-Za-z0-9]+(?:-[A-Za-z0-9]+)*$/D', $username) !== 1 ||
        strlen($username) > 100) {
      throw new GitHubAuthException('GitHub returned an invalid identity.');
    }
  }

  private function duplicateKey(Throwable $exception): bool {
    return $exception instanceof PDOException && (
      str_contains($exception->getMessage(), '2601') ||
      str_contains($exception->getMessage(), '2627')
    );
  }

}
