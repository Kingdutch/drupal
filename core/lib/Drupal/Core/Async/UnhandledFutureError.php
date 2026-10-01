<?php

declare(strict_types=1);

namespace Drupal\Core\Async;

/**
 * Thrown on the event loop when a future failed and nothing observed it.
 *
 * Await the future, attach a catch() callback, or call ignore() when the
 * outcome genuinely does not matter.
 */
final class UnhandledFutureError extends \Error {

  /**
   * Constructs the error.
   */
  public function __construct(\Throwable $previous) {
    parent::__construct(sprintf('A future failed with %s ("%s") and nothing handled it. Await it, attach a catch() callback, or call ignore().', $previous::class, $previous->getMessage()), 0, $previous);
  }

}
