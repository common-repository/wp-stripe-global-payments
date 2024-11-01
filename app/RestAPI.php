<?php

namespace ChinaPayments;

class RestAPI {

	/**
	 * @var RestAPI;
	 */
	protected static $_instance;

	/**
	 * @return RestAPI
	 */
	public static function instance(): RestAPI {
		if ( self::$_instance === null ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	public function setup() {
		RestAPI\Administration::register_routes();
		RestAPI\PaymentGateway::register_routes();
		RestAPI\Plugin::register_routes();
		RestAPI\Tagging::register_routes();
		RestAPI\Webhook::register_routes();
	}
}
