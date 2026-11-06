<?php

declare(strict_types = 1);

namespace App\Services;

use PDO;

/**
 * Sends Ticketio account-related emails.
 */
final class AccountEmailService {

  // ///////////////////////////////////////////////////////////////////////////

  /**
   * Initialise account email services.
   *
   * @param array<string, mixed> $settings
   */
  public function __construct(
    private readonly PDO $database,
    private readonly AccountTokenService $tokens,
    private readonly MailService $mail,
    private readonly BladeService $blade,
    private readonly array $settings
  ) {}

  // ///////////////////////////////////////////////////////////////////////////

  /**
   * Request a password reset without revealing account existence.
   */
  public function requestPasswordReset(string $email): void {
    $query = $this->database->prepare(
      'SELECT [ID], [FIRST_NAME], [EMAIL]
       FROM [dbo].[USERS]
       WHERE [EMAIL] = ? AND [IS_ACTIVE] = 1'
    );

    $query->execute([$email]);
    $user = $query->fetch(PDO::FETCH_ASSOC);

    if ($user === false) {
      return;
    }

    $token = $this->tokens->create(
      (int) $user['ID'],
      'password_reset',
      $this->settings['auth']['password_reset_lifetime']
    );

    $url = $this->settings['url']['base']
      . '/reset-password?token=' . urlencode($token);

    $this->sendSafely(
      $user['EMAIL'],
      'Reset your Ticketio password',
      $this->blade->run('emails.password-reset', [
        'name' => $user['FIRST_NAME'],
        'url' => $url
      ]),
      "Reset your Ticketio password: {$url}"
    );
  }

  // ///////////////////////////////////////////////////////////////////////////

  /**
   * Send an account verification email.
   */
  public function sendVerification(int $userId): void {
    $query = $this->database->prepare(
      'SELECT [ID], [FIRST_NAME], [EMAIL]
       FROM [dbo].[USERS]
       WHERE [ID] = ?
         AND [EMAIL_VERIFIED_AT] IS NULL AND [IS_ACTIVE] = 1'
    );

    $query->execute([$userId]);
    $user = $query->fetch(PDO::FETCH_ASSOC);

    if ($user === false) {
      return;
    }

    $token = $this->tokens->create(
      (int) $user['ID'],
      'email_verification',
      $this->settings['auth']['email_verification_lifetime']
    );

    $url = $this->settings['url']['base']
      . '/verify-email?token=' . urlencode($token);

    $this->sendSafely(
      $user['EMAIL'],
      'Verify your Ticketio email address',
      $this->blade->run('emails.verify-email', [
        'name' => $user['FIRST_NAME'],
        'url' => $url
      ]),
      "Verify your Ticketio account: {$url}"
    );
  }

  // ///////////////////////////////////////////////////////////////////////////

  public function requestVerification(string $email): void {
    $query = $this->database->prepare('SELECT [ID] FROM [dbo].[USERS] WHERE [EMAIL] = ? AND [IS_ACTIVE] = 1 AND [EMAIL_VERIFIED_AT] IS NULL');
    $query->execute([$email]);
    $id = $query->fetchColumn();
    $query->closeCursor();
    if ($id !== false) { $this->sendVerification((int) $id); }
  }

  // ///////////////////////////////////////////////////////////////////////////

  private function sendSafely(string $to, string $subject, string $html, string $text): void {
    try {
      $this->mail->send($to, $subject, $html, $text);
    } catch (\PHPMailer\PHPMailer\Exception $exception) {
      // Keep SMTP diagnostics, addresses, credentials and token URLs out of responses/logs.
      error_log('Account email delivery failed. Check SMTP configuration and availability.');
    }
  }

  // ///////////////////////////////////////////////////////////////////////////
}
