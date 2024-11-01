<?php

namespace ChinaPayments;

use ChinaPayments\Migration\Admin as China_Payments_Migration_Admin;

class Migration {

	/**
	 * @var null|Migration;
	 */
	protected static $_instance = null;

	/**
	 * @return Migration
	 */
	public static function instance(): Migration {
		if ( self::$_instance === null ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	public $current_version           = '0.0.0';
	public $current_version_available = '1.0.0';
	public $version_file_folder       = CHINA_PAYMENTS_BASE_PATH . '/lib/migrations/';
	public $version_map               = array(
		'1.0.0' => 'version-1.0.0.php',
	);

	public $option_alias_version  = 'china_payments_migration_version';
	public $option_alias_progress = 'china_payments_migration_progress';

	public $administration = null;

	public function setup() {
		$this->current_version = get_option( $this->option_alias_version, $this->current_version );

		register_activation_hook( CHINA_PAYMENTS_BASE_FILE_PATH, array( $this, '_plugin_activation_hook' ) );

		if ( is_admin() ) {
			$this->administration = new China_Payments_Migration_Admin();
		}

		if ( $this->current_version == '0.0.0' ) {
			$this->_plugin_activation_hook();
		}
	}

	public function get_table_structure(): array {
		$charset_collate = china_payments_wpdb()->get_charset_collate();

		$stripe_customers = china_payments_wpdb()->prefix . CHINA_PAYMENTS_TABLE_STRIPE_CUSTOMERS;

		$response = array();

		$response[ $stripe_customers ] = 'CREATE TABLE `' . $stripe_customers . '` (
                                `id`                    bigint(20) NOT NULL AUTO_INCREMENT,
                                `email_address`         VARCHAR(500) NOT NULL DEFAULT "",
                                `stripe_id`             VARCHAR(500) NOT NULL DEFAULT "",
                                `stripe_account_id`     VARCHAR(500) NOT NULL DEFAULT "",
                                `is_live`               int(1) NOT NULL DEFAULT 0,
                                `created_at`            int(11) NOT NULL,
                                `updated_at`            int(11) NOT NULL,
                                PRIMARY KEY (id),
                                KEY ' . $stripe_customers . '_stripe_id (stripe_id),
                                KEY ' . $stripe_customers . '_is_live (is_live),
                                KEY ' . $stripe_customers . '_stripe_account_id (stripe_account_id)
                              ) ' . $charset_collate;

		return $response;
	}

	public function get_table_list(): array {
		return array_keys( $this->get_table_structure() );
	}

	public function _plugin_activation_hook() {
		if ( ! $this->handle_migration_in_background() ) {
			return;
		}

		$table_structure = $this->get_table_structure();

		if ( ! empty( $table_structure ) ) {
			foreach ( $table_structure as $table_name => $table_query ) {
				china_payments_dbDelta( $table_query );
			}

			$this->current_version = $this->current_version_available;

			update_option( $this->option_alias_version, $this->current_version_available );
		}

		if ( method_exists( $this, '_after_background_migration' ) ) {
			$this->_after_background_migration();
		}

		if ( method_exists( $this, '_after_activation' ) ) {
			$this->_after_activation();
		}

		Settings::instance()->update(
			array(
				'configuration-setup-rules-flushed' => 0,
			)
		);
	}


	public function get_current_migration_version() {
		foreach ( $this->version_map as $migration_version => $migration_file ) {
			if ( version_compare( $migration_version, $this->current_version, '>' ) ) {
				return $migration_version;
			}
		}

		return false;
	}

	public function is_migration_required() {
		return version_compare( $this->current_version_available, $this->current_version, '>' );
	}

	public function can_run_in_background(): bool {
		if ( $this->current_version != '0.0.0' ) {
			return false;
		}

		return true;
	}

	public function migrate_to_version( string $version_number ): string {
		@set_time_limit( 0 );
		@ini_set( 'memory_limit', '512M' );

		$last_migration = get_option( $this->option_alias_progress, 0 );

		if ( time() - $last_migration < 30 ) {
			return '<p>' . sprintf( __( 'Migration currently marked in progress, it started %s seconds ago.', 'china-payments' ), ( $last_migration == 0 ? 'too many' : time() - $last_migration ) ) . '</p>' .
			'<p><a class="button button-primary china-payments-migration-continue">' . __( 'Retry', 'china-payments' ) . '</a></p>';
		}

		if ( ! version_compare( $version_number, $this->current_version, '>' ) && $this->current_version != 0 ) {
			return '<p>' . __( 'This version has already been installed, skipping', 'china-payments' ) . '</p>';
		}

		update_option( $this->option_alias_progress, time() );

		$response = $this->_migrate_to_version( $version_number );

		delete_option( $this->option_alias_progress );

		return $response;
	}

	private function _migrate_to_version( $migrate_to_version ): string {
		$migrate_to_version_response = '';

		$migration_okay = true;

		foreach ( $this->version_map as $migration_version => $migration_file ) {
			if ( version_compare( $this->current_version, $migration_version ) != -1 ) {
				continue;
			}

			if ( ! file_exists( $this->version_file_folder . $migration_file ) ) {
				$migrate_to_version_response .= '<p data-china-payments-notification="danger">' . sprintf( __( 'File not found %s.', 'china-payments' ), $this->version_file_folder . $migration_file ) . '</p>';
				$migrate_to_version_response .= '<p><a class="button button-primary china-payments-migration-continue">' . __( 'Retry', 'china-payments' ) . '</a></p>';
				$migration_okay               = false;
				break;
			}

			if ( version_compare( $migrate_to_version, $migration_version, '<' ) ) {
				break;
			}

			ob_start();

			require_once $this->version_file_folder . $migration_file;

			$migrate_to_version_response = ob_get_contents() . $migrate_to_version_response;

			ob_end_clean();

			if ( strpos( $migrate_to_version_response, 'china-payments-migration-failed-message' ) !== false
			|| strpos( $migrate_to_version_response, 'china-payments-migration-repeat-file-message' ) !== false ) {
				$migration_okay = false;

				break;
			}
		}

		if ( $migration_okay ) {
			$this->current_version = $migrate_to_version;
			update_option( $this->option_alias_version, $migrate_to_version );
		}

		return $migrate_to_version_response;
	}

	public function is_valid_table_structure(): bool {
		$table_structure = $this->get_table_structure();

		if ( empty( $table_structure ) ) {
			return true;
		}

		foreach ( $table_structure as $table_name => $table_query ) {
			$sql = china_payments_wpdb()->prepare( 'SHOW TABLES LIKE %s', china_payments_wpdb()->esc_like( $table_name ) );

			if ( china_payments_wpdb()->get_var( $sql ) === null ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * @param bool $force_dbDelta
	 * @return array|bool - True(bool) if everything is good
	 */
	public function fix_table_structure( bool $force_dbDelta ) {
		$table_structure = $this->get_table_structure();

		if ( empty( $table_structure ) ) {
			return true;
		}

		foreach ( $table_structure as $table_name => $table_query ) {
			if ( ! $force_dbDelta ) {
				$sql = china_payments_wpdb()->prepare( 'SHOW TABLES LIKE %s', china_payments_wpdb()->esc_like( $table_name ) );

				if ( china_payments_wpdb()->get_var( $sql ) != null ) {
					continue;
				}
			}

			china_payments_dbDelta( $table_query );

			$sql = china_payments_wpdb()->prepare( 'SHOW TABLES LIKE %s', china_payments_wpdb()->esc_like( $table_name ) );

			if ( china_payments_wpdb()->get_var( $sql ) === null ) {
				return array(
					'status'          => 'error',
					'table'           => $table_name,
					'table_query_b64' => base64_encode( $table_query ),
				);
			}
		}

		return true;
	}

	public function handle_migration_in_background(): bool {
		if ( $this->current_version == '0.0.0' ) {
			return get_option( $this->option_alias_version, false ) === false;
		}

		return false;
	}
}
