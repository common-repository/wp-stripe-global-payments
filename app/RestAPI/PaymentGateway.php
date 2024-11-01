<?php

namespace ChinaPayments\RestAPI;

use ChinaPayments\Settings;
use WP_Error;
use WP_REST_Server;
use WP_REST_Request;
use ChinaPayments\API\ChinaPayments as API_ChinaPayments;
use ChinaPayments\PaymentGateway as InstancePaymentGateway;
use ChinaPayments\PaymentGateway\Stripe as PaymentGateway_Stripe;
use ChinaPayments\Settings as ChinaPayments_Settings;

class PaymentGateway {

	public static function register_routes() {
		register_rest_route(
			CHINA_PAYMENTS_REST_API_PREFIX . '/v1',
			'/payment-gateway/connect',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => '\ChinaPayments\RestAPI\PaymentGateway::connect',
				'permission_callback' => function () {
					return current_user_can( CHINA_PAYMENTS_ADMIN_CAP );
				},
			)
		);

		register_rest_route(
			CHINA_PAYMENTS_REST_API_PREFIX . '/v1',
			'/payment-gateway/disconnect',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => '\ChinaPayments\RestAPI\PaymentGateway::disconnect',
				'permission_callback' => function () {
					return current_user_can( CHINA_PAYMENTS_ADMIN_CAP );
				},
			)
		);

		register_rest_route(
			CHINA_PAYMENTS_REST_API_PREFIX . '/v1',
			'/payment-gateway/set-mode',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => '\ChinaPayments\RestAPI\PaymentGateway::set_mode',
				'permission_callback' => function () {
					return current_user_can( CHINA_PAYMENTS_ADMIN_CAP );
				},
			)
		);

		register_rest_route(
			CHINA_PAYMENTS_REST_API_PREFIX . '/v1',
			'/payment-gateway/set-payment-methods',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => '\ChinaPayments\RestAPI\PaymentGateway::set_payment_methods',
				'permission_callback' => function () {
					return current_user_can( CHINA_PAYMENTS_ADMIN_CAP );
				},
			)
		);

		register_rest_route(
			CHINA_PAYMENTS_REST_API_PREFIX . '/v1',
			'/payment-gateway/save-webhook-settings',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => '\ChinaPayments\RestAPI\PaymentGateway::save_webhook_settings',
				'permission_callback' => function () {
					return current_user_can( CHINA_PAYMENTS_ADMIN_CAP );
				},
			)
		);

		register_rest_route(
			CHINA_PAYMENTS_REST_API_PREFIX . '/v1',
			'/payment-gateway/connect-callback',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => '\ChinaPayments\RestAPI\PaymentGateway::connect_callback',
				'permission_callback' => '__return_true',
			)
		);
	}

	public static function connect( WP_REST_Request $request ) {
		foreach ( array( 'is_live', 'payment_gateway' ) as $required_param ) {
			if ( ! $request->has_param( $required_param ) ) {
				return new WP_Error(
					'rest_error',
					esc_html( sprintf( __( 'Missing request param %s', 'china-payments' ), $required_param ) ),
					array(
						'status' => 400,
					)
				);
			}
		}

		if ( $request->get_param( 'payment_gateway' ) === 'stripe' ) {
			return rest_ensure_response(
				PaymentGateway_Stripe::setup_start_connection(
					array(
						'is_live' => $request->get_param( 'is_live' ),
					)
				)
			);
		}

		return new WP_Error(
			'rest_error',
			esc_html( sprintf( __( 'Invalid payment gateway %s', 'china-payments' ), $request->get_param( 'payment_gateway' ) ) ),
			array(
				'status' => 400,
			)
		);
	}

	public static function disconnect( WP_REST_Request $request ) {
		foreach ( array( 'is_live', 'payment_gateway' ) as $required_param ) {
			if ( ! $request->has_param( $required_param ) ) {
				return new WP_Error(
					'rest_error',
					esc_html( sprintf( __( 'Missing request param %s', 'china-payments' ), $required_param ) ),
					array(
						'status' => 400,
					)
				);
			}
		}

		$paymentGatewayInstance = InstancePaymentGateway::get_integration_from_settings( $request->get_param( 'payment_gateway' ) );

		if ( $paymentGatewayInstance === null ) {
			return new WP_Error(
				'rest_error',
				esc_html( sprintf( __( 'Invalid payment gateway %s', 'china-payments' ), $request->get_param( 'payment_gateway' ) ) ),
				array(
					'status' => 400,
				)
			);
		}

		$paymentGatewayInstance->remove_webhook();
		$paymentGatewayInstance->delete_settings_credentials( intval( $request->get_param( 'is_live' ) ) );

		return rest_ensure_response(
			array(
				'status' => 'ok',
			)
		);
	}

	public static function set_mode( WP_REST_Request $request ) {
		foreach ( array( 'is_live', 'payment_gateway' ) as $required_param ) {
			if ( ! $request->has_param( $required_param ) ) {
				return new WP_Error(
					'rest_error',
					esc_html( sprintf( __( 'Missing request param %s', 'china-payments' ), $required_param ) ),
					array(
						'status' => 400,
					)
				);
			}
		}

		$paymentGatewayInstance = InstancePaymentGateway::get_integration_from_settings( $request->get_param( 'payment_gateway' ) );

		if ( $paymentGatewayInstance === null ) {
			return new WP_Error(
				'rest_error',
				esc_html( sprintf( __( 'Invalid payment gateway %s', 'china-payments' ), $request->get_param( 'payment_gateway' ) ) ),
				array(
					'status' => 400,
				)
			);
		}

		ChinaPayments_Settings::instance()->update(
			array(
				$request->get_param( 'payment_gateway' ) . '_is_live' => intval( $request->get_param( 'is_live' ) ),
			)
		);

		return rest_ensure_response(
			array(
				'status' => 'ok',
			)
		);
	}

	public static function set_payment_methods( WP_REST_Request $request ) {
		foreach ( array( 'payment_gateway' ) as $required_param ) {
			if ( ! $request->has_param( $required_param ) ) {
				return new WP_Error(
					'rest_error',
					esc_html( sprintf( __( 'Missing request param %s', 'china-payments' ), $required_param ) ),
					array(
						'status' => 400,
					)
				);
			}
		}

		$paymentGatewayInstance = InstancePaymentGateway::get_integration_from_settings( $request->get_param( 'payment_gateway' ) );

		if ( $paymentGatewayInstance === null ) {
			return new WP_Error(
				'rest_error',
				esc_html( sprintf( __( 'Invalid payment gateway %s', 'china-payments' ), $request->get_param( 'payment_gateway' ) ) ),
				array(
					'status' => 400,
				)
			);
		}

		ChinaPayments_Settings::instance()->update(
			array(
				$request->get_param( 'payment_gateway' ) . '_payment_methods' => ( $request->has_param( 'payment_methods' ) ? $request->get_param( 'payment_methods' ) : array() ),
			)
		);

		return rest_ensure_response(
			array(
				'status' => 'ok',
			)
		);
	}

	public static function save_webhook_settings( WP_REST_Request $request ) {
		foreach ( array( 'is_live', 'payment_gateway' ) as $required_param ) {
			if ( ! $request->has_param( $required_param ) ) {
				return new WP_Error(
					'rest_error',
					esc_html( sprintf( __( 'Missing request param %s', 'china-payments' ), $required_param ) ),
					array(
						'status' => 400,
					)
				);
			}
		}

		$payment_gateway = $request->get_param( 'payment_gateway' );

		$paymentGatewayInstance = InstancePaymentGateway::get_integration_from_settings( $payment_gateway );

		if ( empty( $paymentGatewayInstance ) || ! method_exists( $paymentGatewayInstance, 'get_webhook_settings_administration' ) ) {
			return new WP_Error(
				'rest_error',
				__( 'Invalid payment gateway', 'payment-page' ),
				array(
					'status' => 400,
				)
			);
		}

		$webhook_settings_administration = $paymentGatewayInstance->get_webhook_settings_administration();

		$target_items = $webhook_settings_administration[ intval( $request->get_param( 'is_live' ) ) ? 'live_fields' : 'test_fields' ];

		$settings_update_array = array();

		foreach ( $target_items as $setting_key => $setting_field_information ) {
			if ( ! $request->has_param( $setting_key ) ) {
				continue;
			}

			$settings_update_array[ $setting_key ] = sanitize_text_field( $request->get_param( $setting_key ) );
		}

		if ( ! empty( $settings_update_array ) ) {
			Settings::instance()->update( $settings_update_array );
		}

		return rest_ensure_response( true );
	}

	public static function connect_callback( WP_REST_Request $request ) {
		if ( $request->has_param( 'cancel' ) ) {
			china_payments_redirect( admin_url( CHINA_PAYMENTS_DEFAULT_URL_PATH ) );
			exit;
		}

		foreach ( array( 'payment-gateway', 'credentials' ) as $required_param ) {
			if ( ! $request->has_param( $required_param ) ) {
				return new WP_Error(
					'rest_error',
					esc_html( sprintf( __( 'Missing request param %s', 'china-payments' ), $required_param ) ),
					array(
						'status' => 400,
					)
				);
			}
		}

		$credentials = API_ChinaPayments::instance()->decode_response_data( $request->get_param( 'credentials' ) );

		if ( ! is_array( $credentials ) ) {
			return new WP_Error(
				'rest_error',
				__( 'Invalid Credentials...', 'china-payments' ),
				array(
					'status' => 400,
				)
			);
		}

		if ( $request->get_param( 'payment-gateway' ) === 'stripe' ) {
			if ( ! PaymentGateway_Stripe::save_master_credentials_response( $credentials ) ) {
				return new WP_Error(
					'rest_error',
					__( 'Invalid Credentials...', 'china-payments' ),
					array(
						'status' => 400,
					)
				);
			}
		}

		china_payments_redirect( admin_url( CHINA_PAYMENTS_DEFAULT_URL_PATH ) );
		exit;
	}
}
