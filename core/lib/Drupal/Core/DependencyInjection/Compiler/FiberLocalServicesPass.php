<?php

declare(strict_types=1);

namespace Drupal\Core\DependencyInjection\Compiler;

use Drupal\Core\Async\FiberLocalProxyBuilder;
use Drupal\Core\Async\FiberLocalServices;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Replaces services tagged fiber_local with a per-fiber proxy.
 *
 * The original definition is moved to a prefixed ID and becomes the template
 * every fiber clones from. The service ID itself is taken over by the
 * generated proxy, so callers, injected arguments and aliases keep working
 * unchanged. Other tags move to the proxy, so that tag consumers call the
 * current fiber's instance too.
 *
 * @see \Drupal\Core\Async\FiberLocalServices
 */
class FiberLocalServicesPass implements CompilerPassInterface {

  /**
   * {@inheritdoc}
   */
  public function process(ContainerBuilder $container): void {
    foreach ($container->findTaggedServiceIds(FiberLocalServices::TAG) as $service_id => $tags) {
      $definition = $container->getDefinition($service_id);
      if ($definition->isLazy()) {
        throw new \LogicException(sprintf('The service "%s" cannot be both lazy and fiber_local: the lazy proxy would be cloned per fiber while the real service stays shared.', $service_id));
      }
      $class = $container->getParameterBag()->resolveValue($definition->getClass());
      $proxy_class = FiberLocalProxyBuilder::buildProxyClassName($class);

      $other_tags = $definition->getTags();
      unset($other_tags[FiberLocalServices::TAG]);
      $was_public = $definition->isPublic();
      $definition->setTags([]);
      $definition->setPublic(TRUE);
      $container->setDefinition(FiberLocalServices::ORIGINAL_SERVICE_PREFIX . $service_id, $definition);

      $proxy = $container->register($service_id, $proxy_class)
        ->setArguments([new Reference('service_container'), $service_id])
        ->setTags($other_tags);
      if (!$was_public) {
        $proxy->setPublic(FALSE);
      }
    }
  }

}
