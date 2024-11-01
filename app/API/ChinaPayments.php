<?php

namespace ChinaPayments\API;

use ChinaPayments\ThirdPartyIntegration\Freemius as CP_Freemius;

/**
 * Class ChinaPayments
 *
 * @author Robert Rusu
 */
class ChinaPayments {

	/**
	 * @var ChinaPayments|null
	 */
	protected static $instance = null;

	public static function instance(): ?ChinaPayments {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function request( $path, $request_params, $method = 'POST' ) {
		$request_args = array(
			'body' => CP_Freemius::api_request_details() + $request_params,
		);

		if ( $method === 'POST' ) {
			$response = wp_remote_post( CHINA_PAYMENTS_EXTERNAL_API_URL . $path, $request_args );
		} else {
			$response = wp_remote_get( CHINA_PAYMENTS_EXTERNAL_API_URL . $path, $request_args );
		}

		$response = wp_remote_retrieve_body( $response );

		$response = json_decode( $response, true );

		return is_array( $response ) ? $response : null;
	}

	public function decode_response_data( $data ) {
		$response = json_decode( china_payments_decrypt( $data, CP_Freemius::instance()->get_anonymous_id(), md5( get_site_url() ) ), true );

		if ( ! is_array( $response ) ) {
			return null;
		}

		return $response;
	}
}
