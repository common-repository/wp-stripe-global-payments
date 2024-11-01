<?php

namespace ChinaPayments\ThirdPartyIntegration\SimpleMembership;

use ChinaPayments\PaymentGateway as CP_PaymentGateway;
use ChinaPayments\Settings as CP_Settings;
use ChinaPayments\ThirdPartyIntegration\SimpleMembership as TPI_SimpleMembership;

class Administration {

	public function __construct() {
		add_action( 'swpm_new_button_select_button_type', array( $this, '_swpm_new_button_select_button_type' ) );
		add_action( 'swpm_create_new_button_for_cpp_wechat', array( $this, '_swpm_create_new_button_for_cpp_wechat' ) );
		add_action( 'swpm_create_new_button_for_cpp_alipay', array( $this, '_swpm_create_new_button_for_cpp_alipay' ) );
		add_action( 'swpm_edit_payment_button_for_cpp_wechat', array( $this, '_swpm_edit_payment_button_for_cpp_wechat' ) );
		add_action( 'swpm_edit_payment_button_for_cpp_alipay', array( $this, '_swpm_edit_payment_button_for_cpp_alipay' ) );

		add_action( 'swpm_create_new_button_process_submission', array( $this, '_swpm_create_new_button_process_submission' ) );
		add_action( 'swpm_edit_payment_button_process_submission', array( $this, '_swpm_edit_payment_button_process_submission' ) );
	}

	public function _swpm_new_button_select_button_type() {
		if ( empty( CP_Settings::instance()->get( 'stripe_payment_methods' ) ) ) {
			return;
		}

		echo '<table class="form-table" role="presentation" style="margin-top:0;">
            <tr>
              <td style="margin-top:0;padding-top:0;">
                <fieldset>';

		if ( in_array( 'wechat', CP_Settings::instance()->get( 'stripe_payment_methods' ) ) ) {
			echo '<label><input type="radio" name="button_type" value="cpp_wechat"/>' . esc_html( TPI_SimpleMembership::instance()->payment_gateway_to_title_map['cpp_wechat'] ) . '</label>
            <br />';
		}

		if ( in_array( 'alipay', CP_Settings::instance()->get( 'stripe_payment_methods' ) ) ) {
			echo '<label><input type="radio" name="button_type" value="cpp_alipay"/>' . esc_html( TPI_SimpleMembership::instance()->payment_gateway_to_title_map['cpp_alipay'] ) . '</label>
            <br />';
		}

		echo '      </fieldset>
              </td>
            </tr>
          </table>';
	}

	public function _swpm_create_new_button_for_cpp_wechat() {
		$this->_swpm_manage_button_for_cpp( 'cpp_wechat', 'add' );
	}

	public function _swpm_create_new_button_for_cpp_alipay() {
		$this->_swpm_manage_button_for_cpp( 'cpp_alipay', 'add' );
	}

	public function _swpm_edit_payment_button_for_cpp_wechat() {
		$this->_swpm_manage_button_for_cpp( 'cpp_wechat', 'edit' );
	}

	public function _swpm_edit_payment_button_for_cpp_alipay() {
		$this->_swpm_manage_button_for_cpp( 'cpp_alipay', 'edit' );
	}

	/**
	 * Code is written in the style of Simple Membership plugin, in order to allow changes to happen easily by other experts on it.
	 *
	 * @return void
	 */
	private function _swpm_manage_button_for_cpp( $payment_method, $type = 'add' ) {
		// Retrieve the payment button data and present it for editing.

		$button_id   = ( $type === 'add' ? null : absint( sanitize_text_field( $_REQUEST['button_id'] ) ) );
		$button_type = sanitize_text_field( $_REQUEST['button_type'] );

		$button = ( empty( $button_id ) ? null : get_post( $button_id ) ); // Retrieve the CPT for this button

		$membership_level_id = ( empty( $button_id ) ? false : get_post_meta( $button_id, 'membership_level_id', true ) );
		$payment_amount      = ( empty( $button_id ) ? '' : get_post_meta( $button_id, 'payment_amount', true ) );
		$payment_currency    = ( empty( $button_id ) ? null : get_post_meta( $button_id, 'payment_currency', true ) );

		$return_url       = ( empty( $button_id ) ? null : get_post_meta( $button_id, 'return_url', true ) );
		$button_image_url = ( empty( $button_id ) ? null : get_post_meta( $button_id, 'button_image_url', true ) );
		$currencies       = $this->get_stripe_currencies();
		?>
	<div class="postbox">
		<h3 class="hndle"><label for="title"><?php echo esc_html( sprintf( __( '%s Button Configuration', 'china-payments' ), TPI_SimpleMembership::instance()->payment_gateway_to_title_map[ $payment_method ] ) ); ?></label></h3>
		<div class="inside">

		<form id="stripe_button_config_form" method="post">
			<input type="hidden" name="button_type" value="<?php echo esc_attr( $button_type ); ?>">

			<table class="form-table" width="100%" border="0" cellspacing="0" cellpadding="6">
			<?php if ( ! empty( $button_id ) ) : ?>
			<tr valign="top">
				<th scope="row"><?php echo esc_html( __( 'Button ID', 'china-payments' ) ); ?></th>
				<td>
				<input type="text" size="10" name="button_id" value="<?php echo esc_attr( $button_id ); ?>" readonly required />
				<p class="description">This is the ID of this payment button. It is automatically generated for you and it cannot be changed.</p>
				</td>
			</tr>
			<?php endif; ?>
			<tr valign="top">
				<th scope="row"><?php echo esc_html( __( 'Button Title', 'china-payments' ) ); ?></th>
				<td>
				<input type="text" size="50" name="button_name" value="<?php echo esc_attr( $button->post_title ?? '' ); ?>" required />
				<p class="description">Give this membership payment button a name. Example: Gold membership payment</p>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><?php echo esc_html( __( 'Membership Level', 'china-payments' ) ); ?></th>
				<td>
				<select id="membership_level_id" name="membership_level_id">
					<?php echo \SwpmUtils::membership_level_dropdown( $membership_level_id ); ?>
				</select>
				<p class="description">Select the membership level this payment button is for.</p>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><?php echo esc_html( __( 'Payment Amount', 'china-payments' ) ); ?></th>
				<td>
				<input type="text" size="6" name="payment_amount" value="<?php echo esc_attr( $payment_amount ); ?>" required />
				<p class="description">Enter payment amount. Example values: 10.00 or 19.50 or 299.95 etc (do not put currency symbol).</p>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><?php echo esc_html( __( 'Payment Currency', 'china-payments' ) ); ?></th>
				<td>
				<select id="payment_currency" name="payment_currency">
					<?php if ( in_array( 'USD', $currencies ) ) : ?>
					<option value="USD" <?php echo ( $payment_currency == 'USD' ) ? 'selected="selected"' : ''; ?>>US Dollars ($)</option>
					<?php endif; ?>
					<?php if ( in_array( 'CNY', $currencies ) ) : ?>
					<option value="CNY" <?php echo ( $payment_currency == 'CNY' ) ? 'selected="selected"' : ''; ?>>Chinese Yuan</option>
					<?php endif; ?>
					<?php if ( in_array( 'AUD', $currencies ) ) : ?>
					<option value="AUD" <?php echo ( $payment_currency == 'AUD' ) ? 'selected="selected"' : ''; ?>>Australian Dollars ($)</option>
					<?php endif; ?>
					<?php if ( in_array( 'CAD', $currencies ) ) : ?>
					<option value="CAD" <?php echo ( $payment_currency == 'CAD' ) ? 'selected="selected"' : ''; ?>>Canadian Dollars ($)</option>
					<?php endif; ?>
					<?php if ( in_array( 'EUR', $currencies ) ) : ?>
					<option value="EUR" <?php echo ( $payment_currency == 'EUR' ) ? 'selected="selected"' : ''; ?>>Euros (€)</option>
					<?php endif; ?>
					<?php if ( in_array( 'GBP', $currencies ) ) : ?>
					<option value="GBP" <?php echo ( $payment_currency == 'GBP' ) ? 'selected="selected"' : ''; ?>>Pounds Sterling (£)</option>
					<?php endif; ?>
					<?php if ( in_array( 'HKD', $currencies ) ) : ?>
					<option value="HKD" <?php echo ( $payment_currency == 'HKD' ) ? 'selected="selected"' : ''; ?>>Hong Kong Dollar ($)</option>
					<?php endif; ?>
					<?php if ( in_array( 'JPY', $currencies ) ) : ?>
					<option value="JPY" <?php echo ( $payment_currency == 'JPY' ) ? 'selected="selected"' : ''; ?>>Japanese Yen (¥)</option>
					<?php endif; ?>
					<?php if ( in_array( 'MYR', $currencies ) ) : ?>
					<option value="MYR" <?php echo ( $payment_currency == 'MYR' ) ? 'selected="selected"' : ''; ?>>Malaysian Ringgits</option>
					<?php endif; ?>
					<?php if ( in_array( 'NZD', $currencies ) ) : ?>
					<option value="NZD" <?php echo ( $payment_currency == 'NZD' ) ? 'selected="selected"' : ''; ?>>New Zealand Dollar ($)</option>
					<?php endif; ?>
					<?php if ( in_array( 'SGD', $currencies ) ) : ?>
					<option value="SGD" <?php echo ( $payment_currency == 'SGD' ) ? 'selected="selected"' : ''; ?>>Singapore Dollar ($)</option>
					<?php endif; ?>
				</select>
				<p class="description">Select the currency for this payment button.</p>
				</td>
			</tr>

			<tr valign="top">
				<th colspan="2">
				<div class="swpm-grey-box"><?php echo esc_html( __( 'The following details are optional.', 'china-payments' ) ); ?></div>
				</th>
			</tr>

			<tr valign="top">
				<th scope="row"><?php echo esc_html( __( 'Return URL', 'china-payments' ) ); ?></th>
				<td>
				<input type="text"
						size="100"
						name="return_url"
						value="<?php echo esc_url_raw( $return_url ); ?>"
						placeholder="<?php echo esc_attr( get_site_url() ); ?>"/>
				<p class="description">This is the URL the user will be redirected to after a successful payment. Enter the URL of your Thank You page here.</p>
				</td>
			</tr>

			<tr valign="top">
				<th scope="row"><?php echo esc_html( __( 'Button Image URL', 'china-payments' ) ); ?></th>
				<td>
				<input type="text" size="100" name="button_image_url" value="<?php echo esc_url_raw( $button_image_url ); ?>" />
				<p class="description">If you want to customize the look of the button using an image then enter the URL of the image.</p>
				</td>
			</tr>

			</table>

			<p class="submit">
			<?php wp_nonce_field( 'swpm_admin_add_edit_' . $payment_method . '_buy_now_btn', 'swpm_admin_add_edit_' . $payment_method . '_buy_now_btn' ); ?>
			<input type="submit" name="swpm_<?php echo esc_attr( $payment_method ); ?>_buy_now_<?php echo $type; ?>_submit" class="button-primary" value="<?php echo esc_attr( __( 'Save Payment Data', 'china-payments-button' ) ); ?>">
			</p>

		</form>

		</div>
	</div>
		<?php
	}

	public function _swpm_create_new_button_process_submission() {
		if ( ! isset( $_REQUEST['swpm_cpp_alipay_buy_now_add_submit'] ) && ! isset( $_REQUEST['swpm_cpp_wechat_buy_now_add_submit'] ) ) {
			return;
		}

		$button_type = sanitize_text_field( $_REQUEST['button_type'] );

		// check_admin_referer( 'swpm_admin_add_edit_' . $button_type . '_buy_now_btn', 'swpm_admin_add_edit_' . $button_type . '_buy_now_add_submit' );

		$button_id = wp_insert_post(
			array(
				'post_title'   => sanitize_text_field( $_REQUEST['button_name'] ),
				'post_type'    => 'swpm_payment_button',
				'post_content' => '',
				'post_status'  => 'publish',
			)
		);

		add_post_meta( $button_id, 'button_type', $button_type );
		add_post_meta( $button_id, 'membership_level_id', sanitize_text_field( $_REQUEST['membership_level_id'] ) );
		add_post_meta( $button_id, 'payment_amount', trim( sanitize_text_field( $_REQUEST['payment_amount'] ) ) );
		add_post_meta( $button_id, 'payment_currency', sanitize_text_field( $_REQUEST['payment_currency'] ) );
		add_post_meta( $button_id, 'return_url', trim( sanitize_text_field( $_REQUEST['return_url'] ) ) );
		add_post_meta( $button_id, 'button_image_url', esc_url( $_REQUEST['button_image_url'] ) );

		$url = admin_url() . 'admin.php?page=simple_wp_membership_payments&tab=payment_buttons';
		china_payments_redirect( $url );
	}

	public function _swpm_edit_payment_button_process_submission() {
		if ( ! isset( $_REQUEST['swpm_cpp_alipay_buy_now_edit_submit'] ) && ! isset( $_REQUEST['swpm_cpp_wechat_buy_now_edit_submit'] ) ) {
			return;
		}

		$button_type = sanitize_text_field( $_REQUEST['button_type'] );

		// check_admin_referer( 'swpm_admin_add_edit_' . $button_type . '_buy_now_btn', 'swpm_admin_add_edit_' . $button_type . '_buy_now_add_submit' );

		$button_id   = absint( sanitize_text_field( $_REQUEST['button_id'] ) );
		$button_name = sanitize_text_field( $_REQUEST['button_name'] );

		wp_update_post(
			array(
				'ID'         => $button_id,
				'post_title' => $button_name,
				'post_type'  => 'swpm_payment_button',
			)
		);

		update_post_meta( $button_id, 'button_type', $button_type );
		update_post_meta( $button_id, 'membership_level_id', sanitize_text_field( $_REQUEST['membership_level_id'] ) );
		update_post_meta( $button_id, 'payment_amount', trim( sanitize_text_field( $_REQUEST['payment_amount'] ) ) );
		update_post_meta( $button_id, 'payment_currency', sanitize_text_field( $_REQUEST['payment_currency'] ) );

		update_post_meta( $button_id, 'return_url', trim( sanitize_text_field( $_REQUEST['return_url'] ) ) );
		update_post_meta( $button_id, 'button_image_url', esc_url( $_REQUEST['button_image_url'] ) );

		echo '<div id="message" class="updated fade"><p>Payment button data successfully updated!</p></div>';
	}

	private function get_stripe_currencies() {
		$stripeIntegration = CP_PaymentGateway::get_integration_from_settings( 'stripe' );

		return array_intersect(
			array(
				strtoupper( $stripeIntegration->get_default_currency() ),
				'CNY',
			),
			apply_filters(
				'china_payments_simple_memberships_stripe_currencies',
				array(
					'CNY',
					'AUD',
					'CAD',
					'EUR',
					'GBP',
					'HKD',
					'JPY',
					'SGD',
					'MYR',
					'NZD',
					'USD',
				)
			)
		);
	}
}