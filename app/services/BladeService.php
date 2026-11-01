<?php

declare(strict_types = 1);

namespace App\Services;

use eftec\bladeone\BladeOne;

/**
 * Ticketio BladeOne template engine.
 *
 * Provides application-specific Blade directives.
 */
final class BladeService extends BladeOne {

  /**
   * Initialise BladeOne with the application's view and cache paths.
   */
  public function __construct(string $viewsPath, string $cachePath) {
    parent::__construct(
      $viewsPath,
      $cachePath,
      self::MODE_AUTO
    );
  }

  
  /**
   * Begin an IDE-only block excluded from rendered HTML.
   */
  protected function compileDummy(?string $expression = null): string {
    return '<?php /*';
  }

  /**
   * End an IDE-only block excluded from rendered HTML.
   */
  protected function compileEnddummy(?string $expression = null): string {
    return '*/ ?>';
  }

}
