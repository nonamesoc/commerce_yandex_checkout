<?php

namespace Drupal\Tests\yandex_checkout\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Empty test.
 *
 * @group commerce_yandex_checkout
 */
class EmptyTest extends BrowserTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['user', 'block'];

  /**
   * Tests the duplicate form.
   */
  public function testAuth() {
    $user = $this->drupalCreateUser();
    $this->drupalLogin($user);
  }

}
