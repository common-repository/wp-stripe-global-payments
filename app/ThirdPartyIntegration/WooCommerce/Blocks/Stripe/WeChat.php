<?php

namespace ChinaPayments\ThirdPartyIntegration\WooCommerce\Blocks\Stripe;

class WeChat extends Skeleton {

  protected $name = 'china_payments_stripe_wechat';

  public function get_checkout_block_script_path() {
    return plugins_url(
      'interface/woocommerce-checkout-blocks/stripe/wechat/build/index.js',
      CHINA_PAYMENTS_BASE_FILE_PATH
    );
  }
  
}