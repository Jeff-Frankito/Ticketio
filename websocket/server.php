<?php

declare(strict_types = 1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Services\Redis\RedisService;
use App\Services\WebSocket\WebSocketTicketService;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request;
use Workerman\Redis\Client as AsyncRedisClient;
use Workerman\Timer;
use Workerman\Worker;

// ///////////////////////////////////////////////////////////////////////////
// Configuration
// ///////////////////////////////////////////////////////////////////////////

$redisHost = getenv('REDIS_HOST') ?: 'redis';
$redisPort = (int) (getenv('REDIS_PORT') ?: 6379);

$channel = getenv('REDIS_EVENTS_CHANNEL')
  ?: 'ticketio:notifications';

$appUrl = rtrim(
  getenv('APP_URL') ?: 'http://127.0.0.1:8080',
  '/'
);

$redis = new RedisService();
$tickets = new WebSocketTicketService($redis);

// ///////////////////////////////////////////////////////////////////////////
// WebSocket Server
// ///////////////////////////////////////////////////////////////////////////

$worker = new Worker('websocket://0.0.0.0:8081');

$worker->name = 'TicketioWebSocket';
$worker->count = 1;

// Connections indexed by Ticketio user ID.
$connections = [];

// Connection ID => authenticated user ID.
$connectionUsers = [];

// ///////////////////////////////////////////////////////////////////////////
// Connection Validation
// ///////////////////////////////////////////////////////////////////////////

$worker->onWebSocketConnect = function (
  TcpConnection $connection,
  Request $request
) use ($appUrl): void {
  $origin = rtrim(
    (string) $request->header('origin'),
    '/'
  );

  if ($origin !== $appUrl) {
    $connection->close();
  }
};

// ///////////////////////////////////////////////////////////////////////////
// Authentication Timeout
// ///////////////////////////////////////////////////////////////////////////

$worker->onConnect = function (
  TcpConnection $connection
) use (&$connectionUsers): void {
  Timer::add(10, function () use (
    $connection,
    &$connectionUsers
  ): void {
    if (!isset($connectionUsers[$connection->id])) {
      $connection->close();
    }
  }, [], false);
};

// ///////////////////////////////////////////////////////////////////////////
// Redis Subscription
// ///////////////////////////////////////////////////////////////////////////

$worker->onWorkerStart = function () use (
  $redisHost,
  $redisPort,
  $channel,
  &$connections
): void {
  $subscriber = new AsyncRedisClient(
    "redis://{$redisHost}:{$redisPort}"
  );

  $subscriber->subscribe(
    [$channel],
    function (
      string $channel,
      string $message
    ) use (&$connections): void {
      try {
        $payload = json_decode(
          $message,
          true,
          512,
          JSON_THROW_ON_ERROR
        );

        if (!is_array($payload)) {
          return;
        }

        $userId = $payload['user_id'] ?? null;

        if (!is_int($userId) || $userId <= 0) {
          return;
        }

        foreach ($connections[$userId] ?? [] as $connection) {
          $connection->send($message);
        }
      } catch (\Throwable $exception) {
        echo 'WebSocket event error: ';
        echo $exception->getMessage() . PHP_EOL;
      }
    }
  );

  echo "Ticketio WebSocket server started.\n";
};

// ///////////////////////////////////////////////////////////////////////////
// Incoming Browser Messages
// ///////////////////////////////////////////////////////////////////////////

$worker->onMessage = function (
  TcpConnection $connection,
  string $message
) use (
  $tickets,
  &$connections,
  &$connectionUsers
): void {
  // Never process arbitrary client messages before authentication.
  if (strlen($message) > 4096) {
    $connection->close();
    return;
  }

  try {
    $payload = json_decode(
      $message,
      true,
      512,
      JSON_THROW_ON_ERROR
    );
  } catch (\JsonException) {
    $connection->close();
    return;
  }

  if (!is_array($payload)) {
    $connection->close();
    return;
  }

  $connectionId = $connection->id;

  // /////////////////////////////////////////////////////////////////////////
  // Authenticate
  // /////////////////////////////////////////////////////////////////////////

  if (!isset($connectionUsers[$connectionId])) {
    if (($payload['type'] ?? null) !== 'authenticate') {
      $connection->close();
      return;
    }

    $ticket = $payload['ticket'] ?? null;

    if (!is_string($ticket)) {
      $connection->close();
      return;
    }

    try {
      $userId = $tickets->consume($ticket);
    } catch (\Throwable $exception) {
      echo 'Authentication backend error: ';
      echo $exception->getMessage() . PHP_EOL;

      $connection->close();
      return;
    }

    if ($userId === null) {
      $connection->close();
      return;
    }

    $connectionUsers[$connectionId] = $userId;
    $connections[$userId][$connectionId] = $connection;

    $connection->send(json_encode([
      'type' => 'connection.authenticated',
      'user_id' => $userId
    ], JSON_THROW_ON_ERROR));

    return;
  }

  // /////////////////////////////////////////////////////////////////////////
  // Authenticated Client Messages
  // /////////////////////////////////////////////////////////////////////////

  if (($payload['type'] ?? null) === 'ping') {
    $connection->send(json_encode([
      'type' => 'pong'
    ], JSON_THROW_ON_ERROR));

    return;
  }

  // All other client commands are deliberately rejected.
  $connection->send(json_encode([
    'type' => 'error',
    'message' => 'Unsupported event.'
  ], JSON_THROW_ON_ERROR));
};

// ///////////////////////////////////////////////////////////////////////////
// Disconnection
// ///////////////////////////////////////////////////////////////////////////

$worker->onClose = function (
  TcpConnection $connection
) use (
  &$connections,
  &$connectionUsers
): void {
  $connectionId = $connection->id;
  $userId = $connectionUsers[$connectionId] ?? null;

  if ($userId === null) {
    return;
  }

  unset($connections[$userId][$connectionId]);
  unset($connectionUsers[$connectionId]);

  if (empty($connections[$userId])) {
    unset($connections[$userId]);
  }
};

// ///////////////////////////////////////////////////////////////////////////
// Run
// ///////////////////////////////////////////////////////////////////////////

Worker::runAll();
