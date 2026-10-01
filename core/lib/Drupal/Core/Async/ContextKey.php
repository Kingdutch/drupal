<?php

declare(strict_types=1);

namespace Drupal\Core\Async;

/**
 * Identifies a value stored in a fiber-local execution context.
 *
 * A key is created once, usually as a static or a constant on the class that
 * owns the value, and then used to read from and write to a
 * \Drupal\Core\Async\Context. Keys are compared by name, so two keys with the
 * same name address the same slot.
 *
 * Values stored under a key flagged as copy-on-write are objects that are
 * shared between fibers until one of them writes. When a context is captured
 * for a new task (see ContextStorage::bind()), such objects are marked as
 * shared, and the code that owns them is expected to clone before mutating.
 * This is what the fiber_local service tag relies on.
 *
 * @see \Drupal\Core\Async\Context
 * @see \Drupal\Core\Async\SharedInstances
 */
final class ContextKey {

  /**
   * Constructs a context key.
   *
   * @param string $name
   *   The unique name of the key.
   * @param mixed $default
   *   The value returned by Context::get() when nothing is stored.
   * @param bool $copyOnWrite
   *   Whether objects stored under this key are shared between fibers until
   *   written to.
   */
  public function __construct(
    public readonly string $name,
    public readonly mixed $default = NULL,
    public readonly bool $copyOnWrite = FALSE,
  ) {}

}
