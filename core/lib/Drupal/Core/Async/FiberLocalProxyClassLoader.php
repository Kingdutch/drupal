<?php

declare(strict_types=1);

namespace Drupal\Core\Async;

use Drupal\Component\PhpStorage\FileStorage;
use Drupal\Component\PhpStorage\PhpStorageInterface;
use Drupal\Core\PhpStorage\PhpStorageFactory;

/**
 * Generates and loads fiber-local service proxies that are not committed.
 *
 * Registered after the regular class loader, so it only sees proxy classes
 * no PSR-4 path provides. For those it generates the proxy source, saves it
 * to the fiber_local_proxy PHP storage, and loads it from there, which keeps
 * generated code out of eval() the same way the compiled container and Twig
 * templates are handled. This is what lets a contrib module opt a service
 * into per-fiber instances with nothing but a service tag.
 */
final class FiberLocalProxyClassLoader {

  /**
   * The registered instance, if any.
   *
   * @var \Drupal\Core\Async\FiberLocalProxyClassLoader|null
   */
  private static ?self $registered = NULL;

  /**
   * Constructs the class loader.
   *
   * @param \Drupal\Component\PhpStorage\PhpStorageInterface|null $storage
   *   The storage for generated proxies, or NULL to use the
   *   fiber_local_proxy PHP storage bin.
   * @param \Drupal\Core\Async\FiberLocalProxyBuilder|null $builder
   *   The proxy builder, or NULL for the default.
   */
  public function __construct(
    private ?PhpStorageInterface $storage = NULL,
    private ?FiberLocalProxyBuilder $builder = NULL,
  ) {}

  /**
   * Registers a loader with the default storage, once.
   */
  public static function register(): void {
    if (self::$registered === NULL) {
      self::$registered = new self();
      spl_autoload_register(self::$registered->loadClass(...));
    }
  }

  /**
   * Loads a fiber-local proxy class, generating it first if necessary.
   *
   * @param string $class
   *   The fully qualified class name.
   *
   * @return bool
   *   TRUE if the class was loaded.
   */
  public function loadClass(string $class): bool {
    $original = FiberLocalProxyBuilder::buildOriginalClassName($class);
    if ($original === NULL || !class_exists($original)) {
      return FALSE;
    }
    $name = str_replace('\\', '/', $class) . '.php';
    $storage = $this->getStorage();
    if (!$storage->exists($name)) {
      $code = "<?php\n" . ($this->builder ??= new FiberLocalProxyBuilder())->build($original);
      if (!$storage->save($name, $code)) {
        throw new \RuntimeException(sprintf('Unable to save the generated fiber-local proxy for %s.', $original));
      }
    }
    return (bool) $storage->load($name) && class_exists($class, FALSE);
  }

  /**
   * Returns the storage for generated proxies.
   */
  private function getStorage(): PhpStorageInterface {
    if ($this->storage === NULL) {
      try {
        $this->storage = PhpStorageFactory::get('fiber_local_proxy');
      }
      catch (\RuntimeException) {
        // No hash salt yet, which happens early in the installer. Fall back
        // to the temporary directory rather than failing to boot.
        $this->storage = new FileStorage([
          'directory' => sys_get_temp_dir() . '/drupal-php',
          'bin' => 'fiber_local_proxy',
        ]);
      }
    }
    return $this->storage;
  }

}
