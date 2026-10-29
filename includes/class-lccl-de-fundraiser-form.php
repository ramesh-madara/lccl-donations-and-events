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
		add_action( 'wp_ajax_lccl_de_fundraiser_preview_seats', array( __CLASS__, 'ajax_preview_seats' ) );
		add_action( 'wp_ajax_nopriv_lccl_de_fundraiser_preview_seats', array( __CLASS__, 'ajax_preview_seats' ) );
	}

	public static function render( $atts ) {
		wp_enqueue_style( 'lccl-de-blood-donor-form', LCCL_DE_URL . 'assets/css/lccl-de-blood-donor-form.css', array(), filemtime( LCCL_DE_PATH . 'assets/css/lccl-de-blood-donor-form.css' ) );
		wp_enqueue_style( 'lccl-de-fundraiser-form', LCCL_DE_URL . 'assets/css/lccl-de-fundraiser-form.css', array( 'lccl-de-blood-donor-form' ), filemtime( LCCL_DE_PATH . 'assets/css/lccl-de-fundraiser-form.css' ) );
		wp_enqueue_script( 'lccl-de-fundraiser-phone', LCCL_DE_URL . 'assets/js/lccl-de-fundraiser-phone.js', array(), filemtime( LCCL_DE_PATH . 'assets/js/lccl-de-fundraiser-phone.js' ), true );

		// Return callback from PayCenter
		if ( ! empty( $_GET['lccl_fnd_return'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return self::render_payment_callback();
		}

		// Developer Preview Mode (Web Confirmation UI & Email Template Preview)
		// Safe in production (admin-only), or remove this block before production launch
		if ( ! empty( $_GET['lccl_preview'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return self::render_dev_preview();
		}

		// Expire any stale pending holds before rendering
		LCCL_DE_Fundraiser::expire_stale_pending_bookings();

		$output       = '';
		$active_event = null;

		// Cancel notification
		if ( ! empty( $_GET['lccl_fnd_cancel'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$cancel_ref = isset( $_GET['lccl_fnd_ref'] ) ? sanitize_text_field( wp_unslash( $_GET['lccl_fnd_ref'] ) ) : '';
			if ( '' !== $cancel_ref ) {
				LCCL_DE_Fundraiser::cancel_pending_booking( $cancel_ref );
			}
			$output .= '<div class="lccl-fr-card" style="margin:20px 0; border-left:4px solid #d97706; padding:16px 20px; background:#fffbe8; border-radius:3px;">' .
				'<h3 style="margin:0 0 6px 0; color:#d97706; font-size:16px;">' . esc_html__( 'Payment Cancelled', 'lccl-de' ) . '</h3>' .
				'<p style="margin:0; color:#555; font-size:14px;">' . esc_html__( 'Your booking payment transaction was cancelled. No charges were made. You may adjust your selection and try again.', 'lccl-de' ) . '</p>' .
				'</div>';
		}

		// Error notification from redirect
		if ( ! empty( $_GET['lccl_fnd_error'] ) || ! empty( $_GET['lccl_mf_error'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$err_msg = ! empty( $_GET['lccl_fnd_error'] ) ? sanitize_text_field( wp_unslash( $_GET['lccl_fnd_error'] ) ) : sanitize_text_field( wp_unslash( $_GET['lccl_mf_error'] ) );
			$output .= '<div class="lccl-fr-card" style="margin:20px 0; border-left:4px solid #dc2626; padding:16px 20px; background:#fef2f2; border-radius:3px;">' .
				'<h3 style="margin:0 0 6px 0; color:#dc2626; font-size:16px;">' . esc_html__( 'Booking Notice', 'lccl-de' ) . '</h3>' .
				'<p style="margin:0; color:#991b1b; font-size:14px; line-height: 1.5;">' . esc_html( $err_msg ) . '</p>' .
				'</div>';
		}

		$vip_pass       = isset( $_GET['vip_pass'] ) ? sanitize_text_field( wp_unslash( $_GET['vip_pass'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$vip_validation = null;
		if ( ! empty( $vip_pass ) ) {
			$vip_validation = LCCL_DE_Fundraiser::validate_access_token( $vip_pass );
			if ( $vip_validation['valid'] ) {
				$token_ev_id  = (int) $vip_validation['token_data']['event_id'];
				$active_event = LCCL_DE_Fundraiser::get_event( $token_ev_id );
			} else {
				$vip_msg = $vip_validation['message'] ?? __( 'Invalid VIP reservation link.', 'lccl-de' );
				$output .= '<div class="lccl-fr-card" style="margin:20px 0; border-left:4px solid #ef4444; padding:16px 20px; background:#fef2f2; border-radius:3px;">' .
					'<h3 style="margin:0 0 6px 0; color:#dc2626; font-size:16px;">' . esc_html__( 'VIP Reservation Link Notice', 'lccl-de' ) . '</h3>' .
					'<p style="margin:0; color:#991b1b; font-size:14px; line-height: 1.5;">' . esc_html( $vip_msg ) . '</p>' .
					'</div>';
			}
		}

		if ( ! $active_event && ! empty( $_GET['event_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$active_event = LCCL_DE_Fundraiser::get_event( (int) $_GET['event_id'] );
		}
		if ( ! $active_event ) {
			$active_event = LCCL_DE_Fundraiser::get_active_public_event();
		}
		if ( ! $active_event ) {
			global $wpdb;
			$ev_table = LCCL_DE_Fundraiser::events_table();
			// Fallback to the latest active/upcoming event
			$active_event = $wpdb->get_row( "SELECT * FROM `{$ev_table}` WHERE status != 'archived' ORDER BY id ASC LIMIT 1", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}

		ob_start();
		include LCCL_DE_PATH . 'templates/fundraiser-form.php';
		$output .= ob_get_clean();

		return $output;
	}

	public static function maybe_handle_submit() {
		if ( 'POST' !== strtoupper( sanitize_text_field( isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : 'GET' ) ) ) return;
		if ( empty( $_POST[ self::NONCE_FIELD ] ) ) return;
		if ( ! check_admin_referer( self::NONCE_ACTION, self::NONCE_FIELD ) ) return;
		self::handle_submit();
	}

	private static function handle_submit() {
		$raw = wp_unslash( $_POST );

		$event_id        = (int) ( $raw['event_id'] ?? 0 );
		$booking_type    = sanitize_key( $raw['booking_type'] ?? 'individual' );
		$attendee_name   = sanitize_text_field( $raw['attendee_name'] ?? '' );
		$attendee_email  = sanitize_email( $raw['attendee_email'] ?? '' );
		$attendee_phone  = sanitize_text_field( $raw['attendee_phone'] ?? '' );
		$phone_code = sanitize_text_field( $raw['phone_country_code'] ?? '' );
		if ( '' !== $attendee_phone && 0 !== strpos( $attendee_phone, '+' ) && '' !== $phone_code ) {
			$clean_digits = ltrim( preg_replace( '/[^\d]/', '', $attendee_phone ), '0' );
			$attendee_phone = $phone_code . ' ' . ( $clean_digits ?: $attendee_phone );
		}
		$attendee_notes  = sanitize_textarea_field( $raw['notes'] ?? '' );
		$qty             = (int) ( $raw['qty'] ?? 1 );
		$selected_tables = isset( $raw['selectedTables'] ) && is_array( $raw['selectedTables'] )
			? array_map( 'intval', $raw['selectedTables'] )
			: array();

		$vip_pass    = isset( $raw['vip_pass'] ) ? sanitize_text_field( $raw['vip_pass'] ) : '';
		$vip_details = null;

		$errors = array();
		if ( ! $event_id ) $errors[] = __( 'No active event selected.', 'lccl-de' );
		if ( '' === $attendee_name ) $errors[] = __( 'Name is required.', 'lccl-de' );
		if ( '' === $attendee_email || ! is_email( $attendee_email ) ) $errors[] = __( 'A valid email address is required.', 'lccl-de' );

		if ( ! empty( $vip_pass ) ) {
			$vip_res = LCCL_DE_Fundraiser::validate_access_token( $vip_pass, $event_id );
			if ( ! $vip_res['valid'] ) {
				$errors[] = $vip_res['message'];
			} else {
				$vip_details = $vip_res['item_details'];
			}
		}

		global $wpdb;
		$total_amount = 0.0;

		// Server-side booking guard: Whole Table Mode
		if ( 'table' === $booking_type ) {
			if ( empty( $selected_tables ) && $vip_details && ! empty( $vip_details['table_ids'] ) ) {
				$selected_tables = $vip_details['table_ids'];
			}

			if ( empty( $selected_tables ) ) {
				$errors[] = __( 'Please select at least one table to book.', 'lccl-de' );
			} else {
				$tbl = LCCL_DE_Fundraiser::tables_table();
				$tt  = LCCL_DE_Fundraiser::table_types_table();
				$calc_table_total = 0.0;
				$held_table_ids   = LCCL_DE_Fundraiser::get_held_table_ids( $event_id );
				$held_lookup      = array_flip( $held_table_ids );

				foreach ( $selected_tables as $tid ) {
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$t_row = $wpdb->get_row( $wpdb->prepare(
						"SELECT ta.is_booked, ta.is_blocked, ta.booking_mode, ta.table_label, ty.price
						 FROM `{$tbl}` ta
						 JOIN `{$tt}` ty ON ta.type_id = ty.id
						 WHERE ta.id = %d AND ta.event_id = %d",
						$tid,
						$event_id
					), ARRAY_A );

					$is_vip_unlocked = $vip_details && in_array( $tid, $vip_details['table_ids'], true );

					if ( ( ! $t_row || 'table' !== $t_row['booking_mode'] ) && ! $is_vip_unlocked ) {
						$errors[] = __( 'One or more selected tables are allocated to individual seats and cannot be booked as whole tables.', 'lccl-de' );
						break;
					}
					if ( (int) $t_row['is_blocked'] === 1 && ! $is_vip_unlocked ) {
						$errors[] = sprintf(
							/* translators: %s: table label */
							__( 'Table %s has been reserved or blocked by event administration.', 'lccl-de' ),
							$t_row['table_label']
						);
						break;
					}
					if ( (int) $t_row['is_booked'] === 1 || isset( $held_lookup[ $tid ] ) ) {
						$errors[] = sprintf(
							/* translators: %s: table label */
							__( 'Table %s is currently being booked by another attendee. Please select a different table.', 'lccl-de' ),
							$t_row['table_label']
						);
						break;
					}

					$calc_table_total += (float) $t_row['price'];
				}

				$qty          = count( $selected_tables );
				$total_amount = $calc_table_total;
			}
		}

		// Server-side booking guard: Individual Seat Mode
		$seat_numbers = '';
		if ( 'individual' === $booking_type ) {
			$ev_row = LCCL_DE_Fundraiser::get_event( $event_id );
			$base_ticket_price = $ev_row ? (float) $ev_row['ticket_price'] : 0.0;

			if ( $vip_details && ! empty( $vip_details['seats'] ) ) {
				$vip_seats = $vip_details['seats'];
				if ( $qty < 1 ) {
					$qty = count( $vip_seats );
				}
				if ( $qty > count( $vip_seats ) ) {
					$errors[] = sprintf(
						/* translators: %d: count */
						__( 'This VIP booking link reserves a maximum of %d seat(s).', 'lccl-de' ),
						count( $vip_seats )
					);
				} else {
					$assigned_seats  = array_slice( $vip_seats, 0, $qty );
					$seat_numbers    = implode( ',', $assigned_seats );
					$selected_tables = array();
					$total_amount    = $qty * $base_ticket_price;
				}
			} else {
				if ( $qty < 1 ) {
					$errors[] = __( 'Quantity of seats must be at least 1.', 'lccl-de' );
				} else {
					$allocation = LCCL_DE_Fundraiser::allocate_seats_for_party( $event_id, $qty );
					if ( empty( $allocation['seat_numbers'] ) ) {
						$avail_count = count( LCCL_DE_Fundraiser::get_available_seat_numbers( $event_id ) );
						if ( $avail_count < $qty ) {
							$errors[] = sprintf(
								/* translators: %d: count */
								__( 'Only %d individual seat(s) currently remain available for this event.', 'lccl-de' ),
								$avail_count
							);
						} else {
							$errors[] = __( 'Unable to allocate seats for this party size at this time. Please try a smaller party size or contact event administration.', 'lccl-de' );
						}
					} else {
						$seat_numbers    = implode( ',', $allocation['seat_numbers'] );
						$selected_tables = $allocation['table_ids'];
						$total_amount    = $qty * $base_ticket_price;
					}
				}
			}
		}

		if ( $total_amount <= 0 && empty( $errors ) ) {
			$errors[] = __( 'Total amount must be greater than zero.', 'lccl-de' );
		}

		if ( $errors ) {
			wp_safe_redirect( add_query_arg( array( 'lccl_fnd_error' => rawurlencode( implode( ' ', $errors ) ) ), wp_get_referer() ?: get_permalink() ) );
			exit;
		}

		$order_ref   = 'FND-' . strtoupper( substr( uniqid(), -6 ) );
		$table       = LCCL_DE_Fundraiser::bookings_table();
		$profile_cfg = LCCL_DE_Settings::get_paycenter( self::PROFILE );
		$currency    = isset( $profile_cfg['currency'] ) && '' !== $profile_cfg['currency'] ? strtoupper( (string) $profile_cfg['currency'] ) : 'LKR';

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
				'notes'          => $attendee_notes,
				'access_token'   => ! empty( $vip_pass ) ? $vip_pass : null,
				'amount_lkr'     => $total_amount,
				'currency'       => $currency,
				'status'         => 'pending',
				'ip_address'     => self::client_ip(),
				'created_at'     => current_time( 'mysql' ),
			),
			array( '%s', '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%f', '%s', '%s', '%s', '%s' )
		);

		if ( ! $inserted ) {
			wp_die( esc_html__( 'Failed to record booking initialization.', 'lccl-de' ) );
		}

		// Also record in payments table for audit and unified reporting
		$p_table = LCCL_DE_Schema::payments_table();
		$wpdb->insert( $p_table, array(
			'order_ref'          => $order_ref,
			'member_first_name'  => $attendee_name,
			'member_last_name'   => '',
			'member_email'       => $attendee_email,
			'member_phone'       => $attendee_phone,
			'membership_type'    => 'fundraiser',
			'amount_lkr'         => $total_amount,
			'currency'           => $currency,
			'status'             => 'pending',
			'created_at'         => current_time( 'mysql' ),
		) );

		$base_url   = get_permalink() ?: home_url( '/' );
		$return_url = add_query_arg( array( 'lccl_fnd_return' => '1', 'lccl_fnd_ref' => $order_ref ), $base_url );
		$cancel_url = add_query_arg( array( 'lccl_fnd_cancel' => '1', 'lccl_fnd_ref' => $order_ref ), $base_url );

		$init_result = LCCL_DE_Paycenter_Client::payment_init(
			array(
				'profile'    => self::PROFILE,
				'order_ref'  => $order_ref,
				'amount'     => $total_amount,
				'return_url' => $return_url,
				'cancel_url' => $cancel_url,
				'comment'    => 'Fundraiser Booking: ' . $order_ref,
			)
		);

		if ( is_wp_error( $init_result ) ) {
			wp_die( esc_html( $init_result->get_error_message() ) );
		}
		if ( ! empty( $init_result['payment_page_url'] ) ) {
			wp_redirect( $init_result['payment_page_url'] );
			exit;
		}
		wp_die( esc_html__( 'Invalid response from payment gateway.', 'lccl-de' ) );
	}

	/**
	 * Callback handler when returning from PayCenter gateway.
	 */
	public static function render_payment_callback() {
		global $wpdb;

		$order_ref = '';
		if ( ! empty( $_GET['lccl_fnd_ref'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$order_ref = sanitize_text_field( wp_unslash( $_GET['lccl_fnd_ref'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		} elseif ( ! empty( $_GET['order_ref'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$order_ref = sanitize_text_field( wp_unslash( $_GET['order_ref'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		$reqid = '';
		foreach ( array( 'reqid', 'ReqID', 'reqId', 'REQID' ) as $key ) {
			if ( ! empty( $_GET[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$reqid = sanitize_text_field( wp_unslash( $_GET[ $key ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				break;
			}
		}

		$bk_table = LCCL_DE_Fundraiser::bookings_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$booking = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM `{$bk_table}` WHERE order_ref = %s",
			$order_ref
		), ARRAY_A );

		if ( ! $booking ) {
			return '<div class="lccl-fr-card" style="margin:30px auto; max-width:600px; padding:24px; text-align:center;">' .
				'<h2 style="color:#dc2626;">' . esc_html__( 'Booking Not Found', 'lccl-de' ) . '</h2>' .
				'<p>' . esc_html__( 'We could not locate this booking record. Please contact the administrator.', 'lccl-de' ) . '</p>' .
				'</div>';
		}

		$event = LCCL_DE_Fundraiser::get_event( $booking['event_id'] );
		$receipt = $booking['gateway_receipt'] ?? '';

		// If pending (or pending that expired during checkout) and we have reqid, call PAYMENT_COMPLETE
		if ( ( 'pending' === $booking['status'] || 'cancelled' === $booking['status'] ) && '' !== $reqid ) {
			$response_data = LCCL_DE_Paycenter_Client::payment_complete( $reqid, self::PROFILE );

			if ( is_wp_error( $response_data ) ) {
				return '<div class="lccl-fr-card" style="margin:30px auto; max-width:600px; padding:24px; border-left:4px solid #dc2626;">' .
					'<h2 style="color:#dc2626;">' . esc_html__( 'Payment Verification Notice', 'lccl-de' ) . '</h2>' .
					'<p>' . esc_html__( 'Your payment is being processed. Please check your booking status before trying again.', 'lccl-de' ) . '</p>' .
					'<p style="font-size:13px; color:#666;">' . esc_html__( 'Booking Reference: ', 'lccl-de' ) . '<strong>' . esc_html( $order_ref ) . '</strong></p>' .
					'</div>';
			}

			if ( ! LCCL_DE_Paycenter_Client::is_approved( $response_data ) ) {
				$resp_code = isset( $response_data['responseCode'] ) ? (string) $response_data['responseCode'] : '';
				$resp_text = isset( $response_data['responseText'] ) ? (string) $response_data['responseText'] : 'Declined';
				error_log( sprintf( '[LCCL Paycenter Fundraiser] Booking %s payment declined. Code: %s, Text: %s', $order_ref, $resp_code, $resp_text ) );

				$wpdb->update(
					$bk_table,
					array(
						'status'           => 'failed',
						'session_id'       => $reqid,
						'gateway_response' => wp_json_encode( $response_data ),
					),
					array( 'id' => $booking['id'] )
				);

				$wpdb->update(
					LCCL_DE_Schema::payments_table(),
					array(
						'status'           => 'failed',
						'gateway_response' => wp_json_encode( $response_data ),
					),
					array( 'order_ref' => $order_ref )
				);

				return '<div class="lccl-fr-card" style="margin:30px auto; max-width:600px; padding:24px; border-left:4px solid #dc2626; background:#fef2f2;">' .
					'<h2 style="color:#dc2626; margin-top:0;">' . esc_html__( 'Payment Unsuccessful', 'lccl-de' ) . '</h2>' .
					'<p>' . esc_html__( 'Your payment could not be completed. Please try again or use another payment method.', 'lccl-de' ) . '</p>' .
					'<p style="font-size:13px; color:#666;">' . esc_html__( 'Booking Reference: ', 'lccl-de' ) . '<strong>' . esc_html( $order_ref ) . '</strong></p>' .
					'<div style="margin-top:20px;"><a href="' . esc_url( remove_query_arg( array( 'lccl_fnd_return', 'lccl_fnd_ref', 'reqid', 'ReqID', 'clientRef' ) ) ) . '" class="lccl-fr-button" style="display:inline-block; text-decoration:none; padding:10px 20px; background:#1e293b; color:#fff; border-radius:4px; font-weight:600;">' . esc_html__( 'Return to Booking Form', 'lccl-de' ) . '</a></div>' .
					'</div>';
			}

			$receipt = LCCL_DE_Paycenter_Client::extract_receipt( $response_data ) ?: $order_ref;

			// Mark booking as paid
			$wpdb->update(
				$bk_table,
				array(
					'status'           => 'paid',
					'session_id'       => $reqid,
					'gateway_receipt'  => $receipt,
					'gateway_response' => wp_json_encode( $response_data ),
					'paid_at'          => current_time( 'mysql' ),
				),
				array( 'id' => $booking['id'] )
			);

			// Also mark payment row as paid
			$wpdb->update(
				LCCL_DE_Schema::payments_table(),
				array(
					'status'          => 'paid',
					'gateway_receipt' => $receipt,
				),
				array( 'order_ref' => $order_ref )
			);

			// Mark table pool tables as booked if table booking
			if ( 'table' === $booking['booking_type'] && ! empty( $booking['table_ids'] ) ) {
				$tbl = LCCL_DE_Fundraiser::tables_table();
				$tids = array_map( 'intval', explode( ',', $booking['table_ids'] ) );
				foreach ( $tids as $tid ) {
					if ( $tid > 0 ) {
						$wpdb->update( $tbl, array( 'is_booked' => 1, 'is_blocked' => 0, 'block_group_id' => null, 'block_note' => null ), array( 'id' => $tid ) );
					}
				}
			}

			// If VIP access token was used, redeem token and clear any seat blocks
			if ( ! empty( $booking['access_token'] ) ) {
				LCCL_DE_Fundraiser::redeem_access_token( $booking['access_token'], $booking['id'] );

				if ( ! empty( $booking['seat_numbers'] ) ) {
					$sb = LCCL_DE_Fundraiser::seat_blocks_table();
					$s_nums = array_filter( array_map( 'intval', explode( ',', $booking['seat_numbers'] ) ) );
					if ( ! empty( $s_nums ) ) {
						$placeholders = implode( ',', array_fill( 0, count( $s_nums ), '%d' ) );
						// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
						$wpdb->query( $wpdb->prepare(
							"DELETE FROM `{$sb}` WHERE event_id = %d AND seat_number IN ({$placeholders})",
							$booking['event_id'],
							...$s_nums
						) );
					}
				}
			}

			// Reload fresh booking row with updated status
			$booking = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$bk_table}` WHERE id = %d", $booking['id'] ), ARRAY_A );

			// Dispatch confirmation emails and SMS
			self::send_booking_confirmation( $booking, $receipt, $event );
		}

		// Only show success card after verified payment and booking finalisation
		if ( 'paid' !== $booking['status'] ) {
			if ( 'pending' === $booking['status'] ) {
				return '<div class="lccl-fr-card" style="margin:30px auto; max-width:600px; padding:24px; border-left:4px solid #d97706; background:#fffbe8;">' .
					'<h2 style="color:#d97706; margin-top:0;">' . esc_html__( 'Payment Processing', 'lccl-de' ) . '</h2>' .
					'<p>' . esc_html__( 'Your payment is being processed. Please check your booking status before trying again.', 'lccl-de' ) . '</p>' .
					'<p style="font-size:13px; color:#666;">' . esc_html__( 'Booking Reference: ', 'lccl-de' ) . '<strong>' . esc_html( $order_ref ) . '</strong></p>' .
					'</div>';
			}
			return '<div class="lccl-fr-card" style="margin:30px auto; max-width:600px; padding:24px; border-left:4px solid #dc2626; background:#fef2f2;">' .
				'<h2 style="color:#dc2626; margin-top:0;">' . esc_html__( 'Payment Unsuccessful', 'lccl-de' ) . '</h2>' .
				'<p>' . esc_html__( 'Your payment could not be completed. Please try again or use another payment method.', 'lccl-de' ) . '</p>' .
				'</div>';
		}

		// Render confirmation view
		return self::render_success_view( $booking, $receipt, $event );
	}

	/**
	 * Render payment success card.
	 */
	private static function render_success_view( $booking, $receipt, $event ) {
		$event_name = $event['name'] ?? __( 'Fundraiser Event', 'lccl-de' );
		$venue      = $event['venue'] ?? '';
		$event_date = ! empty( $event['event_date'] ) ? date( 'l, F j, Y', strtotime( $event['event_date'] ) ) : '';
		$event_time = ! empty( $event['event_time'] ) ? date( 'h:i A', strtotime( $event['event_time'] ) ) : '';

		$is_individual = ( 'individual' === $booking['booking_type'] );
		$seat_display = '';
		if ( $is_individual ) {
			$seat_nums = array_filter( array_map( 'trim', explode( ',', $booking['seat_numbers'] ?? '' ) ) );
			$seat_display = LCCL_DE_Fundraiser::format_seats_with_tables( (int) $booking['event_id'], $seat_nums );
			if ( empty( $seat_display ) ) {
				$seat_display = ! empty( $seat_nums ) ? 'Seat #' . implode( ', Seat #', $seat_nums ) : 'Auto-allocated (' . $booking['qty'] . ' seats)';
			}
		} else {
			$table_labels = self::get_table_labels( $booking['table_ids'] ?? '' );
			$seat_display = ! empty( $table_labels ) ? implode( ', ', $table_labels ) : 'Reserved Table(s)';
		}

		ob_start();
		?>
		<div class="lccl-fr-card lccl-fr-conf-card">
			<div class="lccl-fr-conf-header">
				<div class="lccl-fr-conf-icon">✓</div>
				<h2 class="lccl-fr-conf-title"><?php esc_html_e( 'Booking Confirmed!', 'lccl-de' ); ?></h2>
				<p class="lccl-fr-conf-subtitle"><?php esc_html_e( 'Your payment was successful, and your booking is confirmed.', 'lccl-de' ); ?></p>
			</div>

			<div class="lccl-fr-conf-box">
				<div class="lccl-fr-conf-row lccl-fr-conf-row--inline-mobile">
					<span class="lccl-fr-conf-label"><?php esc_html_e( 'Booking Reference', 'lccl-de' ); ?></span>
					<span class="lccl-fr-conf-val lccl-fr-conf-ref"><?php echo esc_html( $booking['order_ref'] ); ?></span>
				</div>
				<div class="lccl-fr-conf-row lccl-fr-conf-row--stack-mobile">
					<span class="lccl-fr-conf-label"><?php esc_html_e( 'Payment Transaction ID', 'lccl-de' ); ?></span>
					<span class="lccl-fr-conf-val lccl-fr-conf-mono"><?php echo esc_html( $receipt ); ?></span>
				</div>
				<div class="lccl-fr-conf-row lccl-fr-conf-row--stack-mobile">
					<span class="lccl-fr-conf-label"><?php esc_html_e( 'Event', 'lccl-de' ); ?></span>
					<span class="lccl-fr-conf-val lccl-fr-conf-event-title"><?php echo esc_html( $event_name ); ?></span>
				</div>
				<?php if ( $event_date ) : ?>
					<div class="lccl-fr-conf-row lccl-fr-conf-row--stack-mobile">
						<span class="lccl-fr-conf-label"><?php esc_html_e( 'Event Date & Time', 'lccl-de' ); ?></span>
						<span class="lccl-fr-conf-val"><?php echo esc_html( $event_date . ( $event_time ? ' at ' . $event_time : '' ) ); ?></span>
					</div>
				<?php endif; ?>
				<?php if ( $venue ) : ?>
					<div class="lccl-fr-conf-row lccl-fr-conf-row--stack-mobile">
						<span class="lccl-fr-conf-label"><?php esc_html_e( 'Venue', 'lccl-de' ); ?></span>
						<span class="lccl-fr-conf-val"><?php echo esc_html( $venue ); ?></span>
					</div>
				<?php endif; ?>
				<div class="lccl-fr-conf-row lccl-fr-conf-row--inline-mobile">
					<span class="lccl-fr-conf-label"><?php esc_html_e( 'Booking Type', 'lccl-de' ); ?></span>
					<span class="lccl-fr-conf-val"><?php echo esc_html( $is_individual ? 'Individual Seats (' . $booking['qty'] . ')' : 'Whole Table Reservation' ); ?></span>
				</div>
				<div class="lccl-fr-conf-row lccl-fr-conf-row--highlight">
					<span class="lccl-fr-conf-label"><?php echo esc_html( $is_individual ? __( 'Assigned Seat(s)', 'lccl-de' ) : __( 'Reserved Table(s)', 'lccl-de' ) ); ?></span>
					<strong class="lccl-fr-conf-val lccl-fr-conf-seats"><?php echo esc_html( $seat_display ); ?></strong>
				</div>
				<?php if ( ! empty( $booking['notes'] ) ) : ?>
					<div class="lccl-fr-conf-row lccl-fr-conf-row--stack-mobile">
						<span class="lccl-fr-conf-label"><?php esc_html_e( 'Special Requests', 'lccl-de' ); ?></span>
						<span class="lccl-fr-conf-val"><?php echo esc_html( $booking['notes'] ); ?></span>
					</div>
				<?php endif; ?>
				<div class="lccl-fr-conf-row lccl-fr-conf-row--inline-mobile">
					<span class="lccl-fr-conf-label"><?php esc_html_e( 'Amount Paid', 'lccl-de' ); ?></span>
					<strong class="lccl-fr-conf-val lccl-fr-conf-amount">LKR <?php echo esc_html( number_format( (float) $booking['amount_lkr'], 2 ) ); ?></strong>
				</div>
			</div>

			<div class="lccl-fr-conf-notice">
				<p>
					<?php printf(
						/* translators: %s: email */
						esc_html__( 'A confirmation email containing your booking details and allocated seat numbers has been sent to %s. Please present your confirmation email or booking reference at the reception counter upon arrival.', 'lccl-de' ),
						'<strong>' . esc_html( $booking['attendee_email'] ) . '</strong>'
					); ?>
				</p>
			</div>

			<div class="lccl-fr-conf-actions">
				<a href="<?php echo esc_url( remove_query_arg( array( 'lccl_fnd_return', 'lccl_fnd_ref', 'reqid', 'ReqID' ) ) ); ?>" class="lccl-fr-button lccl-fr-conf-btn">
					<?php esc_html_e( 'Return to Event Page', 'lccl-de' ); ?>
				</a>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Build confirmation email subject and HTML body.
	 */
	public static function get_booking_confirmation_email_content( $booking, $receipt, $event = null ) {
		if ( ! $event && ! empty( $booking['event_id'] ) ) {
			$event = LCCL_DE_Fundraiser::get_event( $booking['event_id'] );
		}

		$event_name = $event['name'] ?? __( 'Fundraiser Event', 'lccl-de' );
		$venue      = $event['venue'] ?? 'TBA';
		$event_date = ! empty( $event['event_date'] ) ? date( 'l, F j, Y', strtotime( $event['event_date'] ) ) : 'TBA';
		$event_time = ! empty( $event['event_time'] ) ? date( 'h:i A', strtotime( $event['event_time'] ) ) : '';

		$is_individual = ( 'individual' === $booking['booking_type'] );
		$seat_display = '';
		if ( $is_individual ) {
			$seat_nums = array_filter( array_map( 'trim', explode( ',', $booking['seat_numbers'] ?? '' ) ) );
			$seat_display = LCCL_DE_Fundraiser::format_seats_with_tables( (int) $booking['event_id'], $seat_nums );
			if ( empty( $seat_display ) ) {
				$seat_display = ! empty( $seat_nums ) ? 'Seat #' . implode( ', Seat #', $seat_nums ) : 'Auto-allocated (' . $booking['qty'] . ' seats)';
			}
		} else {
			$table_labels = self::get_table_labels( $booking['table_ids'] ?? '' );
			$seat_display = ! empty( $table_labels ) ? implode( ', ', $table_labels ) : 'Reserved Table(s)';
		}

		$subject = sprintf( 'Booking Confirmation – %s (%s)', $event_name, $booking['order_ref'] );

		$logo_url = function_exists( 'content_url' )
			? content_url( '/uploads/2021/09/Logo-resize-replace-01.png' )
			: 'http://lccl.local/wp-content/uploads/2021/09/Logo-resize-replace-01.png';

		// Email HTML body
		$body = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="font-family:Arial,sans-serif; background:#f4f6f8; margin:0; padding:24px;">' .
			'<table width="100%" cellpadding="0" cellspacing="0" style="max-width:600px; margin:0 auto; background:#fff; border-radius:8px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,.08);">' .
			'<tr><td style="background:#1a4a8a; padding:24px 20px 20px; text-align:center; color:#fff;">' .
			'<div style="text-align:center; margin-bottom:12px;">' .
			'<img src="' . esc_url( $logo_url ) . '" alt="LIONS CLUB OF COLOMBO LEADS" width="140" style="display:inline-block; max-width:140px; width:100%; height:auto; border:0; vertical-align:middle;">' .
			'</div>' .
			'<h1 style="margin:0; font-size:18px; font-weight:700; color:#ffffff; letter-spacing:0.04em; text-transform:uppercase;">LIONS CLUB OF COLOMBO LEADS</h1>' .
			'<p style="margin:6px 0 0; font-size:13px; color:#e0e7ff; opacity:0.95;">Event Booking & Seat Allocation Confirmation</p>' .
			'</td></tr>' .
			'<tr><td style="padding:24px 30px;">' .
			'<p style="font-size:15px; color:#333; margin:0 0 16px;">Dear <strong>' . esc_html( $booking['attendee_name'] ) . '</strong>,</p>' .
			'<p style="font-size:14px; color:#555; line-height:1.6; margin:0 0 20px;">' .
			'Thank you for your support. Your booking for <strong>' . esc_html( $event_name ) . '</strong> has been successfully processed and confirmed.' .
			'</p>' .
			'<table width="100%" cellpadding="8" cellspacing="0" style="border:1px solid #e5e7eb; border-radius:6px; font-size:13px; margin-bottom:20px;">' .
			'<tr style="background:#f9fafb;"><td width="40%" style="color:#6b7280; border-bottom:1px solid #e5e7eb;">Booking Reference:</td><td style="font-family:monospace; font-weight:bold; color:#1a4a8a; border-bottom:1px solid #e5e7eb;">' . esc_html( $booking['order_ref'] ) . '</td></tr>' .
			'<tr><td style="color:#6b7280; border-bottom:1px solid #e5e7eb;">Payment Transaction ID:</td><td style="font-family:monospace; border-bottom:1px solid #e5e7eb;">' . esc_html( $receipt ) . '</td></tr>' .
			'<tr style="background:#f9fafb;"><td style="color:#6b7280; border-bottom:1px solid #e5e7eb;">Event Date & Time:</td><td style="border-bottom:1px solid #e5e7eb;">' . esc_html( $event_date . ( $event_time ? ' at ' . $event_time : '' ) ) . '</td></tr>' .
			'<tr><td style="color:#6b7280; border-bottom:1px solid #e5e7eb;">Venue:</td><td style="border-bottom:1px solid #e5e7eb;">' . esc_html( $venue ) . '</td></tr>' .
			'<tr style="background:#f9fafb;"><td style="color:#6b7280; border-bottom:1px solid #e5e7eb;">Booking Type:</td><td style="border-bottom:1px solid #e5e7eb;">' . esc_html( $is_individual ? 'Individual Seats' : 'Whole Table' ) . '</td></tr>' .
			'<tr><td style="color:#6b7280; border-bottom:1px solid #e5e7eb;">' . ( $is_individual ? 'Number of Seats:' : 'Number of Tables:' ) . '</td><td style="border-bottom:1px solid #e5e7eb;">' . esc_html( $booking['qty'] ) . '</td></tr>' .
			'<tr style="background:#eff6ff;"><td style="color:#1d4ed8; font-weight:bold; border-bottom:1px solid #e5e7eb;">' . ( $is_individual ? 'Assigned Seat(s):' : 'Reserved Tables:' ) . '</td><td style="color:#1d4ed8; font-weight:bold; font-size:15px; border-bottom:1px solid #e5e7eb;">' . esc_html( $seat_display ) . '</td></tr>' .
			( ! empty( $booking['notes'] ) ? '<tr><td style="color:#6b7280; border-bottom:1px solid #e5e7eb;">Special Requests:</td><td style="border-bottom:1px solid #e5e7eb; color:#333;">' . esc_html( $booking['notes'] ) . '</td></tr>' : '' ) .
			'<tr style="background:#f9fafb;"><td style="color:#6b7280;">Amount Paid:</td><td style="color:#16a34a; font-weight:bold; font-size:15px;">LKR ' . number_format( (float) $booking['amount_lkr'], 2 ) . '</td></tr>' .
			'</table>' .
			'<div style="background:#fffbeb; border-left:4px solid #f59e0b; padding:12px 16px; margin-bottom:20px; font-size:13px; color:#92400e;">' .
			'<strong>Important:</strong> Please present this confirmation email or quote your booking reference <strong>' . esc_html( $booking['order_ref'] ) . '</strong> at the reception/ticket counter upon arrival at the venue.' .
			'</div>' .
			'<p style="font-size:13px; color:#777; margin:0; line-height:1.6;">Best regards,<br><br><strong>LIONS CLUB OF COLOMBO LEADS</strong></p>' .
			'</td></tr>' .
			'<tr><td style="background:#f4f6f8; padding:16px; text-align:center; font-size:11px; color:#888;">' .
			'Lions Club of Colombo Leads · District 306 B2 · Sri Lanka' .
			'</td></tr>' .
			'</table></body></html>';

		return array(
			'subject' => $subject,
			'body'    => $body,
		);
	}

	/**
	 * Send confirmation email and SMS to attendee, plus admin notification.
	 */
	public static function send_booking_confirmation( $booking, $receipt, $event = null ) {
		if ( ! $event && ! empty( $booking['event_id'] ) ) {
			$event = LCCL_DE_Fundraiser::get_event( $booking['event_id'] );
		}

		$to = sanitize_email( $booking['attendee_email'] ?? '' );
		if ( ! is_email( $to ) ) {
			return;
		}

		$email_content = self::get_booking_confirmation_email_content( $booking, $receipt, $event );
		$subject       = $email_content['subject'];
		$body          = $email_content['body'];
		$event_name    = $event['name'] ?? __( 'Fundraiser Event', 'lccl-de' );
		$is_individual = ( 'individual' === $booking['booking_type'] );
		$seat_display  = '';
		if ( $is_individual ) {
			$seat_nums = array_filter( array_map( 'trim', explode( ',', $booking['seat_numbers'] ?? '' ) ) );
			$seat_display = LCCL_DE_Fundraiser::format_seats_with_tables( (int) $booking['event_id'], $seat_nums );
			if ( empty( $seat_display ) ) {
				$seat_display = ! empty( $seat_nums ) ? 'Seat #' . implode( ', Seat #', $seat_nums ) : 'Auto-allocated (' . $booking['qty'] . ' seats)';
			}
		} else {
			$table_labels = self::get_table_labels( $booking['table_ids'] ?? '' );
			$seat_display = ! empty( $table_labels ) ? implode( ', ', $table_labels ) : 'Reserved Table(s)';
		}

		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
			'From: Lions Club of Colombo Leads <no-reply@colomboleads.org>',
		);

		wp_mail( $to, $subject, $body, $headers );

		// Admin notification copy
		$admin_email = get_option( 'admin_email' );
		if ( $admin_email && is_email( $admin_email ) ) {
			$admin_subject = sprintf( '[Admin Alert] New Fundraiser Booking Paid: %s (#%s)', $event_name, $booking['order_ref'] );
			$admin_body = sprintf(
				"A new fundraiser booking has been paid and confirmed.\n\n" .
				"Event: %s\n" .
				"Order Ref: %s\n" .
				"Attendee: %s (%s, %s)\n" .
				"Type: %s (Qty: %d)\n" .
				"Allocation: %s\n" .
				( ! empty( $booking['notes'] ) ? "Notes: " . $booking['notes'] . "\n" : "" ) .
				"Amount: LKR %s\n" .
				"Receipt: %s\n",
				$event_name,
				$booking['order_ref'],
				$booking['attendee_name'],
				$booking['attendee_email'],
				$booking['attendee_phone'],
				$booking['booking_type'],
				$booking['qty'],
				$seat_display,
				number_format( (float) $booking['amount_lkr'], 2 ),
				$receipt
			);
			wp_mail( $admin_email, $admin_subject, $admin_body );
		}

		// SMS notification to attendee
		$phone = trim( (string) ( $booking['attendee_phone'] ?? '' ) );
		if ( '' !== $phone && class_exists( 'LCCL_DE_Notify' ) && method_exists( 'LCCL_DE_Notify', 'send_custom_sms' ) ) {
			$sms_msg = sprintf(
				'LCCL Fundraiser: Booking confirmed for %s! Ref: %s. %s: %s. Thank you for your support!',
				$event_name,
				$booking['order_ref'],
				$is_individual ? 'Seats' : 'Tables',
				$seat_display
			);
			LCCL_DE_Notify::send_custom_sms( $phone, $sms_msg, (int) ( $booking['id'] ?? 0 ), 'fundraiser' );
		}
	}

	/**
	 * Helper to get table labels from comma-separated table IDs.
	 */
	public static function get_table_labels( $table_ids_csv ) {
		return LCCL_DE_Fundraiser::get_table_labels( $table_ids_csv );
	}

	/**
	 * AJAX endpoint: dynamically preview allocated seats for individual seat selection.
	 */
	public static function ajax_preview_seats() {
		check_ajax_referer( 'lccl_fundraiser_preview_seats', 'nonce' );

		// Clean up any stale holds before computing preview
		LCCL_DE_Fundraiser::expire_stale_pending_bookings();

		$event_id  = isset( $_POST['event_id'] ) ? (int) $_POST['event_id'] : 0;
		$qty       = isset( $_POST['qty'] ) ? (int) $_POST['qty'] : 1;
		$vip_token = isset( $_POST['vip_token'] ) ? sanitize_text_field( wp_unslash( $_POST['vip_token'] ) ) : '';

		$preview = LCCL_DE_Fundraiser::get_party_seat_preview( $event_id, $qty, $vip_token );
		if ( is_wp_error( $preview ) ) {
			wp_send_json_error( array( 'message' => $preview->get_error_message() ) );
		}

		wp_send_json_success( $preview );
	}

	/**
	 * Developer preview renderer for Web Order Confirmation UI & Email Template.
	 *
	 * Accessible via: ?lccl_preview=ui or ?lccl_preview=email
	 * Optional parameters: &mode=individual (default) or &mode=table
	 *
	 * PRODUCTION NOTE:
	 * By default, this is restricted to WordPress Administrators (`manage_options`).
	 * To completely remove when going to production, remove the 3-line check in `render()`.
	 */
	public static function render_dev_preview() {
		// Security check: restrict preview to logged-in administrators
		if ( ! current_user_can( 'manage_options' ) && ! ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ) {
			return '<div style="max-width:680px; margin:30px auto; padding:20px; background:#fef2f2; border:1px solid #fecaca; color:#991b1b; border-radius:4px; text-align:center;">' .
				esc_html__( 'Preview mode is only accessible to WordPress Administrators.', 'lccl-de' ) .
				'</div>';
		}

		$preview_type = isset( $_GET['lccl_preview'] ) ? sanitize_text_field( wp_unslash( $_GET['lccl_preview'] ) ) : 'ui';
		$mode         = ( isset( $_GET['mode'] ) && 'table' === $_GET['mode'] ) ? 'table' : 'individual';
		$is_indiv     = ( 'individual' === $mode );

		$active_event = LCCL_DE_Fundraiser::get_active_public_event();
		if ( ! $active_event ) {
			$active_event = array(
				'id'         => 1,
				'name'       => 'LCCL Annual Fundraiser Gala 2026',
				'event_date' => '2026-11-15',
				'event_time' => '18:30:00',
				'venue'      => 'Grand Ballroom, Hotel Galadari, Colombo',
			);
		}

		if ( $is_indiv ) {
			$mock_booking = array(
				'id'             => 999,
				'order_ref'      => 'FND-65FA38',
				'attendee_name'  => 'Saman Padukka',
				'attendee_email' => 'saman@example.com',
				'attendee_phone' => '+94 77 123 4567',
				'booking_type'   => 'individual',
				'qty'            => 2,
				'seat_numbers'   => '51, 53',
				'table_ids'      => '',
				'notes'          => 'Please arrange vegetarian meal',
				'amount_lkr'     => 15000.00,
				'event_id'       => (int) $active_event['id'],
			);
			$mock_receipt = '2026200000437627';
		} else {
			$mock_booking = array(
				'id'             => 999,
				'order_ref'      => 'FND-TBLE01',
				'attendee_name'  => 'Dr. Samantha Perera',
				'attendee_email' => 'samantha.perera@example.com',
				'attendee_phone' => '+94 71 987 6543',
				'booking_type'   => 'table',
				'qty'            => 1,
				'seat_numbers'   => '',
				'table_ids'      => 'P04',
				'notes'          => 'VIP corporate table reservation',
				'amount_lkr'     => 120000.00,
				'event_id'       => (int) $active_event['id'],
			);
			$mock_receipt = '2026200000981240';
		}

		// Handle live test email dispatch
		$send_status_msg = '';
		if ( ! empty( $_POST['lccl_send_test_email'] ) && check_admin_referer( 'lccl_dev_preview_send', 'preview_nonce' ) ) {
			$test_email = sanitize_email( wp_unslash( $_POST['test_recipient'] ?? '' ) );
			if ( is_email( $test_email ) ) {
				$mock_booking['attendee_email'] = $test_email;
				self::send_booking_confirmation( $mock_booking, $mock_receipt, $active_event );
				$send_status_msg = '<div style="background:#dcfce7; color:#166534; padding:8px 14px; border-radius:4px; font-size:12px; margin-top:10px; font-weight:600;">✓ Live test confirmation email sent to ' . esc_html( $test_email ) . '</div>';
			}
		}

		ob_start();
		$current_url = remove_query_arg( array( 'lccl_preview', 'mode' ) );
		$admin_user  = wp_get_current_user();
		$admin_email = ( $admin_user && $admin_user->user_email ) ? $admin_user->user_email : get_option( 'admin_email' );
		?>
		<div style="background:#0f172a; border-radius:6px; padding:14px 18px; margin:20px auto 26px; max-width:680px; box-shadow:0 4px 12px rgba(0,0,0,0.15); font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif; color:#fff; box-sizing:border-box;">
			<div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-bottom:12px; border-bottom:1px solid #1e293b; padding-bottom:10px;">
				<div style="display:flex; align-items:center; gap:8px;">
					<span style="background:#f59e0b; color:#0f172a; font-weight:700; font-size:11px; padding:2px 8px; border-radius:3px; text-transform:uppercase; letter-spacing:0.04em;">Dev Preview Mode</span>
					<span style="font-size:12px; color:#94a3b8;">Order Confirmation & Email Template Viewer</span>
				</div>
				<a href="<?php echo esc_url( $current_url ); ?>" style="color:#94a3b8; text-decoration:none; font-size:12px; font-weight:500;">&larr; Return to Live Form</a>
			</div>
			
			<div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center; justify-content:space-between;">
				<div style="display:flex; gap:6px; flex-wrap:wrap;">
					<a href="<?php echo esc_url( add_query_arg( array( 'lccl_preview' => 'ui', 'mode' => 'individual' ), $current_url ) ); ?>" 
					   style="background:<?php echo ( 'ui' === $preview_type && $is_indiv ? '#2563eb' : '#1e293b' ); ?>; color:#fff; text-decoration:none; font-size:12px; font-weight:600; padding:6px 12px; border-radius:4px; border:1px solid <?php echo ( 'ui' === $preview_type && $is_indiv ? '#3b82f6' : '#334155' ); ?>;">
						Web UI (Individual Seats)
					</a>
					<a href="<?php echo esc_url( add_query_arg( array( 'lccl_preview' => 'ui', 'mode' => 'table' ), $current_url ) ); ?>" 
					   style="background:<?php echo ( 'ui' === $preview_type && ! $is_indiv ? '#2563eb' : '#1e293b' ); ?>; color:#fff; text-decoration:none; font-size:12px; font-weight:600; padding:6px 12px; border-radius:4px; border:1px solid <?php echo ( 'ui' === $preview_type && ! $is_indiv ? '#3b82f6' : '#334155' ); ?>;">
						Web UI (Table Booking)
					</a>
					<a href="<?php echo esc_url( add_query_arg( array( 'lccl_preview' => 'email', 'mode' => 'individual' ), $current_url ) ); ?>" 
					   style="background:<?php echo ( 'email' === $preview_type && $is_indiv ? '#2563eb' : '#1e293b' ); ?>; color:#fff; text-decoration:none; font-size:12px; font-weight:600; padding:6px 12px; border-radius:4px; border:1px solid <?php echo ( 'email' === $preview_type && $is_indiv ? '#3b82f6' : '#334155' ); ?>;">
						Email (Individual Seats)
					</a>
					<a href="<?php echo esc_url( add_query_arg( array( 'lccl_preview' => 'email', 'mode' => 'table' ), $current_url ) ); ?>" 
					   style="background:<?php echo ( 'email' === $preview_type && ! $is_indiv ? '#2563eb' : '#1e293b' ); ?>; color:#fff; text-decoration:none; font-size:12px; font-weight:600; padding:6px 12px; border-radius:4px; border:1px solid <?php echo ( 'email' === $preview_type && ! $is_indiv ? '#3b82f6' : '#334155' ); ?>;">
						Email (Table Booking)
					</a>
				</div>
			</div>

			<form method="post" style="margin-top:12px; padding-top:10px; border-top:1px dashed #334155; display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
				<?php wp_nonce_field( 'lccl_dev_preview_send', 'preview_nonce' ); ?>
				<input type="hidden" name="lccl_send_test_email" value="1">
				<label style="font-size:12px; color:#cbd5e1;">Send Test Email to:</label>
				<input type="email" name="test_recipient" value="<?php echo esc_attr( $admin_email ); ?>" required style="background:#1e293b; color:#fff; border:1px solid #475569; padding:5px 8px; border-radius:4px; font-size:12px; min-width:210px;">
				<button type="submit" style="background:#10b981; color:#fff; border:0; padding:6px 12px; border-radius:4px; font-size:12px; font-weight:600; cursor:pointer;">Send Test Email</button>
			</form>
			<?php echo $send_status_msg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</div>
		<?php
		$toolbar = ob_get_clean();

		if ( 'email' === $preview_type ) {
			$email_data = self::get_booking_confirmation_email_content( $mock_booking, $mock_receipt, $active_event );
			$email_preview = '<div style="max-width:680px; margin:0 auto 12px; padding:10px 14px; background:#eff6ff; border:1px solid #bfdbfe; border-radius:4px; font-size:13px; color:#1e40af;">' .
				'<strong>Subject:</strong> ' . esc_html( $email_data['subject'] ) .
				'</div>' .
				'<div style="max-width:680px; margin:0 auto 30px; box-shadow:0 4px 16px rgba(0,0,0,0.08); border-radius:8px; overflow:hidden;">' .
				$email_data['body'] .
				'</div>';
			return $toolbar . $email_preview;
		}

		// Web UI Confirmation View
		return $toolbar . self::render_success_view( $mock_booking, $mock_receipt, $active_event );
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
