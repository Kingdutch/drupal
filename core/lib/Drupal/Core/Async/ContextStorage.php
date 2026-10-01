<?php

declare(strict_types=1);

namespace Drupal\Core\Async;

/**
 * Holds the current execution context of every fiber.
 *
 * This is a static facade: it has to be reachable from the Revolt driver and
 * from generated service proxies, both of which may run before or outside of
 * the service container.
 *
 * Inheritance is capture based. PHP exposes no parent for a \Fiber and the
 * event loop reuses fibers between callbacks, so a child cannot look its
 * context up; instead the creator captures its context with bind() and the
 * captured context is installed when the task starts. Fibers created without
 * going through bind() (or Fibers::create()) inherit the main fiber's
 * context lazily, on first access, so that today's behaviour of raw fibers
 * seeing the request's state is preserved.
 *
 * @see \Drupal\Core\Async\ContextPropagatingDriver
 * @see \Drupal\Core\Async\Fibers
 */
final class ContextStorage {

  /**
   * The context of the main fiber.
   */
  private static ?Context $main = NULL;

  /**
   * The context of every other fiber.
   *
   * @var \WeakMap<\Fiber, \Drupal\Core\Async\Context>|null
   */
  private static ?\WeakMap $fibers = NULL;

  /**
   * The fibers that the event loop drives.
   *
   * @var \WeakMap<\Fiber, true>|null
   */
  private static ?\WeakMap $loopManaged = NULL;

  /**
   * Listeners invoked whenever a captured context is installed for a task.
   *
   * @var array<\Closure(\Drupal\Core\Async\Context): void>
   */
  private static array $taskStartListeners = [];

  /**
   * Returns the context of the current fiber.
   */
  public static function current(): Context {
    $fiber = \Fiber::getCurrent();
    if ($fiber === NULL) {
      return self::$main ??= Context::empty();
    }
    self::$fibers ??= new \WeakMap();
    if (!isset(self::$fibers[$fiber])) {
      // A fiber that was not created through bind(): inherit from {main} on
      // first access. Marking shared instances guarantees copy-on-write even
      // for fibers nobody captured a context for.
      $inherited = self::$main ?? Context::empty();
      $inherited->markShared();
      self::$fibers[$fiber] = $inherited;
    }
    return self::$fibers[$fiber];
  }

  /**
   * Replaces the context of the current fiber.
   */
  public static function set(Context $context): void {
    $fiber = \Fiber::getCurrent();
    if ($fiber === NULL) {
      self::$main = $context;
      return;
    }
    self::$fibers ??= new \WeakMap();
    self::$fibers[$fiber] = $context;
  }

  /**
   * Runs a closure with a given context and restores the previous one after.
   *
   * @param \Drupal\Core\Async\Context $context
   *   The context to install while the closure runs.
   * @param \Closure $closure
   *   The closure.
   * @param mixed ...$args
   *   Arguments for the closure.
   *
   * @return mixed
   *   The closure's return value.
   */
  public static function run(Context $context, \Closure $closure, mixed ...$args): mixed {
    return self::doRun($context, FALSE, $closure, ...$args);
  }

  /**
   * Returns whether the event loop drives the current fiber.
   *
   * TRUE inside callbacks and tasks the loop registered, FALSE in the main
   * fiber and in fibers that something else created and resumes by hand.
   * Code that wants to yield uses this to decide whether to hand control to
   * the loop or to whoever drives the fiber.
   *
   * @see \Drupal\Core\Async\Async::suspend()
   */
  public static function isLoopManaged(): bool {
    $fiber = \Fiber::getCurrent();
    return $fiber !== NULL && isset(self::$loopManaged[$fiber]);
  }

  /**
   * Runs a closure with a context, optionally marking the fiber loop-managed.
   */
  private static function doRun(Context $context, bool $loop_managed, \Closure $closure, mixed ...$args): mixed {
    $fiber = \Fiber::getCurrent();
    $had_previous = $fiber === NULL ? self::$main !== NULL : isset(self::$fibers[$fiber]);
    $previous = $had_previous ? self::current() : NULL;
    $was_loop_managed = $fiber !== NULL && isset(self::$loopManaged[$fiber]);
    if ($loop_managed && $fiber !== NULL) {
      self::$loopManaged ??= new \WeakMap();
      self::$loopManaged[$fiber] = TRUE;
    }
    self::set($context);
    try {
      return $closure(...$args);
    }
    finally {
      if ($previous !== NULL) {
        self::set($previous);
      }
      elseif ($fiber !== NULL) {
        unset(self::$fibers[$fiber]);
      }
      else {
        self::$main = NULL;
      }
      if ($loop_managed && $fiber !== NULL && !$was_loop_managed) {
        unset(self::$loopManaged[$fiber]);
      }
    }
  }

  /**
   * Captures the current context so that a closure runs with it later.
   *
   * This is the inheritance primitive. The context is captured now, at
   * registration time, not when the closure eventually runs; that is the
   * semantic every runtime with async context propagation converged on,
   * because it is the only one that is reproducible. Copy-on-write objects
   * in the captured context are marked as shared.
   *
   * The event loop driver installed by ContextPropagatingDriver calls this
   * for every closure registered with the loop, so application code only
   * needs it for closures that are stored by something other than the loop
   * and invoked later, or for fibers it creates by hand (see Fibers).
   *
   * @param \Closure $closure
   *   The closure to bind.
   * @param bool $loop_managed
   *   Whether the event loop will run the closure and resume its fiber. Only
   *   the loop driver passes TRUE.
   *
   * @return \Closure
   *   A closure that installs the captured context, notifies task start
   *   listeners, and then invokes the original closure.
   */
  public static function bind(\Closure $closure, bool $loop_managed = FALSE): \Closure {
    $snapshot = self::current();
    $snapshot->markShared();
    return static function (mixed ...$args) use ($closure, $snapshot, $loop_managed): mixed {
      return self::doRun($snapshot, $loop_managed, static function () use ($closure, $snapshot, $args): mixed {
        foreach (self::$taskStartListeners as $listener) {
          $listener($snapshot);
        }
        return $closure(...$args);
      });
    };
  }

  /**
   * Registers a listener that is invoked when a bound closure starts.
   *
   * This exists so that integrations such as tracing can attach their own
   * per-fiber state when a task starts, without core depending on them. The
   * listener receives the context that was just installed.
   *
   * @param \Closure(\Drupal\Core\Async\Context): void $listener
   *   The listener.
   */
  public static function onTaskStart(\Closure $listener): void {
    self::$taskStartListeners[] = $listener;
  }

  /**
   * Forgets all contexts and listeners. Intended for tests.
   */
  public static function reset(): void {
    self::$main = NULL;
    self::$fibers = NULL;
    self::$loopManaged = NULL;
    self::$taskStartListeners = [];
  }

}
