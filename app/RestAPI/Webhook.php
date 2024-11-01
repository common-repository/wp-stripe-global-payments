<?php

namespace ChinaPayments\RestAPI;

use ChinaPayments\PaymentGateway as CP_PaymentGateway;
use WP_Error;
use WP_REST_Server;
use WP_REST_Request;
use ChinaPayments\API\Features as API_Features;

class Webhook {

	public static function register_routes() {
		register_rest_route(
			CHINA_PAYMENTS_REST_API_PREFIX . '/v1',
			'/webhook/stripe-callback/(?P<mode>[\w-]+)',
			array(
				'methods'             => WP_REST_Server::ALLMETHODS,
				'callback'            => '\ChinaPayments\RestAPI\Webhook::stripe_callback',
				'permission_callback' => function () {
					return true;
				},
			)
		);
	}

	public static function stripe_callback( WP_REST_Request $request ) {
		ini_set( 'display_errors', 1 );
		ini_set( 'display_startup_errors', 1 );
		error_reporting( E_ALL );

		if ( ! isset( $_SERVER['HTTP_STRIPE_SIGNATURE'] ) ) {
			http_response_code( 400 );

			return rest_ensure_response(
				array(
					'message' => sprintf( __( '%s not found', 'china-payments' ), 'HTTP_STRIPE_SIGNATURE' ),
				)
			);
		}

		$mode = $request->get_param( 'mode' );

		if ( ! class_exists( '\Stripe\Stripe' ) ) {
			require_once CHINA_PAYMENTS_BASE_PATH . '/lib/stripe/init.php';
		}

		$payload = @file_get_contents( 'php://input' );

		$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'];

		try {
			$event = \Stripe\Webhook::constructEvent(
				$payload,
				$sig_header,
				china_payments_setting_get( 'stripe_' . $mode . '_webhook_secret' )
			);
		} catch ( \UnexpectedValueException $e ) {
			// Invalid payload
			http_response_code( 400 );

			return rest_ensure_response(
				array(
					'message' => __( 'Invalid Payload', 'china-payments' ),
				)
			);
		}

		if ( ! isset( $event->data->object->metadata->cp_integration ) ) {
			http_response_code( 200 );

			return rest_ensure_response(
				array(
					'message' => sprintf( __( 'Event not tracked, %s metadata not attached', 'china-payments' ), 'cp_integration' ),
				)
			);
		}

		if ( ! isset( $event->data->object->metadata->domain_name ) || $event->data->object->metadata->domain_name !== china_payments_domain_name() ) {
			http_response_code( 200 );

			return rest_ensure_response(
				array(
					'message' => sprintf( __( 'Event not tracked, %s metadata does not match', 'china-payments' ), 'domain_name' ),
				)
			);
		}

		if ( $event->type != 'payment_intent.succeeded' ) {
			http_response_code( 200 );

			return rest_ensure_response(
				array(
					'message' => __( 'Event not tracked', 'china-payments' ),
				)
			);
		}

		if ( $event->data->object->metadata->cp_integration === 'woocommerce' && function_exists( 'wc_get_payment_gateway_by_order' ) ) {
			$stripeIntegration = CP_PaymentGateway::get_integration_from_settings( 'stripe', ( $mode === 'live' ? 1 : 0 ) );
			$order             = wc_get_order( $event->data->object->metadata->order_id );
			$payment_intent_id = $order->get_meta( 'china_payments_payment_intent_id' );

			try {
				$payment_intent = $stripeIntegration->stripeClient()->paymentIntents->retrieve( $payment_intent_id );

				if ( $payment_intent->status !== 'succeeded' ) {
					return rest_ensure_response(
						array(
							'message' => __( 'Order Payment Intent Failed', 'china-payments' ),
						)
					);
				}
			} catch ( \Exception $e ) {
				exit( $e->getMessage() );
			}

			if ( ! $order->is_paid() ) {
				$order->payment_complete( $payment_intent->id );
			}

			http_response_code( 201 );

			return rest_ensure_response(
				array(
					'message' => sprintf( __( 'Handled %s Callback', 'china-payments' ), 'WooCommerce' ),
				)
			);
		}

		// @todo here.
		if ( $event->data->object->metadata->cp_integration === 'memberpress' && function_exists( 'wc_get_payment_gateway_by_order' ) ) {
			/**
			$stripeIntegration = CP_PaymentGateway::get_integration_from_settings( 'stripe', ( $mode === 'live' ? 1 : 0 ) );
			$order = wc_get_order( $event->data->object->metadata->order_id );
			$payment_intent_id = $order->get_meta( 'china_payments_payment_intent_id' );
			try {
				$payment_intent = $stripeIntegration->stripeClient()->paymentIntents->retrieve( $payment_intent_id );
				if( $payment_intent->status !== 'succeeded' )
				return rest_ensure_response( [
					'message' => __( "Order Payment Intent Failed", "china-payments" )
				] );
			} catch ( \Exception $e ) {
				exit( $e->getMessage() );
			}
			if( !$order->is_paid() )
				$order->payment_complete( $payment_intent->id );
			*/

			http_response_code( 201 );

			return rest_ensure_response(
				array(
					'message' => sprintf( __( 'Handled %s Callback', 'china-payments' ), 'MemberPress' ),
				)
			);
		}

		if ( $event->data->object->metadata->cp_integration === 'lifterlms' ) {
			$stripeIntegration = CP_PaymentGateway::get_integration_from_settings( 'stripe', ( $mode === 'live' ? 1 : 0 ) );
			$order             = new \LLMS_Order( $event->data->object->metadata->order_id );
			$payment_intent_id = get_post_meta( $order->id, 'china_payments_payment_intent_id', true );

			try {
				$payment_intent = $stripeIntegration->stripeClient()->paymentIntents->retrieve( $payment_intent_id );

				if ( $payment_intent->status !== 'succeeded' ) {
					return rest_ensure_response(
						array(
							'message' => __( 'Order Payment Intent Failed', 'china-payments' ),
						)
					);
				}
			} catch ( \Exception $e ) {
				exit( $e->getMessage() );
			}

			if ( $order->status !== 'completed' && $order->status !== 'llms-completed' ) {
				$order->record_transaction(
					array(
						'amount'         => $order->total,
						'transaction_id' => ( ! empty( $payment_intent->latest_charge ) ? $payment_intent->latest_charge : $payment_intent->id ),
					)
				);
				$order->set_status( 'completed' );
			}

			http_response_code( 201 );

			return rest_ensure_response(
				array(
					'message' => sprintf( __( 'Handled %s Callback', 'china-payments' ), 'WooCommerce' ),
				)
			);
		}

		http_response_code( 201 );

		return rest_ensure_response(
			array(
				'message' => __( 'Handled Callback', 'china-payments' ),
			)
		);
	}
}
