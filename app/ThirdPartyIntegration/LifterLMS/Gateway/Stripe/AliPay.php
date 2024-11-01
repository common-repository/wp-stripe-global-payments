<?php

namespace ChinaPayments\ThirdPartyIntegration\LifterLMS\Gateway\Stripe;

class AliPay extends Skeleton {

	public function __construct() {
		$this->id                = 'china_payments_stripe_alipay';
		$icon_alt                = __( 'Powered by Stripe', 'lifterlms-stripe' );
		$this->icon              = '<a href="https://stripe.com" target="_blank" title="' . $icon_alt . '"><img style="max-width: 100px;" src="' . plugins_url( 'interface/img/payment-gateway/payment-method-alipay.svg', CHINA_PAYMENTS_BASE_FILE_PATH ) . '" alt="' . $icon_alt . '"></a>';
		$this->admin_description = __( 'Accept Alipay payments using your Stripe account.', 'china-payments' );
		$this->admin_title       = sprintf( __( '%s - Alipay', 'china-payments' ), CHINA_PAYMENTS_NAME );
		$this->title             = __( 'Alipay', 'china-payments' );
		$this->description       = __( 'You will be redirected to the Alipay website to complete checkout.', 'china-payments' );

		parent::__construct();
	}

	public function get_supported_currencies() {
		return apply_filters(
			'china_payments_lifterlms_stripe_wechat_currencies',
			array(
				'CNY',
				'AUD',
				'CAD',
				'EUR',
				'GBP',
				'HKD',
				'JPY',
				'SGD',
				'MYR',
				'NZD',
				'USD',
			)
		);
	}

	public function get_stripe_payment_method_alias() {
		return 'alipay';
	}
}
