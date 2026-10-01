<?php

declare(strict_types=1);

namespace Drupal\Core\Async\Attribute;

/**
 * Marks a method of a fiber-local service as not mutating the service.
 *
 * The proxy generated for a service tagged fiber_local cannot tell reads
 * from writes, so it clones a shared instance before every call. Marking a
 * method with this attribute lets the proxy skip the clone for that method.
 *
 * Only use it on methods that never write to the object, directly or
 * indirectly. A method that lazily initializes state is not read-only: with
 * the attribute it would initialize the shared instance that other fibers
 * see.
 *
 * @see \Drupal\Core\Async\FiberLocalProxyBuilder
 */
#[\Attribute(\Attribute::TARGET_METHOD)]
final class ReadOnlyMethod {
}
