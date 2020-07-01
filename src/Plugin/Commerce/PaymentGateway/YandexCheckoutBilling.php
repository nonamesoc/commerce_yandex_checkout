<?php


namespace Drupal\yandex_checkout\Plugin\Commerce\PaymentGateway;


use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OffsitePaymentGatewayBase;
use Drupal\Core\Form\FormStateInterface;

/**
 *
 * @CommercePaymentGateway(
 *   id = "yandex_checkout_billing",
 *   label = "Yandex Checkout Billing",
 *   display_label = "Yandex Checkout Billing",
 *   forms = {
 *     "offsite-payment" = "Drupal\yandex_checkout\PluginForm\YandexCheckout\PaymentBillingForm",
 *   },
 *   payment_method_types = {
 *     "yandex_checkout_billing"
 *   },
 *   modes = {
 *     "n/a" = @Translation("N/A"),
 *   }
 * )
 *
 */
class YandexCheckoutBilling extends OffsitePaymentGatewayBase
{
    /**
     * {@inheritdoc}
     */
    public function defaultConfiguration()
    {
        return [
                   'billing_id' => '',
                   'narrative'  => $this->t('Order No. [commerce_order:order_id] Payment via Yandex.Billing'),
               ] + parent::defaultConfiguration();
    }

    /**
     * {@inheritdoc}
     */
    public function buildConfigurationForm(array $form, FormStateInterface $form_state)
    {
        $form = parent::buildConfigurationForm($form, $form_state);

        $form['billing_id'] = array(
            '#type'          => 'textfield',
            '#title'         => $this->t('Yandex.Billing\'s identifier'),
            '#default_value' => $this->configuration['billing_id'],
        );

        $token_tree = [
          '#theme' => 'token_tree_link',
          '#token_types' => ['commerce_order'],
          '#show_restricted' => TRUE,
          '#global_types' => FALSE,
        ];
        $rendered_token_tree = \Drupal::service('renderer')->render($token_tree);
        $form['narrative'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Payment purpose'),
          '#description' => [
            '#theme' => 'payment_description_template_help',
            '#description' => [
              $this->t('Payment purpose is added to the payment order: specify whatever will help identify the order paid via Yandex.Billing'),
              $this->t('You may also use tokens: @browse_link', ['@browse_link' => $rendered_token_tree]),
            ],
          ],
          '#default_value' => $this->configuration['narrative'],
          '#element_validate' => ['token_element_validate'],
          '#token_types' => $token_tree['#token_types'],
        ];

        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function submitConfigurationForm(array &$form, FormStateInterface $form_state)
    {
        parent::submitConfigurationForm($form, $form_state);
        if (!$form_state->getErrors()) {
            $values                            = $form_state->getValue($form['#parents']);
            $this->configuration['billing_id'] = $values['billing_id'];
            $this->configuration['narrative']  = $values['narrative'];
        }
    }
}