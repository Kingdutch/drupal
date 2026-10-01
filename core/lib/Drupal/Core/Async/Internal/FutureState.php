<?php

declare(strict_types=1);

namespace Drupal\Core\Async\Internal;

use Drupal\Core\Async\UnhandledFutureError;
use Revolt\EventLoop;

/**
 * The shared state behind a Future and the DeferredFuture that completes it.
 *
 * @template T
 *
 * @internal
 */
final class FutureState {

  /**
   * Whether the future has completed, with a value or an error.
   */
  private bool $complete = FALSE;

  /**
   * The value, once completed successfully.
   *
   * @var T|null
   */
  private mixed $result = NULL;

  /**
   * The error, once completed with one.
   */
  private ?\Throwable $throwable = NULL;

  /**
   * Whether an error has been observed by an await or a callback.
   */
  private bool $handled = FALSE;

  /**
   * Callbacks to invoke on completion.
   *
   * @var array<int, \Closure(?\Throwable, T|null): void>
   */
  private array $callbacks = [];

  /**
   * Next callback ID.
   */
  private int $nextId = 0;

  /**
   * Reports an error nobody looked at, the way an uncaught exception would.
   */
  public function __destruct() {
    if ($this->throwable !== NULL && !$this->handled) {
      $throwable = $this->throwable;
      EventLoop::queue(static fn () => throw new UnhandledFutureError($throwable));
    }
  }

  /**
   * Registers a callback, invoking it right away if already complete.
   *
   * Callbacks are queued on the event loop rather than invoked inline, so
   * that completion never runs user code in the completer's call stack.
   *
   * @param \Closure(?\Throwable, mixed): void $callback
   *   The callback.
   *
   * @return int
   *   An ID for unsubscribe().
   */
  public function subscribe(\Closure $callback): int {
    $id = $this->nextId++;
    $this->handled = TRUE;
    if ($this->complete) {
      EventLoop::queue($callback, $this->throwable, $this->result);
    }
    else {
      $this->callbacks[$id] = $callback;
    }
    return $id;
  }

  /**
   * Removes a callback registered with subscribe().
   */
  public function unsubscribe(int $id): void {
    unset($this->callbacks[$id]);
  }

  /**
   * Completes with a value.
   *
   * @param T $result
   *   The value.
   */
  public function complete(mixed $result): void {
    $this->settle(NULL, $result);
  }

  /**
   * Completes with an error.
   */
  public function error(\Throwable $throwable): void {
    $this->settle($throwable, NULL);
  }

  /**
   * Returns whether the future has completed.
   */
  public function isComplete(): bool {
    return $this->complete;
  }

  /**
   * Settles the state and queues the callbacks.
   */
  private function settle(?\Throwable $throwable, mixed $result): void {
    if ($this->complete) {
      throw new \Error('A future can only be completed once.');
    }
    $this->complete = TRUE;
    $this->throwable = $throwable;
    $this->result = $result;
    $callbacks = $this->callbacks;
    $this->callbacks = [];
    foreach ($callbacks as $callback) {
      EventLoop::queue($callback, $throwable, $result);
    }
  }

}
