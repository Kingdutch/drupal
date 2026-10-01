<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Async;

use Drupal\Core\Async\Context;
use Drupal\Core\Async\ContextKey;
use Drupal\Core\Async\SharedInstances;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the immutable execution context.
 */
#[CoversClass(Context::class)]
#[CoversClass(ContextKey::class)]
#[CoversClass(SharedInstances::class)]
#[Group('Async')]
class ContextTest extends UnitTestCase {

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    SharedInstances::reset();
    parent::tearDown();
  }

  /**
   * Tests that writes produce a new context and leave the original alone.
   */
  public function testCopyOnWrite(): void {
    $key = new ContextKey('test', 'default');
    $empty = Context::empty();
    $this->assertFalse($empty->has($key));
    $this->assertSame('default', $empty->get($key));

    $one = $empty->with($key, 'one');
    $this->assertNotSame($empty, $one);
    $this->assertFalse($empty->has($key));
    $this->assertTrue($one->has($key));
    $this->assertSame('one', $one->get($key));

    $two = $one->with($key, 'two');
    $this->assertSame('one', $one->get($key));
    $this->assertSame('two', $two->get($key));

    $none = $two->without($key);
    $this->assertSame('two', $two->get($key));
    $this->assertSame('default', $none->get($key));
    $this->assertSame([], $none->keys());
    $this->assertSame([$key], $two->keys());
  }

  /**
   * Tests that keys are addressed by name.
   */
  public function testKeysAreComparedByName(): void {
    $context = Context::empty()->with(new ContextKey('name'), 'value');
    $this->assertSame('value', $context->get(new ContextKey('name')));
    $this->assertNull($context->get(new ContextKey('other')));
  }

  /**
   * Tests that only copy-on-write object values are marked as shared.
   */
  public function testMarkShared(): void {
    $cow = new \stdClass();
    $plain = new \stdClass();
    $context = Context::empty()
      ->with(new ContextKey('cow', copyOnWrite: TRUE), $cow)
      ->with(new ContextKey('plain'), $plain)
      ->with(new ContextKey('scalar', copyOnWrite: TRUE), 'scalar');

    $this->assertFalse(SharedInstances::isShared($cow));
    $context->markShared();
    $this->assertTrue(SharedInstances::isShared($cow));
    $this->assertFalse(SharedInstances::isShared($plain));

    // A clone is a new object and therefore private to whoever made it.
    $this->assertFalse(SharedInstances::isShared(clone $cow));
  }

}
