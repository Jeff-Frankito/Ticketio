<?php

declare(strict_types = 1);

namespace App\Services\Events;

use InvalidArgumentException;
use App\Services\Redis\RedisService;

final class EventPublisher {

  // /////////////////////////////////////////////////////////////////////////

  private string $channel;

  // /////////////////////////////////////////////////////////////////////////
  // Constructor
  // /////////////////////////////////////////////////////////////////////////

  public function __construct(
    private readonly RedisService $redis
  ){
    $this->channel = getenv('REDIS_EVENTS_CHANNEL')
      ?: 'ticketio:notifications';
  }

  // /////////////////////////////////////////////////////////////////////////
  // Events
  // /////////////////////////////////////////////////////////////////////////

  /**
   * Publish an event to one authenticated Ticketio user.
   */
  public function toUser(
    int $userId,
    string $type,
    array $data = []
  ): void {
    if ($userId <= 0) {
      throw new InvalidArgumentException(
        'Invalid Ticketio user ID.'
      );
    }

    if ($type === '') {
      throw new InvalidArgumentException(
        'Event type cannot be empty.'
      );
    }

    $this->redis->publish($this->channel, [
      'id' => bin2hex(random_bytes(16)),
      'type' => $type,
      'user_id' => $userId,
      'data' => $data,
      'created_at' => gmdate('c')
    ]);
  }

  // /////////////////////////////////////////////////////////////////////////

  /**
   * Publish a notification to a Ticketio user.
   *
   * This method only sends the live event.
   * Persistent notifications must be saved separately.
   */
  public function notification(
    int $userId,
    string $title,
    string $message,
    string $level = 'info'
  ): void {
    $this->toUser(
      $userId,
      'notification.created',
      [
        'title' => $title,
        'message' => $message,
        'level' => $level,
        'source' => 'Ticketio'
      ]
    );
  }

  // /////////////////////////////////////////////////////////////////////////
}
