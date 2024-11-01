<?php

namespace ChinaPayments;

use ChinaPayments\Settings;

class PaymentGateway {

	protected static $_integrations_from_settings = array();

	public static function available_integrations(): array {
		return array(
			'stripe' => __( 'Stripe', 'china-payments' ),
		);
	}

	public static function get_administration_dashboard(): array {
		$response = array(
			'stripe' => array(
				'name'                    => 'Stripe',
				'alias'                   => 'stripe',
				'logo'                    => self::get_integration_from_settings( 'stripe' )->get_logo_url(),
				'description'             => self::get_integration_from_settings( 'stripe' )->get_description(),
				'mode'                    => self::get_integration_from_settings( 'stripe' )->is_live() ? 'live' : 'test',
				'account_name_live'       => self::get_integration_from_settings( 'stripe', 1 )->get_account_name(),
				'account_name_test'       => self::get_integration_from_settings( 'stripe', 0 )->get_account_name(),
				'connection_layout'       => 'authentication',
				'mode_live_configured'    => ( Settings::instance()->get( 'stripe_live_public_key' ) !== '' ),
				'mode_test_configured'    => ( Settings::instance()->get( 'stripe_test_public_key' ) !== '' ),
				'payment_methods_enabled' => array_values( Settings::instance()->get( 'stripe_payment_methods' ) ),
				'payment_methods'         => array_values( self::get_integration_from_settings( 'stripe' )->get_payment_methods_administration() ),
				'webhook_settings'        => self::get_integration_from_settings( 'stripe' )->get_webhook_settings_administration(),
			),
		);

		return apply_filters( 'china_payments_administration_dashboard', $response );
	}

	/**
	 * @param $identifier
	 * @return PaymentGateway\Skeleton|null
	 */
	public static function get_integration( $identifier ) {
		if ( $identifier === 'stripe' ) {
			return new PaymentGateway\Stripe();
		}

		return null;
	}

	/**
	 * @param $identifier
	 * @param null       $is_live
	 * @return mixed|PaymentGateway\Stripe|null
	 */
	public static function get_integration_from_settings( $identifier, $is_live = null ) {
		$identifier_suffix = '_' . ( $is_live === null ? 'default' : ( $is_live ? 'live' : 'test' ) );

		if ( isset( self::$_integrations_from_settings[ $identifier . $identifier_suffix ] ) ) {
			return self::$_integrations_from_settings[ $identifier . $identifier_suffix ];
		}

		if ( $identifier === 'stripe' ) {
			self::$_integrations_from_settings[ $identifier . $identifier_suffix ] = new PaymentGateway\Stripe();
		} else {
			return null;
		}

		self::$_integrations_from_settings[ $identifier . $identifier_suffix ]->attach_settings_credentials( $is_live );

		return self::$_integrations_from_settings[ $identifier . $identifier_suffix ];
	}
}
