<?php
// phpcs:ignoreFile

/**
 * This file was generated via php core/scripts/generate-fiber-local-proxy.php 'Drupal\language\LanguageNegotiator' "core/modules/language/src".
 */

namespace Drupal\language\FiberLocalProxy {

    /**
     * Provides a proxy class for \Drupal\language\LanguageNegotiator.
     *
     * @see \Drupal\Component\ProxyBuilder
     */
    class LanguageNegotiator implements \Drupal\language\LanguageNegotiatorInterface
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
         * @var \Drupal\language\LanguageNegotiator
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
        public function initLanguageManager()
        {
            return $this->fiberLocalInstance()->initLanguageManager();
        }

        /**
         * {@inheritdoc}
         */
        public function reset()
        {
            return $this->fiberLocalInstance()->reset();
        }

        /**
         * {@inheritdoc}
         */
        public function setCurrentUser(\Drupal\Core\Session\AccountInterface $current_user)
        {
            return $this->fiberLocalInstance()->setCurrentUser($current_user);
        }

        /**
         * {@inheritdoc}
         */
        public function initializeType($type)
        {
            return $this->fiberLocalInstance()->initializeType($type);
        }

        /**
         * {@inheritdoc}
         */
        public function getNegotiationMethods($type = NULL)
        {
            return $this->fiberLocalInstance()->getNegotiationMethods($type);
        }

        /**
         * {@inheritdoc}
         */
        public function getNegotiationMethodInstance($method_id)
        {
            return $this->fiberLocalInstance()->getNegotiationMethodInstance($method_id);
        }

        /**
         * {@inheritdoc}
         */
        public function getPrimaryNegotiationMethod($type)
        {
            return $this->fiberLocalInstance()->getPrimaryNegotiationMethod($type);
        }

        /**
         * {@inheritdoc}
         */
        public function isNegotiationMethodEnabled($method_id, $type = NULL)
        {
            return $this->fiberLocalInstance()->isNegotiationMethodEnabled($method_id, $type);
        }

        /**
         * {@inheritdoc}
         */
        public function saveConfiguration($type, $enabled_methods)
        {
            return $this->fiberLocalInstance()->saveConfiguration($type, $enabled_methods);
        }

        /**
         * {@inheritdoc}
         */
        public function purgeConfiguration()
        {
            return $this->fiberLocalInstance()->purgeConfiguration();
        }

        /**
         * {@inheritdoc}
         */
        public function updateConfiguration(array $types)
        {
            return $this->fiberLocalInstance()->updateConfiguration($types);
        }

        /**
         * {@inheritdoc}
         */
        public function setLoggerFactory(\Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory)
        {
            return $this->fiberLocalInstance()->setLoggerFactory($logger_factory);
        }

    }

}
