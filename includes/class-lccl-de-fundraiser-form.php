<?php
/**
 * Fundraiser Seat Booking Form.
 *
 * @package LCCL_Donations_And_Events
 */

defined( 'ABSPATH' ) || exit;

class LCCL_DE_Fundraiser_Form {

	const SHORTCODE = 'lccl_fundraiser_form';
	const PROFILE = LCCL_DE_Settings::PROFILE_MEMBERSHIP;
	const NONCE_ACTION = 'lccl_fundraiser_submit';
	const NONCE_FIELD = 'lccl_fundraiser_nonce';

	public static function init() {
		add_shortcode( self::SHORTCODE, array( __CLASS__, 'render' ) );
		if ( isset( $_POST[ self::NONCE_FIELD ] ) ) {
			add_action( 'template_redirect', array( __CLASS__, 'maybe_handle_submit' ) );
		}
	}

	public static function render( $atts ) {
		wp_enqueue_style( 'lccl-de-fundraiser-form', LCCL_DE_URL . 'assets/css/lccl-de-fundraiser-form.css', array(), LCCL_DE_VERSION );
		
		$active_event = LCCL_DE_Fundraiser::get_active_public_event();

		ob_start();
		include LCCL_DE_PATH . 'templates/fundraiser-form.php';
		return ob_get_clean();
	}

	public static function maybe_handle_submit() {
		if ( 'POST' !== strtoupper( sanitize_text_field( isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : 'GET' ) ) ) return;
		if ( empty( $_POST[ self::NONCE_FIELD ] ) ) return;
		if ( ! check_admin_referer( self::NONCE_ACTION, self::NONCE_FIELD ) ) return;
		self::handle_submit();
	}

	private static function handle_submit() {
		$raw = wp_unslash( $_POST );

		$event_id      = (int) ( $raw['event_id'] ?? 0 );
		$booking_type  = sanitize_key( $raw['booking_type'] ?? 'individual' );
		$attendee_name = sanitize_text_field( $raw['attendee_name'] ?? '' );
		$attendee_email= sanitize_email( $raw['attendee_email'] ?? '' );
		$attendee_phone= sanitize_text_field( $raw['attendee_phone'] ?? '' );
		$qty           = (int) ( $raw['qty'] ?? 1 );
		$total_amount  = (float) ( $raw['total_amount'] ?? 0 );
		$selected_tables = isset( $raw['selectedTables'] ) && is_array( $raw['selectedTables'] ) ? array_map( 'sanitize_text_field', $raw['selectedTables'] ) : array();

		$errors = array();
		if ( ! $event_id ) $errors[] = __( 'No active event selected.', 'lccl-de' );
		if ( '' === $attendee_name ) $errors[] = __( 'Name is required.', 'lccl-de' );
		if ( '' === $attendee_email || ! is_email( $attendee_email ) ) $errors[] = __( 'A valid email address is required.', 'lccl-de' );
		if ( $total_amount <= 0 ) $errors[] = __( 'Total amount must be greater than zero.', 'lccl-de' );

		if ( $errors ) {
			wp_safe_redirect( add_query_arg( array( 'lccl_mf_error' => rawurlencode( implode( ' ', $errors ) ) ), wp_get_referer() ?: get_permalink() ) );
			exit;
		}

		// Ensure we don't oversell tables
		if ( 'table' === $booking_type ) {
			foreach ( $selected_tables as $tid ) {
				$tid = (int) $tid;
				global $wpdb;
				$tbl = LCCL_DE_Fundraiser::tables_table();
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$is_booked = (int) $wpdb->get_var( $wpdb->prepare( "SELECT is_booked FROM `{$tbl}` WHERE id=%d", $tid ) );
				if ( $is_booked === 1 ) {
					wp_die( esc_html__( 'One or more selected tables have just been booked by someone else. Please try again.', 'lccl-de' ) );
				}
			}
		}

		$order_ref = 'FND-' . strtoupper( substr( uniqid(), -6 ) );
		global $wpdb;
		$table = LCCL_DE_Fundraiser::bookings_table();
		
		$profile_cfg = LCCL_DE_Settings::get_paycenter( self::PROFILE );
		$currency    = isset( $profile_cfg['currency'] ) && '' !== $profile_cfg['currency'] ? strtoupper( (string) $profile_cfg['currency'] ) : 'LKR';

		// If individual, we allocate seats but the final allocation is just sequential count.
		$seat_numbers = '';
		if ( 'individual' === $booking_type ) {
			$avail = LCCL_DE_Fundraiser::get_available_seat_numbers( $event_id );
			$allocated = array_slice( $avail, 0, $qty );
			$seat_numbers = implode( ',', $allocated );
		}

		$inserted = $wpdb->insert(
			$table,
			array(
				'order_ref'      => $order_ref,
				'event_id'       => $event_id,
				'booking_type'   => $booking_type,
				'attendee_name'  => $attendee_name,
				'attendee_email' => $attendee_email,
				'attendee_phone' => $attendee_phone,
				'qty'            => $qty,
				'seat_numbers'   => $seat_numbers,
				'table_ids'      => implode( ',', $selected_tables ),
				'amount_lkr'     => $total_amount,
				'currency'       => $currency,
				'status'         => 'pending',
				'ip_address'     => self::client_ip(),
				'created_at'     => current_time( 'mysql' ),
			),
			array( '%s', '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%f', '%s', '%s', '%s', '%s' )
		);

		if ( ! $inserted ) { wp_die( esc_html__( 'Failed to record booking initialization.', 'lccl-de' ) ); }

		$booking_id = $wpdb->insert_id;

		// IMPORTANT: For MPGS/Paycenter, it needs to hit the payments table to work with the unified callback handler,
		// or we need to update the callback handler. Wait, `class-lccl-de-paycenter-client.php` returns a link. 
		// Where does Paycenter postback? The payment dashboard handles `lccl_de_paycenter_callback`.
		// Let me just save a dummy record in `lccl_de_payments` as well to make sure the callback logic works unmodified!
		// The callback logic expects `LCCL_DE_Schema::payments_table()`.
		$p_table = LCCL_DE_Schema::payments_table();
		$wpdb->insert( $p_table, array(
			'order_ref' => $order_ref, 'member_first_name' => $attendee_name, 'member_last_name' => '', 'member_email' => $attendee_email, 'member_phone' => $attendee_phone,
			'amount_lkr' => $total_amount, 'currency' => $currency, 'status' => 'pending', 'created_at' => current_time( 'mysql' )
		) );
		$payment_id = $wpdb->insert_id;

		$base_url = get_permalink() ?: home_url( '/' );
		$init_result = LCCL_DE_Paycenter_Client::payment_init(
			array(
				'profile'    => self::PROFILE,
				'order_ref'  => $order_ref,
				'amount'     => $total_amount,
				'return_url' => $base_url,
				'cancel_url' => $base_url,
				'comment'    => 'Fundraiser Booking: ' . $order_ref,
			)
		);

		if ( is_wp_error( $init_result ) ) { wp_die( esc_html( $init_result->get_error_message() ) ); }
		if ( ! empty( $init_result['payment_page_url'] ) ) { wp_redirect( $init_result['payment_page_url'] ); exit; }
		wp_die( esc_html__( 'Invalid response from payment gateway.', 'lccl-de' ) );
	}

	private static function client_ip() {
		$keys = array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'REMOTE_ADDR' );
		foreach ( $keys as $k ) {
			if ( ! empty( $_SERVER[ $k ] ) ) {
				$ips = explode( ',', $_SERVER[ $k ] );
				return trim( $ips[0] );
			}
		}
		return '';
	}
}