<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Session;

use Drupal\Core\Async\Fibers;
use Drupal\Core\FiberLocalProxy\Session\AccountProxy as AccountProxyFiberLocalProxy;
use Drupal\Core\FiberLocalProxy\Session\AccountSwitcher as AccountSwitcherFiberLocalProxy;
use Drupal\Core\Session\AccountSwitcherInterface;
use Drupal\Core\Session\UserSession;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Revolt\EventLoop;

/**
 * Tests that the current user is local to the fiber that switches it.
 */
#[Group('Session')]
#[Group('Async')]
#[RunTestsInSeparateProcesses]
class FiberLocalCurrentUserTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user'];

  /**
   * Tests that the services are replaced by their fiber-local proxies.
   */
  public function testServicesAreProxied(): void {
    $this->assertInstanceOf(AccountProxyFiberLocalProxy::class, \Drupal::currentUser());
    $this->assertInstanceOf(AccountProxyFiberLocalProxy::class, $this->container->get('current_user'));
    $this->assertInstanceOf(AccountSwitcherFiberLocalProxy::class, $this->container->get('account_switcher'));
    $this->assertInstanceOf(AccountSwitcherFiberLocalProxy::class, $this->container->get(AccountSwitcherInterface::class));
  }

  /**
   * Tests that an account switch in one fiber is invisible to its siblings.
   */
  public function testSwitchIsLocalToFiber(): void {
    $this->setCurrentUser(new UserSession(['uid' => 2]));
    $switcher = $this->container->get('account_switcher');

    $switching = Fibers::create(function () use ($switcher) {
      $switcher->switchTo(new UserSession(['uid' => 3]));
      \Fiber::suspend(\Drupal::currentUser()->id());
      $during = \Drupal::currentUser()->id();
      $switcher->switchBack();
      return [$during, \Drupal::currentUser()->id()];
    });
    $observing = Fibers::create(function () {
      \Fiber::suspend(\Drupal::currentUser()->id());
      return \Drupal::currentUser()->id();
    });

    $this->assertSame(3, $switching->start());
    $this->assertSame(2, \Drupal::currentUser()->id());
    $this->assertSame(2, $observing->start());
    $switching->resume();
    $observing->resume();
    $this->assertSame([3, 2], $switching->getReturn());
    $this->assertSame(2, $observing->getReturn());
    $this->assertSame(2, \Drupal::currentUser()->id());
  }

  /**
   * Tests that every fiber has its own switch stack.
   */
  public function testSwitchStackIsLocalToFiber(): void {
    $this->setCurrentUser(new UserSession(['uid' => 2]));
    $switcher = $this->container->get('account_switcher');

    $a = Fibers::create(function () use ($switcher) {
      $switcher->switchTo(new UserSession(['uid' => 3]));
      \Fiber::suspend();
      $switcher->switchBack();
      return \Drupal::currentUser()->id();
    });
    $b = Fibers::create(function () use ($switcher) {
      $switcher->switchTo(new UserSession(['uid' => 4]));
      $switcher->switchBack();
      return \Drupal::currentUser()->id();
    });
    // Interleave so that a process-wide stack would pop the wrong entry.
    $a->start();
    $b->start();
    $a->resume();
    $this->assertSame(2, $a->getReturn());
    $this->assertSame(2, $b->getReturn());
    // Nothing is left to switch back to in main.
    $this->expectException(\RuntimeException::class);
    $switcher->switchBack();
  }

  /**
   * Tests that a task on the event loop inherits and isolates the user.
   */
  public function testEventLoopTask(): void {
    $this->setCurrentUser(new UserSession(['uid' => 2]));
    $switcher = $this->container->get('account_switcher');
    $seen = [];

    EventLoop::queue(function () use ($switcher, &$seen) {
      $seen[] = \Drupal::currentUser()->id();
      $switcher->switchTo(new UserSession(['uid' => 3]));
      $suspension = EventLoop::getSuspension();
      EventLoop::defer(static fn () => $suspension->resume());
      $suspension->suspend();
      $seen[] = \Drupal::currentUser()->id();
    });
    // Registered while user 2 is current, this task sees user 2 even though
    // main switches before the loop runs.
    $switcher->switchTo(new UserSession(['uid' => 5]));
    EventLoop::run();
    $switcher->switchBack();

    $this->assertSame([2, 3], $seen);
    $this->assertSame(2, \Drupal::currentUser()->id());
  }

  /**
   * Tests that the current user survives a container rebuild.
   */
  public function testContainerRebuild(): void {
    $this->setCurrentUser(new UserSession(['uid' => 2]));
    $this->container->get('kernel')->rebuildContainer();
    $this->assertSame(2, \Drupal::currentUser()->id());
    $this->assertInstanceOf(AccountProxyFiberLocalProxy::class, \Drupal::currentUser());
  }

}
