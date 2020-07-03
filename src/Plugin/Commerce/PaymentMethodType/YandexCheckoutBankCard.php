<?php

namespace Drupal\yandex_checkout\Plugin\Commerce\PaymentMethodType;

use Drupal\commerce_payment\Entity\PaymentMethodInterface;

/**
 * Provides the yandex_checkout_bankcard payment method type.
 *
 * @CommercePaymentMethodType(
 *   id = "yandex_checkout_bankcard",
 *   label = @Translation("YC account"),
 *   create_label = @Translation("YC account"),
 * )
 */
class YandexCheckoutBankCard extends YandexCheckoutPaymentMethod {

  /**
   * {@inheritdoc}
   */
  public function getLabel() {
    return $this->t('Банковские карты — Visa, Mastercard и Maestro, «Мир»');
  }

  /**
   * {@inheritdoc}
   */
  public function buildLabel(PaymentMethodInterface $payment_method) {
    // TODO: Implement buildLabel() method.
  }

}
