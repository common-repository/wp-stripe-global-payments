<?php

use ChinaPayments\PaymentGateway as InstancePaymentGateway;
use ChinaPayments\Settings as ChinaPayments_Settings;

if ( ! function_exists( 'china_payments_admin_register_menu' ) ) {

	/**
	 * @param $page_title
	 * @param $menu_title
	 * @param $capability
	 * @param $menu_slug
	 * @param $function
	 * @param int|null   $position
	 */
	function china_payments_admin_register_menu( $page_title, $menu_title, $capability, $menu_slug, $function, ?int $position = null ) {
		add_submenu_page(
			CHINA_PAYMENTS_MENU_SLUG,
			$page_title,
			$menu_title,
			$capability,
			$menu_slug,
			$function,
			$position
		);
	}

}
