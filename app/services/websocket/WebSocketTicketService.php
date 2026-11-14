<?php

declare(strict_types = 1);

namespace App\Services\WebSocket;

use InvalidArgumentException;
use App\Services\Redis\RedisService;

final class WebSocketTicketService {

  // /////////////////////////////////////////////////////////////////////////

  private const TTL = 60;

  // /////////////////////////////////////////////////////////////////////////
  // Constructor
  // /////////////////////////////////////////////////////////////////////////

  public function __construct(
    private readonly RedisService $redis
  ){}

  // /////////////////////////////////////////////////////////////////////////
  // Tickets
  // /////////////////////////////////////////////////////////////////////////

  /**
   * Create a short-lived, single-use WebSocket ticket.
   */
  public function issue(int $userId): string {
    if ($userId <= 0) {
      throw new InvalidArgumentException(
        'Invalid Ticketio user ID.'
      );
    }

    $ticket = bin2hex(random_bytes(32));
    $key = $this->key($ticket);

    $this->redis->client()->setex(
      $key,
      self::TTL,
      (string) $userId
    );

    return $ticket;
  }

  // /////////////////////////////////////////////////////////////////////////

  /**
   * Consume a ticket atomically.
   *
   * Returns the authenticated Ticketio user ID,
   * or null if the ticket is invalid or expired.
   */
  public function consume(string $ticket): ?int {
    if (!preg_match('/^[a-f0-9]{64}$/D', $ticket)) {
      return null;
    }

    $value = $this->redis->client()->executeRaw([
      'GETDEL',
      $this->key($ticket)
    ]);

    if ($value === null) {
      return null;
    }

    $userId = filter_var(
      $value,
      FILTER_VALIDATE_INT,
      ['options' => ['min_range' => 1]]
    );

    return $userId === false ? null : $userId;
  }

  // /////////////////////////////////////////////////////////////////////////

  /**
   * Generate the Redis key for a ticket.
   */
  private function key(string $ticket): string {
    return 'ticketio:websocket:ticket:' . hash(
      'sha256',
      $ticket
    );
  }

  // /////////////////////////////////////////////////////////////////////////
}
