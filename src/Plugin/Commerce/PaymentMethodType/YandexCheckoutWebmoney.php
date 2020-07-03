<?php

namespace Drupal\yandex_checkout\Plugin\Commerce\PaymentMethodType;

use Drupal\commerce_payment\Entity\PaymentMethodInterface;

/**
 * Provides the yandex_checkout_webmoney payment method type.
 *
 * @CommercePaymentMethodType(
 *   id = "yandex_checkout_webmoney",
 *   label = @Translation("YC account"),
 *   create_label = @Translation("YC account"),
 * )
 */
class YandexCheckoutWebmoney extends YandexCheckoutPaymentMethod {

  /**
   * {@inheritdoc}
   */
  public function getLabel() {
    return $this->t('Webmoney');
  }

  /**
   * {@inheritdoc}
   */
  public function buildLabel(PaymentMethodInterface $payment_method) {
    // TODO: Implement buildLabel() method.
  }

}
