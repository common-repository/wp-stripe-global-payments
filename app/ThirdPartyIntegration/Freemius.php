<?php

namespace ChinaPayments\ThirdPartyIntegration;

use Freemius as Freemius_Class;
class Freemius {
    /**
     * @var Freemius_Class|null
     */
    protected static $_instance = null;

    public static function instance() : Freemius_Class {
        if ( self::$_instance !== null ) {
            return self::$_instance;
        }
        if ( !class_exists( 'fs_dynamic_init' ) ) {
            require_once CHINA_PAYMENTS_BASE_PATH . '/lib/freemius/wordpress-sdk/start.php';
        }
        self::$_instance = fs_dynamic_init( array(
            'id'              => CHINA_PAYMENTS_FREEMIUS_ID,
            'slug'            => CHINA_PAYMENTS_FREEMIUS_SLUG,
            'premium_slug'    => CHINA_PAYMENTS_FREEMIUS_SLUG_PRO,
            'type'            => 'plugin',
            'public_key'      => CHINA_PAYMENTS_FREEMIUS_PUBLIC_KEY,
            'is_premium'      => false,
            'has_addons'      => false,
            'has_paid_plans'  => true,
            'has_affiliation' => 'selected',
            'menu'            => array(
                'slug' => CHINA_PAYMENTS_PREFIX,
            ),
            'is_live'         => true,
        ) );
        self::$_instance->add_filter( 'plugin_icon', function () {
            return CHINA_PAYMENTS_BASE_PATH . '/interface/img/logo.png';
        } );
        do_action( 'china_payments_fs_loaded' );
        return self::$_instance;
    }

    public static function api_request_details() {
        $site = self::instance()->get_site();
        return array(
            'site_url'              => get_site_url(),
            'site_rest_url'         => rest_url(),
            'freemius_anonymous_id' => self::instance()->get_anonymous_id(),
            'freemius_site_id'      => ( empty( $site ) ? 0 : $site->id ),
            'freemius_plan_id'      => ( empty( $site ) ? 0 : self::instance()->get_plan_id() ),
            'freemius_license_id'   => ( empty( $site ) ? 0 : self::instance()->_get_license()->id ?? 0 ),
        );
    }

}
