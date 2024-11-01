<?php

namespace ChinaPayments\ThirdPartyIntegration;

use ChinaPayments\Settings as CP_Settings;
use Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry as WC_PaymentMethodRegistry;
use Exception;

class WooCommerce {

	/**
	 * @var WooCommerce
	 */
	protected static $_instance = null;

	public static function instance() {
		if ( self::$_instance === null ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	public function setup() {
		add_filter( 'woocommerce_payment_gateways', array( $this, '_woocommerce_payment_gateways' ), 100 );
    add_action( 'woocommerce_blocks_loaded', array( $this, '_woocommerce_blocks_loaded' ) );
		add_action( 'rest_api_init', array( WooCommerce\RestAPI::instance(), 'setup' ) );
		add_action( 'woocommerce_scheduled_subscription_payment_china_payments_stripe_alipay', array( $this, '_woocommerce_scheduled_subscription_payment' ), 10, 2 );
	}

	public function get_current_currency() {
		return strtoupper( get_woocommerce_currency() );
	}

	public function _woocommerce_payment_gateways( $methods ) {
		if ( in_array( 'wechat', CP_Settings::instance()->get( 'stripe_payment_methods' ) ) ) {
			$methods[] = 'ChinaPayments\ThirdPartyIntegration\WooCommerce\Gateway\Stripe\WeChat';
		}

		if ( in_array( 'alipay', CP_Settings::instance()->get( 'stripe_payment_methods' ) ) ) {
			$methods[] = 'ChinaPayments\ThirdPartyIntegration\WooCommerce\Gateway\Stripe\AliPay';
		}

		return $methods;
	}

  public function _woocommerce_blocks_loaded() {
    add_action(
      'woocommerce_blocks_payment_method_type_registration',
      array( $this, '_woocommerce_blocks_payment_method_type_registration' )
    );
  }

  public function _woocommerce_blocks_payment_method_type_registration( WC_PaymentMethodRegistry $payment_method_registry ) {
    if ( in_array( 'wechat', CP_Settings::instance()->get( 'stripe_payment_methods' ) ) ) {
      $payment_method_registry->register( new WooCommerce\Blocks\Stripe\WeChat() );
    }

    if ( in_array( 'alipay', CP_Settings::instance()->get( 'stripe_payment_methods' ) ) ) {
      $payment_method_registry->register( new WooCommerce\Blocks\Stripe\AliPay() );
    }
  }

	public function _woocommerce_scheduled_subscription_payment( $renewal_total, \WC_Order $renewal_order ) {
		$payment_gateway = wc_get_payment_gateway_by_order( $renewal_order );

		if ( empty( $payment_gateway ) ) {
			throw new Exception( 'Missing payment gateway for order : ' . $renewal_order->get_id() );
		}

		if ( ! method_exists( $payment_gateway, 'order_charge_using_setup_intent' ) ) {
			throw new Exception(
				sprintf(
					'Missing function %s for %s order : %s',
					'order_charge_using_setup_intent',
					$payment_gateway->id,
					$renewal_order->get_id()
				)
			);
		}

		$payment_gateway->order_charge_using_setup_intent( $renewal_order );

		if ( ! $renewal_order->is_paid() ) {
			$renewal_order->add_order_note( __( 'Scheduled payment failed', 'china-payments' ) );
		}
	}
}
