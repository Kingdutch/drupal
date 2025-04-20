<?php

namespace Drupal\Core;

/**
 * The interface for services that need explicit destruction.
 *
 * This is useful for services that need to perform additional tasks to
 * finalize operations or clean up after the response is sent and before the
 * service is terminated.
 *
 * Service destructors are called after Revolt's EventLoop::run has been called
 * so any async tasks must be completed and cleaned up at the end of the
 * destructor.
 *
 * Services using this interface need to be registered with the
 * "needs_destruction" tag.
 */
interface DestructableInterface {

  /**
   * Performs destruct operations.
   */
  public function destruct();

}
