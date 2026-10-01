<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Async;

use EventLoop\CallbackType;
use Drupal\Core\Async\Context;
use Drupal\Core\Async\ContextKey;
use Drupal\Core\Async\ContextPropagatingDriver;
use Drupal\Core\Async\ContextStorage;
use Drupal\Core\Async\SharedInstances;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Revolt\EventLoop;
use Revolt\EventLoop\Driver;
use Revolt\EventLoop\Driver\StreamSelectDriver;

/**
 * Tests that callbacks registered with the event loop inherit context.
 */
#[CoversClass(ContextPropagatingDriver::class)]
#[Group('Async')]
class ContextPropagatingDriverTest extends UnitTestCase {

  /**
   * The driver that was active before the test.
   */
  protected Driver $previousDriver;

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
    $this->previousDriver = EventLoop::getDriver();
    EventLoop::setDriver(new ContextPropagatingDriver(new StreamSelectDriver()));
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    EventLoop::setDriver($this->previousDriver);
    ContextStorage::reset();
    SharedInstances::reset();
    parent::tearDown();
  }

  /**
   * Tests that every registration method captures at registration time.
   */
  public function testRegistrationMethodsCapture(): void {
    $seen = [];
    ContextStorage::set(Context::empty()->with($this->key, 'registered'));
    EventLoop::queue(function (string $arg) use (&$seen) {
      $seen['queue'] = ContextStorage::current()->get($this->key) . $arg;
    }, '!');
    EventLoop::defer(function () use (&$seen) {
      $seen['defer'] = ContextStorage::current()->get($this->key);
    });
    EventLoop::delay(0, function () use (&$seen) {
      $seen['delay'] = ContextStorage::current()->get($this->key);
    });
    $repeat = EventLoop::repeat(0, function (string $id) use (&$seen) {
      $seen['repeat'] = ContextStorage::current()->get($this->key);
      EventLoop::cancel($id);
    });
    $this->assertTrue(EventLoop::isEnabled($repeat));
    ContextStorage::set(Context::empty()->with($this->key, 'changed before run'));
    EventLoop::run();

    $this->assertSame([
      'queue' => 'registered!',
      'defer' => 'registered',
      'delay' => 'registered',
      'repeat' => 'registered',
    ], $seen);
    // The loop's callbacks never touch main's context.
    $this->assertSame('changed before run', ContextStorage::current()->get($this->key));
  }

  /**
   * Tests the Amp-style task: queue, suspend, resume, complete.
   *
   * This is exactly what Amp's async() does, so if this passes, Amp tasks
   * inherit context without Amp knowing anything about Drupal.
   */
  public function testTaskKeepsContextAcrossSuspension(): void {
    $log = [];
    ContextStorage::set(Context::empty()->with($this->key, 'parent'));

    // The equivalent of async(): queue the task body as a microtask.
    EventLoop::queue(function () use (&$log) {
      $log[] = 'start:' . ContextStorage::current()->get($this->key);
      // A write inside the task stays in the task.
      ContextStorage::set(ContextStorage::current()->with($this->key, 'child'));
      $suspension = EventLoop::getSuspension();
      // The equivalent of awaiting something: resumed from another callback
      // that runs with a different context.
      EventLoop::defer(static fn () => $suspension->resume('resumed'));
      $log[] = 'before suspend:' . ContextStorage::current()->get($this->key);
      $value = $suspension->suspend();
      $log[] = 'after suspend:' . ContextStorage::current()->get($this->key) . ':' . $value;
    });
    ContextStorage::set(Context::empty()->with($this->key, 'parent changed'));
    EventLoop::run();

    $this->assertSame([
      'start:parent',
      'before suspend:child',
      'after suspend:child:resumed',
    ], $log);
    $this->assertSame('parent changed', ContextStorage::current()->get($this->key));
  }

  /**
   * Tests that copy-on-write objects are marked shared when captured.
   */
  public function testCaptureMarksCopyOnWriteObjects(): void {
    $service = new \stdClass();
    $cow = new ContextKey('service', copyOnWrite: TRUE);
    ContextStorage::set(Context::empty()->with($cow, $service));
    $this->assertFalse(SharedInstances::isShared($service));

    $same = NULL;
    EventLoop::queue(function () use ($cow, &$same) {
      $same = ContextStorage::current()->get($cow);
    });
    // Marked at registration, before the task runs, so that the parent also
    // knows to clone before its next write.
    $this->assertTrue(SharedInstances::isShared($service));
    EventLoop::run();
    $this->assertSame($service, $same);
  }

  /**
   * Tests that install() wraps the active driver exactly once.
   */
  public function testInstall(): void {
    $inner = new StreamSelectDriver();
    EventLoop::setDriver($inner);
    ContextPropagatingDriver::install();
    $driver = EventLoop::getDriver();
    $this->assertInstanceOf(ContextPropagatingDriver::class, $driver);
    $this->assertSame($inner, $driver->getInnerDriver());

    ContextPropagatingDriver::install();
    $this->assertSame($driver, EventLoop::getDriver());
  }

  /**
   * Tests that the non-registration methods pass straight through.
   */
  public function testPassThrough(): void {
    $driver = EventLoop::getDriver();
    $id = EventLoop::defer(static fn () => NULL);
    $this->assertSame([$id], $driver->getIdentifiers());
    $this->assertSame(CallbackType::Defer, $driver->getType($id));
    $this->assertTrue($driver->isReferenced($id));
    $driver->unreference($id);
    $this->assertFalse($driver->isReferenced($id));
    $driver->disable($id);
    $this->assertFalse($driver->isEnabled($id));
    $driver->enable($id);
    $this->assertTrue($driver->isEnabled($id));
    $driver->cancel($id);
    $this->assertSame([], $driver->getIdentifiers());
    $handler = static fn () => NULL;
    $driver->setErrorHandler($handler);
    $this->assertSame($handler, $driver->getErrorHandler());
    $this->assertFalse($driver->isRunning());
    $this->assertSame([], $driver->__debugInfo());
  }

}
