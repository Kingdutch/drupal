<?php

namespace Drupal\Core;

use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Default implementation of AppContextInterface.
 */
class AppContext implements AppContextInterface {
  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The app root directory.
   *
   * @var string
   */
  protected $appRoot;

  /**
   * Constructs a new AppContext.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param string $app_root
   *   The app root directory.
   */
  public function __construct(RequestStack $request_stack, string $app_root) {
    $this->requestStack = $request_stack;
    $this->appRoot = $app_root;
  }

  /**
   * {@inheritdoc}
   */
  public function getBasePath(): string {
    $request = $this->requestStack->getCurrentRequest();
    if (!$request) {
      return '/';
    }

    $base_path = '/';
    // For a request URI of '/index.php/foo', $_SERVER['SCRIPT_NAME'] is
    // '/index.php', whereas $_SERVER['PHP_SELF'] is '/index.php/foo'.
    if ($dir = rtrim(dirname($request->server->get('SCRIPT_NAME')), '\/')) {
      // Remove "core" directory if present, allowing install.php,
      // authorize.php, and others to auto-detect a base path.
      $core_position = strrpos($dir, '/core');
      if ($core_position !== FALSE && strlen($dir) - 5 == $core_position) {
        $base_path = substr($dir, 0, $core_position);
      }
      else {
        $base_path = $dir;
      }
      $base_path .= '/';
    }

    return $base_path;
  }

  /**
   * {@inheritdoc}
   */
  public function getBaseUrl(): string {
    $request = $this->requestStack->getCurrentRequest();
    if (!$request) {
      return '';
    }

    $base_root = $this->getBaseRoot();
    $base_url = $base_root;

    $base_path = $this->getBasePath();
    if ($base_path !== '/' && $base_path !== '') {
      // Ensure the base path is included in the base URL.
      $base_url .= rtrim($base_path, '/');
    }

    return $base_url;
  }

  /**
   * {@inheritdoc}
   */
  public function getBaseRoot(): string {
    $request = $this->requestStack->getCurrentRequest();
    if (!$request) {
      return '';
    }

    return $request->getSchemeAndHttpHost();
  }

  /**
   * {@inheritdoc}
   */
  public function getSecureBaseUrl(): string {
    return str_replace('http://', 'https://', $this->getBaseUrl());
  }

  /**
   * {@inheritdoc}
   */
  public function getInsecureBaseUrl(): string {
    return str_replace('https://', 'http://', $this->getBaseUrl());
  }

  /**
   * {@inheritdoc}
   */
  public function getAppRoot(): string {
    return $this->appRoot;
  }

}
