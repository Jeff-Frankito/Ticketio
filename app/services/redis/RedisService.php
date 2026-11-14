<?php

declare(strict_types = 1);

namespace App\Services\Redis;

use InvalidArgumentException;
use Predis\Client;

final class RedisService {

  // ///////////////////////////////////////////////////////////////////////////

  private Client $client;

  // /////////////////////////////////////////////////////////////////////////
  // Constructor
  // /////////////////////////////////////////////////////////////////////////

  public function __construct(?Client $client = null){
    $this->client = $client ?? new Client([
      'scheme' => 'tcp',
      'host' => getenv('REDIS_HOST') ?: 'redis',
      'port' => (int) (getenv('REDIS_PORT') ?: 6379),
      'timeout' => 3,
      'read_write_timeout' => 3
    ]);
  }

  // /////////////////////////////////////////////////////////////////////////
  // Connection
  // /////////////////////////////////////////////////////////////////////////

  /**
   * Returns the underlying Redis client
   */
  public function client(): Client {
    return $this->client;
  }

  // ///////////////////////////////////////////////////////////////////////////

  /**
   * Check if Redis is available
   */
  public function ping(): bool {
    return (string) $this->client->ping() === 'PONG';
  }

  // /////////////////////////////////////////////////////////////////////////
  // Cache
  // /////////////////////////////////////////////////////////////////////////

  /**
   * Retrieve a JSON encoded cache value
   */
  public function get(string $key): mixed {
    $value = $this->client->get($key);

    if($value === null)
      return null;

    return json_decode(
      $value, true, 512, JSON_THROW_ON_ERROR
    );
  }

  // ///////////////////////////////////////////////////////////////////////////

  /**
   * Store a cache value with an expiry iun seconds
   */
  public function set(
    string $key,
    mixed $value,
    int $ttl = 300
  ): void {
    if($ttl <= 0)
      throw new InvalidArgumentException('Cache TTL must be greater than zero.');

    $this->client->setex(
      $key, $ttl, json_encode($value, JSON_THROW_ON_ERROR)
    );
  }

  // ///////////////////////////////////////////////////////////////////////////

  /**
   * Remove a cached value
   */
  public function delete(string $key): void {
    $this->client->del([$key]);
  }

  // /////////////////////////////////////////////////////////////////////////
  // Messaging
  // /////////////////////////////////////////////////////////////////////////

  /**
   * Publish a structured event to a Redis channel.
   */
  public function publish(
    string $channel,
    array $payload
  ): int {
    return (int) $this->client->publish(
      $channel, json_encode($payload, JSON_THROW_ON_ERROR)
    );
  }

  // ///////////////////////////////////////////////////////////////////////////

}