<?php

namespace Drupal\KernelTests\Core\EventLoop;

use Drupal\Core\DrupalKernel;
use Drupal\KernelTests\KernelTestBase;
use Revolt\EventLoop;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tests integration of the Revolt event loop with Drupal's request handling.
 *
 * @group EventLoop
 */
class EventLoopIntegrationTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [];

  /**
   * Tests that scheduled tasks in the event loop run during request termination.
   */
  public function testEventLoopIntegration() {
    $state = $this->container->get('state');
    $kernel = $this->container->get('kernel');
    $http_kernel = $this->container->get('http_kernel');

    $state->set('task_completed', FALSE);

    // Schedule a deferred task in the event loop.
    EventLoop::defer(fn () => $this->container->get('state')->set('task_completed', TRUE));

    // Create and process a request.
    $request = Request::create('/');
    try {
      $response = $http_kernel->handle($request, DrupalKernel::MAIN_REQUEST, TRUE);
    }
    catch (\Exception $e) {
      $response = $kernel->handleException($e, $request, DrupalKernel::MAIN_REQUEST);
    }
    $response->prepare($request);

    // Verify the task hasn't run yet.
    $this->assertFalse($state->get('task_completed'), 'Task should not have run before terminate.');

    // Call the kernel's terminate method.
    $kernel->terminate($request, $response);

    // Verify the task has now run.
    $this->assertTrue($state->get('task_completed'), 'Task should have run after terminate.');
  }

  /**
   * Tests multiple scheduled tasks in the event loop.
   */
  public function testMultipleEventLoopTasks() {
    // Use a shared array to track task completion.
    $taskResults = [
      'task1' => FALSE,
      'task2' => FALSE,
      'task3' => FALSE,
    ];

    // Schedule multiple deferred tasks in the event loop.
    EventLoop::defer(function () use (&$taskResults) {
      $taskResults['task1'] = TRUE;
    });

    EventLoop::defer(function () use (&$taskResults) {
      $taskResults['task2'] = TRUE;
    });

    EventLoop::defer(function () use (&$taskResults) {
      $taskResults['task3'] = TRUE;
    });

    // Create and process a request.
    $request = Request::create('/');
    $response = new Response('Test response', 200);

    // Verify no tasks have run yet.
    $this->assertFalse($taskResults['task1'], 'Task 1 should not have run before terminate.');
    $this->assertFalse($taskResults['task2'], 'Task 2 should not have run before terminate.');
    $this->assertFalse($taskResults['task3'], 'Task 3 should not have run before terminate.');

    // Call the kernel's terminate method.
    $this->container->get('kernel')->terminate($request, $response);

    // Verify all tasks have run.
    $this->assertTrue($taskResults['task1'], 'Task 1 should have run after terminate.');
    $this->assertTrue($taskResults['task2'], 'Task 2 should have run after terminate.');
    $this->assertTrue($taskResults['task3'], 'Task 3 should have run after terminate.');
  }

  /**
   * Documents how developers can use the event loop in their code.
   */
  public function testEventLoopUsageExample() {
    // This test documents through code how developers can leverage the
    // event loop in their applications.

    // Example 1: Defer a task to run in the next event loop iteration
    $result1 = NULL;
    EventLoop::defer(function() use (&$result1) {
      // Perform some async operation
      $result1 = 'deferred task completed';
    });

    // Example 2: Delay a task to run after a specific time
    $result2 = NULL;
    EventLoop::delay(0.001, function() use (&$result2) {
      // Perform some async operation after a delay
      $result2 = 'delayed task completed';
    });

    // Example 3: Repeat a task at regular intervals
    $counter = 0;
    $timerId = EventLoop::repeat(0.001, function($id) use (&$counter) {
      $counter++;
      // After 3 iterations, cancel the timer
      if ($counter >= 3) {
        EventLoop::cancel($id);
      }
    });

    // Create and process a request to trigger the event loop
    $request = Request::create('/');
    $response = new Response('Test response', 200);
    $this->container->get('kernel')->terminate($request, $response);

    // Verify our tasks completed successfully
    $this->assertEquals('deferred task completed', $result1);
    $this->assertEquals('delayed task completed', $result2);
    $this->assertEquals(3, $counter);

    // The key point: Developers don't need to call EventLoop::run() themselves
    // It's automatically called at the end of the request lifecycle
    $this->addToAssertionCount(1);
  }

}
