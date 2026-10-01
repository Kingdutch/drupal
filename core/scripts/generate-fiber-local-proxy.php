#!/usr/bin/env php
<?php

/**
 * @file
 * Generates the per-fiber proxy class for a service tagged fiber_local.
 *
 * Usage, from the Drupal root:
 * @code
 * php core/scripts/generate-fiber-local-proxy.php \
 *   'Drupal\Core\Session\AccountProxy' "core/lib/Drupal/Core"
 * php core/scripts/generate-fiber-local-proxy.php \
 *   'Drupal\my_module\MyService' "modules/contrib/my_module/src"
 * @endcode
 *
 * Proxies that are not committed this way are generated at runtime into the
 * fiber_local_proxy PHP storage; committing them makes the generated code
 * reviewable and keeps runtime generation out of core.
 */

use Drupal\Core\Async\FiberLocalProxyBuilder;
use Drupal\Core\DrupalKernel;
use Drupal\Core\Site\Settings;
use Symfony\Component\HttpFoundation\Request;

if (PHP_SAPI !== 'cli') {
  return;
}

$autoloader = require __DIR__ . '/../../autoload.php';
if ($argc !== 3) {
  fwrite(STDERR, "Usage: {$argv[0]} 'Fully\\Qualified\\ClassName' \"path/to/namespace/root\"\n");
  exit(1);
}
$request = Request::createFromGlobals();
Settings::initialize(dirname(__DIR__, 2), DrupalKernel::findSitePath($request), $autoloader);
DrupalKernel::createFromRequest($request, $autoloader, 'prod')->boot();

$class_name = ltrim($argv[1], '\\');
$namespace_root = rtrim($argv[2], '/');
$match = [];
if (!preg_match('/([a-zA-Z0-9_]+\\\\[a-zA-Z0-9_]+)\\\\(.+)/', $class_name, $match)) {
  fwrite(STDERR, "The class name must have at least three namespace segments.\n");
  exit(1);
}
// The class may belong to a module that is not installed on this site.
$autoloader->addPsr4($match[1] . '\\', $namespace_root);
$proxy_filename = $namespace_root . '/' . FiberLocalProxyBuilder::NAMESPACE_SEGMENT . '/' . str_replace('\\', '/', $match[2]) . '.php';

$file_string = "<?php\n// phpcs:ignoreFile\n\n/**\n * This file was generated via php core/scripts/generate-fiber-local-proxy.php '$class_name' \"$namespace_root\".\n */\n" . (new FiberLocalProxyBuilder())->build($class_name);

if (!is_dir(dirname($proxy_filename))) {
  mkdir(dirname($proxy_filename), 0775, TRUE);
}
file_put_contents($proxy_filename, $file_string);
echo sprintf("Fiber-local proxy of class %s written to %s\n", $class_name, $proxy_filename);
