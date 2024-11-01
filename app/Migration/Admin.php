<?php

namespace ChinaPayments\Migration;

use ChinaPayments\Migration;
use ChinaPayments\Settings;

class Admin {

	public function __construct() {
		add_action( 'wp_ajax_china_payments_migration_handler', array( $this, 'migrate_single' ) );

		if ( Migration::instance()->is_migration_required() ) {
			add_action( 'admin_init', array( $this, '_init' ) );
			add_action( 'admin_menu', array( $this, '_register_menu' ), 50 );
		} elseif ( isset( $_GET['page'] ) && ( str_starts_with( $_GET['page'], 'china-payments-migration' ) ) ) {
			add_action(
				'init',
				function () {
					china_payments_redirect( admin_url( CHINA_PAYMENTS_DEFAULT_URL_PATH ) );
					exit;
				}
			);
		}
	}

	public function _init() {
		if ( isset( $_GET['page'] ) && ( str_starts_with( $_GET['page'], 'china-payments-migration' ) ) ) {
			return;
		}

		if ( isset( $_GET['page'] ) && str_starts_with( $_GET['page'], 'china-payments-' ) ) {
			china_payments_redirect( admin_url( 'admin.php?page=china-payments-migration' ) );
			exit;
		}

		add_action( 'admin_notices', array( $this, 'admin_notices' ) );
	}

	public function _register_menu() {
		china_payments_admin_register_menu(
			sprintf( __( ' %s Migration', 'china-payments' ), CHINA_PAYMENTS_NAME ),
			__( 'Migration', 'china-payments' ),
			CHINA_PAYMENTS_ADMIN_CAP,
			'china-payments-migration',
			array( $this, 'display' )
		);
	}

	public function display() {
		echo '<div class="wrap">';
		echo '<h2>' . sprintf( __( '%s Migration', 'china-payments' ), CHINA_PAYMENTS_NAME ) . '</h2>';
		echo '<hr/>';

		echo '<p>' . sprintf( __( "You're about to upgrade to %1\$s version %2\$s this version brings in database upgrades.", 'china-payments' ), CHINA_PAYMENTS_NAME, CHINA_PAYMENTS_VERSION ) . '</p>';
		echo '<p>' . __( 'Please do not close this screen after pressing continue, until the update is completed.', 'china-payments' ) . '</p>';
		echo '<a class="button button-primary china-payments-migration-continue">' . __( 'Continue', 'china-payments' ) . '</a>';

		echo '<div id="migration-china-payments-spinner-loader-container" style="display:none;"></div>';

		echo '<div id="china-payments-migration-helper-log"></div>';
		echo '</div>';

		echo '
      <style>
      #china-payments-migration-helper-log {
         display:none;
         background: var( --china-payments-layout-secondary-background-color );
         padding: var( --china-payments-spacing-type-secondary );
         border-radius: 5px;
      }
      
      #china-payments-migration-helper-log > p {
        margin : 0 0 var( --china-payments-spacing-type-block-element ) 0;
        font-size : var( --china-payments-text-default-font-size );
      }
      
      </style>
      <script type="text/javascript">
        var ChinaPaymentsMigration = {
        
          processing : false,
        
          init : function() {
            var objectInstance = this;
            
            jQuery("#wpbody-content").on( "click", ".china-payments-migration-continue", function() {
              jQuery(this).fadeOut("slow");
              
              objectInstance.migrateToNext();
            });
            
            ChinaPayments.setLoadingContent( jQuery("#migration-china-payments-spinner-loader-container") );
          },
          
          migrateToNext : function( request_data, last_response ) {
            if( this.processing === true )
              return;
            
            last_response = ( typeof last_response === "undefined" ? "" : last_response );
            
            jQuery("#migration-china-payments-spinner-loader-container").slideDown("slow");
              
            var objectInstance = this;
            
            request_data = ( request_data !== undefined && request_data !== null && request_data.constructor === Object ? request_data : {} );
            request_data.action  = "china_payments_migration_handler";
            request_data.attempt = ( typeof request_data.attempt !== "undefined" ? request_data.attempt : 0 );
            
            this.processing = true;
            
            jQuery.post(ajaxurl, request_data )
                  .done( function (response) {
                    objectInstance.processing = false;

                    if( last_response !== "" ) {
                      if( jQuery( "#china-payments-migration-attempt-helper" ).length === 0 )
                        jQuery( "body" ).append( \'<div id="china-payments-migration-attempt-helper" style="display: none !important;"></div>\' );
                      
                      var helperObject = jQuery( "#china-payments-migration-attempt-helper" );
                      
                      helperObject.html( response );
                      helperObject.find( "[data-china-payments-migration-attempt]" ).remove();
                      
                      var current_response_clean = helperObject.html();
                      
                      helperObject.html( last_response );
                      helperObject.find( "[data-china-payments-migration-attempt]" ).remove();
                      
                      if( helperObject.html() === current_response_clean )
                        request_data.attempt++;
                      else 
                        request_data.attempt = 0;
                    }
                                                            
                    jQuery("#china-payments-migration-helper-log").show().prepend( response );
                    
                    if( jQuery( response ).find(".china-payments-migration-continue , .china-payments-migration-complete").length === 0 && response.indexOf("china-payments-migration-complete") === -1 )
                      objectInstance.migrateToNext( { 
                        attempt        : request_data.attempt
                      }, response );
                    else 
                      jQuery("#migration-china-payments-spinner-loader-container").slideUp("slow");
                  } ).fail( function() {
                    objectInstance.processing = false;
                    
                    objectInstance.migrateToNext();
                  });
          }
        
        };
        
        jQuery( window ).on( "china_payments_ready", function() {
          ChinaPaymentsMigration.init();
        });
      </script>
    ';
	}

	public function admin_notices( $hook ) {

		$url = admin_url( 'admin.php?page=china-payments-migration' );

		echo '<div class="notice notice-success is-dismissible">';
		echo '<h2>' . sprintf( __( '%s Database Upgrade', 'china-payments' ), CHINA_PAYMENTS_NAME ) . '</h2>';
		echo '<p>' . sprintf( 'Before you continue to use %s you need to migrate to the newest version.', CHINA_PAYMENTS_NAME ) . '</p>';
		echo '<p><a class="button button-primary" href="' . $url . '">' . __( 'Click here to continue', 'china-payments' ) . '</a></p>';
		echo '</div>';
	}

	public function migrate_single() {
		if ( ! current_user_can( CHINA_PAYMENTS_ADMIN_CAP ) ) {
			exit;
		}

		if ( Migration::instance()->current_version >= Migration::instance()->current_version_available ) {
			echo '<p class="china-payments-migration-complete">' . __( 'Database successfully updated. Continue to: ', 'china-payments' ) . '</p>';
			echo '<p>';
			echo '<a href="' . admin_url( CHINA_PAYMENTS_DEFAULT_URL_PATH ) . '" class="button button-primary">' . __( 'Manage Settings', 'china-payments' ) . '</a> ';
			echo '</p>';
			exit;
		}

		$version = Migration::instance()->get_current_migration_version();

		if ( $version === false ) {
			echo '<p data-china-payments-notification="danger">' . __( 'Could not determine the migration version.', 'china-payments' ) . '</p>';
			echo '<p><a class="button button-primary china-payments-migration-continue">' . __( 'Retry', 'china-payments' ) . '</a></p>';
			exit;
		}

		$attempt = ( isset( $_POST['attempt'] ) ? intval( $_POST['attempt'] ) : 0 );

		echo '<p data-china-payments-migration-version="' . $version . '">';
		printf( __( 'Processing File: %s', 'china-payments' ), $version );

		if ( $attempt !== 0 ) {
			echo '<span data-china-payments-migration-attempt="' . $attempt . '">';
			echo '( ' . sprintf( __( 'Retry Attempt: %1$s; Current Version: %2$s', 'china-payments' ), $attempt, Migration::instance()->current_version ) . ' )';
			echo '</span>';
		}
		echo '</p>';

		if ( $attempt >= 5 ) {
			Migration::instance()->current_version = $version;
			update_option( Migration::instance()->option_alias_version, $version );

			echo '<p data-china-payments-notification="danger">';
			printf( __( 'Skipped Migration file for version: %s, check your Platform Health Report for possible issues.', 'china-payments' ), $version );
			echo '</p>';
			exit;
		}

		echo Migration::instance()->migrate_to_version( $version );

		Settings::instance()->update(
			array(
				'configuration-setup-rules-flushed' => 1,
			)
		);

		exit;
	}
}
