<?php

declare(strict_types=1);

namespace Drupal\Core\Async;

use Drupal\Core\Async\Internal\FutureState;
use Drupal\Core\Utility\FiberResumeType;
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

  /**
   * Suspends the current fiber briefly so that other work can run.
   *
   * This is the one way core code yields. Inside a task on the event loop it
   * lets every other queued task run before continuing, which is what
   * batching loaders such as entity storage rely on to collect more IDs.
   * Inside a fiber that something other than the loop drives, it suspends
   * the fiber and leaves resumption to whoever drives it. In the main fiber
   * there is nothing to yield to, so it returns immediately.
   *
   * Calling \Fiber::suspend() directly inside a task is not supported: the
   * event loop would never resume that fiber.
   */
  public static function suspend(): void {
    if (\Fiber::getCurrent() === NULL) {
      return;
    }
    if (ContextStorage::isLoopManaged()) {
      $suspension = EventLoop::getSuspension();
      EventLoop::queue(static fn () => $suspension->resume());
      $suspension->suspend();
      return;
    }
    \Fiber::suspend(FiberResumeType::Immediate);
  }

}
