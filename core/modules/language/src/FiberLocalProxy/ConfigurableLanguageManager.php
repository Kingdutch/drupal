<?php
// phpcs:ignoreFile

/**
 * This file was generated via php core/scripts/generate-fiber-local-proxy.php 'Drupal\language\ConfigurableLanguageManager' "core/modules/language/src".
 */

namespace Drupal\language\FiberLocalProxy {

    /**
     * Provides a proxy class for \Drupal\language\ConfigurableLanguageManager.
     *
     * @see \Drupal\Component\ProxyBuilder
     */
    class ConfigurableLanguageManager implements \Drupal\language\ConfigurableLanguageManagerInterface
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
         * @var \Drupal\language\ConfigurableLanguageManager
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
        public static function rebuildServices()
        {
            return \Drupal\language\ConfigurableLanguageManager::rebuildServices();
        }

        /**
         * {@inheritdoc}
         */
        public function init()
        {
            return $this->fiberLocalInstance()->init();
        }

        /**
         * {@inheritdoc}
         */
        public function isMultilingual()
        {
            return $this->fiberLocalInstance()->isMultilingual();
        }

        /**
         * {@inheritdoc}
         */
        public function getLanguageTypes()
        {
            return $this->fiberLocalInstance()->getLanguageTypes();
        }

        /**
         * {@inheritdoc}
         */
        public function getDefinedLanguageTypes()
        {
            return $this->fiberLocalInstance()->getDefinedLanguageTypes();
        }

        /**
         * {@inheritdoc}
         */
        public function getDefinedLanguageTypesInfo()
        {
            return $this->fiberLocalInstance()->getDefinedLanguageTypesInfo();
        }

        /**
         * {@inheritdoc}
         */
        public function saveLanguageTypesConfiguration(array $values)
        {
            return $this->fiberLocalInstance()->saveLanguageTypesConfiguration($values);
        }

        /**
         * {@inheritdoc}
         */
        public function getCurrentLanguage($type = 'language_interface')
        {
            return $this->fiberLocalInstance()->getCurrentLanguage($type);
        }

        /**
         * {@inheritdoc}
         */
        public function reset($type = NULL)
        {
            return $this->fiberLocalInstance()->reset($type);
        }

        /**
         * {@inheritdoc}
         */
        public function getNegotiator()
        {
            return $this->fiberLocalInstance()->getNegotiator();
        }

        /**
         * {@inheritdoc}
         */
        public function setNegotiator(\Drupal\language\LanguageNegotiatorInterface $negotiator)
        {
            return $this->fiberLocalInstance()->setNegotiator($negotiator);
        }

        /**
         * {@inheritdoc}
         */
        public function getLanguages($flags = 1)
        {
            return $this->fiberLocalInstance()->getLanguages($flags);
        }

        /**
         * {@inheritdoc}
         */
        public function getNativeLanguages()
        {
            return $this->fiberLocalInstance()->getNativeLanguages();
        }

        /**
         * {@inheritdoc}
         */
        public function updateLockedLanguageWeights()
        {
            return $this->fiberLocalInstance()->updateLockedLanguageWeights();
        }

        /**
         * {@inheritdoc}
         */
        public function getFallbackCandidates(array $context = array (
        ))
        {
            return $this->fiberLocalInstance()->getFallbackCandidates($context);
        }

        /**
         * {@inheritdoc}
         */
        public function getLanguageSwitchLinks($type, \Drupal\Core\Url $url)
        {
            return $this->fiberLocalInstance()->getLanguageSwitchLinks($type, $url);
        }

        /**
         * {@inheritdoc}
         */
        public function setConfigOverrideLanguage(?\Drupal\Core\Language\LanguageInterface $language = NULL)
        {
            return $this->fiberLocalInstance()->setConfigOverrideLanguage($language);
        }

        /**
         * {@inheritdoc}
         */
        public function getConfigOverrideLanguage()
        {
            return $this->fiberLocalInstance()->getConfigOverrideLanguage();
        }

        /**
         * {@inheritdoc}
         */
        public function getLanguageConfigOverride($langcode, $name)
        {
            return $this->fiberLocalInstance()->getLanguageConfigOverride($langcode, $name);
        }

        /**
         * {@inheritdoc}
         */
        public function getLanguageConfigOverrideStorage($langcode)
        {
            return $this->fiberLocalInstance()->getLanguageConfigOverrideStorage($langcode);
        }

        /**
         * {@inheritdoc}
         */
        public function getStandardLanguageListWithoutConfigured()
        {
            return $this->fiberLocalInstance()->getStandardLanguageListWithoutConfigured();
        }

        /**
         * {@inheritdoc}
         */
        public function getNegotiatedLanguageMethod($type = 'language_interface')
        {
            return $this->fiberLocalInstance()->getNegotiatedLanguageMethod($type);
        }

        /**
         * {@inheritdoc}
         */
        public function getDefaultLanguage()
        {
            return $this->fiberLocalInstance()->getDefaultLanguage();
        }

        /**
         * {@inheritdoc}
         */
        public function getLanguage($langcode)
        {
            return $this->fiberLocalInstance()->getLanguage($langcode);
        }

        /**
         * {@inheritdoc}
         */
        public function getLanguageName($langcode)
        {
            return $this->fiberLocalInstance()->getLanguageName($langcode);
        }

        /**
         * {@inheritdoc}
         */
        public function getDefaultLockedLanguages($weight = 0)
        {
            return $this->fiberLocalInstance()->getDefaultLockedLanguages($weight);
        }

        /**
         * {@inheritdoc}
         */
        public function isLanguageLocked($langcode)
        {
            return $this->fiberLocalInstance()->isLanguageLocked($langcode);
        }

        /**
         * {@inheritdoc}
         */
        public static function getStandardLanguageList()
        {
            return \Drupal\Core\Language\LanguageManager::getStandardLanguageList();
        }

        /**
         * {@inheritdoc}
         */
        public static function getUnitedNationsLanguageList()
        {
            return \Drupal\Core\Language\LanguageManager::getUnitedNationsLanguageList();
        }





    }

}
