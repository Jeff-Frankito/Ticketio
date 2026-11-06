<?php

declare(strict_types = 1);

namespace App\Services;

/**
 * Validates Ticketio account passwords.
 */
final class PasswordPolicy {

  public const string MESSAGE = 'Password must contain at least 8 characters, a number and a special character.';

  /**
   * Validate a new password.
   */
  public static function valid(mixed $password): bool {
    return is_string($password)
      && preg_match('/\A.{8,}\z/us', $password) === 1
      && strlen($password) <= 72
      && preg_match('/[0-9]/', $password) === 1
      && preg_match('/[^a-zA-Z0-9\s]/', $password) === 1
      && !str_contains($password, "\0");
  }

  /**
   * Validate a password submitted during login.
   */
  public static function validLogin(mixed $password): bool {
    return is_string($password)
      && $password !== ''
      && strlen($password) <= 72
      && !str_contains($password, "\0");
  }

}
