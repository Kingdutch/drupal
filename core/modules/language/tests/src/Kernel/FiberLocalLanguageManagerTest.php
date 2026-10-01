<?php

declare(strict_types=1);

namespace Drupal\Tests\language\Kernel;

use Drupal\Core\Async\Async;
use Drupal\Core\Language\LanguageInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\FiberLocalProxy\ConfigurableLanguageManager as ConfigurableLanguageManagerProxy;
use Drupal\language\FiberLocalProxy\LanguageNegotiator as LanguageNegotiatorProxy;
use Drupal\language\LanguageNegotiatorInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that the language manager is local to each fiber.
 */
#[Group('language')]
#[Group('Async')]
#[RunTestsInSeparateProcesses]
class FiberLocalLanguageManagerTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'language'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['language']);
    ConfigurableLanguage::createFromLangcode('de')->save();
  }

  /**
   * Tests that a task gets a fully wired manager even before main used it.
   *
   * The instance the container builds is the template every fiber's copy is
   * made from, so it has to carry the negotiator itself rather than receive
   * it through a write on the main fiber's copy.
   */
  public function testTemplateCarriesNegotiator(): void {
    $seen = Async::run(static function () {
      $manager = \Drupal::languageManager();
      return [
        $manager instanceof ConfigurableLanguageManagerProxy,
        $manager->getNegotiator() instanceof LanguageNegotiatorInterface,
        $manager->getCurrentLanguage(LanguageInterface::TYPE_INTERFACE)->getId(),
      ];
    })->await();
    $this->assertSame([TRUE, TRUE, 'en'], $seen);

    $manager = \Drupal::languageManager();
    $this->assertInstanceOf(ConfigurableLanguageManagerProxy::class, $manager);
    $this->assertInstanceOf(LanguageNegotiatorInterface::class, $manager->getNegotiator());
    // The manager and the negotiator are isolated as a pair: the manager's
    // copies all delegate to the negotiator through its own proxy.
    $this->assertInstanceOf(LanguageNegotiatorProxy::class, $manager->getNegotiator());
    $this->assertSame($manager->getNegotiator(), $this->container->get('language_negotiator'));
  }

}
