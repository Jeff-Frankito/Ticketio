<?php

declare(strict_types = 1);

namespace App\Services;

use GuzzleHttp\Client;
use JsonException;
use RuntimeException;

/**
 * GitHub OAuth App authorization via League; identity API via Guzzle.
 * Access and refresh tokens are not stored by Ticketio.
 */
final class GitHubOAuth {

  private readonly GitHubPkceProvider $provider;
  private readonly Client $http;
  private readonly bool $configured;

  // ///////////////////////////////////////////////////////////////////////////

  public function __construct(
    string $clientId,
    string $clientSecret,
    string $redirectUri
  ) {
    $this->configured = $clientId !== '' && $clientSecret !== '' && $redirectUri !== '';

    $this->http = new Client([
      'timeout' => 12,
      'connect_timeout' => 5,
      'http_errors' => true,
      'allow_redirects' => false,
      'verify' => true
    ]);

    $this->provider = new GitHubPkceProvider([
      'clientId' => $clientId,
      'clientSecret' => $clientSecret,
      'redirectUri' => $redirectUri
    ]);

    $this->provider->setHttpClient($this->http);
  }

  // ///////////////////////////////////////////////////////////////////////////

  /**
   * Start an authorization flow and return the URL plus single-use secrets.
   *
   * @return array{url: string, state: string, verifier: string}
   */
  public function begin(): array {
    if (!$this->configured) {
      throw new GitHubAuthException('GitHub sign-in is not configured yet. Please use your Ticketio password.');
    }
    $url = $this->provider->getAuthorizationUrl([
      'scope' => ['user:email'],
      'allow_signup' => 'true'
    ]);

    $state = $this->provider->getState();
    $verifier = $this->provider->getPkceCode();

    if (!is_string($state) || $state === '' ||
        !is_string($verifier) || strlen($verifier) < 43) {
      throw new RuntimeException('Unable to initialise GitHub authorization.');
    }

    return [
      'url' => $url,
      'state' => $state,
      'verifier' => $verifier
    ];
  }

  // ///////////////////////////////////////////////////////////////////////////

  /**
   * Exchange the code and retrieve the immutable GitHub identity.
   * Returns an ephemeral access token for fetching a verified email only when
   * a NEW Ticketio account is needed. Never persist or log it.
   *
   * @return array{id: int, login: string, name: ?string, access_token: string}
   */
  public function identity(
    string $code,
    string $verifier
  ): array {
    if ($code === '' || strlen($code) > 2048 ||
        preg_match('/^[a-zA-Z0-9._~-]{43,128}$/D', $verifier) !== 1) {
      throw new GitHubAuthException('Your GitHub sign-in request is invalid. Please try again.');
    }

    $this->provider->setPkceCode($verifier);
    $token = $this->provider->getAccessToken('authorization_code', ['code' => $code]);

    // Do not use GithubResourceOwner::getEmail() to decide verification:
    // its fallback may select an email that is not verified or primary.
    $user = $this->api('/user', $token->getToken());
    $id = $user['id'] ?? null;
    $login = $user['login'] ?? null;

    if (!is_int($id) || $id <= 0 || !is_string($login) ||
        preg_match('/^[A-Za-z0-9]+(?:-[A-Za-z0-9]+)*$/D', $login) !== 1 ||
        strlen($login) > 100) {
      throw new GitHubAuthException('GitHub could not confirm your account identity.');
    }

    $name = $user['name'] ?? null;

    return [
      'id' => $id,
      'login' => $login,
      'name' => is_string($name) && trim($name) !== '' ? trim($name) : null,
      'access_token' => $token->getToken()
    ];
  }

  // ///////////////////////////////////////////////////////////////////////////

  /**
   * A verified primary email is required only for first-time registration.
   * @throws GitHubAuthException When GitHub cannot supply one.
   */
  public function verifiedPrimaryEmail(string $token): string {
    $emails = $this->api('/user/emails', $token);

    foreach ($emails as $entry) {
      if (is_array($entry) &&
          ($entry['primary'] ?? false) === true &&
          ($entry['verified'] ?? false) === true &&
          is_string($entry['email'] ?? null) &&
          strlen($entry['email']) <= 254 &&
          filter_var($entry['email'], FILTER_VALIDATE_EMAIL)) {
        return strtolower($entry['email']);
      }
    }

    throw new GitHubAuthException(
      'A verified primary GitHub email address is required to create a Ticketio account.'
    );
  }

  // ///////////////////////////////////////////////////////////////////////////

  /**
   * Make an authenticated request to a fixed GitHub API endpoint.
   *
   * @return array<mixed>
   */
  private function api(string $endpoint, string $token): array {
    if (!in_array($endpoint, ['/user', '/user/emails'], true)) {
      throw new RuntimeException('Unsupported GitHub endpoint.');
    }

    $response = $this->http->get('https://api.github.com' . $endpoint, [
      'headers' => [
        'Accept' => 'application/vnd.github+json',
        'Authorization' => 'Bearer ' . $token,
        'User-Agent' => 'Ticketio',
        'X-GitHub-Api-Version' => '2022-11-28'
      ]
    ]);

    try {
      $data = json_decode((string) $response->getBody(), true, 32, JSON_THROW_ON_ERROR);
    } catch (JsonException $exception) {
      throw new RuntimeException('Unexpected GitHub API response.', 0, $exception);
    }

    if (!is_array($data)) {
      throw new RuntimeException('Unexpected GitHub API response.');
    }

    return $data;
  }

  // ///////////////////////////////////////////////////////////////////////////

}
