<?php
/**
 * Project sponsorship form with CBC Paycenter Web 4.0 (Bancstac) integration.
 *
 * Uses the Fundraisers (project_1) profile credentials configured in
 * WP Admin -> LCCL Programs -> Payment Gateway -> Fundraisers tab.
 *
 * Flow:
 *  1. Sponsor fills form -> POST -> maybe_handle_submit() -> handle_submit()
 *  2. handle_submit() validates, inserts pending row in lccl_de_sponsorships,
 *     calls PAYMENT_INIT, redirects browser to the Bancstac-hosted payment page.
 *  3. Bancstac redirects browser back to returnUrl with ?reqid=...
 *  4. render() detects QA_RETURN -> handle_callback() calls PAYMENT_COMPLETE,
 *     verifies responseCode / amount / currency / clientRef, updates the DB row,
 *     and displays the Thank You confirmation banner at the top of the form.
 *
 * @package LCCL_Donations_And_Events
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handles the public project sponsorship form rendering and payment gateway workflow.
 */
class LCCL_DE_Sponsorship_Form {

	/**
	 * Shortcode tag.
	 */
	const SHORTCODE = 'lccl_project_sponsorship';

	/**
	 * Nonce action for form submission.
	 */
	const NONCE_ACTION = 'lccl_ps_submit';

	/**
	 * Nonce field name.
	 */
	const NONCE_FIELD = 'lccl_ps_nonce';

	/**
	 * Query arg that signals the gateway has returned the browser.
	 */
	const QA_RETURN = 'lccl_ps_return';

	/**
	 * Query arg carrying the merchant order reference on the callback.
	 */
	const QA_ORDER_REF = 'lccl_ps_ref';

	/**
	 * Query arg that signals the user cancelled payment on the gateway.
	 */
	const QA_CANCEL = 'lccl_ps_cancel';

	/**
	 * Gateway profile key - uses the "Fundraisers / project_1" CBC Paycenter slot.
	 */
	const PROFILE = LCCL_DE_Settings::PROFILE_PROJECT_1;

	/**
	 * Default selected amount.
	 */
	const DEFAULT_AMOUNT = '5000';

	/**
	 * Hook the shortcode into WordPress and WPBakery, and register submit handler.
	 */
	public static function init() {
		add_shortcode( self::SHORTCODE, array( __CLASS__, 'render' ) );
		add_action( 'vc_before_init', array( __CLASS__, 'map' ) );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_handle_submit' ) );
	}

	/**
	 * Default page banner heading.
	 *
	 * @return string
	 */
	public static function default_banner_title() {
		return '';
	}

	/**
	 * Default page banner intro.
	 *
	 * @return string
	 */
	public static function default_banner_intro() {
		return '';
	}

	/**
	 * Default payment form heading.
	 *
	 * @return string
	 */
	public static function default_title() {
		return __( 'Support a Project', 'lccl-de' );
	}

	/**
	 * Default payment form intro.
	 *
	 * @return string
	 */
	public static function default_intro() {
		return __( 'Please provide the information below to support your selected project.', 'lccl-de' );
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
		return __( 'Please enter a valid sponsorship amount of at least 1 LKR.', 'lccl-de' );
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
	 * Message shown when the phone number is missing or invalid.
	 *
	 * @return string
	 */
	public static function phone_error_message() {
		return LCCL_DE_Blood_Donor_Submissions::phone_error_message();
	}

	/**
	 * Sample projects shown in the right-hand panel.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function projects() {
		$configured = LCCL_DE_Settings::get_projects();
		$projects   = array();

		global $wpdb;
		$table = LCCL_DE_Schema::sponsorships_table();

		foreach ( $configured as $id => $p ) {
			if ( empty( $p['enabled'] ) ) {
				continue;
			}

			$raised = 0.0;
			if ( $table ) {
				$raised = (float) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
					$wpdb->prepare(
						"SELECT SUM(amount_lkr) FROM `{$table}` WHERE project = %s AND status = 'paid'",
						$id
					)
				);
			}

			$value = isset( $p['financial_goal'] ) ? (float) $p['financial_goal'] : 0.0;

			$status = 'ongoing';
			$label  = __( 'Ongoing', 'lccl-de' );
			$title  = sanitize_text_field( $p['title'] );

			if ( $value > 0 && $raised >= $value ) {
				$status = 'completed';
				$label  = __( 'Completed', 'lccl-de' );
				$title .= ' ' . __( '(Completed)', 'lccl-de' );
			} elseif ( 0.0 === $raised ) {
				$status = 'upcoming';
				$label  = __( 'Upcoming', 'lccl-de' );
			}

			$projects[ $id ] = array(
				'status'  => $status,
				'label'   => $label,
				'title'   => $title,
				'summary' => '',
				'value'   => $value,
				'raised'  => $raised,
			);
		}

		// Fallback if no projects are configured or enabled
		if ( empty( $projects ) ) {
			$projects['general'] = array(
				'status'  => 'ongoing',
				'label'   => __( 'Ongoing', 'lccl-de' ),
				'title'   => __( 'General Community Projects', 'lccl-de' ),
				'summary' => '',
				'value'   => 0,
				'raised'  => 0,
			);
		}

		return $projects;
	}

	/**
	 * Format a rupee amount as used on the project cards.
	 *
	 * @param float $amount Amount in LKR.
	 * @return string
	 */
	public static function format_rs( $amount ) {
		return 'Rs. ' . number_format( (float) $amount, 2, '.', ',' );
	}

	/**
	 * Raised percentage for the progress bar.
	 *
	 * @param float $value  Project value.
	 * @param float $raised Amount raised.
	 * @return float
	 */
	public static function progress_percent( $value, $raised ) {
		$value = (float) $value;
		if ( $value <= 0 ) {
			return 0;
		}
		return min( 100, max( 0, round( ( (float) $raised / $value ) * 100, 1 ) ) );
	}

	/**
	 * Display amount with thousands separators and LKR.
	 *
	 * @param string|float $amount Raw amount.
	 * @return string
	 */
	public static function format_amount( $amount, $currency = 'LKR' ) {
		$raw = preg_replace( '/[^\d.]/', '', (string) $amount );
		if ( '' === $raw || ! is_numeric( $raw ) ) {
			return '';
		}
		return number_format( (float) $raw, 2 ) . ' ' . strtoupper( $currency );
	}

	/**
	 * Render the sponsorship form or gateway return state.
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string
	 */
	public static function render( $atts ) {
		$atts = shortcode_atts(
			array(
				'banner_title' => self::default_banner_title(),
				'banner_intro' => self::default_banner_intro(),
				'title'        => self::default_title(),
				'intro'        => self::default_intro(),
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
			|| ! empty( $_GET[ self::QA_CANCEL ] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$payment_success = null;
		$payment_notice  = '';

		if ( $is_return ) {
			$result = self::handle_callback();

			if ( 'paid' === $result['status'] ) {
				$payment_success = $result;
			} elseif ( 'cancelled' === $result['status'] ) {
				$payment_notice = __( 'Project sponsorship was cancelled. You may review your information and try again whenever you wish.', 'lccl-de' );
			} else {
				$payment_notice = ! empty( $result['error_message'] )
					? $result['error_message']
					: __( 'Unable to confirm sponsorship payment. Please contact the club administrator or try again.', 'lccl-de' );
			}
		}

		wp_enqueue_script(
			'lccl-de-sponsorship-form',
			LCCL_DE_URL . 'assets/js/lccl-de-sponsorship-form.js',
			array(),
			LCCL_DE_VERSION,
			true
		);

		$profile_cfg        = LCCL_DE_Settings::get_paycenter( self::PROFILE );
		$currency           = isset( $profile_cfg['currency'] ) && '' !== $profile_cfg['currency'] ? strtoupper( (string) $profile_cfg['currency'] ) : 'LKR';
		$values             = array();
		$errors             = array();
		$gateway_configured = LCCL_DE_Paycenter_Client::is_configured( self::PROFILE );

		if ( ! empty( $_GET['lccl_ps_error'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$errors[] = sanitize_text_field( wp_unslash( $_GET['lccl_ps_error'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		ob_start();
		include LCCL_DE_PATH . 'templates/sponsorship-form.php';
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
		$ip_key = 'lccl_ps_rate_' . md5( self::client_ip() );
		$hits   = (int) get_transient( $ip_key );
		if ( $hits >= 10 ) {
			wp_safe_redirect(
				add_query_arg(
					array( 'lccl_ps_error' => rawurlencode( __( 'Too many payment attempts. Please wait a few minutes before trying again.', 'lccl-de' ) ) ),
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

		$projects     = self::projects();
		$raw_project  = isset( $raw['project'] ) ? sanitize_key( $raw['project'] ) : '';
		$project      = isset( $projects[ $raw_project ] ) ? $raw_project : (string) array_key_first( $projects );
		$project_data = $projects[ $project ];

		$raw_amount = isset( $raw['amount'] ) ? preg_replace( '/[^\d.]/', '', (string) $raw['amount'] ) : '0';
		$amount     = (float) $raw_amount;

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
			$errors[] = __( 'Please enter a valid sponsorship amount of at least 1 LKR.', 'lccl-de' );
		}
		if ( isset( $project_data['status'] ) && 'completed' === $project_data['status'] ) {
			/* translators: %s: project title without the (Completed) suffix if possible */
			$clean_title = str_replace( ' ' . __( '(Completed)', 'lccl-de' ), '', $project_data['title'] );
			$errors[] = sprintf( __( 'The financial goal for "%s" has already been met. Thank you for your interest, but we are no longer accepting donations for this project. Please select another project to support.', 'lccl-de' ), $clean_title );
		}
		if ( ! LCCL_DE_Paycenter_Client::is_configured( self::PROFILE ) ) {
			$errors[] = __( 'Online project sponsorship payments are currently not configured. Please contact the club administrator.', 'lccl-de' );
		}

		if ( $errors ) {
			wp_safe_redirect(
				add_query_arg(
					array( 'lccl_ps_error' => rawurlencode( implode( ' ', $errors ) ) ),
					wp_get_referer() ?: get_permalink()
				)
			);
			exit;
		}

		$order_ref = self::generate_order_ref();

		global $wpdb;
		$table = LCCL_DE_Schema::sponsorships_table();

		$profile_cfg = LCCL_DE_Settings::get_paycenter( self::PROFILE );
		$currency    = isset( $profile_cfg['currency'] ) && '' !== $profile_cfg['currency'] ? strtoupper( (string) $profile_cfg['currency'] ) : 'LKR';

		$inserted = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$table,
			array(
				'order_ref'     => $order_ref,
				'first_name'    => $first_name,
				'last_name'     => $last_name,
				'email'         => $email,
				'phone'         => $phone,
				'project'       => $project,
				'project_label' => (string) $project_data['title'],
				'amount_lkr'    => $amount,
				'currency'      => $currency,
				'message'       => $message,
				'status'        => 'pending',
				'ip_address'    => self::client_ip(),
				'created_at'    => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( ! $inserted ) {
			wp_safe_redirect(
				add_query_arg(
					array( 'lccl_ps_error' => rawurlencode( __( 'Could not save your sponsorship request. Please try again.', 'lccl-de' ) ) ),
					wp_get_referer() ?: get_permalink()
				)
			);
			exit;
		}

		$base_url   = get_permalink() ?: home_url( '/' );
		$return_url = add_query_arg(
			array( self::QA_RETURN => '1', self::QA_ORDER_REF => $order_ref ),
			$base_url
		);
		$cancel_url = add_query_arg(
			array( self::QA_CANCEL => '1', self::QA_ORDER_REF => $order_ref ),
			$base_url
		);

		$comment = sprintf(
			/* translators: 1: project title, 2: first name, 3: last name */
			__( 'LCCL Sponsorship - %1$s - %2$s %3$s', 'lccl-de' ),
			$project_data['title'],
			$first_name,
			$last_name
		);

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
			error_log( sprintf( '[LCCL Paycenter Sponsorship Error] Order %s failed PAYMENT_INIT: %s', $order_ref, $init_result->get_error_message() ) );

			$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
				$table,
				array( 'status' => 'failed', 'gateway_response' => $init_result->get_error_message() ),
				array( 'order_ref' => $order_ref ),
				array( '%s', '%s' ),
				array( '%s' )
			);

			if ( current_user_can( 'manage_options' ) ) {
				$user_msg = sprintf( __( 'Gateway Error: %s', 'lccl-de' ), $init_result->get_error_message() );
			} elseif ( 'lccl_pc_disabled' === $init_result->get_error_code() ) {
				$user_msg = __( 'Project sponsorship payments are currently unavailable. Please contact the club administrator.', 'lccl-de' );
			} else {
				$user_msg = __( 'Unable to connect to the payment gateway. Please try again or contact the club administrator.', 'lccl-de' );
			}

			wp_safe_redirect( add_query_arg( array( 'lccl_ps_error' => rawurlencode( $user_msg ) ), wp_get_referer() ?: get_permalink() ) );
			exit;
		}

		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$table,
			array( 'session_id' => $init_result['reqid'] ),
			array( 'order_ref' => $order_ref ),
			array( '%s' ),
			array( '%s' )
		);

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
			'sponsor_name'  => '',
			'project_label' => '',
			'error_message' => '',
		);

		if ( '' === $order_ref ) {
			$error_result['error_message'] = __( 'Payment reference missing. Please contact the club administrator.', 'lccl-de' );
			return $error_result;
		}

		global $wpdb;
		$table = LCCL_DE_Schema::sponsorships_table();

		$row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare( "SELECT * FROM `{$table}` WHERE order_ref = %s LIMIT 1", $order_ref ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);

		if ( ! $row ) {
			$error_result['error_message'] = __( 'Sponsorship payment record not found. Please contact the club administrator.', 'lccl-de' );
			return $error_result;
		}

		$sponsor_name  = trim( $row['first_name'] . ' ' . $row['last_name'] );
		$project_label = ! empty( $row['project_label'] ) ? $row['project_label'] : $row['project'];

		$base_result = array(
			'status'        => 'failed',
			'order_ref'     => $order_ref,
			'receipt'       => '',
			'amount'        => (float) $row['amount_lkr'],
			'sponsor_name'  => $sponsor_name,
			'project_label' => $project_label,
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
				? __( 'You cancelled the sponsorship payment. No charge has been made.', 'lccl-de' )
				: __( 'Your sponsorship payment could not be completed. No charge has been made.', 'lccl-de' );
			return $base_result;
		}

		if ( $is_cancelled || '' === $reqid ) {
			if ( 'pending' === $row['status'] ) {
				$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
					$table,
					array( 'status' => 'cancelled', 'gateway_response' => 'User cancelled sponsorship payment' ),
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
			error_log( sprintf( '[LCCL Paycenter Security Warning] Unauthorized callback attempt on sponsorship order %s with invalid reqid %s', $order_ref, $reqid ) );
			$base_result['error_message'] = __( 'Payment verification failed. Please contact the club administrator.', 'lccl-de' );
			return $base_result;
		}

		$response_data = LCCL_DE_Paycenter_Client::payment_complete( $reqid, self::PROFILE );

		if ( is_wp_error( $response_data ) ) {
			error_log( sprintf( '[LCCL Paycenter Sponsorship Error] Order %s failed PAYMENT_COMPLETE: %s', $order_ref, $response_data->get_error_message() ) );
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
				array( 'status' => 'failed', 'gateway_response' => wp_json_encode( $response_data ) ),
				array( 'order_ref' => $order_ref ),
				array( '%s', '%s' ),
				array( '%s' )
			);
			$base_result['error_message'] = __( 'Your sponsorship payment was declined. No charge has been made. Please try again or use a different card.', 'lccl-de' );
			return $base_result;
		}

		$profile_cfg       = LCCL_DE_Settings::get_paycenter( self::PROFILE );
		$expected_currency = isset( $profile_cfg['currency'] ) && '' !== $profile_cfg['currency'] ? $profile_cfg['currency'] : 'LKR';

		if ( ! LCCL_DE_Paycenter_Client::verify_amount( $response_data, (float) $row['amount_lkr'], $expected_currency ) ) {
			$mismatch_msg = 'Amount/currency mismatch in sponsorship.';
			error_log( '[LCCL Paycenter] ' . $mismatch_msg . ' (Order: ' . $order_ref . ')' );
			$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
				$table,
				array( 'status' => 'failed', 'gateway_response' => $mismatch_msg ),
				array( 'order_ref' => $order_ref ),
				array( '%s', '%s' ),
				array( '%s' )
			);
			$base_result['error_message'] = __( 'Sponsorship payment amount mismatch. Please contact the club administrator.', 'lccl-de' );
			return $base_result;
		}

		if ( ! LCCL_DE_Paycenter_Client::verify_client_ref( $response_data, $order_ref ) ) {
			error_log( sprintf( '[LCCL Paycenter] clientRef mismatch for sponsorship order %s', $order_ref ) );
			$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
				$table,
				array( 'status' => 'failed', 'gateway_response' => 'clientRef mismatch' ),
				array( 'order_ref' => $order_ref ),
				array( '%s', '%s' ),
				array( '%s' )
			);
			$base_result['error_message'] = __( 'Payment reference mismatch. Please contact the club administrator.', 'lccl-de' );
			return $base_result;
		}

		$receipt          = LCCL_DE_Paycenter_Client::extract_receipt( $response_data );
		$gateway_currency = isset( $response_data['transactionAmount']['currency'] ) ? strtoupper( (string) $response_data['transactionAmount']['currency'] ) : $expected_currency;

		$updated = $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"UPDATE `{$table}`
				 SET status = 'paid',
				     currency = %s,
				     session_id = %s,
				     gateway_receipt = %s,
				     gateway_response = %s,
				     paid_at = %s
				 WHERE order_ref = %s AND status = 'pending'",
				$gateway_currency,
				$reqid,
				$receipt,
				wp_json_encode( $response_data ),
				current_time( 'mysql' ),
				$order_ref
			)
		);

		if ( $updated > 0 ) {
			$row['currency'] = $gateway_currency;
			self::send_sponsorship_confirmation( $row, $receipt );
		}

		return array(
			'status'        => 'paid',
			'order_ref'     => $order_ref,
			'receipt'       => $receipt,
			'amount'        => (float) $row['amount_lkr'],
			'currency'      => $gateway_currency,
			'sponsor_name'  => $sponsor_name,
			'project_label' => $project_label,
			'error_message' => '',
		);
	}

	// ------------------------------------------------------------------
	// Notifications
	// ------------------------------------------------------------------

	/**
	 * Dispatch all confirmation messages.
	 *
	 * @param array  $row     Sponsorship record.
	 * @param string $receipt Bank receipt reference.
	 */
	private static function send_sponsorship_confirmation( array $row, $receipt ) {
		self::send_sponsorship_email( $row, $receipt );
		self::send_sponsorship_sms( $row, $receipt );
		self::send_admin_sponsorship_alert( $row, $receipt );
	}

	/**
	 * Send a thank-you SMS to the sponsor.
	 *
	 * @param array  $row     Sponsorship record.
	 * @param string $receipt Bank receipt reference.
	 */
	private static function send_sponsorship_sms( array $row, $receipt ) {
		$phone = ! empty( $row['phone'] ) ? (string) $row['phone'] : '';
		if ( '' === trim( $phone ) ) {
			return;
		}

		$first_name    = trim( (string) $row['first_name'] );
		$amount        = number_format( (float) $row['amount_lkr'], 2 );
		$currency      = ! empty( $row['currency'] ) ? strtoupper( (string) $row['currency'] ) : 'LKR';
		$project_label = ! empty( $row['project_label'] ) ? $row['project_label'] : 'our project';
		$ref           = $receipt ?: (string) $row['order_ref'];

		$message = sprintf(
			/* translators: 1: sponsor name, 2: project title, 3: currency, 4: amount formatted, 5: receipt */
			__( 'Dear %1$s, thank you for sponsoring "%2$s" with %3$s %4$s. Lions Club of Colombo LEADS - your generosity transforms lives! Ref: %5$s', 'lccl-de' ),
			$first_name ?: __( 'Sponsor', 'lccl-de' ),
			$project_label,
			$currency,
			$amount,
			$ref
		);

		if ( class_exists( 'LCCL_DE_Notify' ) && method_exists( 'LCCL_DE_Notify', 'send_custom_sms' ) ) {
			LCCL_DE_Notify::send_custom_sms( $phone, $message, isset( $row['id'] ) ? (int) $row['id'] : 0, 'sponsorship' );
		}
	}

	/**
	 * Send a rich HTML thank-you confirmation email to the sponsor.
	 *
	 * @param array  $row     Sponsorship record.
	 * @param string $receipt Bank receipt reference.
	 */
	private static function send_sponsorship_email( array $row, $receipt ) {
		$to = sanitize_email( (string) $row['email'] );
		if ( ! is_email( $to ) ) {
			return;
		}

		$first_name    = trim( (string) $row['first_name'] );
		$last_name     = trim( (string) $row['last_name'] );
		$name          = trim( $first_name . ' ' . $last_name );
		if ( '' === $name ) {
			$name = __( 'Generous Sponsor', 'lccl-de' );
		}

		$amount_raw    = (float) $row['amount_lkr'];
		$amount        = number_format( $amount_raw, 2 );
		$currency      = ! empty( $row['currency'] ) ? strtoupper( (string) $row['currency'] ) : 'LKR';
		$ref           = $receipt ?: (string) $row['order_ref'];
		$order_ref     = (string) $row['order_ref'];
		$project_label = ! empty( $row['project_label'] ) ? $row['project_label'] : $row['project'];

		$date_raw = ! empty( $row['paid_at'] ) ? $row['paid_at'] : current_time( 'mysql' );
		$date_fmt = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
		$date_str = mysql2date( $date_fmt, $date_raw );

		$message_row = '';
		if ( ! empty( $row['message'] ) ) {
			$message_row = '<p style="margin:0 0 4px;color:#666666;font-size:13px;">' . esc_html__( 'Your Note / Message', 'lccl-de' ) . '</p>'
				. '<p style="margin:0 0 14px;color:#222222;font-size:14px;font-style:italic;background-color:#ffffff;padding:8px 12px;border-radius:4px;border:1px solid #e0e0e0;">'
				. esc_html( $row['message'] ) . '</p>';
		}

		$subject = sprintf(
			/* translators: 1: site name, 2: project title, 3: currency, 4: amount formatted */
			__( '[%1$s] Thank You for Sponsoring "%2$s" - %3$s %4$s!', 'lccl-de' ),
			get_bloginfo( 'name' ),
			$project_label,
			$currency,
			$amount
		);

		$html = '<!DOCTYPE html><html><body style="margin:0;padding:0;background-color:#F3F3F3;font-family:Arial,Helvetica,sans-serif;">'
			. '<div style="max-width:600px;margin:0 auto;background-color:#F3F3F3;padding:28px 20px;">'
			. '<img src="https://registration.colomboleads.org/lccclLOGO.png" alt="Lions Club of Colombo LEADS" width="156" style="display:block;width:156px;max-width:156px;height:auto;margin:0 0 22px;border:0;">'
			. '<div style="background-color:#FFFFFF;border-radius:8px;padding:32px 28px;box-shadow:0 2px 8px rgba(0,0,0,0.06);">'
			. '<h1 style="margin:0 0 18px;padding:0 0 12px;border-bottom:2px solid #f8e4a0;color:#222222;font-size:20px;font-weight:700;">'
			. esc_html__( 'PROJECT SPONSORSHIP RECEIVED WITH THANKS', 'lccl-de' ) . '</h1>'
			. '<p style="margin:0 0 16px;color:#444444;font-size:15px;line-height:1.6;">'
			. sprintf( esc_html__( 'Dear %s,', 'lccl-de' ), '<strong>' . esc_html( $name ) . '</strong>' ) . '</p>'
			. '<p style="margin:0 0 18px;color:#444444;font-size:15px;line-height:1.6;">'
			. sprintf(
				/* translators: 1: club name, 2: project label, 3: currency, 4: amount formatted */
				esc_html__( 'On behalf of the %1$s, we extend our heartfelt gratitude for your generous sponsorship of the "%2$s" project with %3$s %4$s.', 'lccl-de' ),
				'<strong>' . esc_html__( 'Lions Club of Colombo LEADS', 'lccl-de' ) . '</strong>',
				esc_html( $project_label ),
				esc_html( $currency ),
				'<strong style="color:#0073aa;font-size:16px;">' . esc_html( $amount ) . '</strong>'
			) . '</p>'
			. '<p style="margin:0 0 24px;color:#555555;font-size:14px;line-height:1.6;">'
			. esc_html__( 'Your generous support directly funds our community service projects and creates a real, lasting difference in the lives of those in need. Thank you for standing with us!', 'lccl-de' ) . '</p>'
			. '<div style="background-color:#f9f9f9;border-left:4px solid #0073aa;border-radius:4px;padding:18px 20px;margin:0 0 24px;">'
			. '<h2 style="margin:0 0 14px;color:#333333;font-size:13px;font-weight:700;letter-spacing:0.06em;text-transform:uppercase;">'
			. esc_html__( 'SPONSORSHIP RECEIPT DETAILS', 'lccl-de' ) . '</h2>'
			. '<p style="margin:0 0 4px;color:#666666;font-size:13px;">' . esc_html__( 'Sponsor Name', 'lccl-de' ) . '</p>'
			. '<p style="margin:0 0 12px;color:#222222;font-size:15px;font-weight:700;">' . esc_html( $name ) . '</p>'
			. '<p style="margin:0 0 4px;color:#666666;font-size:13px;">' . esc_html__( 'Project Sponsored', 'lccl-de' ) . '</p>'
			. '<p style="margin:0 0 12px;color:#222222;font-size:15px;font-weight:700;">' . esc_html( $project_label ) . '</p>'
			. '<p style="margin:0 0 4px;color:#666666;font-size:13px;">' . esc_html__( 'Amount Sponsored', 'lccl-de' ) . '</p>'
			. '<p style="margin:0 0 12px;color:#0073aa;font-size:18px;font-weight:800;">' . esc_html( $currency . ' ' . $amount ) . '</p>'
			. '<p style="margin:0 0 4px;color:#666666;font-size:13px;">' . esc_html__( 'Receipt / Bank Reference', 'lccl-de' ) . '</p>'
			. '<p style="margin:0 0 12px;color:#222222;font-size:14px;font-family:monospace;font-weight:700;">' . esc_html( $ref ) . '</p>'
			. '<p style="margin:0 0 4px;color:#666666;font-size:13px;">' . esc_html__( 'Order Reference', 'lccl-de' ) . '</p>'
			. '<p style="margin:0 0 12px;color:#222222;font-size:14px;font-family:monospace;">' . esc_html( $order_ref ) . '</p>'
			. '<p style="margin:0 0 4px;color:#666666;font-size:13px;">' . esc_html__( 'Date & Time', 'lccl-de' ) . '</p>'
			. '<p style="margin:0 0 12px;color:#222222;font-size:14px;">' . esc_html( $date_str ) . '</p>'
			. $message_row
			. '<p style="margin:0 0 4px;color:#666666;font-size:13px;">' . esc_html__( 'Payment Gateway', 'lccl-de' ) . '</p>'
			. '<p style="margin:0;color:#222222;font-size:14px;">' . esc_html__( 'Commercial Bank of Ceylon (CBC) Paycenter', 'lccl-de' ) . '</p>'
			. '</div>'
			. '<p style="margin:0 0 24px;color:#666666;font-size:13px;line-height:1.6;">'
			. esc_html__( 'Please keep this email as your official confirmation receipt. If you have any inquiries regarding your sponsorship, please reply directly to this email.', 'lccl-de' ) . '</p>'
			. '<p style="margin:0 0 4px;color:#222222;font-size:14px;font-weight:700;letter-spacing:0.04em;">' . esc_html__( 'LIONS CLUB OF COLOMBO LEADS', 'lccl-de' ) . '</p>'
			. '<p style="margin:0;color:#666666;font-size:13px;line-height:1.5;">'
			. esc_html__( 'Lions International District 306 D6 | Sri Lanka', 'lccl-de' ) . '<br>'
			. '<a href="https://www.colomboleads.org" style="color:#0073aa;text-decoration:none;">www.colomboleads.org</a>'
			. '</p></div></div></body></html>';

		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
			'From: ' . get_bloginfo( 'name' ) . ' <' . get_option( 'admin_email' ) . '>',
		);

		wp_mail( $to, $subject, $html, $headers );
	}

	/**
	 * Send an alert to the club admin when a sponsorship payment succeeds.
	 *
	 * @param array  $row     Sponsorship record.
	 * @param string $receipt Bank receipt reference.
	 */
	private static function send_admin_sponsorship_alert( array $row, $receipt ) {
		$admin_email = get_option( 'admin_email' );
		if ( ! is_email( $admin_email ) ) {
			return;
		}

		$name          = trim( $row['first_name'] . ' ' . $row['last_name'] );
		$amount        = number_format( (float) $row['amount_lkr'], 2 );
		$currency      = ! empty( $row['currency'] ) ? strtoupper( (string) $row['currency'] ) : 'LKR';
		$email         = (string) $row['email'];
		$phone         = (string) $row['phone'];
		$order_ref     = (string) $row['order_ref'];
		$project_label = ! empty( $row['project_label'] ) ? $row['project_label'] : $row['project'];
		$ref           = $receipt ?: $order_ref;

		$subject = sprintf(
			/* translators: 1: currency, 2: amount formatted, 3: sponsor name, 4: project title */
			__( '[Sponsorship Alert] Received %1$s %2$s from %3$s for "%4$s"', 'lccl-de' ),
			$currency,
			$amount,
			$name,
			$project_label
		);

		$body = sprintf(
			/* translators: 1: name, 2: project, 3: currency, 4: amount, 5: email, 6: phone, 7: receipt, 8: order_ref, 9: date */
			__(
				"A new project sponsorship payment has been received!\n\nSponsor Details:\n----------------------------------------\nName:      %1\$s\nProject:   %2\$s\nAmount:    %3\$s %4\$s\nEmail:     %5\$s\nPhone:     %6\$s\nReceipt:   %7\$s\nOrder Ref: %8\$s\nDate/Time: %9\$s\nGateway:   Commercial Bank of Ceylon (CBC) Paycenter\n----------------------------------------\n\nLog in to WP Admin -> LCCL Programs -> Payment Gateway -> Payment Transactions to view full records.",
				'lccl-de'
			),
			$name,
			$project_label,
			$currency,
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

	// ------------------------------------------------------------------
	// Helpers
	// ------------------------------------------------------------------

	/**
	 * Generate a unique merchant order reference for sponsorships.
	 *
	 * Format: LCCL-SPO-YYYYMMDD-XXXXXXXX
	 *
	 * @return string
	 */
	public static function generate_order_ref() {
		return 'LCCL-SPO-' . gmdate( 'Ymd' ) . '-' . strtoupper( wp_generate_password( 8, false ) );
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
				'name'        => __( 'LCCL Project Sponsorship', 'lccl-de' ),
				'base'        => self::SHORTCODE,
				'category'    => __( 'LCCL', 'lccl-de' ),
				'description' => __( 'Project sponsorship form with online card payment via Commercial Bank of Ceylon Paycenter.', 'lccl-de' ),
				'icon'        => 'icon-wpb-ui-separator',
				'params'      => array(
					array(
						'type'        => 'textfield',
						'heading'     => __( 'Banner title', 'lccl-de' ),
						'param_name'  => 'banner_title',
						'value'       => self::default_banner_title(),
						'admin_label' => true,
					),
					array(
						'type'       => 'textarea',
						'heading'    => __( 'Banner intro', 'lccl-de' ),
						'param_name' => 'banner_intro',
						'value'      => self::default_banner_intro(),
					),
					array(
						'type'       => 'textfield',
						'heading'    => __( 'Form title', 'lccl-de' ),
						'param_name' => 'title',
						'value'      => self::default_title(),
					),
					array(
						'type'       => 'textarea',
						'heading'    => __( 'Form intro', 'lccl-de' ),
						'param_name' => 'intro',
						'value'      => self::default_intro(),
					),
				),
			)
		);
	}
}
