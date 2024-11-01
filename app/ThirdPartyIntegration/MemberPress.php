<?php

namespace ChinaPayments\ThirdPartyIntegration;

class MemberPress {

	/**
	 * @var MemberPress
	 */
	protected static $_instance = null;

	public static function instance() {
		if ( self::$_instance === null ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	private $_did_plugins_loaded = false;

	public function setup() {
		add_action( 'rest_api_init', array( MemberPress\RestAPI::instance(), 'setup' ) );

		add_filter( 'mepr-gateway-paths', array( $this, '_mepr_gateway_paths' ), 10, 1 );
		add_filter( 'mepr-ctrls-paths', array( $this, '_mepr_ctrls_paths' ), 99, 1 );

		if ( did_action( 'plugins_loaded' ) ) {
			$this->_plugins_loaded();
		} else {
			add_action( 'plugins_loaded', array( $this, '_plugins_loaded' ), 20 );
		}
	}

	public function _plugins_loaded() {
		if ( $this->_did_plugins_loaded ) {
			return;
		}

		if ( ! class_exists( '\MeprBaseRealGateway' ) || ! class_exists( '\MeprBaseCtrl' ) ) {
			return;
		}

		require_once CHINA_PAYMENTS_BASE_PATH . '/lib/memberpress-payment-gateways/skeleton/ChinaPayments_Skeleton_Gateway.php';
		require_once CHINA_PAYMENTS_BASE_PATH . '/lib/memberpress-payment-gateways/skeleton/ChinaPayments_Skeleton_Ctrl.php';

		require_once CHINA_PAYMENTS_BASE_PATH . '/lib/memberpress-payment-gateways/MeprChinaPaymentsAliPayGateway.php';
		require_once CHINA_PAYMENTS_BASE_PATH . '/lib/memberpress-payment-gateways/MeprChinaPaymentsAliPayCtrl.php';
		require_once CHINA_PAYMENTS_BASE_PATH . '/lib/memberpress-payment-gateways/MeprChinaPaymentsWeChatGateway.php';
		require_once CHINA_PAYMENTS_BASE_PATH . '/lib/memberpress-payment-gateways/MeprChinaPaymentsWeChatCtrl.php';

		$this->_did_plugins_loaded = true;
	}

	public function _mepr_gateway_paths( $paths ) {
		if ( ! $this->_did_plugins_loaded ) {
			$this->_plugins_loaded();
		}

		$paths[] = CHINA_PAYMENTS_BASE_PATH . '/lib/memberpress-payment-gateways';

		return $paths;
	}

	public function _mepr_ctrls_paths( $paths ) {
		if ( ! $this->_did_plugins_loaded ) {
			$this->_plugins_loaded();
		}

		$paths[] = CHINA_PAYMENTS_BASE_PATH . '/lib/memberpress-payment-gateways';

		return $paths;
	}
}
