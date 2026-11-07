<?php

declare(strict_types = 1);

namespace App\Services;

use PDO;
use Psr\Http\Message\ServerRequestInterface;

/** Database-backed fixed windows shared across PHP workers and browser sessions. */
final class AuthRateLimiter {

  // ///////////////////////////////////////////////////////////////////////////

  public function __construct(
    private readonly PDO $database
  ) {}

  // ///////////////////////////////////////////////////////////////////////////

  public function allow(ServerRequestInterface $request, string $action, string $email = ''): bool {
    // Bound retention without a separate scheduled cleanup job.
    $this->database->exec('DELETE TOP (100) FROM [dbo].[AUTH_RATE_LIMITS] WHERE [EXPIRES_AT] < DATEADD(DAY, -1, SYSUTCDATETIME())');

    // Do not trust client-supplied forwarding headers.
    $ip = (string) ($request->getServerParams()['REMOTE_ADDR'] ?? 'unknown');

    $limits = match ($action) {
      'login' => [100, 10],
      'register' => [10, 3],
      default => [20, 3]
    };

    $buckets = [hash('sha256', "$action:ip:$ip") => $limits[0]];

    if ($email !== '') {
      $buckets[hash('sha256', "$action:email:" . strtolower(trim($email)))] = $limits[1];
    }

    ksort($buckets);

    $this->database->beginTransaction();

    try {
      foreach ($buckets as $key => $limit) {
        $query = $this->database->prepare(
          'SELECT [ATTEMPTS], CASE WHEN [EXPIRES_AT] > SYSUTCDATETIME() THEN 1 ELSE 0 END AS [CURRENT_WINDOW]
           FROM [dbo].[AUTH_RATE_LIMITS] WITH (UPDLOCK, HOLDLOCK) WHERE [BUCKET_HASH] = ?'
        );

        $query->execute([$key]);
        $record = $query->fetch();
        $query->closeCursor();

        if ($record !== false && (int) $record['CURRENT_WINDOW'] === 1 && (int) $record['ATTEMPTS'] >= $limit) {
          $this->database->rollBack();
          return false;
        }

        if ($record === false) {
          $query = $this->database->prepare('INSERT INTO [dbo].[AUTH_RATE_LIMITS] ([BUCKET_HASH], [ATTEMPTS], [EXPIRES_AT]) VALUES (?, 1, DATEADD(SECOND, 900, SYSUTCDATETIME()))');
        } elseif ((int) $record['CURRENT_WINDOW'] !== 1) {
          $query = $this->database->prepare('UPDATE [dbo].[AUTH_RATE_LIMITS] SET [ATTEMPTS] = 1, [EXPIRES_AT] = DATEADD(SECOND, 900, SYSUTCDATETIME()) WHERE [BUCKET_HASH] = ?');
        } else {
          $query = $this->database->prepare('UPDATE [dbo].[AUTH_RATE_LIMITS] SET [ATTEMPTS] = [ATTEMPTS] + 1 WHERE [BUCKET_HASH] = ?');
        }

        $query->execute([$key]);
      }

      $this->database->commit();
      return true;
    } catch (\Throwable $exception) {
      if ($this->database->inTransaction()){ 
        $this->database->rollBack(); 
      }
      throw $exception;
    }
  }

  // ///////////////////////////////////////////////////////////////////////////
  
}
