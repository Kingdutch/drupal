<?php

declare(strict_types=1);

namespace Drupal\Core\Async;

/**
 * An immutable map of values that travel with a fiber.
 *
 * Every fiber has a current context (see ContextStorage). Writing to a
 * context returns a new instance and leaves the original untouched, so a
 * context captured for a child task is never affected by later writes in the
 * parent, and writes in the child are never seen by the parent.
 *
 * PHP arrays are copied on write by the engine, so with() is cheap for the
 * handful of keys core and contrib are expected to register.
 */
final class Context {

  /**
   * The stored entries, keyed by key name.
   *
   * @var array<string, array{\Drupal\Core\Async\ContextKey, mixed}>
   */
  private array $entries;

  /**
   * Constructs a context.
   *
   * @param array<string, array{\Drupal\Core\Async\ContextKey, mixed}> $entries
   *   The entries.
   */
  private function __construct(array $entries) {
    $this->entries = $entries;
  }

  /**
   * Returns a context without any values.
   */
  public static function empty(): self {
    return new self([]);
  }

  /**
   * Returns the value stored under a key, or the key's default.
   */
  public function get(ContextKey $key): mixed {
    return isset($this->entries[$key->name]) ? $this->entries[$key->name][1] : $key->default;
  }

  /**
   * Returns whether a value is stored under a key.
   */
  public function has(ContextKey $key): bool {
    return isset($this->entries[$key->name]);
  }

  /**
   * Returns a new context with a value stored under a key.
   */
  public function with(ContextKey $key, mixed $value): self {
    $entries = $this->entries;
    $entries[$key->name] = [$key, $value];
    return new self($entries);
  }

  /**
   * Returns a new context without the value stored under a key.
   */
  public function without(ContextKey $key): self {
    $entries = $this->entries;
    unset($entries[$key->name]);
    return new self($entries);
  }

  /**
   * Returns the keys that have a value in this context.
   *
   * @return \Drupal\Core\Async\ContextKey[]
   *   The keys.
   */
  public function keys(): array {
    return array_column($this->entries, 0);
  }

  /**
   * Marks every copy-on-write object in this context as shared.
   *
   * This is called when a context is captured for a new task, so that both
   * the capturing fiber and the task clone before their next write.
   *
   * @see \Drupal\Core\Async\SharedInstances
   */
  public function markShared(): void {
    foreach ($this->entries as [$key, $value]) {
      if ($key->copyOnWrite && is_object($value)) {
        SharedInstances::mark($value);
      }
    }
  }

}
