<?php

declare(strict_types = 1);

namespace App\Services;

use RuntimeException;

/**
 * Expected GitHub account errors safe to display to the user.
 * Never wrap a database or HTTP exception message in this class.
 */
final class GitHubAuthException extends RuntimeException {}
