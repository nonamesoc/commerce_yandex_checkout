<?php

namespace Drupal\Tests\yandex_checkout\Kernel;

/**
 * Tests the yandex_checkout config schema.
 *
 * @group commerce_yandex_checkout
 */
class YandexCheckoutConfigSchemaTest extends PaymentGatewayConfigSchemaTestBase {

  /**
   * {@inheritdoc}
   */
  protected function getPaymentGatewayConfig() {
    return [
      'id' => $this->getRandomGenerator()->name(),
      'label' => $this->getRandomGenerator()->name(),
      'plugin' => 'yandex_checkout',
      'shop_id' => 716412,
      'secret_key' => 'test_3iEwkXIv5RmV8quKUPadlMIK8oSomIbA6hiU23mSxwU',
      'display_label' => $this->getRandomGenerator()->name(),
      'description_template' => 'Order [commerce_order:order_id]',
      'receipt_enabled' => \random_int(0, 1),
      'default_tax' => \random_int(1, 6),
      'yandex_checkout_tax' => [
        '78d06aae-ba8b-4243-bfad-196472aca1d4' => \random_int(1, 6),
      ],
    ];
  }

}
