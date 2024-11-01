<?php

namespace ChinaPayments;

class Settings {

	/**
	 * @var Settings|null
	 */
	protected static $_instance = null;

	public static function instance(): Settings {
		if ( self::$_instance === null ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	public $defaultOptions = array(
		'skipped_quick_setup'                    => 1,

		'stripe_is_live'                         => 0,
		'stripe_payment_methods'                 => array( 'alipay', 'wechat' ),

		'stripe_live_user_id'                    => '',
		'stripe_live_public_key'                 => '',
		'stripe_live_secret_key'                 => '',
		'stripe_live_connect_integration_secret' => '',
		'stripe_live_webhook_secret'             => '',

		'stripe_test_user_id'                    => '',
		'stripe_test_public_key'                 => '',
		'stripe_test_secret_key'                 => '',
		'stripe_test_connect_integration_secret' => '',
		'stripe_test_webhook_secret'             => '',

		'configuration-setup-rules-flushed'      => 0,
	);

	public $options;
	public $option_name = 'china_payments_settings';

	public function __construct() {
		$this->refresh();
	}

	public function refresh() {
		$this->options = get_option( $this->option_name, $this->defaultOptions );
	}

	/**
	 * @param array|string        $option_name
	 * @param object|array|string $default
	 * @return mixed
	 */
	public function get( $option_name, $default = '' ) {
		if ( is_array( $option_name ) ) {
			$response = array();

			foreach ( $option_name as $option ) {
				$response[ $option ] = $this->get( $option, $default );
			}

			return $response;
		}

		if ( isset( $this->options[ $option_name ] ) ) {
			return $this->options[ $option_name ];
		}

		if ( $default === null ) {
			return null;
		}

		if ( empty( $default ) && isset( $this->defaultOptions[ $option_name ] ) ) {
			return $this->defaultOptions[ $option_name ];
		}

		return $default;
	}

	public function get_flag( string $option_name ): bool {
		return boolval( $this->get( $option_name ) );
	}

	/**
	 * When the settings update is silent, it means it's an update or activation operation.
	 *
	 * @param $options
	 * @return bool
	 */
	public function update( $options ): bool {
		$options = apply_filters( 'china_payments_update_settings', $options );

		foreach ( $options as $option_key => $option_value ) {
			if ( is_array( $option_value ) ) {
				foreach ( $option_value as $opt_val_key => $opt_val ) {
					if ( $opt_val === 'a:0:{}' ) {
						unset( $option_value[ $opt_val_key ] );
					}
				}
			}

			if ( is_string( $option_value ) ) {
				$option_value = stripslashes( $option_value );
			} elseif ( is_array( $option_value ) ) {
				foreach ( $option_value as $opt_val_key => $opt_val_val ) {
					$option_value[ $opt_val_key ] = ( is_string( $opt_val_val ) ? stripslashes( $opt_val_val ) : $opt_val_val );
				}
			}

			if ( isset( $this->defaultOptions[ $option_key ] ) && $this->defaultOptions[ $option_key ] == $option_value ) {
				unset( $this->options[ $option_key ] );
				continue;
			}

			$this->options[ $option_key ] = $option_value;
		}

		update_option( $this->option_name, $this->options );

		return true;
	}

	/**
	 * @param array|string $options
	 * @return bool
	 */
	public function delete( $options ): bool {
		if ( ! is_array( $options ) ) {
			$options = array( $options );
		}

		foreach ( $options as $option_key ) {
			unset( $this->options[ $option_key ] );
		}

		update_option( $this->option_name, $this->options );

		return true;
	}
}
