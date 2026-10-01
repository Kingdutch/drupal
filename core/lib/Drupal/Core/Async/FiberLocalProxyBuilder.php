<?php

declare(strict_types=1);

namespace Drupal\Core\Async;

use Drupal\Core\Async\Attribute\ReadOnlyMethod;
use Drupal\Core\ProxyBuilder\ProxyBuilder;

/**
 * Generates the proxy class for a service tagged fiber_local.
 *
 * The proxy implements the service's interfaces and forwards every public
 * method to the instance that belongs to the current fiber, resolved through
 * FiberLocalServices::resolve(). It lives next to the lazy-service proxies:
 * \Drupal\Core\Session\AccountProxy is proxied by
 * \Drupal\Core\FiberLocalProxy\Session\AccountProxy, so a committed proxy is
 * found by the regular class loader and a missing one is generated on the
 * fly by FiberLocalProxyClassLoader.
 *
 * Core commits the proxies of its own services, generated with
 * core/scripts/generate-fiber-local-proxy.php.
 */
class FiberLocalProxyBuilder extends ProxyBuilder {

  /**
   * The namespace segment that marks a generated fiber-local proxy.
   */
  public const string NAMESPACE_SEGMENT = 'FiberLocalProxy';

  /**
   * {@inheritdoc}
   */
  public static function buildProxyClassName($class_name) {
    $match = [];
    preg_match('/([a-zA-Z0-9_]+\\\\[a-zA-Z0-9_]+)\\\\(.+)/', $class_name, $match);
    return $match[1] . '\\' . self::NAMESPACE_SEGMENT . '\\' . $match[2];
  }

  /**
   * Returns the proxied class for a proxy class name, or NULL if not a proxy.
   */
  public static function buildOriginalClassName(string $proxy_class_name): ?string {
    $match = [];
    if (!preg_match('/^([a-zA-Z0-9_]+\\\\[a-zA-Z0-9_]+)\\\\' . self::NAMESPACE_SEGMENT . '\\\\(.+)$/', $proxy_class_name, $match)) {
      return NULL;
    }
    return $match[1] . '\\' . $match[2];
  }

  /**
   * {@inheritdoc}
   */
  protected function buildLazyLoadItselfMethod() {
    $output = <<<'EOS'
/**
 * Resolves the instance of the service that belongs to the current fiber.
 *
 * @param bool $write
 *   Whether the caller may mutate the instance.
 *
 * @return object
 *   The instance to call.
 */
protected function fiberLocalInstance(bool $write = TRUE)
{
    return \Drupal\Core\Async\FiberLocalServices::resolve($this->container, $this->drupalProxyOriginalServiceId, $write);
}

EOS;

    return $output;
  }

  /**
   * {@inheritdoc}
   */
  protected function buildMethod(\ReflectionMethod $reflection_method) {
    // Serialization and lifecycle hooks describe the proxy object itself, not
    // the instance it forwards to: the proxy serializes as a reference to the
    // service, and clones of the proxy are never made.
    $lifecycle = ['__sleep', '__wakeup', '__serialize', '__unserialize', '__clone', '__destruct'];
    if (in_array($reflection_method->getName(), $lifecycle, TRUE)) {
      return '';
    }
    return parent::buildMethod($reflection_method);
  }

  /**
   * {@inheritdoc}
   */
  protected function buildMethodBody(\ReflectionMethod $reflection_method) {
    if ($reflection_method->isStatic()) {
      // Static methods are forwarded to the class; the value they return
      // has to be returned as well.
      $output = parent::buildMethodBody($reflection_method);
      $return_type = $reflection_method->getReturnType();
      if ($return_type === NULL || (string) $return_type !== 'void') {
        $output = preg_replace('/^    \\\\/', '    return \\\\', $output, 1);
      }
      return $output;
    }
    $write = empty($reflection_method->getAttributes(ReadOnlyMethod::class));
    $resolve = $write ? '$this->fiberLocalInstance()' : '$this->fiberLocalInstance(FALSE)';
    $output = parent::buildMethodBody($reflection_method);
    return str_replace('$this->lazyLoadItself()', $resolve, $output);
  }

}
