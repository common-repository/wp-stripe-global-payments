<?php

namespace ChinaPayments\ThirdPartyIntegration;

use ChinaPayments\Settings as CP_Settings;

class LifterLMS {

	/**
	 * @var LifterLMS
	 */
	protected static $_instance = null;

	public static function instance() {
		if ( self::$_instance === null ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	public function setup() {
		add_filter( 'lifterlms_payment_gateways', array( $this, 'register_gateway' ), 10, 1 );
		add_filter( 'llms_order_can_be_confirmed', array( $this, '_llms_order_can_be_confirmed' ), 100, 3 );
	}

	public function register_gateway( $gateways ) {
		if ( in_array( 'wechat', CP_Settings::instance()->get( 'stripe_payment_methods' ) ) ) {
			$gateways[] = 'ChinaPayments\ThirdPartyIntegration\LifterLMS\Gateway\Stripe\WeChat';
		}

		if ( in_array( 'alipay', CP_Settings::instance()->get( 'stripe_payment_methods' ) ) ) {
			$gateways[] = 'ChinaPayments\ThirdPartyIntegration\LifterLMS\Gateway\Stripe\AliPay';
		}

		return $gateways;
	}

	public function _llms_order_can_be_confirmed( $response, $order, $payment_gateway ) {
		if ( strpos( $payment_gateway, 'china_payments_' ) !== 0 ) {
			return $response;
		}

		// Maybe payment is already confirmed from callback
		if ( $order->status === 'completed' || $order->status === 'llms-completed' ) {
			return true;
		}

		return $response;
	}
}
