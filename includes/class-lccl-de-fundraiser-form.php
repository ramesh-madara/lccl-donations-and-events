<?php
/**
 * Fundraiser Seat Booking Form.
 *
 * @package LCCL_Donations_And_Events
 */

defined( 'ABSPATH' ) || exit;

class LCCL_DE_Fundraiser_Form {

	const SHORTCODE = 'lccl_fundraiser_form';
	const PROFILE = LCCL_DE_Settings::PROFILE_DONATIONS; // Assuming donations profile is used for fundraiser
	const NONCE_ACTION = 'lccl_fundraiser_submit';
	const NONCE_FIELD = 'lccl_fundraiser_nonce';

	public static function init() {
		add_shortcode( self::SHORTCODE, array( __CLASS__, 'render' ) );
	}

	public static function render( $atts ) {
		wp_enqueue_style(
			'lccl-de-blood-donor-form',
			LCCL_DE_URL . 'assets/css/lccl-de-blood-donor-form.css',
			array(),
			LCCL_DE_VERSION
		);

		$atts = shortcode_atts(
			array(),
			$atts,
			self::SHORTCODE
		);

		ob_start();
		include LCCL_DE_PATH . 'templates/fundraiser-form.php';
		return ob_get_clean();
	}

	public static function maybe_handle_submit() {
		if ( 'POST' !== strtoupper( sanitize_text_field( isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : 'GET' ) ) ) {
			return;
		}

		if ( empty( $_POST[ self::NONCE_FIELD ] ) ) {
			return;
		}

		if ( ! check_admin_referer( self::NONCE_ACTION, self::NONCE_FIELD ) ) {
			return;
		}

		self::handle_submit();
	}

	private static function handle_submit() {
		$raw = wp_unslash( $_POST );

		$attendee_name  = sanitize_text_field( isset( $raw['attendee_name'] ) ? $raw['attendee_name'] : '' );
		$attendee_email = sanitize_email( isset( $raw['attendee_email'] ) ? $raw['attendee_email'] : '' );
		$attendee_phone = sanitize_text_field( isset( $raw['attendee_phone'] ) ? $raw['attendee_phone'] : '' );
		$num_tickets    = (int) ( isset( $raw['num_tickets'] ) ? $raw['num_tickets'] : 0 );
		$num_tables     = (int) ( isset( $raw['num_tables'] ) ? $raw['num_tables'] : 0 );
		$total_amount   = (float) ( isset( $raw['total_amount'] ) ? $raw['total_amount'] : 0 );
		
		$selected_tables = isset( $raw['selectedTables'] ) && is_array( $raw['selectedTables'] ) ? array_map( 'sanitize_text_field', $raw['selectedTables'] ) : array();

		$errors = array();

		if ( '' === $attendee_name ) {
			$errors[] = __( 'Name is required.', 'lccl-de' );
		}
		if ( '' === $attendee_email || ! is_email( $attendee_email ) ) {
			$errors[] = __( 'A valid email address is required.', 'lccl-de' );
		}
		if ( $total_amount <= 0 ) {
			$errors[] = __( 'Total amount must be greater than zero.', 'lccl-de' );
		}

		if ( $errors ) {
			wp_safe_redirect(
				add_query_arg(
					array( 'lccl_mf_error' => rawurlencode( implode( ' ', $errors ) ) ),
					wp_get_referer() ?: get_permalink()
				)
			);
			exit;
		}

		$order_ref = 'FND-' . strtoupper( substr( uniqid(), -6 ) );
		global $wpdb;
		$table = LCCL_DE_Schema::payments_table();
		
		$profile_cfg = LCCL_DE_Settings::get_paycenter( self::PROFILE );
		$currency    = isset( $profile_cfg['currency'] ) && '' !== $profile_cfg['currency'] ? strtoupper( (string) $profile_cfg['currency'] ) : 'LKR';

		$gateway_response = wp_json_encode( array(
			'num_tickets' => $num_tickets,
			'num_tables'  => $num_tables,
			'tables'      => $selected_tables
		) );

		$inserted = $wpdb->insert(
			$table,
			array(
				'order_ref'         => $order_ref,
				'member_first_name' => $attendee_name,
				'member_last_name'  => '',
				'member_email'      => $attendee_email,
				'member_phone'      => $attendee_phone,
				'amount_lkr'        => $total_amount,
				'currency'          => $currency,
				'status'            => 'pending',
				'gateway_response'  => $gateway_response,
				'ip_address'        => self::client_ip(),
				'created_at'        => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%f', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( ! $inserted ) {
			wp_die( esc_html__( 'Failed to record payment initialization.', 'lccl-de' ) );
		}

		$payment_id = $wpdb->insert_id;

		$init_result = LCCL_DE_Paycenter_Client::payment_init(
			$payment_id,
			$order_ref,
			$total_amount,
			$currency,
			$attendee_name,
			$attendee_name,
			self::PROFILE
		);

		if ( is_wp_error( $init_result ) ) {
			wp_die( esc_html( $init_result->get_error_message() ) );
		}

		if ( ! empty( $init_result['url'] ) ) {
			wp_redirect( $init_result['url'] );
			exit;
		}

		wp_die( esc_html__( 'Invalid response from payment gateway.', 'lccl-de' ) );
	}

	private static function client_ip() {
		$keys = array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'REMOTE_ADDR' );
		foreach ( $keys as $k ) {
			if ( ! empty( $_SERVER[ $k ] ) ) {
				$ip = sanitize_text_field( wp_unslash( $_SERVER[ $k ] ) );
				$ip = explode( ',', $ip )[0];
				$ip = trim( $ip );
				if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
					return $ip;
				}
			}
		}
		return '127.0.0.1';
	}
}