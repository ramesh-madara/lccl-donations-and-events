<?php
/**
 * Annual membership fee payment form with MPGS Hosted Checkout integration.
 *
 * Flow:
 *  1. Member fills form and submits via POST → handle_submit()
 *  2. handle_submit() validates, inserts pending row, calls MPGS INITIATE_CHECKOUT
 *  3. Browser is redirected to the same page URL with ?lccl_mpgs_session=… query args
 *  4. render() detects query args → loads templates/membership-receipt.php which fires
 *     Checkout.configure() + Checkout.showPaymentPage()
 *  5. MPGS redirects browser back with ?lccl_mpgs_return=1&resultIndicator=…
 *  6. render() calls handle_callback() which verifies the indicator, retrieves the
 *     order server-side, updates the DB row, and shows templates/membership-result.php
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
	 * Query arg that signals we are on the receipt intermediate page.
	 */
	const QA_SESSION = 'lccl_mpgs_session';

	/**
	 * Query arg that signals the gateway has returned the browser.
	 */
	const QA_RETURN = 'lccl_mpgs_return';

	/**
	 * Query arg carrying the merchant order reference on the callback.
	 */
	const QA_ORDER_REF = 'lccl_mpgs_ref';

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
	 * Depending on URL query args this renders one of three templates:
	 *   1. templates/membership-receipt.php  – intermediate Hosted Checkout page
	 *   2. templates/membership-result.php   – success / failure result page
	 *   3. templates/membership-form.php     – the default entry form
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
		// Step 3–4: Gateway returned browser → run callback, show result
		// ----------------------------------------------------------------
		if ( ! empty( $_GET[ self::QA_RETURN ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$result = self::handle_callback();

			ob_start();
			include LCCL_DE_PATH . 'templates/membership-result.php';
			return ob_get_clean();
		}

		// ----------------------------------------------------------------
		// Step 2: Show MPGS Hosted Checkout receipt / loading page
		// ----------------------------------------------------------------
		if ( ! empty( $_GET[ self::QA_SESSION ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$session_id = sanitize_text_field( wp_unslash( $_GET[ self::QA_SESSION ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$order_ref  = isset( $_GET[ self::QA_ORDER_REF ] ) ? sanitize_text_field( wp_unslash( $_GET[ self::QA_ORDER_REF ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

			// Enqueue the MPGS checkout.min.js dynamically in the footer.
			$js_url = LCCL_DE_MPGS_Client::checkout_js_url();
			if ( $js_url ) {
				wp_enqueue_script(
					'lccl-mpgs-checkout',
					$js_url,
					array(),
					null, // No version – the URL itself is versioned by MPGS.
					true  // footer
				);
			}

			ob_start();
			include LCCL_DE_PATH . 'templates/membership-receipt.php';
			return ob_get_clean();
		}

		// ----------------------------------------------------------------
		// Step 1: Default – show the entry form
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

		// Re-populate fields and errors after a failed submit that didn't redirect away.
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
	 * Fired on template_redirect. Intercepts form POST and starts the
	 * MPGS session, then redirects to the receipt intermediate page.
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

		self::handle_submit();
	}

	/**
	 * Validate posted fields, insert a pending payment row, call INITIATE_CHECKOUT,
	 * and redirect to the receipt page or back to the form with an error.
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

		if ( ! LCCL_DE_MPGS_Client::is_configured() ) {
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
		// Calculate fee
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
		// Call MPGS INITIATE_CHECKOUT
		// ----------------------------------------------------------------
		$return_url = add_query_arg(
			array(
				self::QA_RETURN    => '1',
				self::QA_ORDER_REF => $order_ref,
			),
			get_permalink()
		);

		$description = sprintf(
			/* translators: 1: first name, 2: last name */
			__( 'LCCL Annual Membership Fee – %1$s %2$s', 'lccl-de' ),
			$first_name,
			$last_name
		);

		$session = LCCL_DE_MPGS_Client::initiate_checkout(
			array(
				'order_ref'   => $order_ref,
				'amount'      => $amount,
				'currency'    => 'LKR',
				'return_url'  => $return_url,
				'description' => $description,
			)
		);

		if ( is_wp_error( $session ) ) {
			// Mark row as failed so it can be audited.
			$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
				$table,
				array( 'status' => 'failed', 'gateway_response' => $session->get_error_message() ),
				array( 'order_ref' => $order_ref ),
				array( '%s', '%s' ),
				array( '%s' )
			);

			wp_safe_redirect(
				add_query_arg(
					array( 'lccl_mf_error' => rawurlencode( $session->get_error_message() ) ),
					wp_get_referer() ?: get_permalink()
				)
			);
			exit;
		}

		// ----------------------------------------------------------------
		// Store session metadata for callback verification
		// ----------------------------------------------------------------
		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$table,
			array(
				'session_id'        => $session['session_id'],
				'success_indicator' => $session['success_indicator'],
			),
			array( 'order_ref' => $order_ref ),
			array( '%s', '%s' ),
			array( '%s' )
		);

		// ----------------------------------------------------------------
		// Redirect to intermediate receipt page
		// ----------------------------------------------------------------
		wp_safe_redirect(
			add_query_arg(
				array(
					self::QA_SESSION   => rawurlencode( $session['session_id'] ),
					self::QA_ORDER_REF => rawurlencode( $order_ref ),
				),
				get_permalink()
			)
		);
		exit;
	}

	// ------------------------------------------------------------------
	// Callback handler (step 3-5 of MPGS flow)
	// ------------------------------------------------------------------

	/**
	 * Verify the resultIndicator, retrieve the order from MPGS, and update the DB row.
	 *
	 * Called from render() when ?lccl_mpgs_return=1 is in the URL.
	 *
	 * @return array{
	 *     status: string,        'paid'|'failed'|'error'
	 *     order_ref: string,
	 *     receipt: string,
	 *     amount: float,
	 *     member_name: string,
	 *     error_message: string,
	 * }
	 */
	public static function handle_callback() {
		$order_ref = isset( $_GET[ self::QA_ORDER_REF ] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			? sanitize_text_field( wp_unslash( $_GET[ self::QA_ORDER_REF ] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			: '';

		$result_indicator = isset( $_GET['resultIndicator'] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			? sanitize_text_field( wp_unslash( $_GET['resultIndicator'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			: '';

		$error_result = array(
			'status'       => 'error',
			'order_ref'    => $order_ref,
			'receipt'      => '',
			'amount'       => 0,
			'member_name'  => '',
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

		// If already processed (user refreshed), just return current state.
		if ( in_array( $row['status'], array( 'paid', 'failed', 'cancelled' ), true ) ) {
			$base_result['status']  = $row['status'];
			$base_result['receipt'] = (string) $row['gateway_receipt'];
			return $base_result;
		}

		// ----------------------------------------------------------------
		// Verify resultIndicator against stored successIndicator
		// ----------------------------------------------------------------
		if ( '' === $result_indicator ) {
			// No indicator = user cancelled on the gateway page.
			$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
				$table,
				array( 'status' => 'cancelled' ),
				array( 'order_ref' => $order_ref ),
				array( '%s' ),
				array( '%s' )
			);
			$base_result['status'] = 'cancelled';
			return $base_result;
		}

		if ( ! LCCL_DE_MPGS_Client::verify_result_indicator( $result_indicator, (string) $row['success_indicator'] ) ) {
			$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
				$table,
				array( 'status' => 'failed', 'gateway_response' => 'resultIndicator mismatch' ),
				array( 'order_ref' => $order_ref ),
				array( '%s', '%s' ),
				array( '%s' )
			);
			$base_result['error_message'] = __( 'Payment could not be verified. Please contact the club administrator.', 'lccl-de' );
			return $base_result;
		}

		// ----------------------------------------------------------------
		// Server-to-server confirmation: RETRIEVE_ORDER
		// ----------------------------------------------------------------
		$order_data = LCCL_DE_MPGS_Client::retrieve_order( $order_ref );

		if ( is_wp_error( $order_data ) ) {
			$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
				$table,
				array( 'status' => 'failed', 'gateway_response' => $order_data->get_error_message() ),
				array( 'order_ref' => $order_ref ),
				array( '%s', '%s' ),
				array( '%s' )
			);
			$base_result['error_message'] = $order_data->get_error_message();
			return $base_result;
		}

		$receipt = LCCL_DE_MPGS_Client::extract_receipt( $order_data );

		if ( '' === $receipt ) {
			// RETRIEVE_ORDER returned but no successful transaction found.
			$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
				$table,
				array(
					'status'           => 'failed',
					'gateway_response' => wp_json_encode( $order_data ),
				),
				array( 'order_ref' => $order_ref ),
				array( '%s', '%s' ),
				array( '%s' )
			);
			$base_result['error_message'] = __( 'Payment was not completed. Please try again or contact the club administrator.', 'lccl-de' );
			return $base_result;
		}

		// ----------------------------------------------------------------
		// Mark paid!
		// ----------------------------------------------------------------
		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$table,
			array(
				'status'           => 'paid',
				'gateway_receipt'  => $receipt,
				'gateway_response' => wp_json_encode( $order_data ),
				'paid_at'          => current_time( 'mysql' ),
			),
			array( 'order_ref' => $order_ref ),
			array( '%s', '%s', '%s', '%s' ),
			array( '%s' )
		);

		// Send confirmation email to member.
		self::send_payment_confirmation( $row, $receipt );

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
	 * @param string $receipt MPGS gateway receipt ID.
	 */
	private static function send_payment_confirmation( array $row, $receipt ) {
		$to      = sanitize_email( (string) $row['member_email'] );
		$name    = trim( $row['member_first_name'] . ' ' . $row['member_last_name'] );
		$amount  = number_format( (float) $row['amount_lkr'], 2 );

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
				"Payment Reference : %4\$s\n" .
				"Gateway Receipt   : %3\$s\n\n" .
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
	// Fee calculation helpers (unchanged from original)
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
		$ip = '';

		if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$parts = explode( ',', sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) );
			$ip    = trim( $parts[0] );
		}

		if ( '' === $ip && ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}

		return $ip;
	}

	// ------------------------------------------------------------------
	// WPBakery mapping (unchanged)
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
				'description' => __( 'Annual membership fee payment form (MPGS Hosted Checkout).', 'lccl-de' ),
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
