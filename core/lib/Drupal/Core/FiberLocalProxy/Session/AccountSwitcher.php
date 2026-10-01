<?php
// phpcs:ignoreFile

/**
 * This file was generated via php core/scripts/generate-fiber-local-proxy.php 'Drupal\Core\Session\AccountSwitcher' "core/lib/Drupal/Core".
 */

namespace Drupal\Core\FiberLocalProxy\Session {

    /**
     * Provides a proxy class for \Drupal\Core\Session\AccountSwitcher.
     *
     * @see \Drupal\Component\ProxyBuilder
     */
    class AccountSwitcher implements \Drupal\Core\Session\AccountSwitcherInterface
    {

        use \Drupal\Core\DependencyInjection\DependencySerializationTrait;

        /**
         * The id of the original proxied service.
         *
         * @var string
         */
        protected $drupalProxyOriginalServiceId;

        /**
         * The real proxied service, after it was lazy loaded.
         *
         * @var \Drupal\Core\Session\AccountSwitcher
         */
        protected $service;

        /**
         * The service container.
         *
         * @var \Symfony\Component\DependencyInjection\ContainerInterface
         */
        protected $container;

        /**
         * Constructs a ProxyClass Drupal proxy object.
         *
         * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
         *   The container.
         * @param string $drupal_proxy_original_service_id
         *   The service ID of the original service.
         */
        public function __construct(\Symfony\Component\DependencyInjection\ContainerInterface $container, $drupal_proxy_original_service_id)
        {
            $this->container = $container;
            $this->drupalProxyOriginalServiceId = $drupal_proxy_original_service_id;
        }

        /**
         * Resolves the instance of the service that belongs to the current fiber.
         *
         * @param bool $write
         *   Whether the caller may mutate the instance.
         *
         * @return object
         *   The instance to call.
         */
        protected function fiberLocalInstance(bool $write = TRUE)
        {
            return \Drupal\Core\Async\FiberLocalServices::resolve($this->container, $this->drupalProxyOriginalServiceId, $write);
        }

        /**
         * {@inheritdoc}
         */
        public function switchTo(\Drupal\Core\Session\AccountInterface $account)
        {
            return $this->fiberLocalInstance()->switchTo($account);
        }

        /**
         * {@inheritdoc}
         */
        public function switchBack()
        {
            return $this->fiberLocalInstance()->switchBack();
        }

    }

}
