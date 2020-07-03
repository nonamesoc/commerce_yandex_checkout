<?php

namespace Drupal\yandex_checkout\Plugin\Commerce\PaymentGateway;

use Drupal\commerce\Response\NeedsRedirectException;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\PaymentMethodTypeManager;
use Drupal\commerce_payment\PaymentTypeManager;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OffsitePaymentGatewayBase;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use YandexCheckout\Client;
use YandexCheckout\Model\NotificationEventType;
use YandexCheckout\Model\Notification\NotificationSucceeded;
use YandexCheckout\Model\Notification\NotificationWaitingForCapture;
use YandexCheckout\Model\Payment as PaymentModel;
use YandexCheckout\Model\PaymentStatus;
use YandexCheckout\Request\Payments\Payment\CreateCaptureRequest;

/**
 * Provides the Yandex Checkout payment gateway.
 *
 * @CommercePaymentGateway(
 *   id = "yandex_checkout",
 *   label = "Yandex Checkout",
 *   display_label = "Yandex Checkout",
 *   forms = {
 *     "offsite-payment" = "Drupal\yandex_checkout\PluginForm\YandexCheckout\PaymentOffsiteForm",
 *     "test-action" = "Drupal\yandex_checkout\PluginForm\YandexCheckout\PaymentMethodAddForm"
 *   },
 *   payment_method_types = {
 *     "yandex_checkout_epl"
 *   },
 *   modes = {
 *     "n/a" = @Translation("N/A"),
 *   }
 * )
 */
class YandexCheckout extends OffsitePaymentGatewayBase {

  const YAMONEY_MODULE_VERSION = '1.0.2';

  /**
   * YandexCheckout API client.
   *
   * @var \YandexCheckout\Client
   */
  public $apiClient;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, PaymentTypeManager $payment_type_manager, PaymentMethodTypeManager $payment_method_type_manager, TimeInterface $time) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager, $payment_type_manager, $payment_method_type_manager, $time);
    $shopId = $this->configuration['shop_id'];
    $secretKey = $this->configuration['secret_key'];
    $yandexCheckoutClient = new Client();
    $yandexCheckoutClient->setAuth($shopId, $secretKey);
    $this->apiClient = $yandexCheckoutClient;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'shop_id' => '',
      'secret_key' => '',
      'description_template' => '',
      'receipt_enabled' => '',
      'default_tax' => '',
      'yandex_checkout_tax' => [],
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['shop_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('shopId'),
      '#default_value' => $this->configuration['shop_id'],
      '#required' => TRUE,
    ];

    $form['secret_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Secret Key'),
      '#default_value' => $this->configuration['secret_key'],
      '#required' => TRUE,
    ];

    $form = parent::buildConfigurationForm($form, $form_state);

    $token_tree = [
      '#theme' => 'token_tree_link',
      '#token_types' => ['commerce_order'],
      '#show_restricted' => TRUE,
      '#global_types' => FALSE,
    ];
    $rendered_token_tree = \Drupal::service('renderer')->render($token_tree);
    $description_template = !empty($this->configuration['description_template'])
      ? $this->configuration['description_template']
      : $this->t('Оплата заказа №[commerce_order:order_id]');
    $form['description_template'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Описание платежа'),
      '#description' => [
        '#theme' => 'payment_description_template_help',
        '#description' => [
          $this->t('Это описание транзакции, которое пользователь увидит при оплате, а вы — в личном кабинете Яндекс.Кассы. Например, «Оплата заказа №72».'),
          $this->t('You may also use tokens: @browse_link', ['@browse_link' => $rendered_token_tree]),
          $this->t('Ограничение для описания — @max_length символов.', ['@max_length' => PaymentModel::MAX_LENGTH_DESCRIPTION]),
        ],
      ],
      '#default_value' => $description_template,
      '#element_validate' => ['token_element_validate'],
      '#token_types' => $token_tree['#token_types'],
    ];

    $form['receipt_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Отправлять в Яндекс.Кассу данные для чеков (54-ФЗ)'),
      '#default_value' => $this->configuration['receipt_enabled'],
    ];
    if ($this->configuration['receipt_enabled']) {
      $form['default_tax'] = [
        '#type' => 'select',
        '#title' => 'Ставка по умолчанию',
        '#options' => [
          1 => $this->t('Без НДС'),
          2 => $this->t('0%'),
          3 => $this->t('10%'),
          4 => $this->t('20%'),
          5 => $this->t('Расчётная ставка 10/110'),
          6 => $this->t('Расчётная ставка 20/120'),
        ],
        '#default_value' => $this->configuration['default_tax'],
      ];

      $tax_storage = $this->entityTypeManager->getStorage('commerce_tax_type');
      $taxTypes = $tax_storage->loadMultiple();
      $taxRates = [];
      foreach ($taxTypes as $taxType) {
        /** @var \Drupal\commerce_tax\Entity\TaxType $taxType */
        $taxTypeConfiguration = $taxType->getPluginConfiguration();
        $taxRates += $taxTypeConfiguration['rates'];
      }

      if ($taxRates) {

        $form['yandex_checkout_tax_label'] = [
          '#type' => 'html_tag',
          '#tag' => 'label',
          '#value' => $this->t('Сопоставьте ставки'),
          '#state' => [
            'visible' => [
              [
                [':input[name="measurementmethod"]' => ['value' => '5']],
                'xor',
                [':input[name="measurementmethod"]' => ['value' => '6']],
                'xor',
                [':input[name="measurementmethod"]' => ['value' => '7']],
              ],
            ],
          ],

        ];

        $form['yandex_checkout_tax_wrapper_begin'] = [
          '#markup' => '<div>',
        ];

        $form['yandex_checkout_label_shop_tax'] = [
          '#markup' => $this->t('<div style="float: left;width: 200px;">Ставка в вашем магазине.</div>'),
        ];

        $form['yandex_checkout_label_tax_rate'] = [
          '#markup' => $this->t('<div>Ставка для чека в налоговую.</div>'),
        ];

        $form['yandex_checkout_tax_wrapper_end'] = [
          '#markup' => '</div>',
        ];

        foreach ($taxRates as $taxRate) {
          $form['yandex_checkout_tax']['yandex_checkout_tax_label_' . $taxRate['id'] . '_begin'] = [
            '#markup' => '<div>',
          ];
          $form['yandex_checkout_tax']['yandex_checkout_tax_label_' . $taxRate['id'] . '_lbl'] = [
            '#markup' => new FormattableMarkup('<div style="width: 200px; float: left; padding-top: 5px;"><label>@label</label></div>', [
              '@label' => $taxRate['label'],
            ]),
          ];

          $defaultTaxValue = $this->configuration['yandex_checkout_tax'][$taxRate['id']] ?? 1;
          $form['yandex_checkout_tax'][$taxRate['id']] = [
            '#type' => 'select',
            '#title' => FALSE,
            '#label' => FALSE,
            '#options' => [
              1 => $this->t('Без НДС'),
              2 => $this->t('0%'),
              3 => $this->t('10%'),
              4 => $this->t('20%'),
              5 => $this->t('Расчётная ставка 10/110'),
              6 => $this->t('Расчётная ставка 20/120'),
            ],
            '#default_value' => $defaultTaxValue,
          ];

          $form['yandex_checkout_tax']['yandex_checkout_tax_label_' . $taxRate['id'] . '_end'] = [
            '#markup' => '</div><br style="clear: both;">',
          ];
        }
      }
    }

    // @todo: temp solution.
    // @see: https://www.drupal.org/project/commerce/issues/3017551
    $gateway_id = $form_state->getValue('id', NULL);
    $gateway = $gateway_id ?
      $this->entityTypeManager->getStorage('commerce_payment_gateway')->load($gateway_id)
      : NULL;
    if ($gateway) {
      $form['notification_url'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Url для нотификаций'),
        '#default_value' => $gateway->getPlugin()->getNotifyUrl()->toString(),
        '#attributes' => ['readonly' => 'readonly'],
      ];
    }
    $form['log_file'] = [
      '#type' => 'item',
      '#title' => $this->t('Логирование'),
      '#markup' => Link::fromTextAndUrl('Посмотреть записи журнала', Url::fromRoute('dblog.overview', [], [
        'query' => ['type' => ['yandex_checkout']],
        'attributes' => ['target' => '_blank'],
      ]))->toString(),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValue($form['#parents']);
    if (!preg_match('/^test_.*|live_.*$/i', $values['secret_key'])) {
      $markup = new TranslatableMarkup('Такого секретного ключа нет. Если вы уверены, что скопировали ключ правильно, значит, он по какой-то причине не работает.
                  Выпустите и активируйте ключ заново —
                  <a href="https://money.yandex.ru/joinups">в личном кабинете Яндекс.Кассы</a>');
      $form_state->setError($form['secret_key'], $markup);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);
    if (!$form_state->getErrors()) {
      $values = $form_state->getValue($form['#parents']);
      $this->configuration['shop_id'] = $values['shop_id'];
      $this->configuration['secret_key'] = $values['secret_key'];
      $this->configuration['description_template'] = $values['description_template'];
      $this->configuration['receipt_enabled'] = $values['receipt_enabled'];
      $this->configuration['default_tax'] = isset($values['default_tax']) ? $values['default_tax'] : '';
      $this->configuration['yandex_checkout_tax'] = isset($values['yandex_checkout_tax']) ? $values['yandex_checkout_tax'] : [];
    }
  }

  /**
   * Processes the "return" request.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   */
  public function onReturn(OrderInterface $order, Request $request) {
    $payment_storage = \Drupal::entityTypeManager()->getStorage('commerce_payment');
    $payments = $payment_storage->loadByProperties(['order_id' => $order->id()]);
    if ($payments) {
      $payment = reset($payments);
    }
    /** @var \Drupal\commerce_payment\Entity\Payment $payment */
    $paymentId = $payment->getRemoteId();
    $apiClient = $this->apiClient;
    $cancelUrl = $this->buildCancelUrl($order);
    $paymentInfoResponse = $apiClient->getPaymentInfo($paymentId);
    $this->log('Payment info: ' . json_encode($paymentInfoResponse));
    if ($paymentInfoResponse->status == PaymentStatus::WAITING_FOR_CAPTURE) {
      $captureRequest = CreateCaptureRequest::builder()
        ->setAmount($paymentInfoResponse->getAmount())
        ->build();
      $paymentInfoResponse = $apiClient->capturePayment($captureRequest, $paymentId);
      $this->log('Payment info after capture: ' . json_encode($paymentInfoResponse));
    }
    if ($paymentInfoResponse->status == PaymentStatus::SUCCEEDED) {
      $payment->setRemoteState($paymentInfoResponse->status);
      $payment->setState('completed');
      $payment->save();
      $this->log('Payment completed');
    }
    elseif ($paymentInfoResponse->status == PaymentStatus::PENDING && $paymentInfoResponse->getPaid()) {
      $payment->setRemoteState($paymentInfoResponse->status);
      $payment->setState('pending');
      $payment->save();
      $this->log('Payment pending');
    }
    elseif ($paymentInfoResponse->status == PaymentStatus::CANCELED) {
      $payment->setRemoteState($paymentInfoResponse->status);
      $payment->setState('canceled');
      $payment->save();
      $this->log('Payment canceled');
      throw new NeedsRedirectException($cancelUrl->toString());
    }
    else {
      $this->log('Wrong payment status: ' . $paymentInfoResponse->status);
      throw new NeedsRedirectException($cancelUrl->toString());
    }
  }

  /**
   * Processes the notification request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return \Symfony\Component\HttpFoundation\Response|null
   *   The response, or NULL to return an empty HTTP 200 response.
   */
  public function onNotify(Request $request) {
    $rawBody = $request->getContent();
    $this->log('Notification: ' . $rawBody);
    $notificationData = json_decode($rawBody, TRUE);
    $notificationModel = ($notificationData['event'] === NotificationEventType::PAYMENT_SUCCEEDED)
      ? new NotificationSucceeded($notificationData)
      : new NotificationWaitingForCapture($notificationData);
    $apiClient = $this->apiClient;
    $paymentResponse = $notificationModel->getObject();
    $paymentId = $paymentResponse->id;
    $payment_storage = \Drupal::entityTypeManager()->getStorage('commerce_payment');
    $payments = $payment_storage->loadByProperties(['remote_id' => $paymentId]);
    if (!$payments) {
      return new Response('Bad request', 400);
    }
    /** @var \Drupal\commerce_payment\Entity\Payment $payment */
    $payment = reset($payments);
    /** @var \Drupal\commerce_order\Entity\Order $order */
    $order = $payment->getOrder();
    if (!$order) {
      return new Response('Order not found', 404);
    }

    $paymentInfo = $apiClient->getPaymentInfo($paymentId);
    $this->log('Payment info: ' . json_encode($paymentInfo));

    $state = $order->getState()->value;
    if ($state !== 'completed') {
      switch ($paymentInfo->status) {
        case PaymentStatus::WAITING_FOR_CAPTURE:
          $captureRequest = CreateCaptureRequest::builder()
            ->setAmount($paymentInfo->getAmount())
            ->build();
          $captureResponse = $apiClient->capturePayment($captureRequest, $paymentId);
          $this->log('Payment info after capture: ' . json_encode($captureResponse));
          if ($captureResponse->status == PaymentStatus::SUCCEEDED) {
            $payment->setRemoteState($paymentInfo->status);
            $order->state = 'completed';
            $order->setCompletedTime(\Drupal::time()->getRequestTime());
            $order->save();
            $payment->save();
            $this->log('Payment completed');

            return new Response('Payment completed', 200);
          }
          elseif ($captureResponse->status == PaymentStatus::CANCELED) {
            $payment->setRemoteState($paymentInfo->status);
            $payment->save();
            $this->log('Payment canceled');

            return new Response('Payment canceled', 200);
          }
          break;

        case PaymentStatus::PENDING:
          $payment->setRemoteState($paymentInfo->status);
          $payment->save();
          $this->log('Payment pending');

          return new Response(' Payment Required', 402);

        case PaymentStatus::SUCCEEDED:
          $payment->setRemoteState($paymentInfo->status);
          $order->state = 'completed';
          $order->setCompletedTime(\Drupal::time()->getRequestTime());
          $order->save();
          $payment->save();
          $this->log('Payment complete');

          return new Response('Payment complete', 200);

        case PaymentStatus::CANCELED:
          $payment->setRemoteState($paymentInfo->status);
          $payment->save();
          $this->log('Payment canceled');

          return new Response('Payment canceled', 200);
      }
    }

    return new Response('OK', 200);
  }

  /**
   * Builds the URL to the "cancel" page.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   Commerce Order entity.
   *
   * @return \Drupal\Core\Url
   *   The "cancel" page URL.
   */
  protected function buildCancelUrl(OrderInterface $order) {
    return Url::fromRoute('commerce_payment.checkout.cancel', [
      'commerce_order' => $order->id(),
      'step' => 'payment',
    ], ['absolute' => TRUE]);
  }

  /**
   * Log message.
   *
   * @param string|\Drupal\Core\StringTranslation\TranslatableMarkup $message
   *   Message.
   */
  private function log($message) {
    \Drupal::logger('yandex_checkout')->info($message);
  }

}
