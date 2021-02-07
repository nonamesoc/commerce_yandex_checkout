<?php

namespace Drupal\Tests\yookassa\Kernel;

/**
 * Tests the yookassa_billing config schema.
 *
 * @group commerce_yandex_checkout
 */
class YooKassaBillingConfigSchemaTest extends PaymentGatewayConfigSchemaTestBase {

  /**
   * {@inheritdoc}
   */
  protected function getPaymentGatewayConfig() {
    return [
      'id' => $this->getRandomGenerator()->name(),
      'label' => $this->getRandomGenerator()->name(),
      'plugin' => 'yookassa_billing',
      'billing_id' => 716412,
      'secret_key' => 'test_3iEwkXIv5RmV8quKUPadlMIK8oSomIbA6hiU23mSxwU',
      'display_label' => $this->getRandomGenerator()->name(),
    ];
  }

}
