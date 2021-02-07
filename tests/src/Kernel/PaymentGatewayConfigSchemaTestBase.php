<?php

namespace Drupal\Tests\yookassa\Kernel;

use Drupal\Tests\SchemaCheckTestTrait;
use Drupal\KernelTests\KernelTestBase;

/**
 * Defines base class for payment gateways config schema tests.
 */
abstract class PaymentGatewayConfigSchemaTestBase extends KernelTestBase {

  use SchemaCheckTestTrait;

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'system',
    'profile',
    'commerce',
    'commerce_price',
    'commerce_order',
    'commerce_payment',
    'yookassa',
  ];

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
   * Get new payment gateway configuration.
   *
   * @return array
   *   Payment gateway configuration.
   */
  abstract protected function getPaymentGatewayConfig();

  /**
   * Tests payment gateway config schema.
   */
  public function testConfigSchema() {
    $configuration = $this->getPaymentGatewayConfig();
    $gateway = $this->paymentGatewayStorage->create($configuration);
    $gateway->save();

    $config = $this->config('commerce_payment.commerce_payment_gateway.' . $gateway->id());
    $this->assertEqual($config->get('id'), $gateway->id());
    $this->assertConfigSchema($this->typedConfig, $config->getName(), $config->get());
  }

}
