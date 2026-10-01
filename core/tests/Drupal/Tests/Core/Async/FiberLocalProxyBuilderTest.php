<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Async;

use Drupal\Core\Async\ContextStorage;
use Drupal\Core\Async\FiberLocalProxyBuilder;
use Drupal\Core\Async\FiberLocalServices;
use Drupal\Core\Async\Fibers;
use Drupal\Core\Async\SharedInstances;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Tests\Core\Async\Fixtures\FiberLocalCounter;
use Drupal\Tests\Core\Async\Fixtures\FiberLocalCounterInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the generated fiber-local proxy and its copy-on-write semantics.
 */
#[CoversClass(FiberLocalProxyBuilder::class)]
#[CoversClass(FiberLocalServices::class)]
#[Group('Async')]
class FiberLocalProxyBuilderTest extends UnitTestCase {

  /**
   * The generated proxy class name.
   */
  protected const string PROXY_CLASS = 'Drupal\Tests\FiberLocalProxy\Core\Async\Fixtures\FiberLocalCounter';

  /**
   * The container holding the template service.
   */
  protected ContainerBuilder $container;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    ContextStorage::reset();
    SharedInstances::reset();
    FiberLocalServices::reset();

    if (!class_exists(static::PROXY_CLASS, FALSE)) {
      // phpcs:ignore Drupal.Functions.DiscouragedFunctions
      eval((new FiberLocalProxyBuilder())->build(FiberLocalCounter::class));
    }
    $this->container = new ContainerBuilder();
    $this->container->set(FiberLocalServices::ORIGINAL_SERVICE_PREFIX . 'counter', new FiberLocalCounter());
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    ContextStorage::reset();
    SharedInstances::reset();
    FiberLocalServices::reset();
    parent::tearDown();
  }

  /**
   * Returns a proxy for the counter service.
   */
  protected function proxy(): FiberLocalCounterInterface {
    $class = static::PROXY_CLASS;
    return new $class($this->container, 'counter');
  }

  /**
   * Tests the class name mapping in both directions.
   */
  public function testClassNames(): void {
    $this->assertSame(static::PROXY_CLASS, FiberLocalProxyBuilder::buildProxyClassName(FiberLocalCounter::class));
    $this->assertSame(FiberLocalCounter::class, FiberLocalProxyBuilder::buildOriginalClassName(static::PROXY_CLASS));
    $this->assertNull(FiberLocalProxyBuilder::buildOriginalClassName(FiberLocalCounter::class));
  }

  /**
   * Tests the shape of the generated code.
   */
  public function testGeneratedCode(): void {
    $code = (new FiberLocalProxyBuilder())->build(FiberLocalCounter::class);
    $this->assertStringContainsString('class FiberLocalCounter implements \\' . FiberLocalCounterInterface::class, $code);
    $this->assertStringContainsString('return $this->fiberLocalInstance()->increment($by);', $code);
    // Read-only methods resolve without cloning.
    $this->assertStringContainsString('return $this->fiberLocalInstance(FALSE)->get();', $code);
    // Static methods are forwarded to the class, and return its value.
    $this->assertStringContainsString('return \\' . FiberLocalCounter::class . '::describe($what);', $code);
    // Serialization stays with the proxy.
    $this->assertStringNotContainsString('__sleep', $code);
    $this->assertStringNotContainsString('__clone', $code);
    $this->assertStringContainsString('use \Drupal\Core\DependencyInjection\DependencySerializationTrait;', $code);
  }

  /**
   * Tests that static calls on the proxy reach the class and return.
   */
  public function testStaticMethods(): void {
    $proxy = $this->proxy();
    $this->assertSame('counts sheep', $proxy::describe('sheep'));
  }

  /**
   * Tests that the container's service is a template that is never written.
   */
  public function testTemplateIsNeverWritten(): void {
    $proxy = $this->proxy();
    $template = $this->container->get(FiberLocalServices::ORIGINAL_SERVICE_PREFIX . 'counter');
    $this->assertSame(0, $proxy->get());
    $this->assertSame(1, $proxy->increment());
    $this->assertSame(1, $proxy->get());
    $this->assertSame(0, $template->get());
    $this->assertTrue(SharedInstances::isShared($template));
  }

  /**
   * Tests that a fiber sees the parent's state as of its creation.
   */
  public function testCaptureAtCreation(): void {
    $proxy = $this->proxy();
    $proxy->increment();

    $child = Fibers::create(function () use ($proxy) {
      $seen = $proxy->get();
      \Fiber::suspend($seen);
      return [$proxy->increment(10), $proxy->log()];
    });

    // The parent writes after creating the child, before the child starts:
    // the child must not see it.
    $this->assertSame(2, $proxy->increment());
    $this->assertSame(1, $child->start());
    // And the child's write is not seen by the parent.
    $child->resume();
    $this->assertSame([11, [1, 10]], $child->getReturn());
    $this->assertSame(2, $proxy->get());
    $this->assertSame([1, 1], $proxy->log());
  }

  /**
   * Tests that sibling fibers do not see each other's writes.
   */
  public function testSiblingsAreIsolated(): void {
    $proxy = $this->proxy();
    $fibers = [];
    foreach ([1, 2, 3] as $by) {
      $fibers[$by] = Fibers::create(function () use ($proxy, $by) {
        $proxy->increment($by);
        \Fiber::suspend();
        return $proxy->get();
      });
    }
    foreach ($fibers as $fiber) {
      $fiber->start();
    }
    foreach ($fibers as $by => $fiber) {
      $fiber->resume();
      $this->assertSame($by, $fiber->getReturn());
    }
    $this->assertSame(0, $proxy->get());
  }

  /**
   * Tests that read-only methods do not clone a shared instance.
   */
  public function testReadOnlyMethodsDoNotClone(): void {
    $proxy = $this->proxy();
    $proxy->increment();
    $instance = FiberLocalServices::resolve($this->container, 'counter');
    $this->assertFalse(SharedInstances::isShared($instance));

    // Capturing a context marks the instance shared.
    ContextStorage::bind(static fn () => NULL);
    $this->assertTrue(SharedInstances::isShared($instance));

    // A read returns the shared instance itself.
    $this->assertSame($instance, FiberLocalServices::resolve($this->container, 'counter', FALSE));
    $this->assertSame(1, $proxy->get());
    $this->assertTrue(SharedInstances::isShared($instance));

    // A write clones.
    $written = FiberLocalServices::resolve($this->container, 'counter');
    $this->assertNotSame($instance, $written);
    $this->assertFalse(SharedInstances::isShared($written));
    $this->assertSame(1, $written->get());
  }

  /**
   * Tests that reset() drops the main fiber's copies.
   */
  public function testReset(): void {
    $proxy = $this->proxy();
    $proxy->increment();
    FiberLocalServices::reset();
    $this->assertSame(0, $proxy->get());
  }

}
