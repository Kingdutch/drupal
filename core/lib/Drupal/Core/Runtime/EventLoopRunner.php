<?php

declare(strict_types=1);

namespace Drupal\Core\Runtime;

use Drupal\Core\Async\ContextPropagatingDriver;
use Drupal\Core\Async\FiberLocalProxyClassLoader;
use Revolt\EventLoop;
use Symfony\Component\Runtime\RunnerInterface;

/**
 * Wraps a runner so that work scheduled on the event loop can complete.
 *
 * A request may schedule tasks that outlive it, such as cache warming or
 * notifications, and a kernel's terminate() may schedule more. The decorated
 * runner handles the request, sends the response and terminates the kernel;
 * this runner then runs the event loop until nothing referenced remains, so
 * that such work finishes before the process exits without delaying the
 * response. Work that may be dropped at exit is scheduled with
 * EventLoop::unreference().
 *
 * It also installs the pieces that have to be in place before anything is
 * scheduled on the loop: the driver that propagates the execution context
 * into every callback, and the class loader for fiber-local service
 * proxies.
 *
 * @see https://www.drupal.org/project/drupal/issues/3425210
 */
final class EventLoopRunner implements RunnerInterface {

  /**
   * Constructs the runner.
   *
   * @param \Symfony\Component\Runtime\RunnerInterface $inner
   *   The runner that handles the request or command.
   */
  public function __construct(
    private readonly RunnerInterface $inner,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function run(): int {
    ContextPropagatingDriver::install();
    FiberLocalProxyClassLoader::register();

    $exit_code = $this->inner->run();

    try {
      EventLoop::run();
    }
    catch (\Throwable $throwable) {
      // The response has been sent, so there is nobody to show the error
      // to; log it if a kernel is booted, otherwise let PHP report it.
      if (!\Drupal::hasContainer()) {
        throw $throwable;
      }
      \Drupal::logger('async')->error('Asynchronous work failed after the response was sent: @message', [
        '@message' => $throwable->getMessage(),
        'exception' => $throwable,
      ]);
      return $exit_code === 0 ? 1 : $exit_code;
    }

    return $exit_code;
  }

}
