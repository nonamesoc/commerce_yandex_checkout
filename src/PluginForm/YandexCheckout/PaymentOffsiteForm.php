<?php

namespace Drupal\yandex_checkout\PluginForm\YandexCheckout;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Exception\PaymentGatewayException;
use Drupal\commerce_payment\PluginForm\PaymentOffsiteForm as BasePaymentOffsiteForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\yandex_checkout\Plugin\Commerce\PaymentGateway\YandexCheckout;
use YandexCheckout\Common\Exceptions\ApiException;
use YandexCheckout\Model\ConfirmationType;
use YandexCheckout\Model\Payment as PaymentModel;
use YandexCheckout\Request\Payments\CreatePaymentRequest;

/**
 * Offsite payment form.
 */
class PaymentOffsiteForm extends BasePaymentOffsiteForm {

  /**
   * Build configuration form.
   *
   * @param array $form
   *   Configuration form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   Configuration form.
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    try {
      $form = parent::buildConfigurationForm($form, $form_state);

      /** @var \Drupal\commerce_payment\Entity\PaymentInterface $payment */
      $payment = $this->entity;
      /** @var \Drupal\yandex_checkout\Plugin\Commerce\PaymentGateway\YandexCheckout $paymentGatewayPlugin */
      $paymentGatewayPlugin = $payment->getPaymentGateway()->getPlugin();
      $client = $paymentGatewayPlugin->apiClient;
      $order = $payment->getOrder();
      $amount = $order->getTotalPrice();
      $config = $paymentGatewayPlugin->getConfiguration();

      $builder = CreatePaymentRequest::builder()
        ->setAmount($amount->getNumber())
        ->setCapture(TRUE)
        ->setDescription($this->createDescription($order, $config))
        ->setConfirmation([
          'type' => ConfirmationType::REDIRECT,
          'returnUrl' => $form['#return_url'],
        ])
        ->setMetadata([
          'cms_name' => 'ya_api_drupal8',
          'module_version' => YandexCheckout::YAMONEY_MODULE_VERSION,
        ]);

      if ($config['receipt_enabled'] == 1) {
        $profile = $order->getCustomer();
        $builder->setReceiptEmail($profile->getEmail());
        $items = $order->getItems();
        /** @var \Drupal\commerce_order\Entity\OrderItem $item */
        foreach ($items as $item) {
          /** @var \Drupal\commerce_order\Plugin\Field\FieldType\AdjustmentItemList $adjustments */
          $adjustments = $item->get('adjustments');

          $taxUuid = NULL;
          $percentage = 0;
          foreach ($adjustments->getValue() as $adjustmentValue) {
            /** @var \Drupal\commerce_order\Adjustment $adjustment */
            $adjustment = $adjustmentValue['value'];
            if ($adjustment->getType() == 'tax') {
              $sourceId = explode('|', $adjustment->getSourceId());
              $taxUuid = $sourceId[2];
              $percentage = $adjustment->getPercentage();
            }
          }
          if (in_array($taxUuid, array_keys($config['yandex_checkout_tax']))) {
            $vat_code = $config['yandex_checkout_tax'][$taxUuid];
          }
          else {
            $vat_code = $config['default_tax'];
          }

          $priceWithTax = $item->getUnitPrice()->getNumber() * (1 + $percentage);
          $builder->addReceiptItem($item->getTitle(), $priceWithTax, $item->getQuantity(), $vat_code);
        }
      }
      $paymentRequest = $builder->build();
      if (($config['receipt_enabled'] == 1) && $paymentRequest->getReceipt() !== NULL) {
        $paymentRequest->getReceipt()->normalize($paymentRequest->getAmount());
      }
      $response = $client->createPayment($paymentRequest);

      $payment_storage = \Drupal::entityTypeManager()->getStorage('commerce_payment');
      $payments = $payment_storage->loadByProperties(['order_id' => $order->id()]);
      if ($payments) {
        $payment = reset($payments);
        $payment->enforceIsNew(FALSE);
      }
      $payment->setRemoteId($response->getId());
      $payment->setRemoteState($response->getStatus());
      $payment->save();
      $redirect_url = $response->confirmation->confirmationUrl;
      $data = [
        'return' => $form['#return_url'],
        'cancel' => $form['#cancel_url'],
        'total' => $payment->getAmount()->getNumber(),
      ];

      return $this->buildRedirectForm($form, $form_state, $redirect_url, $data);
    }
    catch (ApiException $exception) {
      $message = $exception->getMessage();
      \Drupal::logger('yandex_checkout')->error('API Error: ' . $message);
      \Drupal::messenger()->addError($this->t('Не удалось создать платеж.'));
      throw new PaymentGatewayException();
    }
  }

  /**
   * Get payment narrative.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   Commerce order entity.
   * @param array $config
   *   Payment gateway plugin configuration.
   *
   * @return string
   *   Payment description.
   */
  private function createDescription(OrderInterface $order, array $config) {
    $description_template = !empty($config['description_template'])
      ? $config['description_template']
      : $this->t('Оплата заказа №[commerce_order:order_id]');

    $description = \Drupal::token()->replace($description_template, ['commerce_order' => $order]);

    return (string) mb_substr($description, 0, PaymentModel::MAX_LENGTH_DESCRIPTION);
  }

}
