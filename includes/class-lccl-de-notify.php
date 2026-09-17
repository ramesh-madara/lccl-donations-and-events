<?php
/**
 * Registration email and Dialog e-SMS after a donor signs up.
 *
 * @package LCCL_Donations_And_Events
 */

defined( 'ABSPATH' ) || exit;

/**
 * Sends confirmation SMS/email through Dialog e-SMS (POST API).
 */
class LCCL_DE_Notify {

	/**
	 * Transient that caches the Dialog bearer token and matching SMS URL.
	 */
	const TOKEN_TRANSIENT = 'lccl_de_dialog_sms_token';

	/**
	 * Dialog POST error codes from eSMS API Document v2.9.
	 *
	 * @var array<int,string>
	 */
	const DIALOG_POST_ERRORS = array(
		100 => 'Invalid or expired token.',
		101 => 'Invalid request parameters.',
		102 => 'User account not found.',
		104 => 'Transaction ID is already used.',
		105 => 'Invalid token signature.',
		106 => 'Bearer token missing from the request.',
		107 => 'A required SMS field is missing or invalid.',
		108 => 'This account has no active sender mask.',
		109 => 'No valid mobile number after Dialog filtered the list.',
		114 => 'Not enough wallet balance.',
		115 => 'Username or password is invalid.',
		116 => 'Account is locked.',
		117 => 'Too many requests.',
		118 => 'Dialog is in a blackout window (typically 8:00 PM–8:00 AM).',
		999 => 'Dialog internal server error.',
	);

	/**
	 * Option that stores recent send results for wp-admin.
	 */
	const LOG_OPTION = 'lccl_de_notify_log';

	/**
	 * Notify the donor and staff after a row is stored.
	 *
	 * Failures here must not undo the registration.
	 *
	 * @param array $values    Sanitised registration.
	 * @param int   $insert_id Donor row ID.
	 */
	public static function after_registration( array $values, $insert_id ) {
		try {
			if ( LCCL_DE_Settings::enabled( 'donor_sms' ) ) {
				self::send_sms( $values, (int) $insert_id );
			} else {
				self::record(
					array(
						'kind'   => 'sms',
						'ok'     => 0,
						'to'     => isset( $values['phone'] ) ? (string) $values['phone'] : '',
						'detail' => 'Skipped: donor SMS is turned off.',
					)
				);
			}

			self::send_emails( $values, (int) $insert_id );
		} catch ( Exception $e ) {
			self::record(
				array(
					'kind'   => 'error',
					'ok'     => 0,
					'detail' => $e->getMessage(),
				)
			);
		}
	}

	/**
	 * Send a test of the live donor and/or staff templates from wp-admin.
	 *
	 * @param string $to   Address.
	 * @param string $kind donor, staff, or both.
	 * @return bool
	 */
	public static function send_test( $to, $kind = 'both' ) {
		if ( ! is_email( $to ) ) {
			self::record(
				array(
					'kind'   => 'test',
					'ok'     => 0,
					'to'     => (string) $to,
					'detail' => 'No valid test address.',
				)
			);
			return false;
		}

		$kind = in_array( $kind, array( 'donor', 'staff', 'both' ), true ) ? $kind : 'both';
		$sample = self::sample_registration( $to );
		$ok     = true;

		if ( 'donor' === $kind || 'both' === $kind ) {
			$ok = self::mail(
				$to,
				'[TEST] ' . __( 'Blood donor registration confirmed', 'lccl-de' ),
				self::donor_html( $sample['name'], $sample['bank'] ),
				'test'
			) && $ok;
		}

		if ( 'staff' === $kind || 'both' === $kind ) {
			$ok = self::mail(
				$to,
				'[TEST] ' . sprintf( __( 'New blood donor registration: %s', 'lccl-de' ), $sample['name'] ),
				self::admin_html( $sample['name'], $sample['values'], $sample['bank'], $sample['values']['phone'], $sample['id'] ),
				'test'
			) && $ok;
		}

		return $ok;
	}

	/**
	 * Sample registration used by the wp-admin test send.
	 *
	 * @param string $to Address shown in the staff copy.
	 * @return array{name:string,bank:string,id:int,values:array}
	 */
	private static function sample_registration( $to ) {
		$values = array(
			'first_name'          => 'Saman',
			'last_name'           => 'Padukka',
			'address'             => 'No. 25, Main Street',
			'city'                => 'Colombo',
			'postal_code'         => '00100',
			'email'               => $to,
			'phone'               => '+94712345678',
			'district'            => 'Colombo',
			'blood_bank'          => 'nbc-narahenpita',
			'donation_preference' => 'either',
			'donated_before'      => 'yes',
			'contact_method'      => 'phone',
			'notify_campaigns'    => 1,
		);

		return array(
			'name'   => 'Saman Padukka',
			'bank'   => self::bank_label( $values ),
			'id'     => 1001,
			'values' => $values,
		);
	}

	/**
	 * Recent send results, newest first.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function log() {
		$log = get_option( self::LOG_OPTION, array() );
		return is_array( $log ) ? $log : array();
	}

	/**
	 * Send an SMS through Dialog e-SMS POST /sms.
	 *
	 * @param array $values    Sanitised registration.
	 * @param int   $insert_id Donor row ID.
	 */
	private static function send_sms( array $values, $insert_id ) {
		$phone = isset( $values['phone'] ) ? LCCL_DE_Blood_Donor_Submissions::phone_to_msisdn( $values['phone'] ) : '';
		if ( '' === $phone ) {
			self::record(
				array(
					'kind'   => 'sms',
					'ok'     => 0,
					'detail' => 'Skipped: no valid mobile number on the registration.',
				)
			);
			return;
		}

		if ( '' === LCCL_DE_Settings::sms_api_key() || '' === LCCL_DE_Settings::sms_password() ) {
			self::record(
				array(
					'kind'   => 'sms',
					'ok'     => 0,
					'to'     => $phone,
					'detail' => 'SMS username or password is not saved in Notifications.',
				)
			);
			return;
		}

		$bank    = self::bank_label( $values );
		$message = sprintf(
			'Thank you for registering as a blood donor. Your registration has been successfully received. Preferred blood bank : %s.',
			$bank
		);

		$payload = array(
			'message'        => $message,
			'transaction_id' => self::dialog_transaction_id( (int) $insert_id ),
			'msisdn'         => array(
				array(
					'mobile' => $phone,
				),
			),
			'payment_method' => 0,
		);

		self::dialog_send( $payload, $phone, false );
	}

	/**
	 * POST the SMS, refreshing the bearer token once if Dialog says it expired.
	 *
	 * @param array  $payload JSON body.
	 * @param string $phone   Log target.
	 * @param bool   $retried Whether this is the token-refresh retry.
	 */
	private static function dialog_send( array $payload, $phone, $retried ) {
		$session = self::dialog_session( $retried );
		if ( ! $session ) {
			self::record(
				array(
					'kind'   => 'sms',
					'ok'     => 0,
					'to'     => $phone,
					'detail' => 'Dialog login failed. Check the SMS username and password.',
				)
			);
			return;
		}

		$response = wp_remote_post(
			$session['sms'],
			array(
				'timeout' => 30,
				'headers' => array(
					'Authorization' => 'Bearer ' . $session['token'],
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $payload ),
			)
		);

		if ( is_wp_error( $response ) ) {
			self::record(
				array(
					'kind'   => 'sms',
					'ok'     => 0,
					'to'     => $phone,
					'detail' => $response->get_error_message(),
				)
			);
			return;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$raw  = wp_remote_retrieve_body( $response );
		$body = json_decode( $raw, true );
		$err  = ( is_array( $body ) && isset( $body['errCode'] ) ) ? (int) $body['errCode'] : 0;

		if ( ! $retried && in_array( $err, array( 100, 105, 106 ), true ) ) {
			delete_transient( self::TOKEN_TRANSIENT );
			self::dialog_send( $payload, $phone, true );
			return;
		}

		$ok = is_array( $body ) && isset( $body['status'] ) && 'success' === $body['status'];

		self::record(
			array(
				'kind'   => 'sms',
				'ok'     => $ok ? 1 : 0,
				'to'     => $phone,
				'detail' => $ok ? 'Dialog accepted the SMS.' : self::dialog_error_detail( $body, $code, $raw ),
			)
		);
	}

	/**
	 * Login to Dialog and cache the bearer token with the matching SMS URL.
	 *
	 * Tries the current POST API (v2) first, then the older v1 host still used
	 * by payment.colomboleads.org.
	 *
	 * @param bool $force Ignore a cached token.
	 * @return array{token:string,sms:string}|null
	 */
	private static function dialog_session( $force = false ) {
		if ( ! $force ) {
			$cached = get_transient( self::TOKEN_TRANSIENT );
			if ( is_array( $cached ) && ! empty( $cached['token'] ) && ! empty( $cached['sms'] ) ) {
				return $cached;
			}
			if ( is_string( $cached ) && '' !== $cached ) {
				return array(
					'token' => $cached,
					'sms'   => 'https://e-sms.dialog.lk/api/v1/sms',
				);
			}
		}

		$key      = LCCL_DE_Settings::sms_api_key();
		$password = LCCL_DE_Settings::sms_password();
		if ( '' === $key || '' === $password ) {
			return null;
		}

		$pairs = array(
			array(
				'login' => 'https://esms.dialog.lk/api/v2/user/login',
				'sms'   => 'https://esms.dialog.lk/api/v2/sms',
			),
			array(
				'login' => 'https://e-sms.dialog.lk/api/v1/login',
				'sms'   => 'https://e-sms.dialog.lk/api/v1/sms',
			),
		);

		foreach ( $pairs as $pair ) {
			$response = wp_remote_post(
				$pair['login'],
				array(
					'timeout' => 30,
					'headers' => array(
						'Content-Type' => 'application/json',
					),
					'body'    => wp_json_encode(
						array(
							'username' => $key,
							'password' => $password,
						)
					),
				)
			);

			if ( is_wp_error( $response ) ) {
				continue;
			}

			$body  = json_decode( wp_remote_retrieve_body( $response ), true );
			$token = ( is_array( $body ) && ! empty( $body['token'] ) ) ? (string) $body['token'] : '';
			if ( '' === $token ) {
				continue;
			}

			if ( isset( $body['status'] ) && 'success' !== $body['status'] ) {
				continue;
			}

			$ttl = isset( $body['expiration'] ) ? (int) $body['expiration'] : 45 * MINUTE_IN_SECONDS;
			$ttl = max( MINUTE_IN_SECONDS, min( $ttl - MINUTE_IN_SECONDS, 12 * HOUR_IN_SECONDS ) );

			$session = array(
				'token' => $token,
				'sms'   => $pair['sms'],
			);
			set_transient( self::TOKEN_TRANSIENT, $session, $ttl );

			return $session;
		}

		return null;
	}

	/**
	 * Unique 1–18 digit transaction id required by the POST API.
	 *
	 * @param int $insert_id Donor row ID.
	 * @return string
	 */
	private static function dialog_transaction_id( $insert_id ) {
		return sprintf( '%d%05d', time(), absint( $insert_id ) % 100000 );
	}

	/**
	 * Human Dialog error for the send log.
	 *
	 * @param mixed  $body Decoded JSON.
	 * @param int    $code HTTP status.
	 * @param string $raw  Raw body.
	 * @return string
	 */
	private static function dialog_error_detail( $body, $code, $raw ) {
		if ( ! is_array( $body ) ) {
			return 'HTTP ' . (int) $code . ' ' . $raw;
		}

		$err = isset( $body['errCode'] ) ? (int) $body['errCode'] : 0;
		$text = isset( self::DIALOG_POST_ERRORS[ $err ] ) ? self::DIALOG_POST_ERRORS[ $err ] : '';
		$comment = isset( $body['comment'] ) ? trim( (string) $body['comment'] ) : '';

		$parts = array();
		if ( $err ) {
			$parts[] = 'Dialog ' . $err;
		}
		if ( $text ) {
			$parts[] = $text;
		}
		if ( $comment ) {
			$parts[] = $comment;
		}
		if ( ! $parts ) {
			$parts[] = 'HTTP ' . (int) $code . ' ' . $raw;
		}

		return implode( ' ', $parts );
	}

	/**
	 * HTML confirmation to the donor and a copy to admin, each if enabled.
	 *
	 * @param array $values    Sanitised registration.
	 * @param int   $insert_id Donor row ID.
	 */
	private static function send_emails( array $values, $insert_id ) {
		$first = isset( $values['first_name'] ) ? $values['first_name'] : '';
		$last  = isset( $values['last_name'] ) ? $values['last_name'] : '';
		$name  = trim( $first . ' ' . $last );
		$bank  = self::bank_label( $values );
		$phone = isset( $values['phone'] ) ? $values['phone'] : '';

		$donor_email = isset( $values['email'] ) ? $values['email'] : '';
		if ( LCCL_DE_Settings::enabled( 'donor_email' ) && is_email( $donor_email ) ) {
			self::mail(
				$donor_email,
				__( 'Blood donor registration confirmed', 'lccl-de' ),
				self::donor_html( $name, $bank ),
				'donor'
			);
		} elseif ( LCCL_DE_Settings::enabled( 'donor_email' ) ) {
			self::record(
				array(
					'kind'   => 'donor',
					'ok'     => 0,
					'detail' => 'Skipped: no valid donor email on the registration.',
				)
			);
		} else {
			self::record(
				array(
					'kind'   => 'donor',
					'ok'     => 0,
					'to'     => $donor_email,
					'detail' => 'Skipped: donor email is turned off.',
				)
			);
		}

		if ( LCCL_DE_Settings::enabled( 'admin_email' ) ) {
			$admin_emails = LCCL_DE_Settings::admin_addresses();
			if ( $admin_emails ) {
				self::mail(
					$admin_emails,
					sprintf( __( 'New blood donor registration: %s', 'lccl-de' ), $name ),
					self::admin_html( $name, $values, $bank, $phone, $insert_id ),
					'admin'
				);
			} else {
				self::record(
					array(
						'kind'   => 'admin',
						'ok'     => 0,
						'detail' => 'Skipped: no admin email addresses are saved.',
					)
				);
			}
		} else {
			self::record(
				array(
					'kind'   => 'admin',
					'ok'     => 0,
					'detail' => 'Skipped: admin email is turned off.',
				)
			);
		}
	}

	/**
	 * HTML mail. From address is left to WP Mail SMTP / WordPress.
	 *
	 * @param string|string[] $to      Recipient(s).
	 * @param string          $subject Subject.
	 * @param string          $html    Body.
	 * @param string          $kind    Log label.
	 * @return bool
	 */
	private static function mail( $to, $subject, $html, $kind = 'email' ) {
		$error     = null;
		$on_failed = static function ( $wp_error ) use ( &$error ) {
			$error = $wp_error;
		};

		add_action( 'wp_mail_failed', $on_failed );

		$sent = wp_mail(
			$to,
			$subject,
			$html,
			array(
				'Content-Type: text/html; charset=UTF-8',
			)
		);

		remove_action( 'wp_mail_failed', $on_failed );

		$to_label = is_array( $to ) ? implode( ', ', $to ) : (string) $to;
		$detail   = $sent ? 'wp_mail returned true.' : 'wp_mail returned false.';
		if ( $error instanceof WP_Error ) {
			$detail = $error->get_error_message();
			$data   = $error->get_error_data();
			if ( is_array( $data ) && ! empty( $data['error_message'] ) ) {
				$detail .= ' ' . $data['error_message'];
			}
		}

		self::record(
			array(
				'kind'    => $kind,
				'ok'      => $sent ? 1 : 0,
				'to'      => $to_label,
				'subject' => $subject,
				'detail'  => $detail,
			)
		);

		return (bool) $sent;
	}

	/**
	 * Keep the newest send results.
	 *
	 * @param array $entry Log row.
	 */
	private static function record( array $entry ) {
		$entry = wp_parse_args(
			$entry,
			array(
				'at'      => time(),
				'kind'    => 'email',
				'ok'      => 0,
				'to'      => '',
				'subject' => '',
				'detail'  => '',
			)
		);

		$log = self::log();
		array_unshift( $log, $entry );
		update_option( self::LOG_OPTION, array_slice( $log, 0, 12 ), false );
	}

	/**
	 * Donor-facing HTML, matching registration.colomboleads.org.
	 *
	 * @param string $name Display name.
	 * @param string $bank Blood bank label.
	 * @return string
	 */
	private static function donor_html( $name, $bank ) {
		$logo = 'https://registration.colomboleads.org/lccclLOGO.png';
		$who  = '' !== $name ? $name : __( 'Donor', 'lccl-de' );

		return '<html><body style="margin:0;padding:0;background-color:#F3F3F3;">
			<div style="background-color:#F3F3F3;padding:28px 20px;font-family:Arial,Helvetica,sans-serif;">
				<img src="' . esc_url( $logo ) . '" alt="LCCL Logo" width="156" style="display:block;width:156px;max-width:156px;height:auto;margin:0 0 22px;border:0;">
				<h1 style="margin:0 0 22px;padding:0 0 10px;border-bottom:1px solid #f8e4a0;color:#333333;font-size:20px;font-weight:700;letter-spacing:0.04em;line-height:1.35;">BLOOD DONOR REGISTRATION CONFIRMED</h1>
				<p style="margin:0 0 16px;color:#555555;font-size:15px;line-height:1.6;">Dear ' . esc_html( $who ) . ',</p>
				<p style="margin:0 0 22px;color:#555555;font-size:15px;line-height:1.6;">Thank you for registering your interest in donating blood through Lions Club of Colombo LEADS. Your registration has been successfully received.</p>
				<h2 style="margin:0 0 10px;color:#333333;font-size:13px;font-weight:700;letter-spacing:0.08em;">REGISTRATION DETAILS</h2>
				<p style="margin:0 0 4px;color:#555555;font-size:14px;line-height:1.5;">Preferred Blood Bank:</p>
				<p style="margin:0 0 22px;color:#333333;font-size:16px;font-weight:700;line-height:1.45;">' . esc_html( $bank ) . '</p>
				<p style="margin:0 0 16px;color:#555555;font-size:15px;line-height:1.6;">We will use the information you provided to help facilitate your blood donation through the selected blood bank or a relevant Lions blood donation campaign.</p>
				<p style="margin:0 0 16px;color:#555555;font-size:15px;line-height:1.6;">Please note that donor eligibility and medical suitability will be assessed by the relevant blood bank at the time of donation.</p>
				<p style="margin:0 0 28px;color:#555555;font-size:15px;line-height:1.6;">Thank you for your willingness to help save lives.</p>
				<p style="margin:0 0 6px;color:#333333;font-size:14px;font-weight:700;letter-spacing:0.04em;line-height:1.45;">LIONS CLUB OF COLOMBO LEADS</p>
				<p style="margin:0;color:#555555;font-size:13px;line-height:1.55;">Lions International District 306 D6<br>Sri Lanka</p>
			</div>
		</body></html>';
	}

	/**
	 * Staff copy with contact details, matching the donor confirmation layout.
	 *
	 * @param string $name      Display name.
	 * @param array  $values    Sanitised registration.
	 * @param string $bank      Blood bank label.
	 * @param string $phone     Phone.
	 * @param int    $insert_id Row ID.
	 * @return string
	 */
	private static function admin_html( $name, array $values, $bank, $phone, $insert_id ) {
		$logo     = 'https://registration.colomboleads.org/lccclLOGO.png';
		$who      = '' !== $name ? $name : __( 'Donor', 'lccl-de' );
		$email    = isset( $values['email'] ) ? $values['email'] : '';
		$address  = isset( $values['address'] ) ? $values['address'] : '';
		$city     = isset( $values['city'] ) ? $values['city'] : '';
		$postal   = isset( $values['postal_code'] ) ? $values['postal_code'] : '';
		$district = isset( $values['district'] ) ? $values['district'] : '';
		$notify   = ! empty( $values['notify_campaigns'] )
			? __( 'Yes', 'lccl-de' )
			: __( 'No', 'lccl-de' );

		$prefs    = LCCL_DE_Blood_Donor_Form::get_donation_preferences();
		$history  = LCCL_DE_Blood_Donor_Form::get_donation_history_options();
		$methods  = LCCL_DE_Blood_Donor_Form::get_contact_methods();
		$pref_key = isset( $values['donation_preference'] ) ? $values['donation_preference'] : '';
		$hist_key = isset( $values['donated_before'] ) ? $values['donated_before'] : '';
		$meth_key = isset( $values['contact_method'] ) ? $values['contact_method'] : '';

		$details  = self::email_detail( __( 'Registration ID', 'lccl-de' ), (string) (int) $insert_id );
		$details .= self::email_detail( __( 'Name', 'lccl-de' ), $who );
		$details .= self::email_detail( __( 'Phone', 'lccl-de' ), $phone );
		$details .= self::email_detail( __( 'Email', 'lccl-de' ), $email );
		$details .= self::email_detail( __( 'Address', 'lccl-de' ), $address );
		$details .= self::email_detail( __( 'City', 'lccl-de' ), $city );
		$details .= self::email_detail( __( 'Postal code', 'lccl-de' ), $postal );
		$details .= self::email_detail( __( 'District', 'lccl-de' ), $district );
		$details .= self::email_detail( __( 'Preferred Blood Bank', 'lccl-de' ), $bank );
		$details .= self::email_detail(
			__( 'Donation preference', 'lccl-de' ),
			( $pref_key && isset( $prefs[ $pref_key ] ) ) ? $prefs[ $pref_key ] : $pref_key
		);
		$details .= self::email_detail(
			__( 'Donated before', 'lccl-de' ),
			( $hist_key && isset( $history[ $hist_key ] ) ) ? $history[ $hist_key ] : $hist_key
		);
		$details .= self::email_detail(
			__( 'Contact method', 'lccl-de' ),
			( $meth_key && isset( $methods[ $meth_key ] ) ) ? $methods[ $meth_key ] : $meth_key
		);
		$details .= self::email_detail( __( 'Notify about campaigns', 'lccl-de' ), $notify );

		return '<html><body style="margin:0;padding:0;background-color:#F3F3F3;">
			<div style="background-color:#F3F3F3;padding:28px 20px;font-family:Arial,Helvetica,sans-serif;">
				<img src="' . esc_url( $logo ) . '" alt="LCCL Logo" width="156" style="display:block;width:156px;max-width:156px;height:auto;margin:0 0 22px;border:0;">
				<h1 style="margin:0 0 22px;padding:0 0 10px;border-bottom:1px solid #f8e4a0;color:#333333;font-size:20px;font-weight:700;letter-spacing:0.04em;line-height:1.35;">NEW BLOOD DONOR REGISTRATION</h1>
				<p style="margin:0 0 22px;color:#555555;font-size:15px;line-height:1.6;">A new blood donor has registered through Lions Club of Colombo LEADS. The registration has been successfully received.</p>
				<h2 style="margin:0 0 10px;color:#333333;font-size:13px;font-weight:700;letter-spacing:0.08em;">REGISTRATION DETAILS</h2>
				' . $details . '
				<p style="margin:22px 0 28px;color:#555555;font-size:15px;line-height:1.6;">Please follow up with the donor using the contact details above, as needed.</p>
				<p style="margin:0 0 6px;color:#333333;font-size:14px;font-weight:700;letter-spacing:0.04em;line-height:1.45;">LIONS CLUB OF COLOMBO LEADS</p>
				<p style="margin:0;color:#555555;font-size:13px;line-height:1.55;">Lions International District 306 D6<br>Sri Lanka</p>
			</div>
		</body></html>';
	}

	/**
	 * Label and value pair for HTML notification emails.
	 *
	 * @param string $label Field label.
	 * @param string $value Field value.
	 * @return string
	 */
	private static function email_detail( $label, $value ) {
		$text = '' !== trim( (string) $value ) ? (string) $value : '—';

		return '<p style="margin:0 0 4px;color:#555555;font-size:14px;line-height:1.5;">' . esc_html( $label ) . '</p>
				<p style="margin:0 0 16px;color:#333333;font-size:16px;font-weight:700;line-height:1.45;">' . esc_html( $text ) . '</p>';
	}

	/**
	 * Human blood-bank name for messages.
	 *
	 * @param array $values Sanitised registration.
	 * @return string
	 */
	private static function bank_label( array $values ) {
		$district = isset( $values['district'] ) ? $values['district'] : '';
		$key      = isset( $values['blood_bank'] ) ? $values['blood_bank'] : '';
		$banks    = $district ? LCCL_DE_Blood_Donor_Form::get_blood_banks( $district ) : array();

		if ( $key && isset( $banks[ $key ] ) ) {
			return $banks[ $key ];
		}

		return $key ? $key : __( 'Not specified', 'lccl-de' );
	}
}
