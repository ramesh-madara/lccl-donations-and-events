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
			}

			self::send_emails( $values, (int) $insert_id );
		} catch ( Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			// Registration is already stored; never surface a send failure.
		}
	}

	/**
	 * Send an SMS through Dialog e-SMS.
	 *
	 * @param array $values    Sanitised registration.
	 * @param int   $insert_id Donor row ID.
	 */
	private static function send_sms( array $values, $insert_id ) {
		$phone = isset( $values['phone'] ) ? preg_replace( '/\s+/', '', (string) $values['phone'] ) : '';
		if ( '' === $phone ) {
			return;
		}

		$token = self::dialog_token();
		if ( '' === $token ) {
			return;
		}

		$first   = isset( $values['first_name'] ) ? $values['first_name'] : '';
		$last    = isset( $values['last_name'] ) ? $values['last_name'] : '';
		$bank    = self::bank_label( $values );
		$when    = wp_date( 'd-m-Y H:i:s' );
		$message = sprintf(
			'LCCL BLOOD DONATION - Hello %s %s, Congratulations! Your online registration for blood donation was successful. Your willingness to donate is truly life-saving. We appreciate your generosity. Thank you, Lions Club of Colombo LEADS. Sri Lanka. Date: %s. Your preferred blood bank: %s.',
			$first,
			$last,
			$when,
			$bank
		);

		wp_remote_post(
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
				__( 'Registration Successful', 'lccl-de' ),
				self::donor_html( $name, $bank )
			);
		}

		if ( LCCL_DE_Settings::enabled( 'admin_email' ) ) {
			self::mail(
				LCCL_DE_Settings::admin_address(),
				sprintf( __( 'New blood donor registration: %s', 'lccl-de' ), $name ),
				self::admin_html( $name, $values, $bank, $phone, $insert_id )
			);
		}
	}

	/**
	 * HTML mail with the same From as payment.colomboleads.org.
	 *
	 * @param string $to      Recipient.
	 * @param string $subject Subject.
	 * @param string $html    Body.
	 */
	private static function mail( $to, $subject, $html ) {
		$from_email = static function () {
			return 'noreply@colomboleads.org';
		};
		$from_name  = static function () {
			return 'colomboleads';
		};

		add_filter( 'wp_mail_from', $from_email );
		add_filter( 'wp_mail_from_name', $from_name );

		wp_mail(
			$to,
			$subject,
			$html,
			array(
				'Content-Type: text/html; charset=UTF-8',
				'From: colomboleads <noreply@colomboleads.org>',
			)
		);

		remove_filter( 'wp_mail_from', $from_email );
		remove_filter( 'wp_mail_from_name', $from_name );
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

		return '<html><body><div style="background-color:#f0f0f0;padding:20px;">
			<img src="' . esc_url( $logo ) . '" alt="LCCL Logo" style="max-width:200px;">
			<h1 style="font-size:24px;color:#333;">LCCL Blood Donation</h1>
			<p>Hello ' . esc_html( $name ) . ',</p>
			<p>Congratulations! Your online registration for blood donation was successful. Your willingness to donate is truly life-saving. We appreciate your generosity.</p>
			<p>Your preferred blood bank: ' . esc_html( $bank ) . '.</p>
			<p>Thank you,</p>
			<p>Lions Club of Colombo LEADS.<br>Sri Lanka.</p>
		</div></body></html>';
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
