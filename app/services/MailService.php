<?php

declare(strict_types = 1);

namespace App\Services;

use InvalidArgumentException;
use PHPMailer\PHPMailer\Exception as MailException;
use PHPMailer\PHPMailer\PHPMailer;

/**
 * Handles outgoing application emails through SMTP.
 */
final class MailService {

  // ///////////////////////////////////////////////////////////////////////////

  /**
   * SMTP configuration.
   *
   * @var array<string, mixed>
   */
  private readonly array $settings;

  // ///////////////////////////////////////////////////////////////////////////

  /**
   * Initialise the mail service.
   *
   * @param array<string, mixed> $settings
   */
  public function __construct(array $settings) {
    $this->settings = $settings;
  }

  // ///////////////////////////////////////////////////////////////////////////

  /**
   * Create a configured PHPMailer instance.
   */
  private function createMailer(): PHPMailer {
    $mail = new PHPMailer(true);

    $mail->isSMTP();

    $mail->Host = $this->settings['host'];
    $mail->Port = $this->settings['port'];
    $mail->CharSet = PHPMailer::CHARSET_UTF8;

    $mail->SMTPAuth = $this->settings['auth'];
    $mail->Username = $this->settings['username'];
    $mail->Password = $this->settings['password'];

    $mail->SMTPSecure = $this->settings['encryption'];
    $mail->SMTPAutoTLS = $this->settings['encryption'] !== '';

    $mail->Timeout = 10;

    $mail->setFrom(
      $this->settings['from']['email'],
      $this->settings['from']['name']
    );

    return $mail;
  }

  // ///////////////////////////////////////////////////////////////////////////

  /**
   * Send an HTML email with an optional plain-text alternative.
   *
   * @throws MailException
   */
  public function send(
    string $to,
    string $subject,
    string $html,
    ?string $text = null
  ): void {
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
      throw new InvalidArgumentException('Invalid recipient email address.');
    }

    $mail = $this->createMailer();

    $mail->addAddress($to);
    $mail->isHTML(true);

    $mail->Subject = $subject;
    $mail->Body = $html;
    $mail->AltBody = $text ?? trim(
      html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')
    );

    $mail->send();
  }

  // ///////////////////////////////////////////////////////////////////////////
  
}
