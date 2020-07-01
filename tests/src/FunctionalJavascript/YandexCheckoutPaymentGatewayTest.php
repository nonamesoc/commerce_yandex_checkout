<?php

namespace Drupal\Tests\yandex_checkout\FunctionalJavascript;

/**
 * Tests the admin UI for yandex_checkout payment gateway.
 *
 * @group commerce_yandex_checkout
 */
class YandexCheckoutPaymentGatewayTest extends PaymentGatewayTestBase {

  /**
   * {@inheritdoc}
   */
  protected $pluginId = 'yandex_checkout';

  /**
   * {@inheritdoc}
   */
  protected function getPluginConfiguration() {
    return [
        'shop_id' => 87654321,
        'secret_key' => 'test_3iEwkXIv5RmV8quKUPadlMIK8oSomIbA6hiU23mSxwU',
        'display_label' => 'Yandex Checkout',
        'description_template' => 'Order #[commerce_order:order_id]',
        'receipt_enabled' => 1,
    ];
  }

}
