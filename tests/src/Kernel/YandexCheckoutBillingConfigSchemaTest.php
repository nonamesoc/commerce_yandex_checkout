<?php

namespace Drupal\Tests\yandex_checkout\Kernel;

/**
 * Tests the yandex_checkout_billing config schema.
 *
 * @group commerce_yandex_checkout
 */
class YandexCheckoutBillingConfigSchemaTest extends PaymentGatewayConfigSchemaTestBase {

  /**
   * {@inheritdoc}
   */
  protected function getPaymentGatewayConfig() {
    return [
      'id' => $this->getRandomGenerator()->name(),
      'label' => $this->getRandomGenerator()->name(),
      'plugin' => 'yandex_checkout_billing',
      'billing_id' => 716412,
      'secret_key' => 'test_3iEwkXIv5RmV8quKUPadlMIK8oSomIbA6hiU23mSxwU',
      'display_label' => $this->getRandomGenerator()->name(),
    ];
  }

}
