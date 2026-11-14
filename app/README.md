# Application structure

PHP classes use the `App\` namespace and PSR-4 autoloading. Directory and file names must match namespace and class names exactly, including case. Composer only needs the `App\` to `app/` mapping.

- `Controllers/<Feature>/`: HTTP request handling, such as `Auth`, `Settings`, and `WebSocket`.
- `Services/<Feature>/`: application logic, such as `Auth`, or shared facilities such as `Mail`, `Session`, and `View`.
- `Models/<Feature>/`: database access grouped by feature, such as `Users`.
- `Middleware/`: shared HTTP middleware. Add feature folders when the number of classes warrants them.
- `views/`: Blade templates, with lowercase feature folders and template names.
- `dependencies.php`, `middleware.php`, `routes.php`, and `settings.php`: application setup files loaded directly by the entry point.

For example, a future `App\Controllers\Tickets\TicketController` belongs in `Controllers/Tickets/TicketController.php`. Its related services and models belong in `Services/Tickets/` and `Models/Tickets/` as needed. Create folders when they contain real classes.

After moving classes, update namespaces, imports, route handlers, dependency registrations, scripts, and tests. Run `composer dump-autoload --optimize --strict-psr` to regenerate and validate the autoloader.
