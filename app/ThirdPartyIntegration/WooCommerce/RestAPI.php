<?php

namespace ChinaPayments\ThirdPartyIntegration\WooCommerce;

use ChinaPayments\PaymentGateway as CP_PaymentGateway;
use ChinaPayments\Request as CP_Request;
use WP_Error;
use WP_REST_Server;
use WP_REST_Request;

class RestAPI {

	/**
	 * @var RestAPI;
	 */
	protected static $_instance;

	/**
	 * @return RestAPI
	 */
	public static function instance(): RestAPI {
		if ( self::$_instance === null ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	public function setup() {
		register_rest_route(
			CHINA_PAYMENTS_REST_API_PREFIX . '/v1',
			'/third-party-integration/woocommerce/stripe-payment-completed/(?P<order_id>[\w-]+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, '_stripe_payment_completed' ),
				'permission_callback' => function () {
					return true;
				},
			)
		);

		register_rest_route(
			CHINA_PAYMENTS_REST_API_PREFIX . '/v1',
			'/third-party-integration/woocommerce/stripe-setup-completed/(?P<order_id>[\w-]+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, '_stripe_setup_completed' ),
				'permission_callback' => function () {
					return true;
				},
			)
		);
	}

	public function _stripe_payment_completed( WP_REST_Request $request ) {
		$order = wc_get_order( $request->get_param( 'order_id' ) );

		$is_live           = $order->get_meta( 'china_payments_is_live' );
		$payment_intent_id = $order->get_meta( 'china_payments_payment_intent_id' );

		if ( empty( $payment_intent_id ) ) {
			wp_redirect( add_query_arg( 'china-payments-error', 'payment-failed', wc_get_checkout_url() ) );
			exit;
		}

		$stripeIntegration = CP_PaymentGateway::get_integration_from_settings( 'stripe', $is_live );
		$payment_gateway   = wc_get_payment_gateway_by_order( $order );

		try {
			$payment_intent = $stripeIntegration->stripeClient()->paymentIntents->retrieve( $payment_intent_id );

			if ( $payment_intent->status !== 'succeeded' ) {
				wp_redirect( add_query_arg( 'china-payments-error', 'payment-failed', wc_get_checkout_url() ) );
				exit;
			}
		} catch ( \Exception $e ) {
			exit( $e->getMessage() );
		}

		china_payments_woocommerce_ensure_cart_loaded();

		WC()->cart->empty_cart();

		if ( ! $order->is_paid() ) {
			$order->payment_complete( $payment_intent->id );
		}

		wp_redirect( $payment_gateway->get_return_url( $order ) );
		exit;
	}

	public function _stripe_setup_completed( WP_REST_Request $request ) {
		$order = wc_get_order( $request->get_param( 'order_id' ) );

		$is_live         = $order->get_meta( 'china_payments_is_live' );
		$setup_intent_id = $order->get_meta( 'china_payments_setup_intent_id' );

		if ( empty( $setup_intent_id ) ) {
			wp_redirect( add_query_arg( 'china-payments-error', 'payment-failed', wc_get_checkout_url() ) );
			exit;
		}

		$stripeIntegration = CP_PaymentGateway::get_integration_from_settings( 'stripe', $is_live );
		$payment_gateway   = wc_get_payment_gateway_by_order( $order );

		if ( empty( $payment_gateway ) ) {
			wp_redirect( add_query_arg( 'china-payments-error', 'payment-failed', wc_get_checkout_url() ) );
			exit;
		}

		try {
			$setup_intent = $stripeIntegration->stripeClient()->setupIntents->retrieve( $setup_intent_id );

			if ( $setup_intent->status !== 'succeeded' ) {
				wp_redirect( add_query_arg( 'china-payments-error', 'payment-failed', wc_get_checkout_url() ) );
				exit;
			}
		} catch ( \Exception $e ) {
			wp_redirect( add_query_arg( 'china-payments-error', 'payment-failed', wc_get_checkout_url() ) );
			exit;
		}

		$order->update_meta_data( 'china_payments_customer_id', $setup_intent->customer );
		$order->update_meta_data( 'china_payments_payment_method', $setup_intent->payment_method );
		$order->save_meta_data();

		$payment_gateway->order_charge_using_setup_intent( $order );

		if ( ! $order->is_paid() ) {
			$order->delete_meta_data( 'china_payments_customer_id' );
			$order->delete_meta_data( 'china_payments_payment_method' );
			$order->save_meta_data();

			wp_redirect( add_query_arg( 'china-payments-error', 'payment-failed', wc_get_checkout_url() ) );
			exit;
		}

		china_payments_woocommerce_ensure_cart_loaded();

		WC()->cart->empty_cart();

		wp_redirect( $payment_gateway->get_return_url( $order ) );
		exit;
	}
}
