<?php

namespace ChinaPayments\RestAPI;

use WP_Error;
use WP_REST_Server;
use WP_REST_Request;
use ChinaPayments\API\ChinaPayments as API_ChinaPayments;
use ChinaPayments\PaymentGateway;
use ChinaPayments\Settings;

class Plugin {

	public static function register_routes() {
		register_rest_route(
			CHINA_PAYMENTS_REST_API_PREFIX . '/v1',
			'/plugin/install',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => '\ChinaPayments\RestAPI\Plugin::install',
				'permission_callback' => function () {
					return current_user_can( CHINA_PAYMENTS_ADMIN_CAP );
				},
			)
		);

		register_rest_route(
			CHINA_PAYMENTS_REST_API_PREFIX . '/v1',
			'/plugin/activate',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => '\ChinaPayments\RestAPI\Plugin::activate',
				'permission_callback' => function () {
					return current_user_can( CHINA_PAYMENTS_ADMIN_CAP );
				},
			)
		);
	}

	public static function install( WP_REST_Request $request ) {
		if ( ! $request->has_param( 'identifier' ) ) {
			return new WP_Error(
				'rest_error',
				esc_html( sprintf( __( 'Missing request param %s', 'china-payments' ), 'identifier' ) ),
				array(
					'status' => 400,
				)
			);
		}

		if ( ! array_key_exists( $request->get_param( 'identifier' ), china_payments_integrations() ) ) {
			return new WP_Error(
				'rest_error',
				__( 'Plugin install not allowed.', 'china-payments' ),
				array(
					'status' => 400,
				)
			);
		}

		require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';

		if ( ! class_exists( '\Plugin_Upgrader' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		}

		if ( ! class_exists( '\WP_Ajax_Upgrader_Skin' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-ajax-upgrader-skin.php';
		}

		$identifier = $request->get_param( 'identifier' );

		if ( is_plugin_active( $identifier ) ) {
			return rest_ensure_response(
				array(
					'is_installed' => 1,
				)
			);
		}

		try {
			$api = plugins_api(
				'plugin_information',
				array(
					'slug' => ( strpos( $identifier, '/' ) !== -1 ? substr( $identifier, 0, strpos( $identifier, '/' ) ) : $identifier ),
				)
			);
		} catch ( \Throwable $th ) {
			return new WP_Error( 'rest_error', $th->getMessage() );
		}

		if ( is_wp_error( $api ) ) {
			return $api;
		}

		$skin     = new \WP_Ajax_Upgrader_Skin();
		$upgrader = new \Plugin_Upgrader( $skin );
		$result   = $upgrader->install( $api->download_link );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response(
			array(
				'is_installed' => 1,
			)
		);
	}

	public static function activate( WP_REST_Request $request ) {
		if ( ! $request->has_param( 'identifier' ) ) {
			return new WP_Error(
				'rest_error',
				esc_html( sprintf( __( 'Missing request param %s', 'china-payments' ), 'identifier' ) ),
				array(
					'status' => 400,
				)
			);
		}

		if ( ! array_key_exists( $request->get_param( 'identifier' ), china_payments_integrations() ) ) {
			return new WP_Error(
				'rest_error',
				__( 'Plugin activate not allowed.', 'china-payments' ),
				array(
					'status' => 400,
				)
			);
		}

		require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		$path = WP_PLUGIN_DIR . '/' . $request->get_param( 'identifier' );

		if ( ! file_exists( $path ) ) {
			return new WP_Error(
				'rest_error',
				__( 'Plugin Activation not allowed.', 'china-payments' ),
				array(
					'status' => 400,
				)
			);
		}

		activate_plugin( $path );

		return rest_ensure_response(
			array(
				'is_active' => 1,
			)
		);
	}
}
