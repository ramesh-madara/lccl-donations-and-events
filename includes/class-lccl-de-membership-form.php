<?php
/**
 * Annual membership fee payment form with CBC Paycenter Web 4.0 integration.
 *
 * Flow:
 *  1. Member fills form and submits via POST → handle_submit()
 *  2. handle_submit() validates, inserts pending row, calls PAYMENT_INIT
 *  3. Browser is redirected to the Bancstac-hosted payment page URL
 *  4. Bancstac redirects browser back to returnUrl with ?reqid=…
 *  5. render() detects QA_RETURN → handle_callback() calls PAYMENT_COMPLETE,
 *     verifies responseCode / amount / currency / clientRef, updates the DB row,
 *     and shows templates/membership-result.php
 *
 * Security practices:
 *  – Amount, currency, and clientRef are all verified server-side in step 5.
 *  – Atomic SQL transition to 'paid' prevents race conditions.
 *  – IP-based rate limiting: max 10 initiations per IP per 10 minutes.
 *  – Timing-attack-safe comparison via hash_equals() in verify_client_ref().
 *  – All raw gateway error detail goes to error_log only; sanitised messages shown to user.
 *  – Nonce protection on form submission.
 *
 * @package LCCL_Donations_And_Events
 */

defined( 'ABSPATH' ) || exit;

/**
 * Renders the annual membership fee payment form and handles the payment flow.
 */
class LCCL_DE_Membership_Form {

	/**
	 * Shortcode tag.
	 */
	const SHORTCODE = 'lccl_membership_fee';

	/**
	 * USD to LKR rate used for fee calculation.
	 * Stored as a constant; can later be moved to a settings field.
	 */
	const RATE = 330.8;

	/**
	 * Main member international fee in USD.
	 */
	const PRINCIPAL_USD = 50;

	/**
	 * Additional family member international fee in USD.
	 */
	const FAMILY_USD = 25;

	/**
	 * District payment in LKR, charged once per member.
	 */
	const DISTRICT_LKR = 3500;

	/**
	 * Club payment in LKR, charged once for the membership.
	 */
	const CLUB_LKR = 6000;

	/**
	 * Nonce action for form submission.
	 */
	const NONCE_ACTION = 'lccl_mf_submit';

	/**
	 * Nonce field name.
	 */
	const NONCE_FIELD = 'lccl_mf_nonce';

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
	 * Hook the shortcode, WPBakery, and the early-init callback handler.
	 */
	public static function init() {
		add_shortcode( self::SHORTCODE, array( __CLASS__, 'render' ) );
		add_action( 'vc_before_init', array( __CLASS__, 'map' ) );

		// Handle POST submission before any output.
		add_action( 'template_redirect', array( __CLASS__, 'maybe_handle_submit' ) );
	}

	// ------------------------------------------------------------------
	// Public shortcode renderer
	// ------------------------------------------------------------------

	/**
	 * Render the membership fee shortcode output.
	 *
	 * Depending on URL query args this renders one of two templates:
	 *   1. templates/membership-result.php  – success / failure / cancelled result page
	 *   2. templates/membership-form.php    – the default entry form
	 *
	 * NOTE: There is no intermediate receipt page in the CBC Paycenter flow.
	 * After PAYMENT_INIT the user is redirected directly to the Bancstac-hosted
	 * payment URL. On return Bancstac passes ?reqid=… via GET to the returnUrl.
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

		// Always enqueue the shared form stylesheet.
		wp_enqueue_style(
			'lccl-de-blood-donor-form',
			LCCL_DE_URL . 'assets/css/lccl-de-blood-donor-form.css',
			array(),
			LCCL_DE_VERSION
		);

		// ----------------------------------------------------------------
		// Gateway returned browser → run callback, show result
		// ----------------------------------------------------------------
		$is_return = ! empty( $_GET[ self::QA_RETURN ] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			|| ! empty( $_GET['lccl_mpgs_return'] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			|| ! empty( $_GET[ self::QA_CANCEL ] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( $is_return ) {
			$result = self::handle_callback();

			ob_start();
			include LCCL_DE_PATH . 'templates/membership-result.php';
			return ob_get_clean();
		}

		// ----------------------------------------------------------------
		// Default – show the entry form
		// ----------------------------------------------------------------
		wp_enqueue_script(
			'lccl-de-membership-form',
			LCCL_DE_URL . 'assets/js/lccl-de-membership-form.js',
			array(),
			LCCL_DE_VERSION,
			true
		);

		$values = array();
		$errors = array();
		$fees   = self::breakdown();

		// Re-populate errors after a failed submit that didn't redirect away.
		if ( ! empty( $_GET['lccl_mf_error'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$errors[] = sanitize_text_field( wp_unslash( $_GET['lccl_mf_error'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		ob_start();
		include LCCL_DE_PATH . 'templates/membership-form.php';
		return ob_get_clean();
	}

	// ------------------------------------------------------------------
	// Form submission handler
	// ------------------------------------------------------------------

	/**
	 * Fired on template_redirect. Intercepts form POST, initiates the payment,
	 * and redirects the browser to the Bancstac-hosted payment page URL.
	 */
	public static function maybe_handle_submit() {
		if ( 'POST' !== strtoupper( sanitize_text_field( isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : 'GET' ) ) ) {
			return;
		}

		if ( empty( $_POST[ self::NONCE_FIELD ] ) ) {
			return;
		}

		// Verify nonce.
		if ( ! check_admin_referer( self::NONCE_ACTION, self::NONCE_FIELD ) ) {
			return;
		}

		// Rate limiting: max 10 checkout session initiations per IP per 10 minutes.
		$ip_key = 'lccl_mf_rate_' . md5( self::client_ip() );
		$hits   = (int) get_transient( $ip_key );
		if ( $hits >= 10 ) {
			wp_safe_redirect(
				add_query_arg(
					array( 'lccl_mf_error' => rawurlencode( __( 'Too many payment attempts. Please wait a few minutes before trying again.', 'lccl-de' ) ) ),
					wp_get_referer() ?: get_permalink()
				)
			);
			exit;
		}
		set_transient( $ip_key, $hits + 1, 10 * MINUTE_IN_SECONDS );

		self::handle_submit();
	}

	/**
	 * Validate posted fields, insert a pending payment row, call PAYMENT_INIT,
	 * and redirect to the Bancstac-hosted payment page or back to form with an error.
	 */
	private static function handle_submit() {
		// ----------------------------------------------------------------
		// Validate required fields
		// ----------------------------------------------------------------
		$raw = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

		$first_name       = sanitize_text_field( isset( $raw['first_name'] ) ? $raw['first_name'] : '' );
		$last_name        = sanitize_text_field( isset( $raw['last_name'] ) ? $raw['last_name'] : '' );
		$email            = sanitize_email( isset( $raw['email'] ) ? $raw['email'] : '' );
		$phone            = sanitize_text_field( isset( $raw['phone'] ) ? $raw['phone'] : '' );
		$membership_type  = sanitize_key( isset( $raw['membership_type'] ) ? $raw['membership_type'] : '' );
		$family_count_raw = sanitize_text_field( isset( $raw['family_count'] ) ? $raw['family_count'] : '2' );
		$family_count     = max( 2, min( 5, (int) $family_count_raw ) );

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

		if ( ! in_array( $membership_type, array( 'member', 'family' ), true ) ) {
			$errors[] = __( 'Please select a membership type.', 'lccl-de' );
		}

		if ( ! LCCL_DE_Paycenter_Client::is_configured() ) {
			$errors[] = __( 'Online payment is not available yet. Please contact the club administrator.', 'lccl-de' );
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

		// ----------------------------------------------------------------
		// Calculate fee (server-side only – never accepted from POST)
		// ----------------------------------------------------------------
		$breakdown = self::breakdown( $membership_type, $family_count );
		$amount    = $breakdown['total'];

		// ----------------------------------------------------------------
		// Generate unique order reference
		// ----------------------------------------------------------------
		$order_ref = self::generate_order_ref();

		// ----------------------------------------------------------------
		// Insert pending row
		// ----------------------------------------------------------------
		global $wpdb;
		$table = LCCL_DE_Schema::payments_table();

		$inserted = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$table,
			array(
				'order_ref'         => $order_ref,
				'member_first_name' => $first_name,
				'member_last_name'  => $last_name,
				'member_email'      => $email,
				'member_phone'      => $phone,
				'membership_type'   => $membership_type,
				'family_count'      => ( 'family' === $membership_type ) ? $family_count : 1,
				'amount_lkr'        => $amount,
				'status'            => 'pending',
				'ip_address'        => self::client_ip(),
				'created_at'        => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%f', '%s', '%s', '%s' )
		);

		if ( ! $inserted ) {
			wp_safe_redirect(
				add_query_arg(
					array( 'lccl_mf_error' => rawurlencode( __( 'Could not save your payment request. Please try again.', 'lccl-de' ) ) ),
					wp_get_referer() ?: get_permalink()
				)
			);
			exit;
		}

		// ----------------------------------------------------------------
		// Build return and cancel URLs
		// ----------------------------------------------------------------
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
			__( 'LCCL Annual Membership Fee - %1$s %2$s', 'lccl-de' ),
			$first_name,
			$last_name
		);

		// ----------------------------------------------------------------
		// Call PAYMENT_INIT (CBC Paycenter)
		// ----------------------------------------------------------------
		$init_result = LCCL_DE_Paycenter_Client::payment_init(
			array(
				'order_ref'  => $order_ref,
				'amount'     => $amount,
				'return_url' => $return_url,
				'cancel_url' => $cancel_url,
				'comment'    => $comment,
			)
		);

		if ( is_wp_error( $init_result ) ) {
			// Log technical details securely to server logs for diagnostics.
			error_log(
				sprintf(
					'[LCCL Paycenter Error] Order %s failed PAYMENT_INIT: %s',
					$order_ref,
					$init_result->get_error_message()
				)
			);

			// Mark row as failed so it can be audited.
			$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
				$table,
				array( 'status' => 'failed', 'gateway_response' => $init_result->get_error_message() ),
				array( 'order_ref' => $order_ref ),
				array( '%s', '%s' ),
				array( '%s' )
			);

			// Provide user-friendly masked error on frontend.
			$user_msg = ( 'lccl_pc_disabled' === $init_result->get_error_code() )
				? __( 'Online payment is currently unavailable. Please contact the club administrator.', 'lccl-de' )
				: __( 'Unable to connect to the payment gateway. Please try again or contact the club administrator.', 'lccl-de' );

			wp_safe_redirect(
				add_query_arg(
					array( 'lccl_mf_error' => rawurlencode( $user_msg ) ),
					wp_get_referer() ?: get_permalink()
				)
			);
			exit;
		}

		// ----------------------------------------------------------------
		// Store reqid for callback verification (in the session_id column)
		// ----------------------------------------------------------------
		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$table,
			array(
				'session_id' => $init_result['reqid'],
			),
			array( 'order_ref' => $order_ref ),
			array( '%s' ),
			array( '%s' )
		);

		// ----------------------------------------------------------------
		// Redirect directly to Bancstac-hosted payment page
		// ----------------------------------------------------------------
		wp_redirect( esc_url_raw( $init_result['payment_page_url'] ) ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
		exit;
	}

	// ------------------------------------------------------------------
	// Callback handler (step 4-5 of CBC Paycenter flow)
	// ------------------------------------------------------------------

	/**
	 * Verify the reqid, call PAYMENT_COMPLETE, and update the DB row.
	 *
	 * Called from render() when ?lccl_mpgs_return=1 is in the URL.
	 *
	 * @return array{
	 *     status: string,        'paid'|'failed'|'cancelled'|'error'
	 *     order_ref: string,
	 *     receipt: string,
	 *     amount: float,
	 *     member_name: string,
	 *     error_message: string,
	 * }
	 */
	public static function handle_callback() {
		$order_ref = '';
		if ( ! empty( $_GET[ self::QA_ORDER_REF ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$order_ref = sanitize_text_field( wp_unslash( $_GET[ self::QA_ORDER_REF ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		} elseif ( ! empty( $_GET['lccl_mpgs_ref'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$order_ref = sanitize_text_field( wp_unslash( $_GET['lccl_mpgs_ref'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		// reqid is passed by Bancstac to the returnUrl as ?reqid=… or ?ReqID=…
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
			'member_name'   => '',
			'error_message' => '',
		);

		if ( '' === $order_ref ) {
			$error_result['error_message'] = __( 'Payment reference missing. Please contact the club administrator.', 'lccl-de' );
			return $error_result;
		}

		// Load the payment row.
		global $wpdb;
		$table = LCCL_DE_Schema::payments_table();

		$row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare( "SELECT * FROM `{$table}` WHERE order_ref = %s LIMIT 1", $order_ref ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);

		if ( ! $row ) {
			$error_result['error_message'] = __( 'Payment record not found. Please contact the club administrator.', 'lccl-de' );
			return $error_result;
		}

		$base_result = array(
			'status'        => 'failed',
			'order_ref'     => $order_ref,
			'receipt'       => '',
			'amount'        => (float) $row['amount_lkr'],
			'member_name'   => trim( $row['member_first_name'] . ' ' . $row['member_last_name'] ),
			'error_message' => '',
		);

		// If already paid, return current paid state immediately (idempotent for refreshes).
		if ( 'paid' === $row['status'] ) {
			$base_result['status']  = 'paid';
			$base_result['receipt'] = (string) $row['gateway_receipt'];
			return $base_result;
		}

		// If already in a terminal failed or cancelled state, return that state immediately.
		if ( 'failed' === $row['status'] || 'cancelled' === $row['status'] ) {
			$base_result['status']        = $row['status'];
			$base_result['error_message'] = ( 'cancelled' === $row['status'] )
				? __( 'You cancelled the payment. No charge has been made.', 'lccl-de' )
				: __( 'Your payment could not be completed. No charge has been made.', 'lccl-de' );
			return $base_result;
		}

		// ----------------------------------------------------------------
		// Handle cancellation (cancelled flag in URL or no reqid provided)
		// ----------------------------------------------------------------
		if ( $is_cancelled || '' === $reqid ) {
			if ( 'pending' === $row['status'] ) {
				$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
					$table,
					array( 'status' => 'cancelled', 'gateway_response' => 'User cancelled payment' ),
					array( 'order_ref' => $order_ref ),
					array( '%s', '%s' ),
					array( '%s' )
				);
			}
			$base_result['status'] = 'cancelled';
			return $base_result;
		}

		// ----------------------------------------------------------------
		// Security: verify that the reqid matches the stored session_id
		// The session_id column stores the reqid from PAYMENT_INIT.
		// Must not be empty, and compared using hash_equals().
		// ----------------------------------------------------------------
		$stored_reqid = (string) $row['session_id'];
		if ( empty( $stored_reqid ) || ! hash_equals( $stored_reqid, $reqid ) ) {
			error_log(
				sprintf(
					'[LCCL Paycenter Security Warning] Unauthorized callback attempt on order %s with invalid reqid %s',
					$order_ref,
					$reqid
				)
			);
			// Do NOT mutate DB row to 'failed' from an unauthenticated request to prevent DoS.
			$base_result['error_message'] = __( 'Payment verification failed. Please contact the club administrator.', 'lccl-de' );
			return $base_result;
		}

		// ----------------------------------------------------------------
		// Server-to-server confirmation: PAYMENT_COMPLETE
		// ----------------------------------------------------------------
		$response_data = LCCL_DE_Paycenter_Client::payment_complete( $reqid );

		if ( is_wp_error( $response_data ) ) {
			$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
				$table,
				array( 'status' => 'failed', 'gateway_response' => $response_data->get_error_message() ),
				array( 'order_ref' => $order_ref ),
				array( '%s', '%s' ),
				array( '%s' )
			);
			$base_result['error_message'] = __( 'Unable to confirm payment with the gateway. Please contact the club administrator.', 'lccl-de' );
			return $base_result;
		}

		// ----------------------------------------------------------------
		// Verify: responseCode must be '00' (TRANSACTION APPROVED)
		// ----------------------------------------------------------------
		if ( ! LCCL_DE_Paycenter_Client::is_approved( $response_data ) ) {
			$resp_text = isset( $response_data['responseText'] ) ? (string) $response_data['responseText'] : 'Declined';
			$resp_code = isset( $response_data['responseCode'] ) ? (string) $response_data['responseCode'] : '';

			error_log(
				sprintf(
					'[LCCL Paycenter] Order %s declined. Code: %s, Text: %s',
					$order_ref,
					$resp_code,
					$resp_text
				)
			);

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
			$base_result['error_message'] = __( 'Your payment was declined. No charge has been made. Please try again or use a different card.', 'lccl-de' );
			return $base_result;
		}

		// ----------------------------------------------------------------
		// Verify: amount and currency returned by the gateway match billed amount
		// ----------------------------------------------------------------
		if ( ! LCCL_DE_Paycenter_Client::verify_amount( $response_data, (float) $row['amount_lkr'] ) ) {
			$ta_returned  = isset( $response_data['transactionAmount'] ) ? wp_json_encode( $response_data['transactionAmount'] ) : 'n/a';
			$mismatch_msg = sprintf(
				'Amount/currency mismatch. Expected: %s LKR, Gateway returned: %s',
				round( (float) $row['amount_lkr'] ),
				$ta_returned
			);
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
			$base_result['error_message'] = __( 'Payment amount or currency mismatch. Please contact the club administrator.', 'lccl-de' );
			return $base_result;
		}

		// ----------------------------------------------------------------
		// Verify: clientRef echoed back matches our order reference
		// ----------------------------------------------------------------
		if ( ! LCCL_DE_Paycenter_Client::verify_client_ref( $response_data, $order_ref ) ) {
			$returned_ref = isset( $response_data['clientRef'] ) ? (string) $response_data['clientRef'] : '';
			error_log(
				sprintf(
					'[LCCL Paycenter] clientRef mismatch for order %s. Got: %s',
					$order_ref,
					$returned_ref
				)
			);

			$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
				$table,
				array(
					'status'           => 'failed',
					'gateway_response' => 'clientRef mismatch: ' . $returned_ref,
				),
				array( 'order_ref' => $order_ref ),
				array( '%s', '%s' ),
				array( '%s' )
			);
			$base_result['error_message'] = __( 'Payment reference mismatch. Please contact the club administrator.', 'lccl-de' );
			return $base_result;
		}

		// ----------------------------------------------------------------
		// Extract the bank transaction reference (our "receipt")
		// ----------------------------------------------------------------
		$receipt = LCCL_DE_Paycenter_Client::extract_receipt( $response_data );

		// ----------------------------------------------------------------
		// Atomic transition to 'paid' (strictly from 'pending' status)
		// ----------------------------------------------------------------
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

		// Send confirmation email only if this request performed the transition.
		if ( $updated > 0 ) {
			self::send_payment_confirmation( $row, $receipt );
		}

		return array(
			'status'        => 'paid',
			'order_ref'     => $order_ref,
			'receipt'       => $receipt,
			'amount'        => (float) $row['amount_lkr'],
			'member_name'   => trim( $row['member_first_name'] . ' ' . $row['member_last_name'] ),
			'error_message' => '',
		);
	}

	// ------------------------------------------------------------------
	// Email notification
	// ------------------------------------------------------------------

	/**
	 * Send a payment confirmation email to the member.
	 *
	 * @param array  $row     DB row from lccl_de_payments.
	 * @param string $receipt CBC Paycenter txnReference.
	 */
	private static function send_payment_confirmation( array $row, $receipt ) {
		$to     = sanitize_email( (string) $row['member_email'] );
		$name   = trim( $row['member_first_name'] . ' ' . $row['member_last_name'] );
		$amount = number_format( (float) $row['amount_lkr'], 2 );

		if ( ! is_email( $to ) ) {
			return;
		}

		$subject = sprintf(
			/* translators: site name */
			__( '[%s] Membership Fee Payment Confirmation', 'lccl-de' ),
			get_bloginfo( 'name' )
		);

		$body = sprintf(
			/* translators: 1: name, 2: amount, 3: receipt, 4: order ref */
			__(
				"Dear %1\$s,\n\n" .
				"Thank you! Your annual membership fee payment of LKR %2\$s has been received successfully.\n\n" .
				"Payment Reference  : %4\$s\n" .
				"Bank Transaction # : %3\$s\n\n" .
				"If you have any questions, please reply to this email.\n\n" .
				"Lions Club of Colombo LEADS",
				'lccl-de'
			),
			$name,
			$amount,
			$receipt,
			$row['order_ref']
		);

		wp_mail( $to, $subject, $body );
	}

	// ------------------------------------------------------------------
	// Fee calculation helpers (unchanged)
	// ------------------------------------------------------------------

	/**
	 * Default form heading.
	 *
	 * @return string
	 */
	public static function default_title() {
		return __( 'Annual Membership Fee', 'lccl-de' );
	}

	/**
	 * Default heading intro.
	 *
	 * @return string
	 */
	public static function default_intro() {
		return __( 'Please complete the details below to pay your annual membership fee to Lions Club of Colombo LEADS.', 'lccl-de' );
	}

	/**
	 * Membership type options.
	 *
	 * @return array<string,string>
	 */
	public static function types() {
		return array(
			'member' => __( 'Member', 'lccl-de' ),
			'family' => __( 'Family Membership', 'lccl-de' ),
		);
	}

	/**
	 * Family member count options, including the main member.
	 *
	 * @return array<string,string>
	 */
	public static function family_counts() {
		return array(
			'2' => __( '2 Family Members', 'lccl-de' ),
			'3' => __( '3 Family Members', 'lccl-de' ),
			'4' => __( '4 Family Members', 'lccl-de' ),
			'5' => __( '5 Family Members', 'lccl-de' ),
		);
	}

	/**
	 * Format a rupee amount with two decimals.
	 *
	 * @param float $amount Amount in LKR.
	 * @return string
	 */
	public static function format_lkr( $amount ) {
		return 'LKR ' . number_format( (float) $amount, 2, '.', ',' );
	}

	/**
	 * Format the displayed exchange rate.
	 *
	 * @param float $rate USD to LKR rate.
	 * @return string
	 */
	public static function format_rate( $rate ) {
		return number_format( (float) $rate, 2, '.', ',' );
	}

	/**
	 * Fee breakdown for the current membership type.
	 *
	 * @param string $type  member|family or empty.
	 * @param int    $count Family members including the main member.
	 * @return array<string,mixed>
	 */
	public static function breakdown( $type = '', $count = 2 ) {
		$is_family          = 'family' === $type;
		$members            = $is_family ? max( 2, (int) $count ) : 1;
		$additional         = $is_family ? max( 0, $members - 1 ) : 0;
		$international_main = self::PRINCIPAL_USD * self::RATE;
		$family_fee         = $additional * self::FAMILY_USD * self::RATE;
		$district           = $members * self::DISTRICT_LKR;
		$club               = self::CLUB_LKR;
		$total              = $international_main + $family_fee + $district + $club;

		return array(
			'is_family'          => $is_family,
			'members'            => $members,
			'additional'         => $additional,
			'rate'               => self::RATE,
			'international_main' => $international_main,
			'family_fee'         => $family_fee,
			'district'           => $district,
			'club'               => $club,
			'total'              => $total,
		);
	}

	// ------------------------------------------------------------------
	// Utilities
	// ------------------------------------------------------------------

	/**
	 * Generate a unique merchant order reference.
	 *
	 * Format: LCCL-MF-YYYYMMDD-XXXXXXXX (8 random uppercase alphanumeric chars)
	 *
	 * @return string
	 */
	public static function generate_order_ref() {
		return 'LCCL-MF-' . gmdate( 'Ymd' ) . '-' . strtoupper( wp_generate_password( 8, false ) );
	}

	/**
	 * Return the client IP address, favouring X-Forwarded-For if behind a proxy.
	 *
	 * @return string
	 */
	private static function client_ip() {
		$candidates = array();

		// Cloudflare support.
		if ( ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
			$candidates[] = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] ) );
		}

		// Standard proxy header.
		if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$parts        = explode( ',', sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) );
			$candidates[] = trim( $parts[0] );
		}

		// Standard direct IP.
		if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$candidates[] = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}

		foreach ( $candidates as $candidate ) {
			if ( filter_var( $candidate, FILTER_VALIDATE_IP ) ) {
				return $candidate;
			}
		}

		return '0.0.0.0';
	}

	// ------------------------------------------------------------------
	// WPBakery mapping
	// ------------------------------------------------------------------

	/**
	 * Expose the form as a WPBakery element.
	 */
	public static function map() {
		if ( ! function_exists( 'vc_map' ) ) {
			return;
		}

		vc_map(
			array(
				'name'        => __( 'LCCL Membership Fee', 'lccl-de' ),
				'base'        => self::SHORTCODE,
				'category'    => __( 'LCCL', 'lccl-de' ),
				'description' => __( 'Annual membership fee payment form (CBC Paycenter Web 4.0).', 'lccl-de' ),
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
