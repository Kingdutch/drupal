<?php

declare(strict_types=1);

namespace Drupal\Core\Async;

use Drupal\Core\Async\Internal\FutureState;
use Revolt\EventLoop;

/**
 * The eventual result of asynchronous work.
 *
 * Futures are built on Revolt suspensions only, so they can be awaited from
 * any fiber the event loop manages, including inside tasks created by other
 * Revolt consumers, and vice versa. Awaiting from the main fiber runs the
 * event loop until the future completes, which is the single blocking bridge
 * a request cycle that is still synchronous needs.
 *
 * @template T
 */
final class Future {

  /**
   * Constructs a future. Use DeferredFuture or Async::run() instead.
   *
   * @param \Drupal\Core\Async\Internal\FutureState<T> $state
   *   The shared state.
   *
   * @internal
   */
  public function __construct(
    private readonly FutureState $state,
  ) {}

  /**
   * Returns a future that has already completed with a value.
   *
   * @param V $value
   *   The value.
   *
   * @return self<V>
   *   The future.
   */
  public static function complete(mixed $value = NULL): self {
    $state = new FutureState();
    $state->complete($value);
    return new self($state);
  }

  /**
   * Returns a future that has already failed.
   *
   * @return self<never>
   *   The future.
   */
  public static function error(\Throwable $throwable): self {
    $state = new FutureState();
    $state->error($throwable);
    return new self($state);
  }

  /**
   * Awaits several futures and returns their values, keyed like the input.
   *
   * The first error encountered is thrown; the remaining futures keep
   * running. Use iterate() to handle each outcome individually.
   *
   * @param iterable<array-key, self> $futures
   *   The futures.
   *
   * @return array
   *   The values, in input order.
   */
  public static function awaitAll(iterable $futures): array {
    $values = [];
    foreach ($futures as $key => $future) {
      $values[$key] = $future;
    }
    foreach ($values as $key => $future) {
      $values[$key] = $future->await();
    }
    return $values;
  }

  /**
   * Yields futures as they complete, keyed like the input.
   *
   * Each yielded future is complete, so await() on it returns or throws
   * immediately; that is how a caller handles every outcome without one
   * failure aborting the rest.
   *
   * @param iterable<array-key, self> $futures
   *   The futures.
   *
   * @return \Generator<array-key, self>
   *   The completed futures in completion order.
   */
  public static function iterate(iterable $futures): \Generator {
    $pending = [];
    foreach ($futures as $key => $future) {
      $pending[$key] = $future;
    }
    if (!$pending) {
      return;
    }
    $suspension = EventLoop::getSuspension();
    $completed = [];
    $waiting = FALSE;
    foreach ($pending as $key => $future) {
      $future->state->subscribe(static function () use (&$completed, &$waiting, $suspension, $key): void {
        $completed[] = $key;
        if ($waiting) {
          $waiting = FALSE;
          $suspension->resume();
        }
      });
    }
    while ($pending) {
      if (!$completed) {
        $waiting = TRUE;
        $suspension->suspend();
      }
      while ($completed) {
        $key = array_shift($completed);
        $future = $pending[$key];
        unset($pending[$key]);
        yield $key => $future;
      }
    }
  }

  /**
   * Returns whether the future has completed, with a value or an error.
   */
  public function isComplete(): bool {
    return $this->state->isComplete();
  }

  /**
   * Suspends the current fiber until the future completes.
   *
   * @return T
   *   The value.
   *
   * @throws \Throwable
   *   The error the future failed with.
   */
  public function await(): mixed {
    $suspension = EventLoop::getSuspension();
    $this->state->subscribe(static function (?\Throwable $throwable, mixed $value) use ($suspension): void {
      if ($throwable !== NULL) {
        $suspension->throw($throwable);
      }
      else {
        $suspension->resume($value);
      }
    });
    return $suspension->suspend();
  }

  /**
   * Returns a future that applies a callback to this future's value.
   *
   * The callback runs as a task of its own: it may await, and an exception
   * fails the returned future.
   *
   * @param \Closure(T): R $map
   *   The callback.
   *
   * @return self<R>
   *   The mapped future.
   */
  public function map(\Closure $map): self {
    $state = new FutureState();
    $this->state->subscribe(static function (?\Throwable $throwable, mixed $value) use ($state, $map): void {
      if ($throwable !== NULL) {
        $state->error($throwable);
        return;
      }
      try {
        $state->complete($map($value));
      }
      catch (\Throwable $e) {
        $state->error($e);
      }
    });
    return new self($state);
  }

  /**
   * Returns a future that recovers from an error with a callback.
   *
   * @param \Closure(\Throwable): T $catch
   *   The callback; its return value completes the returned future.
   *
   * @return self<T>
   *   The recovered future.
   */
  public function catch(\Closure $catch): self {
    $state = new FutureState();
    $this->state->subscribe(static function (?\Throwable $throwable, mixed $value) use ($state, $catch): void {
      if ($throwable === NULL) {
        $state->complete($value);
        return;
      }
      try {
        $state->complete($catch($throwable));
      }
      catch (\Throwable $e) {
        $state->error($e);
      }
    });
    return new self($state);
  }

  /**
   * Returns a future that runs a callback once this one completes either way.
   *
   * @param \Closure(): void $finally
   *   The callback; an exception from it replaces the outcome.
   *
   * @return self<T>
   *   A future with this future's outcome.
   */
  public function finally(\Closure $finally): self {
    $state = new FutureState();
    $this->state->subscribe(static function (?\Throwable $throwable, mixed $value) use ($state, $finally): void {
      try {
        $finally();
      }
      catch (\Throwable $e) {
        $state->error($e);
        return;
      }
      if ($throwable !== NULL) {
        $state->error($throwable);
      }
      else {
        $state->complete($value);
      }
    });
    return new self($state);
  }

  /**
   * Marks the outcome as deliberately unobserved.
   *
   * Without this, a future that fails and is never awaited reports its
   * error on the event loop.
   */
  public function ignore(): void {
    $this->state->subscribe(static fn () => NULL);
  }

}
