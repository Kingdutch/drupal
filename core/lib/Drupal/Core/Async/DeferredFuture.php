<?php

declare(strict_types=1);

namespace Drupal\Core\Async;

use Drupal\Core\Async\Internal\FutureState;

/**
 * The producer side of a Future.
 *
 * Hand out getFuture() to consumers and keep the deferred to complete or
 * fail it, for example from an event loop callback when I/O finishes, or
 * from a batching loader when the batch returns.
 *
 * @template T
 */
final class DeferredFuture {

  /**
   * The shared state.
   *
   * @var \Drupal\Core\Async\Internal\FutureState<T>
   */
  private readonly FutureState $state;

  /**
   * The future handed to consumers.
   *
   * @var \Drupal\Core\Async\Future<T>
   */
  private readonly Future $future;

  /**
   * Constructs a deferred future.
   */
  public function __construct() {
    $this->state = new FutureState();
    $this->future = new Future($this->state);
  }

  /**
   * Returns the future consumers await.
   *
   * @return \Drupal\Core\Async\Future<T>
   *   The future.
   */
  public function getFuture(): Future {
    return $this->future;
  }

  /**
   * Completes the future with a value.
   *
   * @param T $value
   *   The value.
   */
  public function complete(mixed $value = NULL): void {
    $this->state->complete($value);
  }

  /**
   * Fails the future.
   */
  public function error(\Throwable $throwable): void {
    $this->state->error($throwable);
  }

  /**
   * Returns whether the future has completed.
   */
  public function isComplete(): bool {
    return $this->state->isComplete();
  }

}
