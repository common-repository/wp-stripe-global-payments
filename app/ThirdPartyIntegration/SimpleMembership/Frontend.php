<?php

namespace ChinaPayments\ThirdPartyIntegration\SimpleMembership;

use ChinaPayments\Settings as CP_Settings;
use ChinaPayments\ThirdPartyIntegration\SimpleMembership as TPI_SimpleMembership;
use SwpmUtils;
use SwpmMemberUtils;

class Frontend {

	public function __construct() {
		add_filter( 'swpm_payment_button_shortcode_for_cpp_wechat', array( $this, 'swpm_payment_button_shortcode_for_cpp_wechat' ), 10, 2 );
		add_filter( 'swpm_payment_button_shortcode_for_cpp_alipay', array( $this, 'swpm_payment_button_shortcode_for_cpp_alipay' ), 10, 2 );
	}

	public function swpm_payment_button_shortcode_for_cpp_wechat( $button_html, $args ) {
		return $this->_get_button( $args, 'wechat' );
	}

	public function swpm_payment_button_shortcode_for_cpp_alipay( $button_html, $args ) {
		return $this->_get_button( $args, 'alipay' );
	}

	private function _get_button( $args, $payment_method ) {
		$button_id = isset( $args['id'] ) ? $args['id'] : '';
		if ( empty( $button_id ) ) {
			return '<p class="swpm-red-box">Error! swpm_render_pp_buy_now_new_button_sc_output() function requires the button ID value to be passed to it.</p>';
		}

		// Membership level for this button
		$membership_level_id = get_post_meta( $button_id, 'membership_level_id', true );
		// Verify that this membership level exists (to prevent user paying for a level that has been deleted)
		if ( ! SwpmUtils::membership_level_id_exists( $membership_level_id ) ) {
			return '<p class="swpm-red-box">Error! The membership level specified in this button does not exist. You may have deleted this membership level. Edit the button and use the correct membership level.</p>';
		}

		// Payment amount
		$payment_amount = get_post_meta( $button_id, 'payment_amount', true );

		// Get the Item name for this button. This will be used as the item name in the IPN.
		$button_cpt = get_post( $button_id ); // Retrieve the CPT for this button
		$item_name  = htmlspecialchars( $button_cpt->post_title );

		$swpm_member_id = ( SwpmMemberUtils::is_member_logged_in() ? SwpmMemberUtils::get_logged_in_members_id() : '' );
		$user_id        = is_user_logged_in() ? get_current_user_id() : ( SwpmMemberUtils::is_member_logged_in() ? SwpmMemberUtils::get_wp_user_from_swpm_user_id( $swpm_member_id )->ID : '' );

		$url              = rest_url() . CHINA_PAYMENTS_REST_API_PREFIX . '/v1/third-party-integration/simple-membership/complete-payment/' . $button_id;
		$button_image_url = get_post_meta( $button_id, 'button_image_url', true );

		$response = '';

		$response .= '<form class="china-payments-simple-membership-form" 
                        method="POST"
                        action="' . esc_url( $url ) . '">';
		if ( empty( $user_id ) ) {
			$response .= '<input required="required" type="email" name="email_address" placeholder="' . esc_attr( __( 'Email Address', 'china-payments' ) ) . '"/>';
		} else {
			$response .= '<input type="hidden" name="user_id" value="' . esc_attr( $user_id ) . '"/>';
			$response .= '<input type="hidden" name="user_id_secret" value="' . esc_attr( md5( $user_id . ( defined( 'NONCE_SALT' ) ? NONCE_SALT : 'abc' ) ) ) . '"/>';
		}

		$content = ( empty( $button_image_url ) ? esc_html( $item_name ) : '<img src="' . esc_url( $button_image_url ) . '" alt="' . esc_attr( $item_name ) . '" style="max-width:300px;"/>' );

		$response .= '<button' . ( empty( $button_image_url ) ? '' : ' style="padding:0;border:0;"' ) . '>' . $content . '</button>';
		$response .= '</form>';

		return $response;
	}
}
