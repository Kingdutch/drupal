<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Async;

use Drupal\Core\Async\Async;
use Drupal\Core\Async\Context;
use Drupal\Core\Async\ContextKey;
use Drupal\Core\Async\ContextPropagatingDriver;
use Drupal\Core\Async\ContextStorage;
use Drupal\Core\Async\DeferredFuture;
use Drupal\Core\Async\Future;
use Drupal\Core\Async\Internal\FutureState;
use Drupal\Core\Async\UnhandledFutureError;
use Drupal\Core\Utility\FiberResumeType;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Revolt\EventLoop;
use Revolt\EventLoop\Driver;
use Revolt\EventLoop\Driver\StreamSelectDriver;

/**
 * Tests the async primitives.
 */
#[CoversClass(Async::class)]
#[CoversClass(Future::class)]
#[CoversClass(DeferredFuture::class)]
#[CoversClass(FutureState::class)]
#[CoversClass(UnhandledFutureError::class)]
#[Group('Async')]
class FutureTest extends UnitTestCase {

  /**
   * The driver that was active before the test.
   */
  protected Driver $previousDriver;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    ContextStorage::reset();
    $this->previousDriver = EventLoop::getDriver();
    static::drainLoop();
    EventLoop::setDriver(new ContextPropagatingDriver(new StreamSelectDriver()));
  }

  /**
   * Lets the loop fiber finish so that the driver can be swapped.
   *
   * Awaiting from the main fiber interrupts the loop rather than letting it
   * return, which leaves the loop fiber suspended; Revolt treats that as
   * running.
   */
  protected static function drainLoop(): void {
    if (EventLoop::getDriver()->isRunning()) {
      EventLoop::run();
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    EventLoop::setErrorHandler(NULL);
    static::drainLoop();
    EventLoop::setDriver($this->previousDriver);
    ContextStorage::reset();
    parent::tearDown();
  }

  /**
   * Tests running a task and awaiting it from the main fiber.
   */
  public function testRunAndAwait(): void {
    $future = Async::run(static fn (int $a, int $b) => $a + $b, 2, 3);
    $this->assertFalse($future->isComplete());
    $this->assertSame(5, $future->await());
    $this->assertTrue($future->isComplete());
    // Awaiting a complete future again returns the same value.
    $this->assertSame(5, $future->await());
  }

  /**
   * Tests that an exception in a task fails the future.
   */
  public function testError(): void {
    $future = Async::run(static fn () => throw new \RuntimeException('task failed'));
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('task failed');
    $future->await();
  }

  /**
   * Tests pre-completed futures.
   */
  public function testCompleteAndErrorFactories(): void {
    $this->assertSame('value', Future::complete('value')->await());
    $errored = Future::error(new \LogicException());
    $this->assertTrue($errored->isComplete());
    $this->expectException(\LogicException::class);
    $errored->await();
  }

  /**
   * Tests the deferred producer side.
   */
  public function testDeferredFuture(): void {
    $deferred = new DeferredFuture();
    $future = $deferred->getFuture();
    EventLoop::delay(0, static fn () => $deferred->complete('done'));
    $this->assertFalse($deferred->isComplete());
    $this->assertSame('done', $future->await());
    $this->assertTrue($deferred->isComplete());

    $this->expectException(\Error::class);
    $deferred->complete('again');
  }

  /**
   * Tests map(), catch() and finally().
   */
  public function testCombinators(): void {
    $calls = [];
    $result = Async::run(static fn () => 2)
      ->map(static fn (int $value) => $value * 10)
      ->finally(static function () use (&$calls) {
        $calls[] = 'finally';
      })
      ->await();
    $this->assertSame(20, $result);
    $this->assertSame(['finally'], $calls);

    $recovered = Async::run(static fn () => throw new \RuntimeException('nope'))
      ->map(static fn () => 'not reached')
      ->catch(static fn (\Throwable $e) => 'recovered from ' . $e->getMessage())
      ->await();
    $this->assertSame('recovered from nope', $recovered);

    $this->expectException(\DomainException::class);
    Future::complete(1)->map(static fn () => throw new \DomainException())->await();
  }

  /**
   * Tests awaitAll() preserves keys and order.
   */
  public function testAwaitAll(): void {
    $deferred = new DeferredFuture();
    $futures = [
      'slow' => $deferred->getFuture(),
      'fast' => Async::run(static fn () => 'fast'),
      'done' => Future::complete('done'),
    ];
    EventLoop::delay(0.01, static fn () => $deferred->complete('slow'));
    $this->assertSame(['slow' => 'slow', 'fast' => 'fast', 'done' => 'done'], Future::awaitAll($futures));
    $this->assertSame([], Future::awaitAll([]));
  }

  /**
   * Tests iterate() yields in completion order and isolates failures.
   */
  public function testIterate(): void {
    $first = new DeferredFuture();
    $second = new DeferredFuture();
    $futures = [
      'a' => $first->getFuture(),
      'b' => $second->getFuture(),
      'c' => Async::run(static fn () => throw new \RuntimeException('c failed')),
    ];
    EventLoop::delay(0.01, static fn () => $first->complete('a done'));
    EventLoop::delay(0.001, static fn () => $second->complete('b done'));

    $order = [];
    foreach (Future::iterate($futures) as $key => $future) {
      $this->assertTrue($future->isComplete());
      try {
        $order[$key] = $future->await();
      }
      catch (\RuntimeException $e) {
        $order[$key] = $e->getMessage();
      }
    }
    $this->assertSame(['c' => 'c failed', 'b' => 'b done', 'a' => 'a done'], $order);
    $this->assertSame([], iterator_to_array(Future::iterate([])));
  }

  /**
   * Tests that completions seen together are yielded in input order.
   */
  public function testIterateTieBreaksInInputOrder(): void {
    $deferred = [];
    $futures = [];
    foreach (['c', 'a', 'b'] as $key) {
      $deferred[$key] = new DeferredFuture();
      $futures[$key] = $deferred[$key]->getFuture();
    }
    // Complete all three within one callback, so that the consumer only
    // looks once they have all completed. The consumer is a task: the main
    // fiber is interrupted by the loop after every callback and therefore
    // sees completions one at a time, in completion order.
    EventLoop::delay(0.001, static function () use ($deferred) {
      $deferred['b']->complete('b');
      $deferred['a']->complete('a');
      $deferred['c']->complete('c');
    });
    $keys = Async::run(static fn () => array_keys(iterator_to_array(Future::iterate($futures))))->await();
    $this->assertSame(['c', 'a', 'b'], $keys);
  }

  /**
   * Tests suspend(): a no-op in main, a yield in a task, a suspend elsewhere.
   */
  public function testSuspend(): void {
    // Main has nothing to yield to.
    Async::suspend();
    $this->assertNull(\Fiber::getCurrent());

    // Inside a task, every other queued task runs before it continues.
    $log = [];
    $first = Async::run(static function () use (&$log) {
      $log[] = 'first before';
      Async::suspend();
      $log[] = 'first after';
    });
    $second = Async::run(static function () use (&$log) {
      $log[] = 'second';
    });
    Future::awaitAll([$first, $second]);
    $this->assertSame(['first before', 'second', 'first after'], $log);

    // Inside a fiber nobody registered with the loop, whoever drives the
    // fiber gets control back.
    $fiber = new \Fiber(static function () {
      Async::suspend();
      return 'done';
    });
    $this->assertSame(FiberResumeType::Immediate, $fiber->start());
    $fiber->resume();
    $this->assertSame('done', $fiber->getReturn());
  }

  /**
   * Tests that tasks can await each other.
   */
  public function testNestedTasks(): void {
    $outer = Async::run(static function () {
      $inner = Async::run(static fn () => 'inner');
      return 'outer got ' . $inner->await();
    });
    $this->assertSame('outer got inner', $outer->await());
  }

  /**
   * Tests that a task inherits the execution context of its creator.
   */
  public function testTaskInheritsContext(): void {
    $key = new ContextKey('user');
    ContextStorage::set(Context::empty()->with($key, 'creator'));
    $future = Async::run(static function () use ($key) {
      $seen = ContextStorage::current()->get($key);
      ContextStorage::set(ContextStorage::current()->with($key, 'task'));
      return $seen;
    });
    ContextStorage::set(Context::empty()->with($key, 'changed'));
    $this->assertSame('creator', $future->await());
    $this->assertSame('changed', ContextStorage::current()->get($key));
  }

  /**
   * Tests that an unobserved failure is reported on the loop.
   */
  public function testUnhandledError(): void {
    $reported = NULL;
    EventLoop::setErrorHandler(static function (\Throwable $e) use (&$reported) {
      $reported = $e;
    });
    Async::run(static fn () => throw new \RuntimeException('nobody looked'));
    EventLoop::run();
    // The future object was never kept, so its state is destroyed once the
    // task completes.
    $this->assertInstanceOf(UnhandledFutureError::class, $reported);
    $this->assertSame('nobody looked', $reported->getPrevious()->getMessage());

    // ignore() suppresses the report.
    $reported = NULL;
    Async::run(static fn () => throw new \RuntimeException('ignored'))->ignore();
    EventLoop::run();
    $this->assertNull($reported);
  }

}
