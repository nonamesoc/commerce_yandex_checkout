<?php

namespace Drupal\Tests\yandex_checkout\FunctionalJavascript;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Tests\commerce\FunctionalJavascript\CommerceWebDriverTestBase;

/**
 * Defines base class for payment gateways admin UI test cases.
 */
abstract class PaymentGatewayTestBase extends CommerceWebDriverTestBase {

  use StringTranslationTrait;

  /**
   * The payment gateway storage.
   *
   * @var \Drupal\commerce_payment\PaymentGatewayStorageInterface
   */
  protected $storage;

  /**
   * The payment gateway plugin ID.
   *
   * @var string
   */
  protected $pluginId;

  /**
   * {@inheritdoc}
   */
  public static $modules = ['yandex_checkout'];

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $this->storage = $this->container->get('entity_type.manager')
      ->getStorage('commerce_payment_gateway');
  }

  /**
   * {@inheritdoc}
   */
  protected function getAdministratorPermissions() {
    return array_merge([
      'administer commerce_payment_gateway',
    ], parent::getAdministratorPermissions());
  }

  /**
   * Get new payment gateway configuration.
   *
   * @return array
   *   Payment gateway configuration.
   */
  abstract protected function getPluginConfiguration();

  /**
   * Get payment gateway base fields.
   *
   * @return array
   *   Payment gateway base fields.
   */
  protected function getPluginBaseFields() {
    $pluginId = $this->pluginId;

    $data['label'] = ucfirst(str_replace('_', ' ', $pluginId));
    $data['id'] = $pluginId;
    $data['plugin'] = $pluginId;

    return $data;
  }

  /**
   * Get payment gateway configuration.
   *
   * @return array
   *   Payment gateway configuration.
   */
  protected function getPaymentGatewayConfiguration() {
    return \array_merge($this->getPluginBaseFields(), $this->getPluginConfiguration());
  }

  /**
   * Tests creating a payment gateway.
   */
  public function testAdd() {
    $this->drupalGet('admin/commerce/config/payment-gateways');
    $this->getSession()->getPage()->clickLink('Add payment gateway');
    $this->assertSession()->addressEquals('admin/commerce/config/payment-gateways/add');

    $base_fields = $this->getPluginBaseFields();
    foreach ($base_fields as $field => $value) {
      $this->assertArrayHasKey($field, $base_fields);
      $this->assertSession()->fieldExists($field);
      $this->assertTrue($this->getSession()->getPage()->findField($field)->isVisible());
      $this->getSession()->getPage()->fillField($field, $value);
    }

    $payment_gateway_id = $base_fields['id'];
    $payment_gateway_label = $base_fields['label'];
    $payment_gateway_plugin_id = $base_fields['plugin'];

    $this->getSession()->getPage()->selectFieldOption('plugin', $payment_gateway_plugin_id);
    $this->assertSession()->assertWaitOnAjaxRequest();

    $plugin_config = $this->getPluginConfiguration();
    foreach ($plugin_config as $field => $value) {
      $config_field_name = "configuration[$payment_gateway_plugin_id][$field]";

      $this->assertSession()->fieldExists($config_field_name);
      $this->getSession()->getPage()->fillField($config_field_name, $value);
    }

    $this->submitForm([], $this->t('Save'));
    $this->assertSession()->pageTextContains("Saved the $payment_gateway_label payment gateway.");
    $this->assertSession()->addressEquals('admin/commerce/config/payment-gateways');

    $payment_gateway = $this->storage->load($payment_gateway_id);
    $this->assertEquals($payment_gateway_id, $payment_gateway->id());
    $this->assertEquals($payment_gateway_label, $payment_gateway->label());
    $this->assertEquals($payment_gateway_plugin_id, $payment_gateway->getPluginId());

    $payment_gateway_plugin = $payment_gateway->getPlugin();
    $saved_config = $payment_gateway_plugin->getConfiguration();
    foreach ($plugin_config as $field => $value) {
      $this->assertEquals($value, $saved_config[$field]);
    }
  }

  /**
   * Tests editing a payment gateway.
   */
  public function testEdit() {
    // @todo: add tests.
  }

  /**
   * Tests duplicating a payment gateway.
   */
  public function testDuplicate() {
    // @todo: add tests.
  }

  /**
   * Tests deleting a payment gateway.
   */
  public function testDelete() {
    $payment_gateway = $this->createEntity('commerce_payment_gateway', [
      'id' => 'for_deletion',
      'label' => 'For deletion',
      'plugin' => $this->pluginId,
    ]);
    $this->drupalGet($payment_gateway->toUrl('delete-form'));
    $this->submitForm([], 'Delete');
    $this->assertSession()->addressEquals('admin/commerce/config/payment-gateways');

    $payment_gateway_exists = (bool) $this->storage->load('for_deletion');
    $this->assertEmpty($payment_gateway_exists, 'The payment gateway has been deleted from the database.');
  }

}
