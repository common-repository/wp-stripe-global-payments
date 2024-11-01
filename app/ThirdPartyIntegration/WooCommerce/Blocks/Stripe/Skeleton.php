<?php

namespace ChinaPayments\ThirdPartyIntegration\WooCommerce\Blocks\Stripe;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

abstract class Skeleton extends AbstractPaymentMethodType {

  /**
   * @var \ChinaPayments\ThirdPartyIntegration\WooCommerce\Gateway\Stripe\Skeleton
   */
  private $gateway;

  public function initialize() {
    // No checks, if this fails, it means it's bad.
    $this->gateway = WC()->payment_gateways()->payment_gateways()[ $this->name ];
  }

  public function is_active() {
    return true;
  }

  public function get_payment_method_script_handles() {
    wp_register_script(
      'china-payments-' . $this->name . '-blocks-integration',
      $this->get_checkout_block_script_path(),
      array(
        'wc-blocks-registry',
        'wc-settings',
        'wp-element',
        'wp-html-entities'
      ),
      CHINA_PAYMENTS_VERSION,
      true
    );

    return array( 'china-payments-' . $this->name . '-blocks-integration' );
  }

  public function get_payment_method_data() {
    return array(
      'title'        => $this->gateway->get_title(),
      'description'  => $this->gateway->get_description(),
      'icon'         => $this->gateway->icon,
      'supports'     => $this->get_supported_features(),
    );
  }

  public function get_supported_features() {
    return $this->gateway->supports;
  }

  abstract public function get_checkout_block_script_path();

}