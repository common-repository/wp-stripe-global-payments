<?php

namespace ChinaPayments\PaymentGateway;

abstract class Skeleton {

	abstract public static function setup_start_connection( $options ): array;

	abstract public static function save_master_credentials_response( $credentials ): bool;

	/**
	 * @return $this
	 */
	abstract public function attach_settings_credentials();

	abstract public function is_configured(): bool;

	abstract public function attach_credentials( $credentials );

	abstract public function delete_settings_credentials( $is_live = true );

	abstract public function get_name(): string;

	abstract public function get_logo_url(): string;

	abstract public function get_description(): string;

	abstract public function get_account_name(): string;
}
