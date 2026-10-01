<?php

declare(strict_types=1);

namespace Drupal\Core\Async;

/**
 * Creates fibers that inherit the creator's execution context.
 *
 * A \Fiber created directly starts without a context of its own and falls
 * back to the main fiber's context on first access, which means it shares
 * state with the request rather than with the code that created it. Fibers
 * created here capture the creator's context at creation time instead, the
 * same way a closure registered with the event loop does.
 *
 * This is transitional. Once rendering runs on the Revolt event loop there
 * is no reason for core to create fibers by hand, and this class can go.
 */
final class Fibers {

  /**
   * Creates a fiber whose callback runs with the current context.
   *
   * @param callable $callback
   *   The fiber callback. Arguments passed to \Fiber::start() are forwarded.
   *
   * @return \Fiber
   *   The fiber, not yet started.
   */
  public static function create(callable $callback): \Fiber {
    return new \Fiber(ContextStorage::bind(\Closure::fromCallable($callback)));
  }

}
