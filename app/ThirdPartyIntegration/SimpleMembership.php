<?php

namespace ChinaPayments\ThirdPartyIntegration;

use ChinaPayments\ThirdPartyIntegration\SimpleMembership\Administration as TPI_SimpleMembership_Administration;
use ChinaPayments\ThirdPartyIntegration\SimpleMembership\Frontend as TPI_SimpleMembership_Frontend;

class SimpleMembership {


	/**
	 * @var SimpleMembership
	 */
	protected static $_instance = null;

	public static function instance() {
		if ( self::$_instance === null ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	/**
	 * @var TPI_SimpleMembership_Administration
	 */
	public $administration;
	/**
	 * @var TPI_SimpleMembership_Frontend
	 */
	public $frontend;

	public $payment_gateway_to_title_map = array(
		'cpp_wechat' => 'WeChat Pay via China Payments Plugin (Stripe)',
		'cpp_alipay' => 'Alipay via China Payments Plugin (Stripe)',
	);

	public function setup() {
		add_action( 'rest_api_init', array( SimpleMembership\RestAPI::instance(), 'setup' ) );

		$this->frontend = new TPI_SimpleMembership_Frontend();

		if ( is_admin() ) {
			$this->administration = new TPI_SimpleMembership_Administration();
		}
	}
}
