<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Async\Fixtures;

use Drupal\Core\Async\Attribute\ReadOnlyMethod;

/**
 * A stateful service used to test fiber-local proxies.
 */
class FiberLocalCounter implements FiberLocalCounterInterface {

  /**
   * The count.
   */
  protected int $count = 0;

  /**
   * A mutable object that __clone() has to copy.
   */
  protected \ArrayObject $log;

  public function __construct() {
    $this->log = new \ArrayObject();
  }

  /**
   * {@inheritdoc}
   */
  public function increment(int $by = 1): int {
    $this->count += $by;
    $this->log[] = $by;
    return $this->count;
  }

  /**
   * {@inheritdoc}
   */
  #[ReadOnlyMethod]
  public function get(): int {
    return $this->count;
  }

  /**
   * {@inheritdoc}
   */
  public function log(): array {
    return $this->log->getArrayCopy();
  }

  /**
   * A static method, forwarded to the class by the proxy.
   */
  public static function describe(string $what): string {
    return 'counts ' . $what;
  }

  public function __clone() {
    $this->log = clone $this->log;
  }

  public function __sleep(): array {
    return ['count'];
  }

}
