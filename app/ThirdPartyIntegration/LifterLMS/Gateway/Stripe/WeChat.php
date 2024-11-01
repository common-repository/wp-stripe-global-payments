<?php

namespace ChinaPayments\ThirdPartyIntegration\LifterLMS\Gateway\Stripe;

class WeChat extends Skeleton {

	public function __construct() {
		$this->id                = 'china_payments_stripe_wechat';
		$icon_alt                = __( 'Powered by Stripe', 'lifterlms-stripe' );
		$this->icon              = '<a href="https://stripe.com" target="_blank" title="' . $icon_alt . '"><img style="max-width: 100px;" src="' . plugins_url( 'interface/img/payment-gateway/payment-method-wechat-pay.svg', CHINA_PAYMENTS_BASE_FILE_PATH ) . '" alt="' . $icon_alt . '"></a>';
		$this->admin_description = __( 'Accept WeChat Pay payments using your Stripe account.', 'china-payments' );
		$this->admin_title       = sprintf( __( '%s - WeChat Pay', 'china-payments' ), CHINA_PAYMENTS_NAME );
		$this->title             = __( 'WeChat Pay', 'china-payments' );
		$this->description       = __( 'A WeChat Pay QR Code will display.', 'china-payments' );

		parent::__construct();
	}

	public function get_supported_currencies() {
		return apply_filters(
			'china_payments_lifterlms_stripe_wechat_currencies',
			array(
				'AUD',
				'CAD',
				'CNY',
				'EUR',
				'GBP',
				'HKD',
				'JPY',
				'SGD',
				'USD',
			)
		);
	}

	public function get_stripe_payment_method_alias() {
		return 'wechat_pay';
	}
}
