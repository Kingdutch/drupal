<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Async\Fixtures;

/**
 * Interface for the fiber-local proxy test fixture.
 */
interface FiberLocalCounterInterface {

  /**
   * Adds to the count and returns the new count.
   */
  public function increment(int $by = 1): int;

  /**
   * Returns the count without changing anything.
   */
  public function get(): int;

  /**
   * Returns the increments made on this instance.
   */
  public function log(): array;

}
