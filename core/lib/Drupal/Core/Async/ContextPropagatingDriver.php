<?php

declare(strict_types=1);

namespace Drupal\Core\Async;

use Revolt\EventLoop;
use Revolt\EventLoop\CallbackType;
use Revolt\EventLoop\Driver;
use Revolt\EventLoop\Suspension;

/**
 * Decorates the Revolt event loop driver so callbacks inherit context.
 *
 * Every closure handed to the loop is bound to the execution context that is
 * current at registration time (see ContextStorage::bind()). Because every
 * consumer of the loop, including Amp's async() which is nothing more than a
 * queue() call, registers its work through the driver, this one decorator is
 * the only place where context capture has to happen.
 *
 * Resumes of suspended fibers are not registered through this driver: a
 * Suspension holds the inner driver's queue closure, and a resumed fiber is
 * the same fiber with its context intact. That keeps the hot path free.
 *
 * A consequence worth knowing: a closure queued while completing a future
 * runs with the completer's context, because queueing is the registration.
 * Code awaiting the future keeps its own context, because awaiting suspends
 * the fiber and resuming does not go through registration.
 */
final class ContextPropagatingDriver implements Driver {

  /**
   * Constructs the decorator.
   *
   * @param \Revolt\EventLoop\Driver $inner
   *   The decorated driver.
   */
  public function __construct(
    private readonly Driver $inner,
  ) {}

  /**
   * Wraps the active event loop driver, unless it is wrapped already.
   *
   * @throws \Error
   *   If the active driver is running; Revolt refuses to swap it then.
   */
  public static function install(): void {
    $driver = EventLoop::getDriver();
    if ($driver instanceof self) {
      return;
    }
    EventLoop::setDriver(new self($driver));
  }

  /**
   * Returns the decorated driver.
   */
  public function getInnerDriver(): Driver {
    return $this->inner;
  }

  /**
   * {@inheritdoc}
   */
  public function run(): void {
    $this->inner->run();
  }

  /**
   * {@inheritdoc}
   */
  public function stop(): void {
    $this->inner->stop();
  }

  /**
   * {@inheritdoc}
   */
  public function getSuspension(): Suspension {
    return $this->inner->getSuspension();
  }

  /**
   * {@inheritdoc}
   */
  public function isRunning(): bool {
    return $this->inner->isRunning();
  }

  /**
   * {@inheritdoc}
   */
  public function queue(\Closure $closure, mixed ...$args): void {
    $this->inner->queue(ContextStorage::bind($closure), ...$args);
  }

  /**
   * {@inheritdoc}
   */
  public function defer(\Closure $closure): string {
    return $this->inner->defer(ContextStorage::bind($closure));
  }

  /**
   * {@inheritdoc}
   */
  public function delay(float $delay, \Closure $closure): string {
    return $this->inner->delay($delay, ContextStorage::bind($closure));
  }

  /**
   * {@inheritdoc}
   */
  public function repeat(float $interval, \Closure $closure): string {
    return $this->inner->repeat($interval, ContextStorage::bind($closure));
  }

  /**
   * {@inheritdoc}
   */
  public function onReadable(mixed $stream, \Closure $closure): string {
    return $this->inner->onReadable($stream, ContextStorage::bind($closure));
  }

  /**
   * {@inheritdoc}
   */
  public function onWritable(mixed $stream, \Closure $closure): string {
    return $this->inner->onWritable($stream, ContextStorage::bind($closure));
  }

  /**
   * {@inheritdoc}
   */
  public function onSignal(int $signal, \Closure $closure): string {
    return $this->inner->onSignal($signal, ContextStorage::bind($closure));
  }

  /**
   * {@inheritdoc}
   */
  public function enable(string $callbackId): string {
    return $this->inner->enable($callbackId);
  }

  /**
   * {@inheritdoc}
   */
  public function cancel(string $callbackId): void {
    $this->inner->cancel($callbackId);
  }

  /**
   * {@inheritdoc}
   */
  public function disable(string $callbackId): string {
    return $this->inner->disable($callbackId);
  }

  /**
   * {@inheritdoc}
   */
  public function reference(string $callbackId): string {
    return $this->inner->reference($callbackId);
  }

  /**
   * {@inheritdoc}
   */
  public function unreference(string $callbackId): string {
    return $this->inner->unreference($callbackId);
  }

  /**
   * {@inheritdoc}
   */
  public function setErrorHandler(?\Closure $errorHandler): void {
    $this->inner->setErrorHandler($errorHandler);
  }

  /**
   * {@inheritdoc}
   */
  public function getErrorHandler(): ?\Closure {
    return $this->inner->getErrorHandler();
  }

  /**
   * {@inheritdoc}
   */
  public function getHandle(): mixed {
    return $this->inner->getHandle();
  }

  /**
   * {@inheritdoc}
   */
  public function getIdentifiers(): array {
    return $this->inner->getIdentifiers();
  }

  /**
   * {@inheritdoc}
   */
  public function getType(string $callbackId): CallbackType {
    return $this->inner->getType($callbackId);
  }

  /**
   * {@inheritdoc}
   */
  public function isEnabled(string $callbackId): bool {
    return $this->inner->isEnabled($callbackId);
  }

  /**
   * {@inheritdoc}
   */
  public function isReferenced(string $callbackId): bool {
    return $this->inner->isReferenced($callbackId);
  }

  /**
   * {@inheritdoc}
   */
  public function __debugInfo(): array {
    return $this->inner->__debugInfo();
  }

}
