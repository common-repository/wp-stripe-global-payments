<?php

namespace ChinaPayments;

use WP_Query;

class Controller {

	/**
	 * @var null|Controller;
	 */
	protected static $_instance = null;

	/**
	 * @return Controller
	 */
	public static function instance(): Controller {
		if ( self::$_instance === null ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	public function setup() {
		if ( ! defined( 'CHINA_PAYMENTS_EXTERNAL_API_URL' ) ) {
			define( 'CHINA_PAYMENTS_EXTERNAL_API_URL', 'https://api-v3.chinapaymentsplugin.com/wp-json/china-payments-api/v1/' );
		}

		if ( ! defined( 'NOTIFICATION_SYSTEM_API_URL' ) ) {
			define( 'CHINA_PAYMENTS_NOTIFICATION_API_URL', 'https://api-v3.chinapaymentsplugin.com/wp-json/notification-system-api/v1/' );
		} else {
			define( 'CHINA_PAYMENTS_NOTIFICATION_API_URL', NOTIFICATION_SYSTEM_API_URL );
		}

		if ( ! defined( 'CHINA_PAYMENTS_FEATURES_API_URL' ) ) {
			define( 'CHINA_PAYMENTS_FEATURES_API_URL', 'https://api-v3.chinapaymentsplugin.com/wp-json/features-api/v1/' );
		}

		load_plugin_textdomain( 'china-payments', false, CHINA_PAYMENTS_LANGUAGE_DIRECTORY );

		add_action( 'admin_bar_menu', array( $this, '_admin_bar_menu' ), 999 );
		add_action( 'wp_footer', array( $this, '_wp_footer' ), 999 );
		add_action( 'admin_footer', array( $this, '_wp_footer' ), 999 );
	}

	public function _admin_bar_menu( $admin_bar ) {
		if ( ! current_user_can( CHINA_PAYMENTS_ADMIN_CAP ) ) {
			return;
		}

		$map = array(
			'live' => 0,
			'test' => 0,
			'none' => 0,
		);

		$integrations = array(
			'stripe' => PaymentGateway::get_integration_from_settings( 'stripe' ),
		);

		foreach ( $integrations as $integration ) {
			if ( ! $integration->is_configured() ) {
				++$map['none'];
				continue;
			}

			if ( $integration->is_live() ) {
				++$map['live'];
			} else {
				++$map['test'];
			}
		}

		if ( $map['live'] === 0 && $map['test'] === 0 ) {
			return;
		}

		$admin_bar->add_node(
			array(
				'parent' => 'top-secondary',
				'id'     => CHINA_PAYMENTS_ALIAS,
				'title'  => sprintf( __( 'China Payments %s Mode', 'china-payments' ), ( $map['live'] > 0 && $map['test'] > 0 ? 'Mixed' : ( $map['test'] === 0 ? 'Live' : 'Test' ) ) ),
				'href'   => esc_url( admin_url( 'admin.php?page=' . CHINA_PAYMENTS_MENU_SLUG ) ) . '#payment-gateways',
				'meta'   => array(
					'class' => 'china-payments-top-bar-item ' . ( $map['live'] > 0 && $map['test'] > 0 ? 'is-mixed' : ( $map['test'] === 0 ? 'is-live' : 'is-test' ) ),
				),
			)
		);

		foreach ( $integrations as $integration_alias => $integration ) {
			if ( ! $integration->is_configured() ) {
				return;
			}

			$admin_bar->add_node(
				array(
					'parent' => CHINA_PAYMENTS_ALIAS,
					'id'     => CHINA_PAYMENTS_ALIAS . '_' . $integration_alias,
					'title'  => $integration->get_name() . ' ' . ( $integration->is_live() ? 'Live' : 'Test' ),
					'href'   => esc_url( admin_url( 'admin.php?page=' . CHINA_PAYMENTS_MENU_SLUG ) ) . '#payment-gateways',
					'meta'   => array(
						'class' => 'china-payments-child-bar-item ' . ( $integration->is_live() ? 'is-live' : 'is-test' ),
					),
				)
			);
		}
	}

	public function _wp_footer() {
		if ( ! current_user_can( CHINA_PAYMENTS_ADMIN_CAP ) ) {
			return;
		}

		echo '<style>
            #wpadminbar .china-payments-top-bar-item.is-test {
              background : #f1c40f;
            }
            #wpadminbar .china-payments-top-bar-item.is-mixed {
              background : #e67e22;
            }
            #wpadminbar .china-payments-top-bar-item.is-live {
              background : #2ecc71;
            }
            #wpadminbar .china-payments-child-bar-item.is-test > a {
              color : #f1c40f !important;
            }
            #wpadminbar .china-payments-child-bar-item.is-live > a {
              color : #2ecc71 !important;
            }
          </style>';
	}
}
