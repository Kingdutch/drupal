<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\DependencyInjection\Compiler;

use Drupal\Core\Async\FiberLocalServices;
use Drupal\Core\DependencyInjection\Compiler\FiberLocalServicesPass;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Tests\Core\Async\Fixtures\FiberLocalCounter;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Tests replacing fiber_local services with proxies.
 */
#[CoversClass(FiberLocalServicesPass::class)]
#[Group('DependencyInjection')]
class FiberLocalServicesPassTest extends UnitTestCase {

  /**
   * Tests that the tagged service is moved and proxied.
   */
  public function testProcess(): void {
    $container = new ContainerBuilder();
    $container->setParameter('counter_class', FiberLocalCounter::class);
    $container->register('counter', '%counter_class%')
      ->addTag(FiberLocalServices::TAG)
      ->addTag('other', ['priority' => 5])
      ->setPublic(FALSE);
    $container->register('untouched', FiberLocalCounter::class);

    (new FiberLocalServicesPass())->process($container);

    $original = $container->getDefinition(FiberLocalServices::ORIGINAL_SERVICE_PREFIX . 'counter');
    $this->assertSame('%counter_class%', $original->getClass());
    $this->assertTrue($original->isPublic());
    $this->assertSame([], $original->getTags());

    $proxy = $container->getDefinition('counter');
    $this->assertSame('Drupal\Tests\FiberLocalProxy\Core\Async\Fixtures\FiberLocalCounter', $proxy->getClass());
    $this->assertEquals([new Reference('service_container'), 'counter'], $proxy->getArguments());
    $this->assertFalse($proxy->isPublic());
    $this->assertSame(['other' => [['priority' => 5]]], $proxy->getTags());

    $this->assertSame(FiberLocalCounter::class, $container->getDefinition('untouched')->getClass());
    $this->assertFalse($container->hasDefinition(FiberLocalServices::ORIGINAL_SERVICE_PREFIX . 'untouched'));
  }

  /**
   * Tests that a lazy service cannot be fiber-local.
   */
  public function testLazyIsRejected(): void {
    $container = new ContainerBuilder();
    $container->register('counter', FiberLocalCounter::class)
      ->addTag(FiberLocalServices::TAG)
      ->setLazy(TRUE);
    $this->expectException(\LogicException::class);
    (new FiberLocalServicesPass())->process($container);
  }

}
