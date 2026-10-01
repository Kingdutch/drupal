<?php

declare(strict_types=1);

namespace Drupal\Core\Async;

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Resolves the per-fiber instance of a service tagged fiber_local.
 *
 * A service tagged fiber_local is replaced in the container by a generated
 * proxy (see FiberLocalProxyBuilder and FiberLocalServicesPass). Every call
 * on the proxy resolves the instance that belongs to the current fiber:
 *
 * - The service the container built is a pristine template. It is never
 *   written to; it is permanently marked as shared so that the first fiber
 *   to touch it, including the main fiber, works on a clone.
 * - Each fiber keeps its clone in its execution context under a
 *   copy-on-write key. When the context is captured for a new task the clone
 *   is marked as shared, so both the capturing fiber and the task clone again
 *   before their next write. This gives capture-at-creation semantics: a
 *   write in the parent after the task was created is not seen by the task,
 *   and a write in the task is never seen by the parent.
 * - Methods marked with the ReadOnlyMethod attribute resolve without
 *   cloning, since a shared instance may be read freely.
 *
 * clone is shallow. Arrays are copied on write by the engine and injected
 * dependencies are meant to be shared, but a service that holds mutable
 * objects as state has to implement __clone() to copy them.
 */
final class FiberLocalServices {

  /**
   * The service tag that opts a service into per-fiber instances.
   */
  public const string TAG = 'fiber_local';

  /**
   * The prefix under which the original service definition is re-registered.
   */
  public const string ORIGINAL_SERVICE_PREFIX = 'drupal.fiber_local_original_service.';

  /**
   * The context keys, by service ID.
   *
   * @var array<string, \Drupal\Core\Async\ContextKey>
   */
  private static array $keys = [];

  /**
   * Returns the context key under which a service's clones are stored.
   */
  public static function key(string $service_id): ContextKey {
    return self::$keys[$service_id] ??= new ContextKey('fiber_local_service:' . $service_id, copyOnWrite: TRUE);
  }

  /**
   * Returns the instance of a service that belongs to the current fiber.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The container holding the original service.
   * @param string $service_id
   *   The ID the service is tagged under, without prefix.
   * @param bool $write
   *   Whether the caller may mutate the instance. When TRUE and the instance
   *   is shared with another fiber, it is cloned first.
   *
   * @return object
   *   The instance to call.
   */
  public static function resolve(ContainerInterface $container, string $service_id, bool $write = TRUE): object {
    $key = self::key($service_id);
    $context = ContextStorage::current();
    $instance = $context->get($key);
    if ($instance === NULL) {
      // The template as the container built it. Never written to.
      $instance = $container->get(self::ORIGINAL_SERVICE_PREFIX . $service_id);
      SharedInstances::mark($instance);
    }
    if ($write && SharedInstances::isShared($instance)) {
      $instance = clone $instance;
      ContextStorage::set($context->with($key, $instance));
    }
    return $instance;
  }

  /**
   * Drops every per-fiber service instance from the main fiber's context.
   *
   * Called when the container is (re)initialized: the clones belong to the
   * services of the previous container.
   */
  public static function reset(): void {
    $context = ContextStorage::current();
    foreach ($context->keys() as $key) {
      if (str_starts_with($key->name, 'fiber_local_service:')) {
        $context = $context->without($key);
      }
    }
    ContextStorage::set($context);
    self::$keys = [];
  }

}
