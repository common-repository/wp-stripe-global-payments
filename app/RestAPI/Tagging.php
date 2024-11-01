<?php

namespace ChinaPayments\RestAPI;

use WP_Error;
use WP_REST_Server;
use WP_REST_Request;
use ChinaPayments\API\Features as API_Features;

class Tagging {

	public static function register_routes() {
		register_rest_route(
			CHINA_PAYMENTS_REST_API_PREFIX . '/v1',
			'/tagging/area/(?P<slug>[\w-]+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => '\ChinaPayments\RestAPI\Tagging::area',
				'permission_callback' => function () {
					return current_user_can( CHINA_PAYMENTS_ADMIN_CAP );
				},
			)
		);

		register_rest_route(
			CHINA_PAYMENTS_REST_API_PREFIX . '/v1',
			'/tagging/apply',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => '\ChinaPayments\RestAPI\Tagging::apply',
				'permission_callback' => function () {
					return current_user_can( CHINA_PAYMENTS_ADMIN_CAP );
				},
			)
		);
	}

	public static function area( WP_REST_Request $request ) {
		return rest_ensure_response( API_Features::instance()->area( $request->get_param( 'slug' ) ) );
	}

	public static function apply( WP_REST_Request $request ) {
		foreach ( array( 'first_name', 'last_name', 'email_address', 'tags', 'area_slug' ) as $required_param ) {
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

		if ( ! is_email( $request->get_param( 'email_address' ) ) ) {
			return new WP_Error(
				'rest_error',
				esc_html( sprintf( __( 'Invalid email address provided.', 'china-payments' ), $required_param ) ),
				array(
					'status' => 400,
				)
			);
		}

		$response = API_Features::instance()->tag(
			$request->get_param( 'tags' ),
			$request->get_param( 'email_address' ),
			array(
				'first_name' => $request->get_param( 'first_name' ),
				'last_name'  => $request->get_param( 'last_name' ),
			),
			$request->get_param( 'area_slug' )
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( empty( $response ) ) {
			return new WP_Error(
				'rest_error',
				esc_html( __( 'An unexpected error has happened, please try again', 'china-payments' ) ),
				array(
					'status' => 400,
				)
			);
		}

		return rest_ensure_response( $response );
	}
}
