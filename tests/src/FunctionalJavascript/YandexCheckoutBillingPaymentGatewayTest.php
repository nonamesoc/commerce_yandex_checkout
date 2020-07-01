<?php

namespace Drupal\Tests\yandex_checkout\FunctionalJavascript;

/**
 * Tests the admin UI for yandex_checkout_billing payment gateway.
 *
 * @group commerce_yandex_checkout
 */
class YandexCheckoutBillingPaymentGatewayTest extends PaymentGatewayTestBase {

  /**
   * {@inheritdoc}
   */
  protected $pluginId = 'yandex_checkout_billing';

  /**
   * {@inheritdoc}
   */
  protected function getPluginConfiguration() {
    return [
      'billing_id' => $this->randomGenerator->string(),
      'display_label' => 'Yandex Checkout Billing',
      'narrative' => 'Order #[commerce_order:order_id]',
    ];
  }

}
