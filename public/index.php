<?php

declare(strict_types = 1);

use DI\Container;
use Slim\Factory\AppFactory;

require dirname(__DIR__) . '/vendor/autoload.php';

$basePath = dirname(__DIR__);

// Create the dependency injection container.
$container = new Container();

// Register application settings.
(require "{$basePath}/app/settings.php")($container);

// Register application dependencies.
(require "{$basePath}/app/dependencies.php")($container);

// Register the container with Slim.
AppFactory::setContainer($container);

// Create the Slim application.
$app = AppFactory::create();

// Register application routes.
(require "{$basePath}/app/routes.php")($app);

// Register application middleware.
(require "{$basePath}/app/middleware.php")($app);

// Run the application.
$app->run();
