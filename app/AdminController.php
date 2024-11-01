<?php

namespace ChinaPayments;

class AdminController {

	/**
	 * @var null|AdminController;
	 */
	protected static $_instance = null;

	/**
	 * @return AdminController
	 */
	public static function instance(): AdminController {
		if ( self::$_instance === null ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	private $_action_map = array(
		'force-db-table-integrity' => '_action_force_db_table_integrity',
		'stripe-webhook-create'    => '_action_stripe_webhook_create',
	);

	public function setup() {
		$latest_notification = API\Notification::instance()->get_latest_notification();

		if ( $latest_notification !== null && isset( $latest_notification['id'] ) ) {
			add_action( 'admin_notices', array( $this, '_admin_notice' ) );
		}

		add_action( 'admin_menu', array( $this, '_register_menu' ) );

		if ( Request::instance()->is_request_type( 'admin' ) ) {
			add_action( 'admin_enqueue_scripts', 'china_payments_register_universal_interface', 5 );
		}

		if ( Settings::instance()->get_flag( 'configuration-setup-rules-flushed' ) === false ) {
			add_action( 'init', array( $this, '_setup_rules_flush_action_init' ), 7 );
		}

		if ( isset( $_GET[ CHINA_PAYMENTS_PREFIX . '-action' ] ) && isset( $this->_action_map[ $_GET[ CHINA_PAYMENTS_PREFIX . '-action' ] ] ) ) {
			add_action( 'init', array( $this, $this->_action_map[ $_GET[ CHINA_PAYMENTS_PREFIX . '-action' ] ] ) );
		}
	}

	public function _admin_notice() {
		if ( ! current_user_can( CHINA_PAYMENTS_ADMIN_CAP ) ) {
			return;
		}

		$latest_notification = API\Notification::instance()->get_latest_notification();

		if ( intval( get_user_meta( get_current_user_id(), CHINA_PAYMENTS_ALIAS . '_last_notification_id', true ) ) === intval( $latest_notification['id'] ) ) {
			return;
		}

		$protocols = wp_allowed_protocols();

		if ( ! in_array( 'data', $protocols ) ) {
			$protocols[] = 'data';
		}

		$content = wp_kses( $latest_notification['content'], china_payments_content_allowed_html_tags(), $protocols );

		echo '<div id="china-payments-notification-container" class="notice notice-info is-dismissible">
            <h2>' . $latest_notification['title'] . '</h2>
            ' . $content . '
          </div>';
	}

	public function _register_menu() {
		add_menu_page(
			CHINA_PAYMENTS_NAME,
			CHINA_PAYMENTS_MENU_NAME,
			CHINA_PAYMENTS_ADMIN_CAP,
			CHINA_PAYMENTS_MENU_SLUG,
			array( $this, '_display_dashboard' ),
			'data:image/svg+xml;base64,' . base64_encode( file_get_contents( CHINA_PAYMENTS_BASE_PATH . '/interface/img/icon.svg' ) ),
			100
		);
	}

	public function _display_dashboard() {
		Template::load_template( 'component-dashboard.php' );
	}

	public function _setup_rules_flush_action_init() {
		if ( Request::instance()->is_request_type( 'ajax' ) ) {
			return;
		}

		_china_payments_refresh_rewrite_rules_and_capabilities();

		Settings::instance()->update(
			array(
				'configuration-setup-rules-flushed' => 1,
			)
		);

		add_action(
			'init',
			function () {
				china_payments_redirect( Request::instance()->get_current_url() );
				exit;
			},
			500
		);
	}

	public function _action_force_db_table_integrity() {
		if ( ! current_user_can( CHINA_PAYMENTS_ADMIN_CAP ) ) {
			exit( __( 'Invalid Request', 'china-payments' ) );
		}

		$response = Migration::instance()->fix_table_structure( true );

		if ( is_array( $response ) ) {
			china_payments_debug_dump( base64_decode( $response['table_query_b64'] ) );

			exit( __( 'Did not manage to fix Table Structure', 'china-payments' ) );
		}

		china_payments_redirect( get_admin_url( null, 'site-health.php' ), 302 );
		exit;
	}

	public function _action_stripe_webhook_create() {
		if ( ! current_user_can( CHINA_PAYMENTS_ADMIN_CAP ) ) {
			exit( __( 'Invalid Request', 'china-payments' ) );
		}

		PaymentGateway::get_integration_from_settings( 'stripe', isset( $_GET['mode'] ) && $_GET['mode'] === 'live' )->handle_webhook_integrity();

		china_payments_redirect( get_admin_url( null, 'site-health.php' ), 302 );
		exit;
	}
}
