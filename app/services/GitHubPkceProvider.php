<?php

declare(strict_types = 1);

namespace App\Services;

use League\OAuth2\Client\Provider\Github;

/**
 * Enable S256 PKCE for League's GitHub provider.
 * The GitHub provider itself does not expose a pkceMethod constructor option.
 * @see https://oauth2-client.thephpleague.com/usage/
 */
final class GitHubPkceProvider extends Github {

  protected function getPkceMethod(): string {
    return self::PKCE_METHOD_S256;
  }

}
