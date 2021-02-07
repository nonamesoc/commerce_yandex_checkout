<?php

namespace Drupal\Tests\yookassa\Kernel;

/**
 * Tests the yookassa config schema.
 *
 * @group commerce_yandex_checkout
 */
class YooKassaConfigSchemaTest extends PaymentGatewayConfigSchemaTestBase {

  /**
   * {@inheritdoc}
   */
  protected function getPaymentGatewayConfig() {
    return [
      'id' => $this->getRandomGenerator()->name(),
      'label' => $this->getRandomGenerator()->name(),
      'plugin' => 'yookassa',
      'shop_id' => 716412,
      'secret_key' => 'test_3iEwkXIv5RmV8quKUPadlMIK8oSomIbA6hiU23mSxwU',
      'display_label' => $this->getRandomGenerator()->name(),
      'description_template' => 'Order [commerce_order:order_id]',
      'receipt_enabled' => \random_int(0, 1),
      'default_tax' => \random_int(1, 6),
      'yookassa_tax' => [
        '78d06aae-ba8b-4243-bfad-196472aca1d4' => \random_int(1, 6),
      ],
    ];
  }

}
