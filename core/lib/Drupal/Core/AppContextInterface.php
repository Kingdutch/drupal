<?php

namespace Drupal\Core;

/**
 * Provides access to application context information.
 *
 * This interface defines methods for accessing information about the current
 * application context, including paths, URLs, and other context-specific data.
 */
interface AppContextInterface {

  /**
   * Gets the base path for the Drupal installation.
   *
   * This represents the path from the domain root to the Drupal installation
   * directory. It always starts and ends with a forward slash.
   *
   * Examples:
   * - "/" for a Drupal installation at the root of the domain
   * - "/drupal/" for a Drupal installation in a "drupal" subdirectory
   *
   * @return string
   *   The base path with trailing slash.
   */
  public function getBasePath(): string;

  /**
   * Gets the base URL for the Drupal installation.
   *
   * This includes the scheme, host, port (if non-standard), and base path.
   *
   * Examples:
   * - "https://example.com" for a Drupal installation at the root
   * - "https://example.com/drupal" for a Drupal installation in a subdirectory
   *
   * @return string
   *   The base URL including scheme, host, and path.
   */
  public function getBaseUrl(): string;

  /**
   * Gets the base root URL for the Drupal installation.
   *
   * This includes only the scheme and host (with port if non-standard).
   *
   * Examples:
   * - "https://example.com"
   * - "http://localhost:8080"
   *
   * @return string
   *   The base root URL including scheme and host.
   */
  public function getBaseRoot(): string;

  /**
   * Gets the secure (HTTPS) version of the base URL.
   *
   * @return string
   *   The secure base URL.
   */
  public function getSecureBaseUrl(): string;

  /**
   * Gets the insecure (HTTP) version of the base URL.
   *
   * @return string
   *   The insecure base URL.
   */
  public function getInsecureBaseUrl(): string;

  /**
   * Gets the app root directory path.
   *
   * This is the absolute filesystem path to the Drupal installation directory.
   *
   * Example: "/var/www/html/drupal"
   *
   * @return string
   *   The absolute filesystem path to the app root directory.
   */
  public function getAppRoot(): string;

}
