<?php

declare(strict_types = 1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Services\Redis\RedisService;
use App\Services\Events\EventPublisher;
use App\Services\WebSocket\WebSocketTicketService;

// ///////////////////////////////////////////////////////////////////////////
// Configuration
// ///////////////////////////////////////////////////////////////////////////

$command = $argv[1] ?? 'help';
$redis = new RedisService();

// ///////////////////////////////////////////////////////////////////////////
// Commands
// ///////////////////////////////////////////////////////////////////////////

try {
  switch ($command) {

    // ///////////////////////////////////////////////////////////////////////
    // Redis Connectivity
    // ///////////////////////////////////////////////////////////////////////

    case 'ping':
      echo $redis->ping() ? "PONG\n" : "Redis unavailable\n";
      break;

    // ///////////////////////////////////////////////////////////////////////
    // Cache
    // ///////////////////////////////////////////////////////////////////////

    case 'cache':
      $redis->set(
        'ticketio:test:cache',
        [
          'message' => 'Hello from Ticketio!',
          'timestamp' => gmdate('c')
        ],
        60
      );

      echo json_encode(
        $redis->get('ticketio:test:cache'),
        JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR
      ) . PHP_EOL;

      $redis->delete('ticketio:test:cache');
      break;

    // ///////////////////////////////////////////////////////////////////////
    // Authentication Ticket
    // ///////////////////////////////////////////////////////////////////////

    case 'ticket':
      $userId = filter_var(
        $argv[2] ?? null,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
      );

      if (!$userId) {
        throw new InvalidArgumentException(
          'A positive user ID is required.'
        );
      }

      $service = new WebSocketTicketService($redis);

      echo $service->issue((int) $userId) . PHP_EOL;
      break;

    // ///////////////////////////////////////////////////////////////////////
    // Publish Notification
    // ///////////////////////////////////////////////////////////////////////

    case 'notify':
      $userId = filter_var(
        $argv[2] ?? null,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
      );

      if (!$userId) {
        throw new InvalidArgumentException(
          'A positive user ID is required.'
        );
      }

      $publisher = new EventPublisher($redis);

      $publisher->notification(
        (int) $userId,
        'Ticketio Test Notification',
        'Hello from PHP, Redis and Workerman!',
        'success'
      );

      echo "Notification published to user {$userId}.\n";
      break;

    // ///////////////////////////////////////////////////////////////////////
    // Help
    // ///////////////////////////////////////////////////////////////////////

    default:
      echo "TICKETIO REAL-TIME TEST\n";
      echo "=======================\n\n";
      echo "Available commands:\n";
      echo "  ping\n";
      echo "  cache\n";
      echo "  ticket <userId>\n";
      echo "  notify <userId>\n";
      break;
  }
} catch (Throwable $exception) {
  fwrite(
    STDERR,
    'ERROR: ' . $exception->getMessage() . PHP_EOL
  );

  exit(1);
}
