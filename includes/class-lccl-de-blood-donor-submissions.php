<?php
/**
 * Blood donor registration persistence and POST handling.
 *
 * @package LCCL_Donations_And_Events
 */

defined( 'ABSPATH' ) || exit;

/**
 * Validates, stores, and flashes the result of a registration.
 */
class LCCL_DE_Blood_Donor_Submissions {

	/**
	 * admin-post.php action name. Must match the hidden field on the form.
	 */
	const ACTION = 'lccl_de_blood_donor_register';

	/**
	 * Query arg used to pass a flash token back to the form page.
	 */
	const FLASH_QUERY = 'lccl_de';

	/**
	 * Hook the public and logged-in POST handlers.
	 */
	public static function init() {
		add_action( 'admin_post_nopriv_' . self::ACTION, array( __CLASS__, 'handle' ) );
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'handle' ) );
	}

	/**
	 * Consume a one-time flash payload for the current request.
	 *
	 * @return array{success:bool,values:array,errors:array}
	 */
	public static function consume_flash() {
		$empty = array(
			'success' => false,
			'values'  => array(),
			'errors'  => array(),
		);

		if ( empty( $_GET[ self::FLASH_QUERY ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return $empty;
		}

		$token = sanitize_key( wp_unslash( $_GET[ self::FLASH_QUERY ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( '' === $token ) {
			return $empty;
		}

		$flash = get_transient( self::flash_key( $token ) );
		delete_transient( self::flash_key( $token ) );

		if ( ! is_array( $flash ) ) {
			return $empty;
		}

		return wp_parse_args( $flash, $empty );
	}

	/**
	 * Handle a registration POST.
	 */
	public static function handle() {
		$redirect = self::safe_redirect_url();

		if ( ! isset( $_POST['lccl_de_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['lccl_de_nonce'] ) ), self::ACTION ) ) {
			self::redirect_with_flash(
				$redirect,
				array(
					'success' => false,
					'values'  => array(),
					'errors'  => array(
						'form' => __( 'The form expired. Please try again.', 'lccl-de' ),
					),
				)
			);
		}

		// Honeypot: bots fill hidden fields; humans never see this one.
		// Honeypot: bots fill hidden fields. Name is not "company" so browsers
		// do not autofill it and fake a successful registration.
		if ( ! empty( $_POST['lccl_de_hp'] ) ) {
			self::redirect_with_flash( $redirect, array( 'success' => true, 'values' => array(), 'errors' => array() ) );
		}

		if ( self::is_rate_limited() ) {
			self::redirect_with_flash(
				$redirect,
				array(
					'success' => false,
					'values'  => array(),
					'errors'  => array(
						'form' => __( 'Too many attempts from this connection. Please wait a while and try again.', 'lccl-de' ),
					),
				)
			);
		}

		$values = self::sanitize_request();
		$errors = self::validate( $values );

		if ( ! empty( $errors ) ) {
			self::redirect_with_flash(
				$redirect,
				array(
					'success' => false,
					'values'  => $values,
					'errors'  => $errors,
				)
			);
		}

		$inserted = self::insert( $values );
		if ( ! $inserted ) {
			self::redirect_with_flash(
				$redirect,
				array(
					'success' => false,
					'values'  => $values,
					'errors'  => array(
						'form' => __( 'The registration could not be saved. Please try again.', 'lccl-de' ),
					),
				)
			);
		}

		LCCL_DE_Notify::after_registration( $values, $inserted );

		self::redirect_with_flash(
			$redirect,
			array(
				'success' => true,
				'values'  => array(),
				'errors'  => array(),
			)
		);
	}

	/**
	 * Required field names, matching the asterisks on the form.
	 *
	 * @return array
	 */
	public static function required_fields() {
		return array(
			'first_name',
			'last_name',
			'address',
			'city',
			'postal_code',
			'phone',
			'district',
			'blood_bank',
			'contact_method',
			'consent',
		);
	}

	/**
	 * Pull and sanitise posted values.
	 *
	 * notify_campaigns is always a boolean: 1 if ticked, 0 if not.
	 *
	 * @return array
	 */
	public static function sanitize_request() {
		$post = wp_unslash( $_POST );

		$notify = ! empty( $post['notify_campaigns'] );
		$consent = ! empty( $post['consent'] );

		return array(
			'first_name'           => isset( $post['first_name'] ) ? sanitize_text_field( $post['first_name'] ) : '',
			'last_name'            => isset( $post['last_name'] ) ? sanitize_text_field( $post['last_name'] ) : '',
			'address'              => isset( $post['address'] ) ? sanitize_text_field( $post['address'] ) : '',
			'city'                 => isset( $post['city'] ) ? sanitize_text_field( $post['city'] ) : '',
			'postal_code'          => self::sanitize_postal_code( isset( $post['postal_code'] ) ? $post['postal_code'] : '' ),
			'email'                => self::sanitize_email_field( isset( $post['email'] ) ? $post['email'] : '' ),
			'phone'                => self::sanitize_phone_field( isset( $post['phone'] ) ? $post['phone'] : '' ),
			'district'             => isset( $post['district'] ) ? sanitize_text_field( $post['district'] ) : '',
			'blood_bank'           => isset( $post['blood_bank'] ) ? sanitize_text_field( $post['blood_bank'] ) : '',
			'donation_preference'  => isset( $post['donation_preference'] ) ? sanitize_key( $post['donation_preference'] ) : '',
			'donated_before'       => isset( $post['donated_before'] ) ? sanitize_key( $post['donated_before'] ) : '',
			'contact_method'       => isset( $post['contact_method'] ) ? sanitize_key( $post['contact_method'] ) : '',
			'notify_campaigns'     => $notify ? 1 : 0,
			'consent'              => $consent ? 1 : 0,
		);
	}

	/**
	 * Pull and sanitise a JSON or form-style payload (admin updates).
	 *
	 * @param array $source Raw field map.
	 * @return array
	 */
	public static function sanitize_payload( array $source ) {
		$notify_raw = isset( $source['notify_campaigns'] ) ? $source['notify_campaigns'] : 0;
		if ( is_bool( $notify_raw ) ) {
			$notify = $notify_raw ? 1 : 0;
		} else {
			$notify = in_array( (string) $notify_raw, array( '1', 'true', 'yes', 'on' ), true ) ? 1 : 0;
		}

		return array(
			'first_name'          => self::clip_field( isset( $source['first_name'] ) ? $source['first_name'] : '', 100 ),
			'last_name'           => self::clip_field( isset( $source['last_name'] ) ? $source['last_name'] : '', 100 ),
			'address'             => self::clip_field( isset( $source['address'] ) ? $source['address'] : '', 255 ),
			'city'                => self::clip_field( isset( $source['city'] ) ? $source['city'] : '', 100 ),
			'postal_code'         => self::sanitize_postal_code( isset( $source['postal_code'] ) ? $source['postal_code'] : '' ),
			'email'               => self::sanitize_email_field( isset( $source['email'] ) ? $source['email'] : '' ),
			'phone'               => self::sanitize_phone_field( isset( $source['phone'] ) ? $source['phone'] : '' ),
			'district'            => isset( $source['district'] ) ? sanitize_text_field( (string) $source['district'] ) : '',
			'blood_bank'          => isset( $source['blood_bank'] ) ? sanitize_text_field( (string) $source['blood_bank'] ) : '',
			'donation_preference' => isset( $source['donation_preference'] ) ? sanitize_key( $source['donation_preference'] ) : '',
			'donated_before'      => isset( $source['donated_before'] ) ? sanitize_key( $source['donated_before'] ) : '',
			'contact_method'      => isset( $source['contact_method'] ) ? sanitize_key( $source['contact_method'] ) : '',
			'notify_campaigns'    => $notify,
			'consent'             => ! empty( $source['consent'] ) ? 1 : 0,
		);
	}

	/**
	 * Truncate a sanitised string to a column width.
	 *
	 * @param mixed $value Raw value.
	 * @param int   $max   Max characters.
	 * @return string
	 */
	private static function clip_field( $value, $max ) {
		$text = sanitize_text_field( (string) $value );
		if ( strlen( $text ) <= $max ) {
			return $text;
		}

		return substr( $text, 0, $max );
	}

	/**
	 * Validate sanitised values. Returns field => message.
	 *
	 * @param array $values          Sanitised input.
	 * @param bool  $require_consent Whether consent must be ticked (public form only).
	 * @return array
	 */
	public static function validate( array $values, $require_consent = true ) {
		$errors   = array();
		$required = array(
			'first_name',
			'last_name',
			'address',
			'city',
			'postal_code',
			'phone',
			'district',
			'blood_bank',
			'contact_method',
		);
		$missing = false;

		foreach ( $required as $field ) {
			if ( '' === $values[ $field ] ) {
				$errors[ $field ] = self::required_field_message();
				$missing          = true;
			}
		}

		if ( $require_consent && empty( $values['consent'] ) ) {
			$errors['consent'] = self::required_field_message();
			$missing           = true;
		}

		if ( $missing ) {
			$errors['form'] = self::required_banner_message();
		}

		if ( '' === $values['district'] ) {
			$errors['blood_bank'] = self::blood_bank_district_message();
		}

		if ( '' !== $values['phone'] && ! self::is_valid_sl_phone( $values['phone'] ) ) {
			$errors['phone'] = self::phone_error_message();
		}

		if ( '' !== $values['postal_code'] && ! self::is_valid_postal_code( $values['postal_code'] ) ) {
			$errors['postal_code'] = self::postal_error_message();
		}

		if ( '' !== $values['email'] && ! self::is_valid_email_field( $values['email'] ) ) {
			$errors['email'] = self::email_error_message();
		} elseif ( 'email' === $values['contact_method'] && '' === $values['email'] ) {
			$errors['email'] = __( 'Please enter an email address so we can contact you by email.', 'lccl-de' );
		}

		$districts = LCCL_DE_Blood_Donor_Form::get_districts();
		if ( '' !== $values['district'] && ! array_key_exists( $values['district'], $districts ) ) {
			$errors['district'] = __( 'Please choose a valid district.', 'lccl-de' );
		}

		if ( '' !== $values['blood_bank'] && '' !== $values['district'] && ! LCCL_DE_Blood_Donor_Form::is_valid_blood_bank( $values['blood_bank'], $values['district'] ) ) {
			$errors['blood_bank'] = __( 'Please choose a blood bank in the selected district.', 'lccl-de' );
		}

		$preferences = LCCL_DE_Blood_Donor_Form::get_donation_preferences();
		if ( '' !== $values['donation_preference'] && ! array_key_exists( $values['donation_preference'], $preferences ) ) {
			$errors['donation_preference'] = __( 'Please choose a valid donation preference.', 'lccl-de' );
		}

		$history = LCCL_DE_Blood_Donor_Form::get_donation_history_options();
		if ( '' !== $values['donated_before'] && ! array_key_exists( $values['donated_before'], $history ) ) {
			$errors['donated_before'] = __( 'Please choose a valid option.', 'lccl-de' );
		}

		$methods = LCCL_DE_Blood_Donor_Form::get_contact_methods();
		if ( '' !== $values['contact_method'] && ! array_key_exists( $values['contact_method'], $methods ) ) {
			$errors['contact_method'] = __( 'Please choose a valid contact method.', 'lccl-de' );
		}

		return $errors;
	}

	/**
	 * Message shown when the phone number is not a Sri Lankan format.
	 *
	 * @return string
	 */
	public static function phone_error_message() {
		return __( 'Enter a Sri Lankan phone number: 10 digits, or +94 followed by 9 digits.', 'lccl-de' );
	}

	/**
	 * Message shown when the optional email is present but invalid.
	 *
	 * @return string
	 */
	public static function email_error_message() {
		return __( 'Please enter a valid email address, or leave it blank.', 'lccl-de' );
	}

	/**
	 * Message shown when the postal code is not exactly five digits.
	 *
	 * @return string
	 */
	public static function postal_error_message() {
		return __( 'Enter a 5-digit postal code. Numbers only.', 'lccl-de' );
	}

	/**
	 * Message shown under an empty required field.
	 *
	 * @return string
	 */
	public static function required_field_message() {
		return __( 'This field is required.', 'lccl-de' );
	}

	/**
	 * Top-of-form message when required fields are empty.
	 *
	 * @return string
	 */
	public static function required_banner_message() {
		return __( 'Please complete all fields marked with an *.', 'lccl-de' );
	}

	/**
	 * Message shown when Preferred Blood Bank is used before a district.
	 *
	 * @return string
	 */
	public static function blood_bank_district_message() {
		return __( 'Please select your district first, then select a blood bank.', 'lccl-de' );
	}

	/**
	 * Keep digits only, at most five.
	 *
	 * @param mixed $value Raw postal code.
	 * @return string
	 */
	public static function sanitize_postal_code( $value ) {
		$digits = preg_replace( '/\D+/', '', (string) $value );
		return substr( (string) $digits, 0, 5 );
	}

	/**
	 * Sri Lankan postal codes are exactly five digits.
	 *
	 * @param string $value Sanitised postal code.
	 * @return bool
	 */
	public static function is_valid_postal_code( $value ) {
		return (bool) preg_match( '/^\d{5}$/', (string) $value );
	}

	/**
	 * Keep a typed email for redisplay; only fully sanitise valid ones.
	 *
	 * @param string $email Raw posted email.
	 * @return string
	 */
	public static function sanitize_email_field( $email ) {
		$email = sanitize_text_field( $email );
		if ( self::is_valid_email_field( $email ) ) {
			return sanitize_email( $email );
		}

		return $email;
	}

	/**
	 * Optional email: empty is allowed, otherwise RFC-shaped and within the column.
	 *
	 * @param string $email Sanitised email.
	 * @return bool
	 */
	public static function is_valid_email_field( $email ) {
		$email = trim( (string) $email );
		if ( '' === $email ) {
			return true;
		}

		if ( strlen( $email ) > 191 ) {
			return false;
		}

		if ( ! is_email( $email ) || false !== strpos( $email, '..' ) ) {
			return false;
		}

		return (bool) preg_match( '/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/', $email );
	}

	/**
	 * Normalise a valid Sri Lankan number; leave invalid input readable.
	 *
	 * @param string $phone Raw posted phone.
	 * @return string
	 */
	public static function sanitize_phone_field( $phone ) {
		$phone = sanitize_text_field( $phone );
		if ( self::is_valid_sl_phone( $phone ) ) {
			return self::normalize_sl_phone( $phone );
		}

		return $phone;
	}

	/**
	 * Accept local 0XXXXXXXXX or international +94XXXXXXXXX / 94XXXXXXXXX.
	 *
	 * @param string $phone Posted or sanitised phone.
	 * @return bool
	 */
	public static function is_valid_sl_phone( $phone ) {
		$phone  = trim( (string) $phone );
		$digits = preg_replace( '/\D+/', '', $phone );
		if ( '' === $digits ) {
			return false;
		}

		$international = ( 0 === strpos( $phone, '+' ) || 0 === strpos( $digits, '94' ) );
		$national      = self::sl_national_number( $phone );

		if ( $international ) {
			return (bool) preg_match( '/^[1-9][0-9]{8}$/', $national );
		}

		return (bool) preg_match( '/^0[1-9][0-9]{8}$/', $digits );
	}

	/**
	 * Store +94… when the donor used the country code, otherwise 0… .
	 *
	 * @param string $phone Valid phone.
	 * @return string
	 */
	public static function normalize_sl_phone( $phone ) {
		$national = self::sl_national_number( $phone );
		if ( ! preg_match( '/^[1-9][0-9]{8}$/', $national ) ) {
			return trim( (string) $phone );
		}

		$digits = preg_replace( '/\D+/', '', (string) $phone );
		if ( 0 === strpos( ltrim( (string) $phone ), '+' ) || 0 === strpos( $digits, '94' ) ) {
			return '+94' . $national;
		}

		return '0' . $national;
	}

	/**
	 * Dialog MSISDN: 94 plus the 9-digit national number.
	 *
	 * @param string $phone Stored phone.
	 * @return string
	 */
	public static function phone_to_msisdn( $phone ) {
		$national = self::sl_national_number( $phone );
		if ( preg_match( '/^[1-9][0-9]{8}$/', $national ) ) {
			return '94' . $national;
		}

		return preg_replace( '/\s+/', '', (string) $phone );
	}

	/**
	 * Nine-digit national number with trunk 0 and +94 stripped.
	 *
	 * @param string $phone Phone input.
	 * @return string
	 */
	public static function sl_national_number( $phone ) {
		$digits = preg_replace( '/\D+/', '', (string) $phone );
		if ( '' === $digits ) {
			return '';
		}

		if ( 0 === strpos( $digits, '94' ) && strlen( $digits ) > 2 ) {
			$digits = substr( $digits, 2 );
		}

		if ( 0 === strpos( $digits, '0' ) ) {
			$digits = substr( $digits, 1 );
		}

		return $digits;
	}

	/**
	 * Insert a valid registration.
	 *
	 * @param array $values Sanitised, validated values.
	 * @return int|false Insert ID or false.
	 */
	public static function insert( array $values ) {
		global $wpdb;

		$banks = LCCL_DE_Blood_Donor_Form::get_blood_banks( $values['district'] );
		$label = isset( $banks[ $values['blood_bank'] ] ) ? $banks[ $values['blood_bank'] ] : '';

		$result = $wpdb->insert(
			LCCL_DE_Schema::blood_donors_table(),
			array(
				'first_name'           => $values['first_name'],
				'last_name'            => $values['last_name'],
				'address'              => $values['address'],
				'city'                 => $values['city'],
				'postal_code'          => '' !== $values['postal_code'] ? $values['postal_code'] : null,
				'email'                => '' !== $values['email'] ? $values['email'] : null,
				'phone'                => $values['phone'],
				'district'             => $values['district'],
				'blood_bank'           => $values['blood_bank'],
				'blood_bank_label'     => $label,
				'donation_preference'  => '' !== $values['donation_preference'] ? $values['donation_preference'] : null,
				'donated_before'       => '' !== $values['donated_before'] ? $values['donated_before'] : null,
				'contact_method'       => $values['contact_method'],
				'notify_campaigns'     => (int) $values['notify_campaigns'],
				'consent'              => 1,
				'ip_address'           => self::request_ip(),
				'created_at'           => current_time( 'mysql' ),
			),
			array(
				'%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s',
				'%s', '%s', '%s', '%d', '%d', '%s', '%s',
			)
		);

		if ( false === $result ) {
			return false;
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Update a registration. Does not change consent, IP, or created_at.
	 *
	 * @param int   $id      Donor ID.
	 * @param array $values  Sanitised, validated values.
	 * @param int   $user_id Acting administrator.
	 * @return bool
	 */
	public static function update( $id, array $values, $user_id ) {
		global $wpdb;

		$id      = (int) $id;
		$user_id = (int) $user_id;
		if ( $id <= 0 || $user_id <= 0 ) {
			return false;
		}

		$banks = LCCL_DE_Blood_Donor_Form::get_blood_banks( $values['district'] );
		$label = isset( $banks[ $values['blood_bank'] ] ) ? $banks[ $values['blood_bank'] ] : '';

		$result = $wpdb->update(
			LCCL_DE_Schema::blood_donors_table(),
			array(
				'first_name'          => $values['first_name'],
				'last_name'           => $values['last_name'],
				'address'             => $values['address'],
				'city'                => $values['city'],
				'postal_code'         => '' !== $values['postal_code'] ? $values['postal_code'] : null,
				'email'               => '' !== $values['email'] ? $values['email'] : null,
				'phone'               => $values['phone'],
				'district'            => $values['district'],
				'blood_bank'          => $values['blood_bank'],
				'blood_bank_label'    => $label,
				'donation_preference' => '' !== $values['donation_preference'] ? $values['donation_preference'] : null,
				'donated_before'      => '' !== $values['donated_before'] ? $values['donated_before'] : null,
				'contact_method'      => $values['contact_method'],
				'notify_campaigns'    => (int) $values['notify_campaigns'],
				'updated_at'          => current_time( 'mysql' ),
				'updated_by'          => $user_id,
			),
			array( 'id' => $id ),
			array(
				'%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s',
				'%s', '%s', '%s', '%d', '%s', '%d',
			),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Transient key for a flash token.
	 *
	 * @param string $token Random token.
	 * @return string
	 */
	private static function flash_key( $token ) {
		return 'lccl_de_flash_' . $token;
	}

	/**
	 * Store a flash payload and redirect back to the form.
	 *
	 * @param string $url   Safe redirect target.
	 * @param array  $flash Payload.
	 */
	private static function redirect_with_flash( $url, array $flash ) {
		$token = wp_generate_password( 12, false, false );
		set_transient( self::flash_key( $token ), $flash, 10 * MINUTE_IN_SECONDS );

		wp_safe_redirect( add_query_arg( self::FLASH_QUERY, $token, $url ) );
		exit;
	}

	/**
	 * Redirect target: the referring form page, or home.
	 *
	 * @return string
	 */
	private static function safe_redirect_url() {
		if ( ! empty( $_POST['redirect_to'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$url = esc_url_raw( wp_unslash( $_POST['redirect_to'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			if ( $url ) {
				return $url;
			}
		}

		if ( ! empty( $_POST['_wp_http_referer'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$url = esc_url_raw( wp_unslash( $_POST['_wp_http_referer'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			if ( $url ) {
				return $url;
			}
		}

		return home_url( '/' );
	}

	/**
	 * Whether this IP has submitted too often recently.
	 *
	 * @return bool
	 */
	private static function is_rate_limited() {
		$ip = self::request_ip();
		if ( '' === $ip ) {
			return false;
		}

		$key   = 'lccl_de_rl_' . md5( $ip );
		$count = (int) get_transient( $key );
		if ( $count >= 8 ) {
			return true;
		}

		set_transient( $key, $count + 1, HOUR_IN_SECONDS );
		return false;
	}

	/**
	 * Sanitised remote address, IPv4 or IPv6.
	 *
	 * @return string
	 */
	private static function request_ip() {
		if ( empty( $_SERVER['REMOTE_ADDR'] ) ) {
			return '';
		}

		$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
	}
}
