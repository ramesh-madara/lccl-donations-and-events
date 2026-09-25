<?php
/**
 * Public donation form with CBC Paycenter Web 4.0 (Bancstac) integration.
 *
 * Uses the Donations profile credentials configured in WP Admin -> LCCL Programs -> Payment Gateway.
 *
 * Flow:
 *  1. Donor fills form and submits via POST -> maybe_handle_submit() -> handle_submit()
 *  2. handle_submit() validates, inserts pending row in payments table, calls PAYMENT_INIT
 *  3. Browser is redirected to the Bancstac-hosted payment page URL
 *  4. Bancstac redirects browser back to returnUrl with ?reqid=…
 *  5. render() detects QA_RETURN -> handle_callback() calls PAYMENT_COMPLETE,
 *     verifies responseCode / amount / currency / clientRef, updates the DB row,
 *     and displays the Thank You confirmation banner at the top of the form.
 *
 * @package LCCL_Donations_And_Events
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handles the public donation form rendering and payment gateway workflow.
 */
class LCCL_DE_Donation_Form {

	/**
	 * Shortcode tag.
	 */
	const SHORTCODE = 'lccl_donation_form';

	/**
	 * Nonce action for form submission.
	 */
	const NONCE_ACTION = 'lccl_df_submit';

	/**
	 * Nonce field name.
	 */
	const NONCE_FIELD = 'lccl_df_nonce';

	/**
	 * Query arg that signals the gateway has returned the browser.
	 */
	const QA_RETURN = 'lccl_payment_return';

	/**
	 * Query arg carrying the merchant order reference on the callback.
	 */
	const QA_ORDER_REF = 'lccl_payment_ref';

	/**
	 * Query arg that signals the user cancelled payment on the gateway.
	 */
	const QA_CANCEL = 'lccl_payment_cancel';

	/**
	 * Gateway profile key for Donations.
	 */
	const PROFILE = LCCL_DE_Settings::PROFILE_DONATIONS;

	/**
	 * Hook the shortcode into WordPress and WPBakery, and register submit handler.
	 */
	public static function init() {
		add_shortcode( self::SHORTCODE, array( __CLASS__, 'render' ) );
		add_action( 'vc_before_init', array( __CLASS__, 'map' ) );

		// Handle POST submission before output.
		add_action( 'template_redirect', array( __CLASS__, 'maybe_handle_submit' ) );
	}

	/**
	 * Default form heading.
	 *
	 * @return string
	 */
	public static function default_title() {
		return __( 'Make a Donation', 'lccl-de' );
	}

	/**
	 * Default heading intro.
	 *
	 * @return string
	 */
	public static function default_intro() {
		return __( 'Thank you for supporting the community service work of the Lions Club of Colombo LEADS. Please provide the information below to make your donation.', 'lccl-de' );
	}

	/**
	 * Message shown under an empty required field.
	 *
	 * @return string
	 */
	public static function required_field_message() {
		return LCCL_DE_Blood_Donor_Submissions::required_field_message();
	}

	/**
	 * Message shown when the amount is missing or not a positive number.
	 *
	 * @return string
	 */
	public static function amount_error_message() {
		return __( 'Please enter a valid donation amount of at least 1 LKR.', 'lccl-de' );
	}

	/**
	 * Message shown when the email is missing or invalid.
	 *
	 * @return string
	 */
	public static function email_error_message() {
		return __( 'Please enter a valid email address.', 'lccl-de' );
	}

	/**
	 * Cause checkboxes, matching the live donation page.
	 *
	 * @return array<string,string>
	 */
	public static function causes() {
		return array(
			'diabetes'        => __( 'Diabetes Awareness & Screening', 'lccl-de' ),
			'vision'          => __( 'Vision & Eye Care', 'lccl-de' ),
			'hunger'          => __( 'Hunger & Food Assistance', 'lccl-de' ),
			'environment'     => __( 'Environment', 'lccl-de' ),
			'child-cancer'    => __( 'Childhood Cancer Support', 'lccl-de' ),
			'youth'           => __( 'Youth & Education', 'lccl-de' ),
			'disaster-relief' => __( 'Disaster Relief', 'lccl-de' ),
			'humanitarian'    => __( 'Humanitarian Assistance', 'lccl-de' ),
		);
	}

	/**
	 * Display amount with thousands separators and LKR.
	 *
	 * @param string|float $amount Raw amount.
	 * @return string
	 */
	public static function format_amount( $amount ) {
		$raw = preg_replace( '/[^\d.]/', '', (string) $amount );
		if ( '' === $raw || ! is_numeric( $raw ) ) {
			return '';
		}
		return number_format( (float) $raw, 2 ) . ' LKR';
	}

	/**
	 * Render the donation form or gateway return state.
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string
	 */
	public static function render( $atts ) {
		$atts = shortcode_atts(
			array(
				'title' => self::default_title(),
				'intro' => self::default_intro(),
			),
			$atts,
			self::SHORTCODE
		);

		wp_enqueue_style(
			'lccl-de-blood-donor-form',
			LCCL_DE_URL . 'assets/css/lccl-de-blood-donor-form.css',
			array(),
			LCCL_DE_VERSION
		);

		// Gateway return detection.
		$is_return = ! empty( $_GET[ self::QA_RETURN ] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			|| ! empty( $_GET['lccl_mpgs_return'] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			|| ! empty( $_GET[ self::QA_CANCEL ] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$payment_success = null;
		$payment_notice  = '';

		if ( $is_return ) {
			$result = self::handle_callback();

			if ( 'paid' === $result['status'] ) {
				$payment_success = $result;
			} elseif ( 'cancelled' === $result['status'] ) {
				$payment_notice = __( 'Donation was cancelled. You may review your information and try again whenever you wish.', 'lccl-de' );
			} else {
				$payment_notice = ! empty( $result['error_message'] )
					? $result['error_message']
					: __( 'Unable to confirm donation payment. Please contact the club administrator or try again.', 'lccl-de' );
			}
		}

		wp_enqueue_script(
			'lccl-de-donation-form',
			LCCL_DE_URL . 'assets/js/lccl-de-donation-form.js',
			array(),
			LCCL_DE_VERSION,
			true
		);

		$values             = array();
		$errors             = array();
		$gateway_configured = LCCL_DE_Paycenter_Client::is_configured( self::PROFILE );

		if ( ! empty( $_GET['lccl_df_error'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$errors[] = sanitize_text_field( wp_unslash( $_GET['lccl_df_error'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		ob_start();
		include LCCL_DE_PATH . 'templates/donation-form.php';
		return (string) ob_get_clean();
	}

	/**
	 * Intercept POST submission on template_redirect.
	 */
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

		// Rate limiting: max 10 checkout session initiations per IP per 10 minutes.
		$ip_key = 'lccl_df_rate_' . md5( self::client_ip() );
		$hits   = (int) get_transient( $ip_key );
		if ( $hits >= 10 ) {
			wp_safe_redirect(
				add_query_arg(
					array( 'lccl_df_error' => rawurlencode( __( 'Too many payment attempts. Please wait a few minutes before trying again.', 'lccl-de' ) ) ),
					wp_get_referer() ?: get_permalink()
				)
			);
			exit;
		}
		set_transient( $ip_key, $hits + 1, 10 * MINUTE_IN_SECONDS );

		self::handle_submit();
	}

	/**
	 * Validate fields, insert pending record, initiate gateway payment, and redirect.
	 */
	private static function handle_submit() {
		$raw = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

		$first_name = sanitize_text_field( isset( $raw['first_name'] ) ? $raw['first_name'] : '' );
		$last_name  = sanitize_text_field( isset( $raw['last_name'] ) ? $raw['last_name'] : '' );
		$email      = sanitize_email( isset( $raw['email'] ) ? $raw['email'] : '' );
		$phone      = sanitize_text_field( isset( $raw['phone'] ) ? $raw['phone'] : '' );
		$message    = sanitize_textarea_field( isset( $raw['message'] ) ? substr( $raw['message'], 0, 1000 ) : '' );

		$raw_amount = isset( $raw['amount'] ) ? preg_replace( '/[^\d.]/', '', (string) $raw['amount'] ) : '0';
		$amount     = (float) $raw_amount;

		$selected_causes = isset( $raw['causes'] ) && is_array( $raw['causes'] )
			? array_values( array_intersect( $raw['causes'], array_keys( self::causes() ) ) )
			: array();

		$errors = array();

		if ( '' === $first_name ) {
			$errors[] = __( 'First name is required.', 'lccl-de' );
		}

		if ( '' === $last_name ) {
			$errors[] = __( 'Last name is required.', 'lccl-de' );
		}

		if ( '' === $email || ! is_email( $email ) ) {
			$errors[] = __( 'A valid email address is required.', 'lccl-de' );
		}

		if ( '' === $phone ) {
			$errors[] = __( 'Mobile / WhatsApp number is required.', 'lccl-de' );
		}

		if ( $amount < 1.00 ) {
			$errors[] = __( 'Please enter a valid donation amount of at least 1 LKR.', 'lccl-de' );
		}

		if ( ! LCCL_DE_Paycenter_Client::is_configured( self::PROFILE ) ) {
			$errors[] = __( 'Online donations are currently not configured. Please contact the club administrator.', 'lccl-de' );
		}

		if ( $errors ) {
			wp_safe_redirect(
				add_query_arg(
					array( 'lccl_df_error' => rawurlencode( implode( ' ', $errors ) ) ),
					wp_get_referer() ?: get_permalink()
				)
			);
			exit;
		}

		// Generate unique order reference.
		$order_ref = self::generate_order_ref();

		global $wpdb;
		$table = LCCL_DE_Schema::payments_table();

		$payload_meta = array(
			'type'     => 'donation',
			'causes'   => $selected_causes,
			'message'  => $message,
		);

		$inserted = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$table,
			array(
				'order_ref'         => $order_ref,
				'member_first_name' => $first_name,
				'member_last_name'  => $last_name,
				'member_email'      => $email,
				'member_phone'      => $phone,
				'membership_type'   => 'donation',
				'family_count'      => 1,
				'amount_lkr'        => $amount,
				'status'            => 'pending',
				'gateway_response'  => wp_json_encode( $payload_meta ),
				'ip_address'        => self::client_ip(),
				'created_at'        => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%f', '%s', '%s', '%s', '%s' )
		);

		if ( ! $inserted ) {
			wp_safe_redirect(
				add_query_arg(
					array( 'lccl_df_error' => rawurlencode( __( 'Could not save your donation request. Please try again.', 'lccl-de' ) ) ),
					wp_get_referer() ?: get_permalink()
				)
			);
			exit;
		}

		$base_url = get_permalink() ?: home_url( '/' );

		$return_url = add_query_arg(
			array(
				self::QA_RETURN    => '1',
				self::QA_ORDER_REF => $order_ref,
			),
			$base_url
		);

		$cancel_url = add_query_arg(
			array(
				self::QA_CANCEL    => '1',
				self::QA_ORDER_REF => $order_ref,
			),
			$base_url
		);

		$comment = sprintf(
			/* translators: 1: first name, 2: last name */
			__( 'LCCL Donation - %1$s %2$s', 'lccl-de' ),
			$first_name,
			$last_name
		);

		// Call PAYMENT_INIT with Donations profile.
		$init_result = LCCL_DE_Paycenter_Client::payment_init(
			array(
				'profile'    => self::PROFILE,
				'order_ref'  => $order_ref,
				'amount'     => $amount,
				'return_url' => $return_url,
				'cancel_url' => $cancel_url,
				'comment'    => $comment,
			)
		);

		if ( is_wp_error( $init_result ) ) {
			error_log(
				sprintf(
					'[LCCL Paycenter Donation Error] Order %s failed PAYMENT_INIT: %s',
					$order_ref,
					$init_result->get_error_message()
				)
			);

			$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
				$table,
				array( 'status' => 'failed', 'gateway_response' => $init_result->get_error_message() ),
				array( 'order_ref' => $order_ref ),
				array( '%s', '%s' ),
				array( '%s' )
			);

			if ( current_user_can( 'manage_options' ) ) {
				$user_msg = sprintf(
					/* translators: %s: error details */
					__( 'Gateway Error: %s', 'lccl-de' ),
					$init_result->get_error_message()
				);
			} elseif ( 'lccl_pc_disabled' === $init_result->get_error_code() ) {
				$user_msg = __( 'Online donations are currently unavailable. Please contact the club administrator.', 'lccl-de' );
			} else {
				$user_msg = __( 'Unable to connect to the payment gateway. Please try again or contact the club administrator.', 'lccl-de' );
			}

			wp_safe_redirect(
				add_query_arg(
					array( 'lccl_df_error' => rawurlencode( $user_msg ) ),
					wp_get_referer() ?: get_permalink()
				)
			);
			exit;
		}

		// Store reqid for callback verification (in session_id column).
		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$table,
			array(
				'session_id' => $init_result['reqid'],
			),
			array( 'order_ref' => $order_ref ),
			array( '%s' ),
			array( '%s' )
		);

		// Redirect directly to Bancstac payment page.
		wp_redirect( esc_url_raw( $init_result['payment_page_url'] ) ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
		exit;
	}

	/**
	 * Handle return from Bancstac gateway.
	 *
	 * @return array
	 */
	public static function handle_callback() {
		$order_ref = '';
		if ( ! empty( $_GET[ self::QA_ORDER_REF ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$order_ref = sanitize_text_field( wp_unslash( $_GET[ self::QA_ORDER_REF ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		} elseif ( ! empty( $_GET['lccl_mpgs_ref'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$order_ref = sanitize_text_field( wp_unslash( $_GET['lccl_mpgs_ref'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		$reqid = '';
		foreach ( array( 'reqid', 'ReqID', 'reqId', 'REQID' ) as $key ) {
			if ( ! empty( $_GET[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$reqid = sanitize_text_field( wp_unslash( $_GET[ $key ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				break;
			}
		}

		$is_cancelled = ! empty( $_GET[ self::QA_CANCEL ] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$error_result = array(
			'status'        => 'error',
			'order_ref'     => $order_ref,
			'receipt'       => '',
			'amount'        => 0,
			'donor_name'    => '',
			'error_message' => '',
		);

		if ( '' === $order_ref ) {
			$error_result['error_message'] = __( 'Payment reference missing. Please contact the club administrator.', 'lccl-de' );
			return $error_result;
		}

		global $wpdb;
		$table = LCCL_DE_Schema::payments_table();

		$row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare( "SELECT * FROM `{$table}` WHERE order_ref = %s LIMIT 1", $order_ref ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);

		if ( ! $row ) {
			$error_result['error_message'] = __( 'Donation payment record not found. Please contact the club administrator.', 'lccl-de' );
			return $error_result;
		}

		$donor_name = trim( $row['member_first_name'] . ' ' . $row['member_last_name'] );

		$base_result = array(
			'status'        => 'failed',
			'order_ref'     => $order_ref,
			'receipt'       => '',
			'amount'        => (float) $row['amount_lkr'],
			'donor_name'    => $donor_name,
			'error_message' => '',
		);

		if ( 'paid' === $row['status'] ) {
			$base_result['status']  = 'paid';
			$base_result['receipt'] = (string) $row['gateway_receipt'];
			return $base_result;
		}

		if ( 'failed' === $row['status'] || 'cancelled' === $row['status'] ) {
			$base_result['status']        = $row['status'];
			$base_result['error_message'] = ( 'cancelled' === $row['status'] )
				? __( 'You cancelled the donation payment. No charge has been made.', 'lccl-de' )
				: __( 'Your donation payment could not be completed. No charge has been made.', 'lccl-de' );
			return $base_result;
		}

		if ( $is_cancelled || '' === $reqid ) {
			if ( 'pending' === $row['status'] ) {
				$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
					$table,
					array( 'status' => 'cancelled', 'gateway_response' => 'User cancelled donation payment' ),
					array( 'order_ref' => $order_ref ),
					array( '%s', '%s' ),
					array( '%s' )
				);
			}
			$base_result['status'] = 'cancelled';
			return $base_result;
		}

		$stored_reqid = (string) $row['session_id'];
		if ( empty( $stored_reqid ) || ! hash_equals( $stored_reqid, $reqid ) ) {
			error_log(
				sprintf(
					'[LCCL Paycenter Security Warning] Unauthorized callback attempt on donation order %s with invalid reqid %s',
					$order_ref,
					$reqid
				)
			);
			$base_result['error_message'] = __( 'Payment verification failed. Please contact the club administrator.', 'lccl-de' );
			return $base_result;
		}

		// Server-to-server confirmation: PAYMENT_COMPLETE with Donations profile.
		$response_data = LCCL_DE_Paycenter_Client::payment_complete( $reqid, self::PROFILE );

		if ( is_wp_error( $response_data ) ) {
			error_log(
				sprintf(
					'[LCCL Paycenter Donation Error] Order %s failed PAYMENT_COMPLETE: %s',
					$order_ref,
					$response_data->get_error_message()
				)
			);

			$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
				$table,
				array( 'status' => 'failed', 'gateway_response' => $response_data->get_error_message() ),
				array( 'order_ref' => $order_ref ),
				array( '%s', '%s' ),
				array( '%s' )
			);

			$base_result['error_message'] = __( 'Gateway confirmation failed. Please contact the club administrator.', 'lccl-de' );
			return $base_result;
		}

		if ( ! LCCL_DE_Paycenter_Client::is_approved( $response_data ) ) {
			$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
				$table,
				array(
					'status'           => 'failed',
					'gateway_response' => wp_json_encode( $response_data ),
				),
				array( 'order_ref' => $order_ref ),
				array( '%s', '%s' ),
				array( '%s' )
			);

			$base_result['error_message'] = __( 'Your donation payment was declined. No charge has been made. Please try again or use a different card.', 'lccl-de' );
			return $base_result;
		}

		$profile_cfg       = LCCL_DE_Settings::get_paycenter( LCCL_DE_Settings::PROFILE_DONATIONS );
		$expected_currency = isset( $profile_cfg['currency'] ) && '' !== $profile_cfg['currency'] ? $profile_cfg['currency'] : 'LKR';

		if ( ! LCCL_DE_Paycenter_Client::verify_amount( $response_data, (float) $row['amount_lkr'], $expected_currency ) ) {
			$mismatch_msg = 'Amount/currency mismatch in donation.';
			error_log( '[LCCL Paycenter] ' . $mismatch_msg . ' (Order: ' . $order_ref . ')' );

			$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
				$table,
				array(
					'status'           => 'failed',
					'gateway_response' => $mismatch_msg,
				),
				array( 'order_ref' => $order_ref ),
				array( '%s', '%s' ),
				array( '%s' )
			);
			$base_result['error_message'] = __( 'Donation payment amount mismatch. Please contact the club administrator.', 'lccl-de' );
			return $base_result;
		}

		if ( ! LCCL_DE_Paycenter_Client::verify_client_ref( $response_data, $order_ref ) ) {
			error_log( sprintf( '[LCCL Paycenter] clientRef mismatch for donation order %s', $order_ref ) );

			$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
				$table,
				array(
					'status'           => 'failed',
					'gateway_response' => 'clientRef mismatch',
				),
				array( 'order_ref' => $order_ref ),
				array( '%s', '%s' ),
				array( '%s' )
			);
			$base_result['error_message'] = __( 'Payment reference mismatch. Please contact the club administrator.', 'lccl-de' );
			return $base_result;
		}

		$receipt = LCCL_DE_Paycenter_Client::extract_receipt( $response_data );

		$updated = $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"UPDATE `{$table}`
				 SET status = 'paid',
				     session_id = %s,
				     gateway_receipt = %s,
				     gateway_response = %s,
				     paid_at = %s
				 WHERE order_ref = %s AND status = 'pending'",
				$reqid,
				$receipt,
				wp_json_encode( $response_data ),
				current_time( 'mysql' ),
				$order_ref
			)
		);

		if ( $updated > 0 ) {
			self::send_donation_confirmation( $row, $receipt );
		}

		return array(
			'status'        => 'paid',
			'order_ref'     => $order_ref,
			'receipt'       => $receipt,
			'amount'        => (float) $row['amount_lkr'],
			'donor_name'    => $donor_name,
			'error_message' => '',
		);
	}

	/**
	 * Send email and SMS confirmations to the donor, plus an admin notification copy.
	 *
	 * @param array  $row     Payment record.
	 * @param string $receipt Bank receipt reference.
	 */
	private static function send_donation_confirmation( array $row, $receipt ) {
		self::send_donation_email( $row, $receipt );
		self::send_donation_sms( $row, $receipt );
		self::send_admin_donation_alert( $row, $receipt );
	}

	/**
	 * Send a thank-you SMS with the donated amount to the donor.
	 *
	 * @param array  $row     Payment record.
	 * @param string $receipt Bank receipt reference.
	 */
	private static function send_donation_sms( array $row, $receipt ) {
		$phone = ! empty( $row['member_phone'] ) ? (string) $row['member_phone'] : '';
		if ( '' === trim( $phone ) ) {
			return;
		}

		$first_name = trim( (string) $row['member_first_name'] );
		$amount     = number_format( (float) $row['amount_lkr'], 2 );
		$ref        = $receipt ?: (string) $row['order_ref'];

		$message = sprintf(
			/* translators: 1: donor name, 2: amount formatted, 3: transaction receipt */
			__( 'Dear %1$s, thank you for your generous donation of LKR %2$s to Lions Club of Colombo LEADS. Your support transforms lives! Ref: %3$s', 'lccl-de' ),
			$first_name ?: __( 'Donor', 'lccl-de' ),
			$amount,
			$ref
		);

		if ( class_exists( 'LCCL_DE_Notify' ) && method_exists( 'LCCL_DE_Notify', 'send_custom_sms' ) ) {
			LCCL_DE_Notify::send_custom_sms( $phone, $message, isset( $row['id'] ) ? (int) $row['id'] : 0, 'donations' );
		}
	}

	/**
	 * Send a rich HTML thank-you confirmation email to the donor.
	 *
	 * @param array  $row     Payment record.
	 * @param string $receipt Bank receipt reference.
	 */
	private static function send_donation_email( array $row, $receipt ) {
		$to = sanitize_email( (string) $row['member_email'] );
		if ( ! is_email( $to ) ) {
			return;
		}

		$first_name = trim( (string) $row['member_first_name'] );
		$last_name  = trim( (string) $row['member_last_name'] );
		$name       = trim( $first_name . ' ' . $last_name );
		if ( '' === $name ) {
			$name = __( 'Generous Donor', 'lccl-de' );
		}

		$amount_raw = (float) $row['amount_lkr'];
		$amount     = number_format( $amount_raw, 2 );
		$ref        = $receipt ?: (string) $row['order_ref'];
		$order_ref  = (string) $row['order_ref'];

		$date_raw   = ! empty( $row['paid_at'] ) ? $row['paid_at'] : current_time( 'mysql' );
		$date_fmt   = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
		$date_str   = mysql2date( $date_fmt, $date_raw );

		// Decode causes and message if present.
		$meta          = ! empty( $row['gateway_response'] ) ? json_decode( (string) $row['gateway_response'], true ) : null;
		$causes_labels = array();
		$donor_message = '';

		if ( is_array( $meta ) ) {
			if ( ! empty( $meta['causes'] ) && is_array( $meta['causes'] ) ) {
				$all_causes = self::causes();
				foreach ( $meta['causes'] as $ckey ) {
					if ( isset( $all_causes[ $ckey ] ) ) {
						$causes_labels[] = $all_causes[ $ckey ];
					}
				}
			}
			if ( ! empty( $meta['message'] ) ) {
				$donor_message = trim( (string) $meta['message'] );
			}
		}

		$causes_str = $causes_labels ? implode( ', ', $causes_labels ) : __( 'General Community Service', 'lccl-de' );

		$subject = sprintf(
			/* translators: 1: site name, 2: amount formatted */
			__( '[%1$s] Thank You for Your Donation of LKR %2$s!', 'lccl-de' ),
			get_bloginfo( 'name' ),
			$amount
		);

		$message_row = '';
		if ( '' !== $donor_message ) {
			$message_row = '<p style="margin:0 0 4px;color:#666666;font-size:13px;">' . esc_html__( 'Your Note / Message', 'lccl-de' ) . '</p>' .
				'<p style="margin:0 0 14px;color:#222222;font-size:14px;font-style:italic;background-color:#ffffff;padding:8px 12px;border-radius:4px;border:1px solid #e0e0e0;">' . esc_html( $donor_message ) . '</p>';
		}

		$html = '<html>
<body style="margin:0;padding:0;background-color:#F3F3F3;font-family:Arial,Helvetica,sans-serif;-webkit-font-smoothing:antialiased;">
	<div style="max-width:600px;margin:0 auto;background-color:#F3F3F3;padding:28px 20px;">
		<img src="https://registration.colomboleads.org/lccclLOGO.png" alt="Lions Club of Colombo LEADS" width="156" style="display:block;width:156px;max-width:156px;height:auto;margin:0 0 22px;border:0;">
		<div style="background-color:#FFFFFF;border-radius:8px;padding:32px 28px;box-shadow:0 2px 8px rgba(0,0,0,0.06);">
			<h1 style="margin:0 0 18px;padding:0 0 12px;border-bottom:2px solid #f8e4a0;color:#222222;font-size:20px;font-weight:700;letter-spacing:0.03em;line-height:1.35;">
				' . esc_html__( 'DONATION RECEIVED WITH THANKS', 'lccl-de' ) . '
			</h1>
			<p style="margin:0 0 16px;color:#444444;font-size:15px;line-height:1.6;">
				' . sprintf( esc_html__( 'Dear %s,', 'lccl-de' ), '<strong>' . esc_html( $name ) . '</strong>' ) . '
			</p>
			<p style="margin:0 0 18px;color:#444444;font-size:15px;line-height:1.6;">
				' . sprintf(
					/* translators: 1: club name, 2: amount formatted */
					esc_html__( 'On behalf of the %1$s, we extend our deepest gratitude for your generous contribution of %2$s.', 'lccl-de' ),
					'<strong>' . esc_html__( 'Lions Club of Colombo LEADS', 'lccl-de' ) . '</strong>',
					'<strong style="color:#0073aa;font-size:16px;">LKR ' . esc_html( $amount ) . '</strong>'
				) . '
			</p>
			<p style="margin:0 0 24px;color:#555555;font-size:14px;line-height:1.6;">
				' . esc_html__( 'Your generous support directly empowers our vital community service projects—including eye care & spectacles, diabetes screening, hunger relief, and educational assistance. Every contribution makes a real, lasting difference in the lives of those in need.', 'lccl-de' ) . '
			</p>
			<div style="background-color:#f9f9f9;border-left:4px solid #0073aa;border-radius:4px;padding:18px 20px;margin:0 0 24px;">
				<h2 style="margin:0 0 14px;color:#333333;font-size:13px;font-weight:700;letter-spacing:0.06em;text-transform:uppercase;">
					' . esc_html__( 'DONATION RECEIPT DETAILS', 'lccl-de' ) . '
				</h2>
				<p style="margin:0 0 4px;color:#666666;font-size:13px;">' . esc_html__( 'Donor Name', 'lccl-de' ) . '</p>
				<p style="margin:0 0 12px;color:#222222;font-size:15px;font-weight:700;">' . esc_html( $name ) . '</p>

				<p style="margin:0 0 4px;color:#666666;font-size:13px;">' . esc_html__( 'Amount Donated', 'lccl-de' ) . '</p>
				<p style="margin:0 0 12px;color:#0073aa;font-size:18px;font-weight:800;">LKR ' . esc_html( $amount ) . '</p>

				<p style="margin:0 0 4px;color:#666666;font-size:13px;">' . esc_html__( 'Receipt / Bank Reference', 'lccl-de' ) . '</p>
				<p style="margin:0 0 12px;color:#222222;font-size:14px;font-family:monospace;font-weight:700;">' . esc_html( $ref ) . '</p>

				<p style="margin:0 0 4px;color:#666666;font-size:13px;">' . esc_html__( 'Order Reference', 'lccl-de' ) . '</p>
				<p style="margin:0 0 12px;color:#222222;font-size:14px;font-family:monospace;">' . esc_html( $order_ref ) . '</p>

				<p style="margin:0 0 4px;color:#666666;font-size:13px;">' . esc_html__( 'Date & Time', 'lccl-de' ) . '</p>
				<p style="margin:0 0 12px;color:#222222;font-size:14px;">' . esc_html( $date_str ) . '</p>

				<p style="margin:0 0 4px;color:#666666;font-size:13px;">' . esc_html__( 'Supported Cause(s)', 'lccl-de' ) . '</p>
				<p style="margin:0 0 12px;color:#222222;font-size:14px;">' . esc_html( $causes_str ) . '</p>

				' . $message_row . '

				<p style="margin:0 0 4px;color:#666666;font-size:13px;">' . esc_html__( 'Payment Gateway', 'lccl-de' ) . '</p>
				<p style="margin:0;color:#222222;font-size:14px;">' . esc_html__( 'Commercial Bank of Ceylon (CBC) Paycenter', 'lccl-de' ) . '</p>
			</div>
			<p style="margin:0 0 24px;color:#666666;font-size:13px;line-height:1.6;">
				' . esc_html__( 'Please keep this email as your official confirmation receipt. If you have any inquiries regarding your donation, please reply directly to this email.', 'lccl-de' ) . '
			</p>
			<p style="margin:0 0 4px;color:#222222;font-size:14px;font-weight:700;letter-spacing:0.04em;">' . esc_html__( 'LIONS CLUB OF COLOMBO LEADS', 'lccl-de' ) . '</p>
			<p style="margin:0;color:#666666;font-size:13px;line-height:1.5;">
				' . esc_html__( 'Lions International District 306 D6 | Sri Lanka', 'lccl-de' ) . '<br>
				<a href="https://www.colomboleads.org" style="color:#0073aa;text-decoration:none;">www.colomboleads.org</a>
			</p>
		</div>
	</div>
</body>
</html>';

		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
			'From: ' . get_bloginfo( 'name' ) . ' <' . get_option( 'admin_email' ) . '>',
		);

		wp_mail( $to, $subject, $html, $headers );
	}

	/**
	 * Send an alert to the club admin when a donation is successfully paid.
	 *
	 * @param array  $row     Payment record.
	 * @param string $receipt Bank receipt reference.
	 */
	private static function send_admin_donation_alert( array $row, $receipt ) {
		$admin_email = get_option( 'admin_email' );
		if ( ! is_email( $admin_email ) ) {
			return;
		}

		$name       = trim( $row['member_first_name'] . ' ' . $row['member_last_name'] );
		$amount     = number_format( (float) $row['amount_lkr'], 2 );
		$email      = (string) $row['member_email'];
		$phone      = (string) $row['member_phone'];
		$order_ref  = (string) $row['order_ref'];
		$ref        = $receipt ?: $order_ref;

		$subject = sprintf(
			/* translators: 1: amount formatted, 2: donor name */
			__( '[Donation Alert] Received LKR %1$s from %2$s', 'lccl-de' ),
			$amount,
			$name
		);

		$body = sprintf(
			/* translators: 1: name, 2: amount, 3: email, 4: phone, 5: receipt, 6: order_ref, 7: date */
			__(
				"A new online donation has been received!\n\nDonor Details:\n----------------------------------------\nName:      %1\$s\nAmount:    LKR %2\$s\nEmail:     %3\$s\nPhone:     %4\$s\nReceipt:   %5\$s\nOrder Ref: %6\$s\nDate/Time: %7\$s\nGateway:   Commercial Bank of Ceylon (CBC) Paycenter\n----------------------------------------\n\nLog in to WP Admin -> LCCL Programs -> Payment Gateway -> Payment Transactions to view full records.",
				'lccl-de'
			),
			$name,
			$amount,
			$email,
			$phone,
			$ref,
			$order_ref,
			current_time( 'mysql' )
		);

		$headers = array(
			'Content-Type: text/plain; charset=UTF-8',
			'From: ' . get_bloginfo( 'name' ) . ' <' . $admin_email . '>',
		);

		wp_mail( $admin_email, $subject, $body, $headers );
	}

	/**
	 * Generate a unique merchant order reference for donations.
	 *
	 * Format: LCCL-DON-YYYYMMDD-XXXXXXXX
	 *
	 * @return string
	 */
	public static function generate_order_ref() {
		return 'LCCL-DON-' . gmdate( 'Ymd' ) . '-' . strtoupper( wp_generate_password( 8, false ) );
	}

	/**
	 * Return the client IP address.
	 *
	 * @return string
	 */
	private static function client_ip() {
		$candidates = array();

		if ( ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
			$candidates[] = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] ) );
		}

		if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$parts        = explode( ',', sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) );
			$candidates[] = trim( $parts[0] );
		}

		if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$candidates[] = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}

		foreach ( $candidates as $ip ) {
			if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
				return $ip;
			}
		}

		return '127.0.0.1';
	}

	/**
	 * Expose the form as a WPBakery element.
	 */
	public static function map() {
		if ( ! function_exists( 'vc_map' ) ) {
			return;
		}

		vc_map(
			array(
				'name'        => __( 'LCCL Donation Form', 'lccl-de' ),
				'base'        => self::SHORTCODE,
				'category'    => __( 'LCCL', 'lccl-de' ),
				'description' => __( 'Donation form with online card payment processed via Commercial Bank of Ceylon Paycenter.', 'lccl-de' ),
				'icon'        => 'icon-wpb-ui-separator',
				'params'      => array(
					array(
						'type'        => 'textfield',
						'heading'     => __( 'Title', 'lccl-de' ),
						'param_name'  => 'title',
						'value'       => self::default_title(),
						'admin_label' => true,
					),
					array(
						'type'       => 'textarea',
						'heading'    => __( 'Intro text', 'lccl-de' ),
						'param_name' => 'intro',
						'value'      => self::default_intro(),
					),
				),
			)
		);
	}
}
