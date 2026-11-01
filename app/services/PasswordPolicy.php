<?php

declare(strict_types = 1);

namespace App\Services;

final class PasswordPolicy {
  public const string MESSAGE = 'Passwords must match and contain 12 to 72 bytes (some characters use more than one byte).';

  public static function valid(mixed $password): bool {
    return is_string($password)
      && strlen($password) >= 12
      && strlen($password) <= 72
      && !str_contains($password, "\0");
  }

  public static function validLogin(mixed $password): bool {
    return is_string($password)
      && $password !== ''
      && strlen($password) <= 72
      && !str_contains($password, "\0");
  }
}
