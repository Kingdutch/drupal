<?php

declare(strict_types=1);

namespace Drupal\Core\Async;

use Drupal\Core\Async\Internal\FutureState;
use Revolt\EventLoop;

/**
 * Starts asynchronous tasks on the event loop.
 */
final class Async {

  /**
   * Runs a closure as a task and returns a future for its result.
   *
   * The task is queued on the event loop and starts once the current fiber
   * suspends or returns to the loop. Inside the task, anything built on
   * Revolt suspensions may be awaited, including other futures. Because the
   * task is registered with the loop, it inherits the execution context
   * (current user and other fiber-local state) of the code that created it.
   *
   * This is deliberately the same shape as Amp's async(): a queue() call
   * and nothing else, so tasks from either library can await each other.
   *
   * @param \Closure $closure
   *   The task body. Its return value completes the future; an exception
   *   fails it.
   * @param mixed ...$args
   *   Arguments for the task body.
   *
   * @return \Drupal\Core\Async\Future
   *   The future result.
   */
  public static function run(\Closure $closure, mixed ...$args): Future {
    $state = new FutureState();
    EventLoop::queue(static function () use ($state, $closure, $args): void {
      try {
        $state->complete($closure(...$args));
      }
      catch (\Throwable $throwable) {
        $state->error($throwable);
      }
    });
    return new Future($state);
  }

}
