<?php

namespace Drupal\Tests\yookassa\FunctionalJavascript;

/**
 * Tests the admin UI for yookassa payment gateway.
 *
 * @group commerce_yookassa
 */
class YooKassaPaymentGatewayTest extends PaymentGatewayTestBase {

  /**
   * {@inheritdoc}
   */
  protected $pluginId = 'yookassa';

  /**
   * {@inheritdoc}
   */
  protected function getPluginConfiguration() {
    return [
      'shop_id' => 87654321,
      'secret_key' => 'test_3iEwkXIv5RmV8quKUPadlMIK8oSomIbA6hiU23mSxwU',
      'display_label' => 'YooKassa',
      'description_template' => 'Order #[commerce_order:order_id]',
      'receipt_enabled' => 1,
    ];
  }

}
