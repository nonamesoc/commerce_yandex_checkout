<?php

namespace Drupal\Tests\commerce_yandex_checkout\Kernel;

use Drupal\Tests\SchemaCheckTestTrait;
use Drupal\KernelTests\KernelTestBase;

/**
 * Ensures the payment gateway have valid config schema.
 *
 * @group commerce_yandex_checkout
 */
class PaymentGatewayConfigSchemaTest extends KernelTestBase {

  use SchemaCheckTestTrait;

  /**
   * {@inheritdoc}
   */
  public static $modules = ['system', 'profile', 'commerce', 'commerce_price', 'commerce_order', 'commerce_payment', 'yandex_checkout'];

  /**
   * The payment gateway storage.
   *
   * @var \Drupal\commerce_payment\PaymentGatewayStorageInterface
   */
  protected $paymentGatewayStorage;

  /**
   * The typed config handler.
   *
   * @var \Drupal\Core\Config\TypedConfigManagerInterface
   */
  protected $typedConfig;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $entity_type_manager = $this->container->get('entity_type.manager');
    $this->paymentGatewayStorage = $entity_type_manager->getStorage('commerce_payment_gateway');
    $this->typedConfig = $this->container->get('config.typed');
  }

  /**
   * Tests whether the payment gateway schema is valid.
   */
  public function testValidYandexCheckoutConfigSchema() {
    $gateway_configuration = [
      'id' => $this->getRandomGenerator()->name(),
      'label' => $this->getRandomGenerator()->name(),
      'plugin' => 'yandex_checkout',
      'shop_id' => 716412,
      'secret_key' => 'test_3iEwkXIv5RmV8quKUPadlMIK8oSomIbA6hiU23mSxwU',
      'display_label' => $this->getRandomGenerator()->name(),
      'description_template' => 'Order #%order_id%',
      'receipt_enabled' => \random_int(0, 1),
      'default_tax' => \random_int(1, 6),
      'yandex_checkout_tax' => [
        '78d06aae-ba8b-4243-bfad-196472aca1d4' => \random_int(1, 6),
      ],
    ];

    $gateway = $this->paymentGatewayStorage->create($gateway_configuration);
    $gateway->save();

    $config = $this->config('commerce_payment.commerce_payment_gateway.' . $gateway->id());
    $this->assertEqual($config->get('id'), $gateway->id());
    $this->assertConfigSchema($this->typedConfig, $config->getName(), $config->get());
  }

}
