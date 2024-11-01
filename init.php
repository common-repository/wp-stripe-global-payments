<?php

require_once CHINA_PAYMENTS_BASE_PATH . '/autoload.php';
require_once CHINA_PAYMENTS_BASE_PATH . '/lib/definitions.php';
require_once CHINA_PAYMENTS_BASE_PATH . '/lib/polyfill.php';

require_once CHINA_PAYMENTS_BASE_PATH . '/app/_functions/administration.php';
require_once CHINA_PAYMENTS_BASE_PATH . '/app/_functions/general.php';
require_once CHINA_PAYMENTS_BASE_PATH . '/app/_functions/utilities.php';

ChinaPayments\Migration::instance()->setup();

ChinaPayments\ThirdPartyIntegration\Freemius::instance();

if ( ! function_exists( 'china_payments_fs' ) ) {
  function china_payments_fs() {
    return ChinaPayments\ThirdPartyIntegration\Freemius::instance();
  }
}

ChinaPayments\ThirdPartyIntegration\MemberPress::instance()->setup();
ChinaPayments\ThirdPartyIntegration\PaymentPage::instance()->setup();
ChinaPayments\ThirdPartyIntegration\WooCommerce::instance()->setup();
ChinaPayments\ThirdPartyIntegration\LifterLMS::instance()->setup();

if( did_action( 'plugins_loaded' ) || doing_action( 'plugins_loaded' ) ) {
  if( defined( "SIMPLE_WP_MEMBERSHIP_VER" ) )
    ChinaPayments\ThirdPartyIntegration\SimpleMembership::instance()->setup();
} else {
  add_action( 'plugins_loaded', function() {
    if( defined( "SIMPLE_WP_MEMBERSHIP_VER" ) )
      ChinaPayments\ThirdPartyIntegration\SimpleMembership::instance()->setup();
  }, 99 );
}


// General Functionality
ChinaPayments\Controller::instance()->setup();

if( is_admin() )
  ChinaPayments\AdminController::instance()->setup();

// RestAPI
add_action( 'rest_api_init', [ ChinaPayments\RestAPI::instance(), 'setup' ] );

// Site Health
add_filter( 'site_status_tests', [ ChinaPayments\SiteHealth::instance(), 'tests' ] );