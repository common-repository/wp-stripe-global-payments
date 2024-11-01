<?php

namespace ChinaPayments\ThirdPartyIntegration\WooCommerce\Blocks\Stripe;

class AliPay extends Skeleton {

  protected $name = 'china_payments_stripe_alipay';

  public function get_checkout_block_script_path() {
    return plugins_url(
      'interface/woocommerce-checkout-blocks/stripe/alipay/build/index.js',
      CHINA_PAYMENTS_BASE_FILE_PATH
    );
  }

}