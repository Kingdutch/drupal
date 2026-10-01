<?php

declare(strict_types=1);

namespace Drupal\Core\Async;

/**
 * Tracks objects that are referenced from more than one fiber's context.
 *
 * An object becomes shared when a context that holds it is captured for a
 * new task. Code that owns a shared object must clone it before mutating it,
 * and store the clone in its own fiber's context; the clone is a new object
 * and therefore not shared. This gives copy-on-write semantics for service
 * objects without the owner having to know about fibers at all.
 *
 * Objects are tracked weakly, so the registry never keeps them alive.
 */
final class SharedInstances {

  /**
   * The shared objects.
   *
   * @var \WeakMap<object, true>|null
   */
  private static ?\WeakMap $shared = NULL;

  /**
   * Marks an object as shared between fibers.
   */
  public static function mark(object $object): void {
    self::$shared ??= new \WeakMap();
    self::$shared[$object] = TRUE;
  }

  /**
   * Returns whether an object is shared between fibers.
   */
  public static function isShared(object $object): bool {
    return isset(self::$shared[$object]);
  }

  /**
   * Forgets every shared object. Intended for tests.
   */
  public static function reset(): void {
    self::$shared = NULL;
  }

}
