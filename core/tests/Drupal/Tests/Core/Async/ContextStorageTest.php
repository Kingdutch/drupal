<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Async;

use Drupal\Core\Async\Context;
use Drupal\Core\Async\ContextKey;
use Drupal\Core\Async\ContextStorage;
use Drupal\Core\Async\Fibers;
use Drupal\Core\Async\SharedInstances;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests per-fiber context storage and capture-based inheritance.
 */
#[CoversClass(ContextStorage::class)]
#[CoversClass(Fibers::class)]
#[Group('Async')]
class ContextStorageTest extends UnitTestCase {

  /**
   * A key used throughout the tests.
   */
  protected ContextKey $key;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    ContextStorage::reset();
    SharedInstances::reset();
    $this->key = new ContextKey('test');
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    ContextStorage::reset();
    SharedInstances::reset();
    parent::tearDown();
  }

  /**
   * Tests reading and writing the main fiber's context.
   */
  public function testMainContext(): void {
    $this->assertNull(\Fiber::getCurrent());
    $this->assertFalse(ContextStorage::current()->has($this->key));
    ContextStorage::set(ContextStorage::current()->with($this->key, 'main'));
    $this->assertSame('main', ContextStorage::current()->get($this->key));
  }

  /**
   * Tests that a fiber created by hand inherits from main on first access.
   */
  public function testRawFiberInheritsLazilyAndIsIsolated(): void {
    $shared = new \stdClass();
    $cow = new ContextKey('cow', copyOnWrite: TRUE);
    ContextStorage::set(Context::empty()->with($this->key, 'main')->with($cow, $shared));

    $fiber = new \Fiber(function () use ($cow) {
      $seen = ContextStorage::current()->get($this->key);
      // The inherited object has to be treated as shared so that the raw
      // fiber cannot mutate main's copy in place.
      $marked = SharedInstances::isShared(ContextStorage::current()->get($cow));
      ContextStorage::set(ContextStorage::current()->with($this->key, 'fiber'));
      \Fiber::suspend([$seen, $marked]);
      return ContextStorage::current()->get($this->key);
    });

    $this->assertFalse(SharedInstances::isShared($shared));
    [$seen, $marked] = $fiber->start();
    $this->assertSame('main', $seen);
    $this->assertTrue($marked);
    $this->assertTrue(SharedInstances::isShared($shared));
    // The write inside the fiber is not visible to main.
    $this->assertSame('main', ContextStorage::current()->get($this->key));
    // And survives the suspension.
    $fiber->resume();
    $this->assertSame('fiber', $fiber->getReturn());
  }

  /**
   * Tests that bind() captures the context at registration time.
   */
  public function testBindCapturesAtRegistrationTime(): void {
    ContextStorage::set(Context::empty()->with($this->key, 'at registration'));
    $bound = ContextStorage::bind(fn (string $suffix) => ContextStorage::current()->get($this->key) . $suffix);
    ContextStorage::set(Context::empty()->with($this->key, 'later'));

    // Invoked synchronously in main, the captured context is installed for
    // the duration of the call and the previous one restored afterwards.
    $this->assertSame('at registration!', $bound('!'));
    $this->assertSame('later', ContextStorage::current()->get($this->key));

    // Invoked in a fiber, the fiber gets its own slot holding the snapshot.
    $fiber = new \Fiber($bound);
    $fiber->start('?');
    $this->assertSame('at registration?', $fiber->getReturn());
    $this->assertSame('later', ContextStorage::current()->get($this->key));
  }

  /**
   * Tests that task start listeners run with the installed context.
   */
  public function testTaskStartListeners(): void {
    $received = [];
    ContextStorage::onTaskStart(function (Context $context) use (&$received) {
      $received[] = $context->get($this->key);
      // The listener runs after the context has been installed.
      $received[] = ContextStorage::current()->get($this->key);
    });
    ContextStorage::set(Context::empty()->with($this->key, 'task'));
    $bound = ContextStorage::bind(fn () => NULL);
    ContextStorage::set(Context::empty());
    $bound();
    $this->assertSame(['task', 'task'], $received);
  }

  /**
   * Tests run() restores the previous context, or clears a fresh slot.
   */
  public function testRunRestoresPrevious(): void {
    ContextStorage::set(Context::empty()->with($this->key, 'outer'));
    $result = ContextStorage::run(Context::empty()->with($this->key, 'inner'), function (int $a, int $b) {
      $this->assertSame('inner', ContextStorage::current()->get($this->key));
      return $a + $b;
    }, 1, 2);
    $this->assertSame(3, $result);
    $this->assertSame('outer', ContextStorage::current()->get($this->key));

    // An exception still restores.
    try {
      ContextStorage::run(Context::empty()->with($this->key, 'inner'), fn () => throw new \RuntimeException('boom'));
      $this->fail('Exception expected.');
    }
    catch (\RuntimeException) {
      $this->assertSame('outer', ContextStorage::current()->get($this->key));
    }

    // In a fiber without a slot of its own, the slot is removed again after
    // the run, so the next access inherits lazily rather than seeing a stale
    // snapshot.
    $fiber = new \Fiber(function () {
      ContextStorage::run(Context::empty()->with($this->key, 'inner'), fn () => NULL);
      return ContextStorage::current()->get($this->key);
    });
    $fiber->start();
    $this->assertSame('outer', $fiber->getReturn());
  }

  /**
   * Tests that fibers from the factory inherit at creation and stay apart.
   */
  public function testFibersCreateInheritsAndIsolates(): void {
    ContextStorage::set(Context::empty()->with($this->key, 'parent'));

    $first = Fibers::create(function (string $name) {
      $inherited = ContextStorage::current()->get($this->key);
      ContextStorage::set(ContextStorage::current()->with($this->key, $name));
      \Fiber::suspend($inherited);
      return ContextStorage::current()->get($this->key);
    });
    $second = Fibers::create(function (string $name) {
      $inherited = ContextStorage::current()->get($this->key);
      ContextStorage::set(ContextStorage::current()->with($this->key, $name));
      \Fiber::suspend($inherited);
      return ContextStorage::current()->get($this->key);
    });

    // A write in the parent after creation is not seen by either child.
    ContextStorage::set(Context::empty()->with($this->key, 'parent changed'));

    $this->assertSame('parent', $first->start('first'));
    $this->assertSame('parent', $second->start('second'));
    $first->resume();
    $second->resume();
    $this->assertSame('first', $first->getReturn());
    $this->assertSame('second', $second->getReturn());
    $this->assertSame('parent changed', ContextStorage::current()->get($this->key));
  }

  /**
   * Tests that arguments to \Fiber::start() reach the callback.
   */
  public function testFibersCreateForwardsArguments(): void {
    $fiber = Fibers::create(fn (int $a, int $b) => $a * $b);
    $fiber->start(6, 7);
    $this->assertSame(42, $fiber->getReturn());
  }

}
