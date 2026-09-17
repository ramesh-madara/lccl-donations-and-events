<?php
/**
 * Blood donation notification switches in wp-admin.
 *
 * @package LCCL_Donations_And_Events
 */

defined( 'ABSPATH' ) || exit;

/**
 * Lets admins turn donor SMS, donor email, and the admin copy on or off.
 */
class LCCL_DE_Settings {

	/**
	 * Submenu slug under Blood Donation Users.
	 */
	const PAGE = 'lccl-de-blood-notify';

	/**
	 * Option that stores the switches.
	 */
	const OPTION = 'lccl_de_notify';

	/**
	 * Nonce for saving.
	 */
	const NONCE = 'lccl_de_save_notify';

	/**
	 * Prefix for AES-256-GCM values in the options table.
	 */
	const SECRET_PREFIX = 'lccl1:';

	/**
	 * Hook save handler. The menu lives on LCCL Programs.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'upgrade_secrets' ), 1 );
		add_action( 'admin_init', array( __CLASS__, 'handle_post' ) );
	}

	/**
	 * Current settings with defaults (all on).
	 *
	 * SMS username and password are decrypted in memory only.
	 *
	 * @return array{donor_sms:int,donor_email:int,admin_email:int,admin_addresses:array,admin_address:string,sms_api_key:string,sms_password:string}
	 */
	public static function get() {
		$stored = get_option( self::OPTION, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		$settings = wp_parse_args(
			$stored,
			array(
				'donor_sms'     => 1,
				'donor_email'   => 1,
				'admin_email'   => 1,
				'sms_api_key'   => '',
				'sms_password'  => '',
			)
		);

		$settings['admin_addresses'] = self::emails_from_stored( $stored );
		$settings['admin_address']   = isset( $settings['admin_addresses'][0] ) ? $settings['admin_addresses'][0] : '';
		$settings['sms_api_key']     = self::decrypt_secret( isset( $stored['sms_api_key'] ) ? $stored['sms_api_key'] : '' );
		$settings['sms_password']    = self::decrypt_secret( isset( $stored['sms_password'] ) ? $stored['sms_password'] : '' );
		$settings['sms_ready']       = '' !== $settings['sms_api_key'] && '' !== $settings['sms_password'];

		return $settings;
	}

	/**
	 * Whether a named notification is enabled.
	 *
	 * @param string $key donor_sms|donor_email|admin_email.
	 * @return bool
	 */
	public static function enabled( $key ) {
		$settings = self::get();
		return ! empty( $settings[ $key ] );
	}

	/**
	 * Persist switches from wp-admin or REST.
	 *
	 * @param array $input Raw values.
	 * @return array{donor_sms:int,donor_email:int,admin_email:int,admin_addresses:array,admin_address:string}
	 */
	public static function save( $input ) {
		$before = self::get();
		$clean  = self::sanitize( $input );
		update_option( self::OPTION, self::with_encrypted_secrets( $clean ) );

		if ( $before['sms_api_key'] !== $clean['sms_api_key'] || $before['sms_password'] !== $clean['sms_password'] ) {
			delete_transient( LCCL_DE_Notify::TOKEN_TRANSIENT );
		}

		return $clean;
	}

	/**
	 * Normalise a settings payload.
	 *
	 * @param array $input Raw values.
	 * @return array{donor_sms:int,donor_email:int,admin_email:int,admin_addresses:array,admin_address:string}
	 */
	public static function sanitize( $input ) {
		if ( ! is_array( $input ) ) {
			$input = array();
		}

		$addresses = self::collect_addresses( $input );
		if ( null === $addresses ) {
			$addresses = self::admin_addresses();
		}

		$stored = get_option( self::OPTION, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		$api_key = array_key_exists( 'sms_api_key', $input )
			? trim( sanitize_text_field( (string) $input['sms_api_key'] ) )
			: self::decrypt_secret( isset( $stored['sms_api_key'] ) ? $stored['sms_api_key'] : '' );

		$password = self::decrypt_secret( isset( $stored['sms_password'] ) ? $stored['sms_password'] : '' );
		if ( array_key_exists( 'sms_password', $input ) ) {
			$posted = trim( (string) $input['sms_password'] );
			if ( '' !== $posted ) {
				$password = $posted;
			}
		}

		return array(
			'donor_sms'       => self::flag( isset( $input['donor_sms'] ) ? $input['donor_sms'] : 0 ),
			'donor_email'     => self::flag( isset( $input['donor_email'] ) ? $input['donor_email'] : 0 ),
			'admin_email'     => self::flag( isset( $input['admin_email'] ) ? $input['admin_email'] : 0 ),
			'admin_addresses' => $addresses,
			'admin_address'   => isset( $addresses[0] ) ? $addresses[0] : '',
			'sms_api_key'     => $api_key,
			'sms_password'    => $password,
		);
	}

	/**
	 * Encrypt any SMS secrets still stored as plain text.
	 */
	public static function upgrade_secrets() {
		$stored = get_option( self::OPTION, array() );
		if ( ! is_array( $stored ) ) {
			return;
		}

		$key_raw  = isset( $stored['sms_api_key'] ) ? (string) $stored['sms_api_key'] : '';
		$pass_raw = isset( $stored['sms_password'] ) ? (string) $stored['sms_password'] : '';
		if ( ! self::secret_is_plain( $key_raw ) && ! self::secret_is_plain( $pass_raw ) ) {
			return;
		}

		$stored['sms_api_key']  = self::encrypt_secret( self::decrypt_secret( $key_raw ) );
		$stored['sms_password'] = self::encrypt_secret( self::decrypt_secret( $pass_raw ) );
		update_option( self::OPTION, $stored );
	}

	/**
	 * Copy of settings with SMS secrets encrypted for the options table.
	 *
	 * @param array $clean Sanitised in-memory values.
	 * @return array
	 */
	private static function with_encrypted_secrets( array $clean ) {
		$clean['sms_api_key']  = self::encrypt_secret( isset( $clean['sms_api_key'] ) ? $clean['sms_api_key'] : '' );
		$clean['sms_password'] = self::encrypt_secret( isset( $clean['sms_password'] ) ? $clean['sms_password'] : '' );
		return $clean;
	}

	/**
	 * Whether a stored secret is non-empty plain text.
	 *
	 * @param string $value Raw option value.
	 * @return bool
	 */
	private static function secret_is_plain( $value ) {
		$value = (string) $value;
		return '' !== $value && 0 !== strpos( $value, self::SECRET_PREFIX );
	}

	/**
	 * AES-256-GCM encrypt a Dialog credential. Empty stays empty.
	 *
	 * @param string $plain Decrypted value.
	 * @return string
	 */
	private static function encrypt_secret( $plain ) {
		$plain = (string) $plain;
		if ( '' === $plain ) {
			return '';
		}

		$key = self::secret_key();
		if ( '' === $key ) {
			return $plain;
		}

		$iv  = random_bytes( 12 );
		$tag = '';
		$raw = openssl_encrypt( $plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, '', 16 );
		if ( false === $raw || 16 !== strlen( $tag ) ) {
			return $plain;
		}

		return self::SECRET_PREFIX . base64_encode( $iv . $tag . $raw );
	}

	/**
	 * Decrypt a Dialog credential. Legacy plain text is returned as-is.
	 *
	 * @param mixed $stored Raw option value.
	 * @return string
	 */
	private static function decrypt_secret( $stored ) {
		$stored = (string) $stored;
		if ( '' === $stored ) {
			return '';
		}

		if ( 0 !== strpos( $stored, self::SECRET_PREFIX ) ) {
			return $stored;
		}

		$key = self::secret_key();
		if ( '' === $key ) {
			return '';
		}

		$blob = base64_decode( substr( $stored, strlen( self::SECRET_PREFIX ) ), true );
		if ( false === $blob || strlen( $blob ) < 29 ) {
			return '';
		}

		$iv     = substr( $blob, 0, 12 );
		$tag    = substr( $blob, 12, 16 );
		$cipher = substr( $blob, 28 );
		$plain  = openssl_decrypt( $cipher, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag );
		return false === $plain ? '' : $plain;
	}

	/**
	 * 32-byte key from WordPress salts in wp-config.php, not from the database.
	 *
	 * @return string
	 */
	private static function secret_key() {
		if ( ! function_exists( 'openssl_encrypt' ) ) {
			return '';
		}

		return hash( 'sha256', 'lccl-de-sms-v1|' . wp_salt( 'auth' ) . '|' . wp_salt( 'secure_auth' ), true );
	}

	/**
	 * Treat 1 / true / on as enabled.
	 *
	 * @param mixed $value Incoming flag.
	 * @return int
	 */
	private static function flag( $value ) {
		if ( true === $value || 1 === $value || '1' === $value || 'true' === $value || 'on' === $value ) {
			return 1;
		}

		return 0;
	}

	/**
	 * Staff inboxes for the admin copy.
	 *
	 * @return string[]
	 */
	public static function admin_addresses() {
		$settings = self::get();
		return isset( $settings['admin_addresses'] ) && is_array( $settings['admin_addresses'] )
			? $settings['admin_addresses']
			: array();
	}

	/**
	 * First staff inbox, kept for older callers.
	 *
	 * @return string
	 */
	public static function admin_address() {
		$emails = self::admin_addresses();
		return isset( $emails[0] ) ? $emails[0] : '';
	}

	/**
	 * Dialog e-SMS API key saved in wp-admin.
	 *
	 * @return string
	 */
	public static function sms_api_key() {
		$settings = self::get();
		return isset( $settings['sms_api_key'] ) ? (string) $settings['sms_api_key'] : '';
	}

	/**
	 * Dialog e-SMS password saved in wp-admin.
	 *
	 * @return string
	 */
	public static function sms_password() {
		$settings = self::get();
		return isset( $settings['sms_password'] ) ? (string) $settings['sms_password'] : '';
	}

	/**
	 * Pull a unique email list out of stored options, including the old single field.
	 *
	 * @param array $stored Raw option.
	 * @return string[]
	 */
	private static function emails_from_stored( $stored ) {
		if ( array_key_exists( 'admin_addresses', $stored ) ) {
			return self::clean_email_list( $stored['admin_addresses'] );
		}

		if ( isset( $stored['admin_address'] ) ) {
			$emails = self::clean_email_list( $stored['admin_address'] );
			return $emails ? $emails : array( 'admin@colomboleads.org' );
		}

		return array( 'admin@colomboleads.org' );
	}

	/**
	 * Addresses from a save payload, or null to keep the stored list.
	 *
	 * @param array $input Raw values.
	 * @return string[]|null
	 */
	private static function collect_addresses( $input ) {
		if ( array_key_exists( 'admin_addresses', $input ) ) {
			return self::clean_email_list( $input['admin_addresses'] );
		}

		if ( array_key_exists( 'admin_address', $input ) ) {
			return self::clean_email_list( $input['admin_address'] );
		}

		return null;
	}

	/**
	 * Unique valid emails from a string or array.
	 *
	 * @param mixed $raw Incoming value.
	 * @return string[]
	 */
	private static function clean_email_list( $raw ) {
		if ( is_string( $raw ) ) {
			$raw = preg_split( '/[\s,;]+/', $raw );
		}

		if ( ! is_array( $raw ) ) {
			return array();
		}

		$out = array();
		foreach ( $raw as $email ) {
			$email = trim( (string) $email );
			if ( '' === $email ) {
				continue;
			}

			if ( ! LCCL_DE_Blood_Donor_Submissions::is_valid_email_field( $email ) ) {
				continue;
			}

			$email = sanitize_email( $email );
			if ( ! is_email( $email ) ) {
				continue;
			}

			$seen = false;
			foreach ( $out as $existing ) {
				if ( 0 === strcasecmp( $existing, $email ) ) {
					$seen = true;
					break;
				}
			}

			if ( ! $seen ) {
				$out[] = $email;
			}
		}

		return $out;
	}

	/**
	 * Error if a posted staff address is present but not a real email.
	 *
	 * @return string Empty when the list is usable.
	 */
	private static function invalid_posted_address() {
		if ( ! isset( $_POST['admin_addresses'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return '';
		}

		$raw = wp_unslash( $_POST['admin_addresses'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! is_array( $raw ) ) {
			$raw = array( $raw );
		}

		$has_valid = false;
		foreach ( $raw as $email ) {
			$email = trim( (string) $email );
			if ( '' === $email ) {
				continue;
			}

			if ( ! LCCL_DE_Blood_Donor_Submissions::is_valid_email_field( $email ) || ! is_email( sanitize_email( $email ) ) ) {
				return __( 'Please enter a valid email address.', 'lccl-de' );
			}

			$has_valid = true;
		}

		if ( ! empty( $_POST['admin_email'] ) && ! $has_valid ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return __( 'Enter at least one valid staff email address.', 'lccl-de' );
		}

		return '';
	}

	/**
	 * Error if SMS is on but the Dialog credentials are incomplete.
	 *
	 * @return string
	 */
	private static function invalid_sms_credentials() {
		if ( empty( $_POST['donor_sms'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return '';
		}

		$key = isset( $_POST['sms_api_key'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['sms_api_key'] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$posted_pass = isset( $_POST['sms_password'] ) ? trim( (string) wp_unslash( $_POST['sms_password'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$stored_pass = self::sms_password();

		if ( '' === $key || ( '' === $posted_pass && '' === $stored_pass ) ) {
			return __( 'Enter the SMS username and password.', 'lccl-de' );
		}

		return '';
	}

	/**
	 * Save toggles or send a test email.
	 */
	public static function handle_post() {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( LCCL_DE_Admin_Programs::PAGE !== $page && self::PAGE !== $page ) {
			return;
		}

		if ( ! empty( $_POST['lccl_de_notify_test'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			check_admin_referer( self::NONCE );

			$to_raw = isset( $_POST['test_email'] ) ? sanitize_text_field( wp_unslash( $_POST['test_email'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$to     = sanitize_email( $to_raw );
			if ( ! LCCL_DE_Blood_Donor_Submissions::is_valid_email_field( $to_raw ) || ! is_email( $to ) ) {
				wp_safe_redirect(
					LCCL_DE_Admin_Programs::blood_url(
						array(
							'tab'     => 'notifications',
							'message' => 'error',
							'error'   => rawurlencode( __( 'Please enter a valid email address.', 'lccl-de' ) ),
						)
					)
				);
				exit;
			}

			$kind = isset( $_POST['test_kind'] ) ? sanitize_key( wp_unslash( $_POST['test_kind'] ) ) : 'both'; // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$ok   = LCCL_DE_Notify::send_test( $to, $kind );

			wp_safe_redirect(
				LCCL_DE_Admin_Programs::blood_url(
					array(
						'tab'     => 'notifications',
						'message' => $ok ? 'test-ok' : 'test-fail',
					)
				)
			);
			exit;
		}

		if ( empty( $_POST['lccl_de_notify_save'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return;
		}

		check_admin_referer( self::NONCE );

		$invalid = self::invalid_posted_address();
		if ( '' === $invalid ) {
			$invalid = self::invalid_sms_credentials();
		}
		if ( $invalid ) {
			wp_safe_redirect(
				LCCL_DE_Admin_Programs::blood_url(
					array(
						'tab'     => 'notifications',
						'message' => 'error',
						'error'   => rawurlencode( $invalid ),
					)
				)
			);
			exit;
		}

		self::save( wp_unslash( $_POST ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

		wp_safe_redirect(
			LCCL_DE_Admin_Programs::blood_url(
				array(
					'tab'     => 'notifications',
					'message' => 'saved',
				)
			)
		);
		exit;
	}

	/**
	 * Legacy renderer — the workspace now lives under LCCL Programs.
	 */
	public static function render() {
		wp_safe_redirect( LCCL_DE_Admin_Programs::blood_url( array( 'tab' => 'notifications' ) ) );
		exit;
	}
}
