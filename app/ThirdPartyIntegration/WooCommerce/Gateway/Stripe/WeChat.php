<?php

namespace ChinaPayments\ThirdPartyIntegration\WooCommerce\Gateway\Stripe;

class WeChat extends Skeleton {

	public $id         = 'china_payments_stripe_wechat';
	public $has_fields = false;

	public function __construct() {
		$this->method_title       = sprintf( __( '%s - WeChat Pay', 'china-payments' ), CHINA_PAYMENTS_NAME );
		$this->method_description = __( 'Accept WeChat Pay payments using your Stripe account.', 'china-payments' );
		$this->icon               = plugins_url( 'interface/img/payment-gateway/payment-method-wechat-pay.svg', CHINA_PAYMENTS_BASE_FILE_PATH );

		parent::__construct();
	}

	public function default_name() {
		return 'WeChat Pay';
	}

	public function get_supported_currencies() {
		return apply_filters(
			'china_payments_woocommerce_stripe_wechat_currencies',
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

	public function init_form_fields() {
		parent::init_form_fields();

		$this->form_fields['title']['default']       = __( 'WeChat Pay', 'china-payments' );
		$this->form_fields['description']['default'] = __( 'A WeChat Pay QR Code will display.', 'china-payments' );
	}
}
