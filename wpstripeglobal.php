<?php
/**
 * Plugin Name: China Payments Plugin
 * Plugin URI: https://chinapaymentsplugin.com
 * Description: Easily accept WeChat Pay & Alipay payments from Chinese customers. 
 * Version: 3.4.0
 * Author: China Plugins
 * Author URI: https://chinaplugins.com
 * License: GPLv3
 * Text Domain: wp-stripe-global-payments
 * Domain Path: /languages/
 */
/*
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 *
 * Copyright 2018 Brand on Fire, LLC.
 */

defined( 'ABSPATH' ) || exit;

if( !function_exists( '_china_payments_init' ) ) {
  function _china_payments_init() {
    define( "CHINA_PAYMENTS_VERSION", '3.4.0' );
    define( "CHINA_PAYMENTS_BASE_FILE_PATH", __FILE__ );
    define( "CHINA_PAYMENTS_BASE_PATH", dirname( CHINA_PAYMENTS_BASE_FILE_PATH ) );
    define( "CHINA_PAYMENTS_PLUGIN_IDENTIFIER", ltrim( str_ireplace( dirname( CHINA_PAYMENTS_BASE_PATH ), '', CHINA_PAYMENTS_BASE_FILE_PATH ), '/' ) );

    require_once CHINA_PAYMENTS_BASE_PATH . "/init.php";
  }
}

if( !file_exists( WP_CONTENT_DIR . '/plugins/wp-stripe-global-payments/wpstripeglobal.php' ) ) {
  _china_payments_init();
} else {
  add_action( 'plugins_loaded', function() {
    if( defined( "CHINA_PAYMENTS_VERSION" ) )
      return;

    if (!function_exists('is_plugin_active')) {
      include_once(ABSPATH . 'wp-admin/includes/plugin.php');
    }

    if ( is_plugin_active( 'china-payments-plugin-premium/wpstripeglobal.php' )
      && strpos( __FILE__, 'wp-stripe-global-payments/wpstripeglobal.php' ) !== false )
      return;

    if ( is_plugin_active( 'wp-stripe-global-payments/wpstripeglobal.php' )
      && strpos( __FILE__, 'china-payments-plugin-premium/wpstripeglobal.php' ) !== false ) {
      deactivate_plugins( [ 'wp-stripe-global-payments/wpstripeglobal.php' ] );
    }

    _china_payments_init();
  }, 0 );
}