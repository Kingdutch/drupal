<?php
// phpcs:ignoreFile

/**
 * This file was generated via php core/scripts/generate-fiber-local-proxy.php 'Drupal\Core\Session\AccountProxy' "core/lib/Drupal/Core".
 */

namespace Drupal\Core\FiberLocalProxy\Session {

    /**
     * Provides a proxy class for \Drupal\Core\Session\AccountProxy.
     *
     * @see \Drupal\Component\ProxyBuilder
     */
    class AccountProxy implements \Drupal\Core\Session\AccountProxyInterface
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
         * @var \Drupal\Core\Session\AccountProxy
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
        public function setAccount(\Drupal\Core\Session\AccountInterface $account)
        {
            return $this->fiberLocalInstance()->setAccount($account);
        }

        /**
         * {@inheritdoc}
         */
        public function getAccount()
        {
            return $this->fiberLocalInstance()->getAccount();
        }

        /**
         * {@inheritdoc}
         */
        public function id()
        {
            return $this->fiberLocalInstance(FALSE)->id();
        }

        /**
         * {@inheritdoc}
         */
        public function getRoles($exclude_locked_roles = false)
        {
            return $this->fiberLocalInstance()->getRoles($exclude_locked_roles);
        }

        /**
         * {@inheritdoc}
         */
        public function hasRole(string $rid): bool
        {
            return $this->fiberLocalInstance()->hasRole($rid);
        }

        /**
         * {@inheritdoc}
         */
        public function hasPermission($permission)
        {
            return $this->fiberLocalInstance()->hasPermission($permission);
        }

        /**
         * {@inheritdoc}
         */
        public function isAuthenticated()
        {
            return $this->fiberLocalInstance()->isAuthenticated();
        }

        /**
         * {@inheritdoc}
         */
        public function isAnonymous()
        {
            return $this->fiberLocalInstance()->isAnonymous();
        }

        /**
         * {@inheritdoc}
         */
        public function getPreferredLangcode($fallback_to_default = true)
        {
            return $this->fiberLocalInstance()->getPreferredLangcode($fallback_to_default);
        }

        /**
         * {@inheritdoc}
         */
        public function getPreferredAdminLangcode($fallback_to_default = true)
        {
            return $this->fiberLocalInstance()->getPreferredAdminLangcode($fallback_to_default);
        }

        /**
         * {@inheritdoc}
         */
        public function getAccountName()
        {
            return $this->fiberLocalInstance()->getAccountName();
        }

        /**
         * {@inheritdoc}
         */
        public function getDisplayName()
        {
            return $this->fiberLocalInstance()->getDisplayName();
        }

        /**
         * {@inheritdoc}
         */
        public function getEmail()
        {
            return $this->fiberLocalInstance()->getEmail();
        }

        /**
         * {@inheritdoc}
         */
        public function getTimeZone()
        {
            return $this->fiberLocalInstance()->getTimeZone();
        }

        /**
         * {@inheritdoc}
         */
        public function getLastAccessedTime()
        {
            return $this->fiberLocalInstance()->getLastAccessedTime();
        }

        /**
         * {@inheritdoc}
         */
        public function setInitialAccountId($account_id)
        {
            return $this->fiberLocalInstance()->setInitialAccountId($account_id);
        }





    }

}
