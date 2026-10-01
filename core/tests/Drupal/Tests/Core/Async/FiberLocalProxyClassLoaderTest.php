<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Async;

use Drupal\Component\PhpStorage\FileStorage;
use Drupal\Core\Async\FiberLocalProxyClassLoader;
use Drupal\Tests\Core\Async\Fixtures\FiberLocalCounterInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Tests on-the-fly generation of fiber-local proxies.
 */
#[CoversClass(FiberLocalProxyClassLoader::class)]
#[Group('Async')]
class FiberLocalProxyClassLoaderTest extends UnitTestCase {

  /**
   * The directory the storage writes to.
   */
  protected string $directory;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->directory = sys_get_temp_dir() . '/' . uniqid('fiber_local_proxy_', TRUE);
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    (new Filesystem())->remove($this->directory);
    parent::tearDown();
  }

  /**
   * Tests generating, storing and loading a proxy.
   */
  public function testLoadClass(): void {
    $storage = new FileStorage(['directory' => $this->directory, 'bin' => 'test']);
    $loader = new FiberLocalProxyClassLoader($storage);

    // Not a proxy class name.
    $this->assertFalse($loader->loadClass(FiberLocalCounterInterface::class));
    // A proxy of a class that does not exist.
    $this->assertFalse($loader->loadClass('Drupal\Tests\FiberLocalProxy\Core\Async\Fixtures\Missing'));

    $name = 'Drupal/Tests/FiberLocalProxy/Core/Async/Fixtures/FiberLocalLoaderFixture.php';
    $class = 'Drupal\Tests\FiberLocalProxy\Core\Async\Fixtures\FiberLocalLoaderFixture';
    $this->assertFalse($storage->exists($name));
    $this->assertFalse(class_exists($class, FALSE));
    $this->assertTrue($loader->loadClass($class));
    $this->assertTrue($storage->exists($name));
    $this->assertTrue(class_exists($class, FALSE));
    $this->assertTrue(is_subclass_of($class, FiberLocalCounterInterface::class));
  }

}
