<?php

namespace ChinaPayments\RestAPI;

use WP_Error;
use WP_REST_Server;
use WP_REST_Request;
use ChinaPayments\API\Notification as API_Notification;
use ChinaPayments\API\ChinaPayments as API_ChinaPayments;
use ChinaPayments\PaymentGateway;
use ChinaPayments\Settings;

class Administration {

	public static function register_routes() {
		register_rest_route(
			CHINA_PAYMENTS_REST_API_PREFIX . '/v1',
			'/administration/dashboard',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => '\ChinaPayments\RestAPI\Administration::dashboard',
				'permission_callback' => function () {
					return current_user_can( CHINA_PAYMENTS_ADMIN_CAP );
				},
			)
		);

		register_rest_route(
			CHINA_PAYMENTS_REST_API_PREFIX . '/v1',
			'/administration/dismiss-notification',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => '\ChinaPayments\RestAPI\Administration::dismiss_notification',
				'permission_callback' => function () {
					return current_user_can( CHINA_PAYMENTS_ADMIN_CAP );
				},
			)
		);

		register_rest_route(
			CHINA_PAYMENTS_REST_API_PREFIX . '/v1',
			'/administration/set-quick-setup-skip',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => '\ChinaPayments\RestAPI\Administration::set_quick_setup_skip',
				'permission_callback' => function () {
					return current_user_can( CHINA_PAYMENTS_ADMIN_CAP );
				},
			)
		);
	}

	public static function dashboard( WP_REST_Request $request ) {
		return rest_ensure_response(
			array(
				'invalid_url_characters' => array( '~' ),
				'lang'                   => array(
					'upgrade'                             => __( 'Upgrade >', 'china-payments' ),
					'upgrade_payment_gateway'             => __( 'To accept payments with %1$s, please %2$s', 'china-payments' ),

					'menu_name_payment_gateways'          => __( 'Payment Gateways', 'china-payments' ),
					'menu_name_integrations'              => __( 'Integrations', 'china-payments' ),
					'menu_name_optimizations'             => __( 'Optimizations', 'china-payments' ),

					'payment_gateway_settings_set'        => __( 'Configure', 'china-payments' ),
					'payment_gateway_settings_edit'       => __( 'Configured', 'china-payments' ),
					'payment_gateway_settings_title'      => __( '%s Connection Settings', 'china-payments' ),

					'payment_gateway_connect'             => __( 'Connect with %s', 'china-payments' ),
					'payment_gateway_disconnect'          => __( 'Disconnect' ),
					'payment_gateway_mode_test'           => __( 'Test', 'china-payments' ),
					'payment_gateway_mode_live'           => __( 'Live', 'china-payments' ),

					'payment_gateway_webhook_settings_save' => __( 'Save Settings', 'china-payments' ),

					'payment_methods_title'               => __( 'Payment Methods', 'china-payments' ),
					'payment_methods_status'              => __( 'Status', 'china-payments' ),
					'payment_method_requires_https'       => __( 'This payment method requires a HTTPS connection for both live & testing', 'china-payments' ),

					'integration_name_woocommerce'        => __( 'WooCommerce', 'china-payments' ),
					'integration_name_payment_page'       => __( 'Payment Page', 'china-payments' ),
					'integration_install'                 => __( 'Install %s Now', 'china-payments' ),
					'integration_active'                  => __( 'Activate %s Now', 'china-payments' ),

					'quick_setup_return'                  => __( '< %s', 'china-payments' ),
					'quick_setup_next'                    => __( 'Next, %s >', 'china-payments' ),
					'quick_setup_skip_to'                 => __( 'Skip to %s >', 'china-payments' ),
					'quick_setup_exit'                    => __( 'Exit Quick Setup >', 'china-payments' ),
					'quick_setup_resume'                  => __( 'Start Quick Setup >', 'china-payments' ),

					'optimizations_coming_soon'           => __( 'Checkout Optimizations coming soon!', 'china-payments' ),

					'notification_url_invalid_characters' => __( 'Your website URL contains invalid character(s): %1$s which will cause problems with the %2$s connection.', 'china-payments' ),
					'notification_url_mismatch_ssl'       => __( 'Your SSL security certificate is not properly configured on your site. Please configure SSL in order to connect %s. Your hosting provider can help with this.', 'china-payments' ),
				),
				'upgrade_link'           => admin_url( 'admin.php?page=china-payments-pricing' ),
				'payment_gateway'        => PaymentGateway::get_administration_dashboard(),
				'integration_list'       => array_values( china_payments_integrations() ),
				'quick_setup_skipped'    => intval( Settings::instance()->get( 'skipped_quick_setup' ) ),
				'quick_setup_steps'      => array(
					array(
						'alias'        => 'connect_payment_gateway',
						'title'        => __( 'Connect your Payment Gateway', 'china-payments' ),
						'sub_title'    => sprintf( __( 'Welcome to %s', 'china-payments' ), CHINA_PAYMENTS_NAME ),
						'nav_title'    => __( 'Manage Gateways', 'china-payments' ),
						'is_completed' => ( PaymentGateway::get_integration_from_settings( 'stripe' )->get_public_key() !== '' ),
						'template'     => 'payment-gateways',
					),
					array(
						'alias'          => 'connect_stripe_test_gateway',
						'title'          => __( 'Next, Connect Stripe in Test Mode', 'china-payments' ),
						'nav_title'      => __( 'Manage Gateways', 'china-payments' ),
						'is_completed'   => ( Settings::instance()->get( 'stripe_test_public_key' ) !== '' ),
						'requires_steps' => array( 0 ),
						'template'       => 'payment-gateways',
					),
					array(
						'alias'          => 'connect_stripe_live_gateway',
						'title'          => __( 'Next, Connect Stripe in Live Mode', 'china-payments' ),
						'nav_title'      => __( 'Manage Gateways', 'china-payments' ),
						'is_completed'   => ( Settings::instance()->get( 'stripe_live_public_key' ) !== '' ),
						'requires_steps' => array( 0, 1 ),
						'template'       => 'payment-gateways',
					),
					array(
						'alias'        => 'select_integrations',
						'title'        => __( 'Next, Select Integrations', 'china-payments' ),
						'nav_title'    => __( 'Select Integrations', 'china-payments' ),
						'is_completed' => 0, // ( intval( Settings::instance()->get( 'primary_template_page_id' ) ) != 0 ),
						'template'     => 'integrations',
					),
				),
			)
		);
	}

	public static function dismiss_notification( WP_REST_Request $request ) {
		$latest_notification = API_Notification::instance()->get_latest_notification();

		if ( isset( $latest_notification['id'] ) ) {
			update_user_meta(
				get_current_user_id(),
				CHINA_PAYMENTS_ALIAS . '_last_notification_id',
				$latest_notification['id']
			);
		}

		return rest_ensure_response(
			array(
				'status' => 'ok',
			)
		);
	}

	public static function set_quick_setup_skip( WP_REST_Request $request ) {
		if ( ! $request->has_param( 'status' ) ) {
			return new WP_Error(
				'rest_error',
				esc_html( sprintf( __( 'Missing request param %s', 'china-payments' ), 'status' ) ),
				array(
					'status' => 400,
				)
			);
		}

		Settings::instance()->update(
			array(
				'skipped_quick_setup' => ( intval( $request->get_param( 'status' ) ) ? 1 : 0 ),
			)
		);

		return rest_ensure_response(
			array(
				'quick_setup_skipped' => intval( Settings::instance()->get( 'skipped_quick_setup' ) ),
			)
		);
	}
}
