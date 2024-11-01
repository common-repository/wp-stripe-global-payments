<?php

namespace ChinaPayments;

class SiteHealth {

	/**
	 * @var null|SiteHealth;
	 */
	protected static $_instance = null;

	/**
	 * @return SiteHealth
	 */
	public static function instance(): SiteHealth {
		if ( self::$_instance === null ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	public function tests( $tests ) {
		$tests['direct'][ CHINA_PAYMENTS_ALIAS . '_test_database_integrity' ] = array(
			'label' => __( '%s - Database Integrity', 'china-payments' ),
			'test'  => array( $this, '_database_integrity' ),
		);

		/**
		if( Settings::instance()->get( 'stripe_live_public_key' ) !== '' ) {
			$tests[ 'direct' ][ CHINA_PAYMENTS_ALIAS . '_stripe_live' ] = [
			'label' => __( '%s - Stripe Live Webhook', "china-payments" ),
			'test'  => [ $this, '_stripe_live_webhook' ],
			];
		}
		if( Settings::instance()->get( 'stripe_test_public_key' ) !== '' ) {
			$tests[ 'direct' ][ CHINA_PAYMENTS_ALIAS . '_stripe_test' ] = [
			'label' => __( '%s - Stripe Test Webhook', "china-payments" ),
			'test'  => [ $this, '_stripe_test_webhook' ],
			];
		}
		*/

		return $tests;
	}

	public function _database_integrity() {
		$default = array(
			'description' => '<p>' . sprintf( __( '%s uses custom tables to store data efficiently.', 'china-payments' ), CHINA_PAYMENTS_NAME ) . '</p>',
			'test'        => CHINA_PAYMENTS_ALIAS . '_database_integrity',
		);

		if ( Migration::instance()->is_valid_table_structure() ) {
			return array(
				'label'  => sprintf( __( '%s - Valid DB Table Structure.', 'china-payments' ), CHINA_PAYMENTS_NAME ),
				'status' => 'good',
				'badge'  => array(
					'label' => __( 'Critical', 'china-payments' ),
					'color' => 'green',
				),
			) + $default;
		}

		return array(
			'label'   => sprintf( __( '%s - Invalid DB Table Structure.', 'china-payments' ), CHINA_PAYMENTS_NAME ),
			'status'  => 'critical',
			'badge'   => array(
				'label' => __( 'Critical', 'china-payments' ),
				'color' => 'red',
			),
			'actions' => sprintf(
				'<p><a href="%s">%s</a></p>',
				esc_url( admin_url( '?' . CHINA_PAYMENTS_PREFIX . '-action=force-db-table-integrity' ) ),
				__( 'Fix Table Integrity', 'china-payments' )
			),
		) + $default;
	}

	public function _stripe_live_webhook(): array {
		return $this->_stripe_webhook_by_mode( 'live' );
	}

	public function _stripe_test_webhook(): array {
		return $this->_stripe_webhook_by_mode( 'test' );
	}

	public function _stripe_webhook_by_mode( $mode ): array {
		$default = array(
			'description' => '<p>' . sprintf( __( '%s listens to Stripe Webhook callbacks to know when payments are completed.', 'china-payments' ), CHINA_PAYMENTS_NAME ) . '</p>',
			'test'        => CHINA_PAYMENTS_ALIAS . '_stripe_' . $mode . '_webhook',
		);

		$stripeInstance = PaymentGateway::get_integration_from_settings( 'stripe', ( $mode === 'live' ? 1 : 0 ) );

		if ( $stripeInstance->is_webhook_configured() ) {
			return array(
				'label'  => sprintf( __( '%1$s - Stripe %2$s Webhook created.', 'china-payments' ), CHINA_PAYMENTS_NAME, ( $stripeInstance->is_live() ? 'Live' : 'Test' ) ),
				'status' => 'good',
				'badge'  => array(
					'label' => __( 'Critical', 'china-payments' ),
					'color' => 'green',
				),
			) + $default;
		}

		return array(
			'label'   => sprintf( __( '%1$s - Stripe %2$s Webhook missing.', 'china-payments' ), CHINA_PAYMENTS_NAME, ( $stripeInstance->is_live() ? 'Live' : 'Test' ) ),
			'status'  => 'critical',
			'badge'   => array(
				'label' => __( 'Critical', 'china-payments' ),
				'color' => 'red',
			),
			'actions' => sprintf(
				'<p><a href="%s">%s</a></p>',
				esc_url( admin_url( '?' . CHINA_PAYMENTS_PREFIX . '-action=stripe-webhook-create&mode=' . $mode ) ),
				__( 'Create Webhook', 'china-payments' )
			),
		) + $default;
	}
}
