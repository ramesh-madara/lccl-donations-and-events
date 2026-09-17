<?php
/**
 * Registration email and Dialog e-SMS after a donor signs up.
 *
 * @package LCCL_Donations_And_Events
 */

defined( 'ABSPATH' ) || exit;

/**
 * Sends confirmation SMS/email using the same Dialog gateway as payment.colomboleads.org.
 */
class LCCL_DE_Notify {

	/**
	 * Dialog e-SMS login, from payment.colomboleads.org/downfs324/cron/smscron.php.
	 */
	const DIALOG_LOGIN = 'https://e-sms.dialog.lk/api/v1/login';

	/**
	 * Dialog e-SMS send endpoint.
	 */
	const DIALOG_SMS = 'https://e-sms.dialog.lk/api/v1/sms';

	/**
	 * Dialog portal username.
	 */
	const DIALOG_USER = 'lionsclubadmin2';

	/**
	 * Dialog portal password.
	 */
	const DIALOG_PASS = 'Lions@1234';

	/**
	 * Transient that caches the Dialog bearer token.
	 */
	const TOKEN_TRANSIENT = 'lccl_de_dialog_sms_token';

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
	 * Send a test staff email from wp-admin.
	 *
	 * @param string $to Address.
	 * @return bool
	 */
	public static function send_test( $to ) {
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

		return self::mail(
			$to,
			__( 'LCCL blood donation — test email', 'lccl-de' ),
			'<html><body><p>This is a test from the LCCL Donations and Events plugin. If you received this, wp_mail is working on this server.</p></body></html>',
			'test'
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
	 * Send an SMS through Dialog e-SMS.
	 *
	 * @param array $values    Sanitised registration.
	 * @param int   $insert_id Donor row ID.
	 */
	private static function send_sms( array $values, $insert_id ) {
		$phone = isset( $values['phone'] ) ? LCCL_DE_Blood_Donor_Submissions::phone_to_msisdn( $values['phone'] ) : '';
		if ( '' === $phone ) {
			return;
		}

		$token = self::dialog_token();
		if ( '' === $token ) {
			self::record(
				array(
					'kind'   => 'sms',
					'ok'     => 0,
					'to'     => $phone,
					'detail' => 'Dialog login failed. No bearer token.',
				)
			);
			return;
		}

		$bank    = self::bank_label( $values );
		$message = sprintf(
			'Thank you for registering your interest in blood donation. Your registration has been successfully received. Preferred blood bank: %s.',
			$bank
		);

		$response = wp_remote_post(
			self::DIALOG_SMS,
			array(
				'timeout' => 20,
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'message'        => $message,
						'transaction_id' => 'bdf-' . $insert_id . '-' . time(),
						'msisdn'         => array(
							array(
								'mobile' => $phone,
							),
						),
					)
				),
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
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		$ok   = is_array( $body ) && isset( $body['status'] ) && 'success' === $body['status'];

		self::record(
			array(
				'kind'   => 'sms',
				'ok'     => $ok ? 1 : 0,
				'to'     => $phone,
				'detail' => $ok ? 'Dialog accepted the SMS.' : 'HTTP ' . $code . ' ' . wp_remote_retrieve_body( $response ),
			)
		);
	}

	/**
	 * Login to Dialog and cache the bearer token.
	 *
	 * @return string
	 */
	private static function dialog_token() {
		$cached = get_transient( self::TOKEN_TRANSIENT );
		if ( is_string( $cached ) && '' !== $cached ) {
			return $cached;
		}

		$response = wp_remote_post(
			self::DIALOG_LOGIN,
			array(
				'timeout' => 20,
				'headers' => array(
					'Content-Type' => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'username' => self::DIALOG_USER,
						'password' => self::DIALOG_PASS,
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return '';
		}

		$body  = json_decode( wp_remote_retrieve_body( $response ), true );
		$token = ( is_array( $body ) && ! empty( $body['token'] ) ) ? (string) $body['token'] : '';

		if ( '' !== $token ) {
			set_transient( self::TOKEN_TRANSIENT, $token, 45 * MINUTE_IN_SECONDS );
		}

		return $token;
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
	 * Staff copy with contact details.
	 *
	 * @param string $name      Display name.
	 * @param array  $values    Sanitised registration.
	 * @param string $bank      Blood bank label.
	 * @param string $phone     Phone.
	 * @param int    $insert_id Row ID.
	 * @return string
	 */
	private static function admin_html( $name, array $values, $bank, $phone, $insert_id ) {
		$email    = isset( $values['email'] ) ? $values['email'] : '';
		$district = isset( $values['district'] ) ? $values['district'] : '';
		$city     = isset( $values['city'] ) ? $values['city'] : '';

		return '<html><body>
			<h2>New blood donor registration</h2>
			<p><strong>ID:</strong> ' . (int) $insert_id . '</p>
			<p><strong>Name:</strong> ' . esc_html( $name ) . '</p>
			<p><strong>Phone:</strong> ' . esc_html( $phone ) . '</p>
			<p><strong>Email:</strong> ' . esc_html( $email ) . '</p>
			<p><strong>City:</strong> ' . esc_html( $city ) . '</p>
			<p><strong>District:</strong> ' . esc_html( $district ) . '</p>
			<p><strong>Blood bank:</strong> ' . esc_html( $bank ) . '</p>
		</body></html>';
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
