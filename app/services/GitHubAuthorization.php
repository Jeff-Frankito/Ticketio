<?php

declare(strict_types = 1);

namespace App\Services;

use InvalidArgumentException;

/** 
 * Starts OAuth with session-bound, single-use state for login or linking. 
 */
final class GitHubAuthorization {

  // ///////////////////////////////////////////////////////////////////////////

  public function __construct(
    private readonly GitHubOAuth $github,
    private readonly SessionManager $session
  ) {}

  // ///////////////////////////////////////////////////////////////////////////

  public function begin(string $mode): string {
    if (!in_array($mode, ['login', 'link'], true)) {
      throw new InvalidArgumentException('Unsupported GitHub authorization mode.');
    }

    $pending = ['mode' => $mode, 'created_at' => time()];
    if ($mode === 'link') {
      $pending['user_id'] = $this->session->requireUserId();
      $pending['session_version'] = $this->session->sessionVersion();
    }

    // Invalidate any older flow even if generating the new authorization fails.
    $this->session->remove('temporary.github_oauth');
    $authorization = $this->github->begin();
    $pending['state'] = $authorization['state'];
    $pending['verifier'] = $authorization['verifier'];
    $this->session->set('temporary.github_oauth', $pending);

    return $authorization['url'];
  }

  // ///////////////////////////////////////////////////////////////////////////
  
}
