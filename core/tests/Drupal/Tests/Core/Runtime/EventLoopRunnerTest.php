<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Runtime;

use Drupal\Core\Async\Async;
use Drupal\Core\Async\ContextPropagatingDriver;
use Drupal\Core\Async\ContextStorage;
use Drupal\Core\DrupalKernel;
use Drupal\Core\Runtime\DrupalRuntime;
use Drupal\Core\Runtime\EventLoopRunner;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Revolt\EventLoop;
use Revolt\EventLoop\Driver;
use Revolt\EventLoop\Driver\StreamSelectDriver;
use Symfony\Component\Runtime\RunnerInterface;

/**
 * Tests that the runner lets scheduled work complete before exit.
 */
#[CoversClass(EventLoopRunner::class)]
#[CoversClass(DrupalRuntime::class)]
#[Group('Async')]
class EventLoopRunnerTest extends UnitTestCase {

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
    if ($this->previousDriver->isRunning()) {
      EventLoop::run();
    }
    EventLoop::setDriver(new StreamSelectDriver());
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    if (EventLoop::getDriver()->isRunning()) {
      EventLoop::run();
    }
    EventLoop::setDriver($this->previousDriver);
    ContextStorage::reset();
    parent::tearDown();
  }

  /**
   * Tests that work scheduled by the inner runner completes afterwards.
   */
  public function testRunsScheduledWork(): void {
    $log = [];
    $inner = new class($log) implements RunnerInterface {

      public function __construct(protected array &$log) {}

      public function run(): int {
        // The equivalent of a request scheduling background work.
        Async::run(function () {
          $this->log[] = 'task started';
          $suspension = EventLoop::getSuspension();
          EventLoop::delay(0.001, static fn () => $suspension->resume());
          $suspension->suspend();
          $this->log[] = 'task finished';
        });
        // Optional work that must not keep the process alive.
        EventLoop::unreference(EventLoop::delay(60, function () {
          $this->log[] = 'never';
        }));
        $this->log[] = 'response sent';
        return 3;
      }

    };

    $this->assertSame(3, (new EventLoopRunner($inner))->run());
    $this->assertSame(['response sent', 'task started', 'task finished'], $log);
    // The driver was wrapped before the inner runner ran.
    $this->assertInstanceOf(ContextPropagatingDriver::class, EventLoop::getDriver());
  }

  /**
   * Tests that the runtime wraps the HTTP kernel runner.
   */
  public function testRuntimeWrapsHttpKernelRunner(): void {
    $kernel = $this->createStub(DrupalKernel::class);
    $runtime = new DrupalRuntime();
    $this->assertInstanceOf(EventLoopRunner::class, $runtime->getRunner($kernel));
  }

}
