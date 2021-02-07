<?php

namespace Drupal\Tests\yookassa\FunctionalJavascript;

/**
 * Tests the admin UI for yookassa_billing payment gateway.
 *
 * @group commerce_yookassa
 */
class YooKassaBillingPaymentGatewayTest extends PaymentGatewayTestBase {

  /**
   * {@inheritdoc}
   */
  protected $pluginId = 'yookassa_billing';

  /**
   * {@inheritdoc}
   */
  protected function getPluginConfiguration() {
    return [
      'billing_id' => $this->randomGenerator->string(),
      'display_label' => 'YooKassa Billing',
      'narrative' => 'Order #[commerce_order:order_id]',
    ];
  }

}
