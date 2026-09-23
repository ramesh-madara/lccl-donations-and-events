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
	 * Legacy submenu slug. Still accepted on POST for old bookmarks.
	 */
	const PAGE = 'lccl-de-blood-notify';

	/**
	 * Option that stores Blood Donation switches and shared SMS credentials.
	 */
	const OPTION = 'lccl_de_notify';

	/**
	 * Option that stores Join Our Projects notification switches.
	 */
	const OPTION_PROJECTS = 'lccl_de_projects_notify';

	/**
	 * Option that stores Free Spectacles notification switches.
	 */
	const OPTION_SPECTACLES = 'lccl_de_spectacles_notify';

	/**
	 * Option that stores MPGS payment gateway credentials.
	 */
	const OPTION_MPGS = 'lccl_de_mpgs';

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
	 * SMS username and password are decrypted in memory only and are shared
	 * across programmes. Toggles and staff inboxes are per programme.
	 *
	 * @param string $program blood-donation|our-projects.
	 * @return array{donor_sms:int,donor_email:int,admin_email:int,admin_addresses:array,admin_address:string,sms_api_key:string,sms_password:string}
	 */
	public static function get( $program = '' ) {
		$program    = self::normalize_program( $program );
		$sms_stored = get_option( self::OPTION, array() );
		if ( ! is_array( $sms_stored ) ) {
			$sms_stored = array();
		}

		$stored = $sms_stored;
		if ( LCCL_DE_Admin_Programs::PROGRAM_PROJECTS === $program ) {
			$stored = get_option( self::OPTION_PROJECTS, array() );
			if ( ! is_array( $stored ) ) {
				$stored = array();
			}
		} elseif ( LCCL_DE_Admin_Programs::PROGRAM_SPECTACLES === $program ) {
			$stored = get_option( self::OPTION_SPECTACLES, array() );
			if ( ! is_array( $stored ) ) {
				$stored = array();
			}
		}

		$settings = wp_parse_args(
			$stored,
			array(
				'donor_sms'    => 1,
				'donor_email'  => 1,
				'admin_email'  => 1,
				'sms_api_key'  => '',
				'sms_password' => '',
			)
		);

		$settings['admin_addresses'] = self::emails_from_stored( $stored );
		$settings['admin_address']   = isset( $settings['admin_addresses'][0] ) ? $settings['admin_addresses'][0] : '';
		$settings['sms_api_key']     = self::decrypt_secret( isset( $sms_stored['sms_api_key'] ) ? $sms_stored['sms_api_key'] : '' );
		$settings['sms_password']    = self::decrypt_secret( isset( $sms_stored['sms_password'] ) ? $sms_stored['sms_password'] : '' );
		$settings['sms_ready']       = '' !== $settings['sms_api_key'] && '' !== $settings['sms_password'];
		$settings['program']         = $program;

		return $settings;
	}

	/**
	 * blood-donation or our-projects.
	 *
	 * @param string $program Raw programme key.
	 * @return string
	 */
	public static function normalize_program( $program ) {
		$program = sanitize_key( (string) $program );
		if ( LCCL_DE_Admin_Programs::PROGRAM_PROJECTS === $program ) {
			return LCCL_DE_Admin_Programs::PROGRAM_PROJECTS;
		}

		if ( LCCL_DE_Admin_Programs::PROGRAM_SPECTACLES === $program ) {
			return LCCL_DE_Admin_Programs::PROGRAM_SPECTACLES;
		}

		return LCCL_DE_Admin_Programs::PROGRAM_BLOOD;
	}

	/**
	 * Whether a named notification is enabled.
	 *
	 * @param string $key     donor_sms|donor_email|admin_email.
	 * @param string $program blood-donation|our-projects.
	 * @return bool
	 */
	public static function enabled( $key, $program = '' ) {
		$settings = self::get( $program );
		return ! empty( $settings[ $key ] );
	}

	/**
	 * Persist switches from wp-admin or REST.
	 *
	 * @param array  $input   Raw values.
	 * @param string $program blood-donation|our-projects.
	 * @return array{donor_sms:int,donor_email:int,admin_email:int,admin_addresses:array,admin_address:string}
	 */
	public static function save( $input, $program = '' ) {
		$program = self::normalize_program( $program );
		$before  = self::get( $program );
		$clean   = self::sanitize( $input, $program );

		$blood = get_option( self::OPTION, array() );
		if ( ! is_array( $blood ) ) {
			$blood = array();
		}

		$blood['sms_api_key']  = self::encrypt_secret( $clean['sms_api_key'] );
		$blood['sms_password'] = self::encrypt_secret( $clean['sms_password'] );

		$toggles = array(
			'donor_sms'       => $clean['donor_sms'],
			'donor_email'     => $clean['donor_email'],
			'admin_email'     => $clean['admin_email'],
			'admin_addresses' => $clean['admin_addresses'],
			'admin_address'   => $clean['admin_address'],
		);

		if ( LCCL_DE_Admin_Programs::PROGRAM_PROJECTS === $program ) {
			update_option( self::OPTION, $blood );
			update_option( self::OPTION_PROJECTS, $toggles );
		} elseif ( LCCL_DE_Admin_Programs::PROGRAM_SPECTACLES === $program ) {
			update_option( self::OPTION, $blood );
			update_option( self::OPTION_SPECTACLES, $toggles );
		} else {
			update_option( self::OPTION, array_merge( $blood, $toggles ) );
		}

		if ( $before['sms_api_key'] !== $clean['sms_api_key'] || $before['sms_password'] !== $clean['sms_password'] ) {
			delete_transient( LCCL_DE_Notify::TOKEN_TRANSIENT );
		}

		return $clean;
	}

	/**
	 * Normalise a settings payload.
	 *
	 * @param array  $input   Raw values.
	 * @param string $program blood-donation|our-projects.
	 * @return array{donor_sms:int,donor_email:int,admin_email:int,admin_addresses:array,admin_address:string}
	 */
	public static function sanitize( $input, $program = '' ) {
		$program = self::normalize_program( $program );

		if ( ! is_array( $input ) ) {
			$input = array();
		}

		$addresses = self::collect_addresses( $input );
		if ( null === $addresses ) {
			$addresses = self::admin_addresses( $program );
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
	 * @param string $program blood-donation|our-projects.
	 * @return string[]
	 */
	public static function admin_addresses( $program = '' ) {
		$settings = self::get( $program );
		return isset( $settings['admin_addresses'] ) && is_array( $settings['admin_addresses'] )
			? $settings['admin_addresses']
			: array();
	}

	/**
	 * First staff inbox, kept for older callers.
	 *
	 * @param string $program blood-donation|our-projects.
	 * @return string
	 */
	public static function admin_address( $program = '' ) {
		$emails = self::admin_addresses( $program );
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
	 * Error if this program wants SMS but the shared Dialog login is missing.
	 *
	 * @return string
	 */
	private static function invalid_sms_credentials() {
		if ( empty( $_POST['donor_sms'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return '';
		}

		if ( '' === self::sms_api_key() || '' === self::sms_password() ) {
			return __( 'Save the Dialog SMS username and password on the SMS tab before turning SMS on.', 'lccl-de' );
		}

		return '';
	}

	/**
	 * Persist only the shared Dialog login.
	 *
	 * @param array $input Raw values.
	 * @return array{sms_api_key:string,sms_password:string}
	 */
	public static function save_sms( $input ) {
		if ( ! is_array( $input ) ) {
			$input = array();
		}

		$before = self::get();
		$stored = get_option( self::OPTION, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		$api_key = array_key_exists( 'sms_api_key', $input )
			? trim( sanitize_text_field( (string) $input['sms_api_key'] ) )
			: $before['sms_api_key'];

		$password = $before['sms_password'];
		if ( array_key_exists( 'sms_password', $input ) ) {
			$posted = trim( (string) $input['sms_password'] );
			if ( '' !== $posted ) {
				$password = $posted;
			}
		}

		$stored['sms_api_key']  = self::encrypt_secret( $api_key );
		$stored['sms_password'] = self::encrypt_secret( $password );
		update_option( self::OPTION, $stored );

		if ( $before['sms_api_key'] !== $api_key || $before['sms_password'] !== $password ) {
			delete_transient( LCCL_DE_Notify::TOKEN_TRANSIENT );
		}

		return array(
			'sms_api_key'  => $api_key,
			'sms_password' => $password,
		);
	}

	/**
	 * Error if the SMS tab is missing a username or password.
	 *
	 * @return string
	 */
	private static function invalid_sms_tab() {
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
		if ( ! is_admin() || ! current_user_can( 'read' ) ) {
			return;
		}

		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( LCCL_DE_Admin_Programs::PAGE !== $page && self::PAGE !== $page ) {
			return;
		}

		$program = self::posted_program();

		if ( ! empty( $_POST['lccl_de_sms_save'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			check_admin_referer( self::NONCE );

			$invalid = self::invalid_sms_tab();
			if ( $invalid ) {
				wp_safe_redirect(
					LCCL_DE_Admin_Programs::sms_url(
						array(
							'message' => 'error',
							'error'   => rawurlencode( $invalid ),
						)
					)
				);
				exit;
			}

			self::save_sms( wp_unslash( $_POST ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

			wp_safe_redirect(
				LCCL_DE_Admin_Programs::sms_url(
					array(
						'message' => 'saved',
					)
				)
			);
			exit;
		}

		if ( ! empty( $_POST['lccl_de_notify_test'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			check_admin_referer( self::NONCE );

			$to_raw = isset( $_POST['test_email'] ) ? sanitize_text_field( wp_unslash( $_POST['test_email'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$to     = sanitize_email( $to_raw );
			if ( ! LCCL_DE_Blood_Donor_Submissions::is_valid_email_field( $to_raw ) || ! is_email( $to ) ) {
				wp_safe_redirect(
					LCCL_DE_Admin_Programs::notify_url(
						$program,
						array(
							'message' => 'error',
							'error'   => rawurlencode( __( 'Please enter a valid email address.', 'lccl-de' ) ),
						)
					)
				);
				exit;
			}

			$kind = isset( $_POST['test_kind'] ) ? sanitize_key( wp_unslash( $_POST['test_kind'] ) ) : 'both'; // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$ok   = LCCL_DE_Notify::send_test( $to, $kind, $program );

			wp_safe_redirect(
				LCCL_DE_Admin_Programs::notify_url(
					$program,
					array(
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
				LCCL_DE_Admin_Programs::notify_url(
					$program,
					array(
						'message' => 'error',
						'error'   => rawurlencode( $invalid ),
					)
				)
			);
			exit;
		}

		self::save( wp_unslash( $_POST ), $program ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

		wp_safe_redirect(
			LCCL_DE_Admin_Programs::notify_url(
				$program,
				array(
					'message' => 'saved',
				)
			)
		);
		exit;
	}

	/**
	 * Programme for this notifications POST.
	 *
	 * @return string
	 */
	private static function posted_program() {
		$program = '';
		if ( ! empty( $_POST['lccl_de_notify_program'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$program = sanitize_key( wp_unslash( $_POST['lccl_de_notify_program'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		} elseif ( ! empty( $_GET['program'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$program = sanitize_key( wp_unslash( $_GET['program'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		return self::normalize_program( $program );
	}

	/**
	 * Legacy renderer — the workspace now lives under LCCL Programs.
	 */
	public static function render() {
		wp_safe_redirect( LCCL_DE_Admin_Programs::blood_url() );
		exit;
	}

	// ------------------------------------------------------------------
	// MPGS Payment Gateway credentials
	// ------------------------------------------------------------------

	/**
	 * Default / blank MPGS configuration.
	 *
	 * @return array{gateway_url:string,api_version:int,merchant_id:string,api_password:string}
	 */
	private static function mpgs_defaults() {
		return array(
			'gateway_url'  => 'https://ap-gateway.mastercard.com/',
			'api_version'  => LCCL_DE_MPGS_Client::DEFAULT_API_VERSION,
			'merchant_id'  => '',
			'api_password' => '',
		);
	}

	/**
	 * Retrieve MPGS credentials from wp_options (api_password decrypted).
	 *
	 * @return array{gateway_url:string,api_version:int,merchant_id:string,api_password:string}
	 */
	public static function get_mpgs() {
		$stored = get_option( self::OPTION_MPGS, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		$defaults = self::mpgs_defaults();
		$cfg      = wp_parse_args( $stored, $defaults );

		$cfg['gateway_url']  = esc_url_raw( (string) $cfg['gateway_url'] );
		$cfg['api_version']  = (int) $cfg['api_version'];
		$cfg['merchant_id']  = sanitize_text_field( (string) $cfg['merchant_id'] );
		$cfg['api_password'] = self::decrypt_secret( isset( $stored['api_password'] ) ? (string) $stored['api_password'] : '' );

		return $cfg;
	}

	/**
	 * Persist MPGS credentials. api_password is AES-256-GCM encrypted at rest.
	 *
	 * @param array $input Raw posted values.
	 * @return array Saved (decrypted) values.
	 */
	public static function save_mpgs( $input ) {
		if ( ! is_array( $input ) ) {
			$input = array();
		}

		$before = self::get_mpgs();

		$gateway_url = array_key_exists( 'gateway_url', $input )
			? esc_url_raw( trim( (string) $input['gateway_url'] ) )
			: $before['gateway_url'];

		$api_version = array_key_exists( 'api_version', $input )
			? (int) $input['api_version']
			: $before['api_version'];

		$merchant_id = array_key_exists( 'merchant_id', $input )
			? sanitize_text_field( trim( (string) $input['merchant_id'] ) )
			: $before['merchant_id'];

		// Only update the password if a non-empty value was posted.
		$api_password = $before['api_password'];
		if ( array_key_exists( 'api_password', $input ) ) {
			$posted = trim( (string) $input['api_password'] );
			if ( '' !== $posted ) {
				$api_password = $posted;
			}
		}

		update_option(
			self::OPTION_MPGS,
			array(
				'gateway_url'  => $gateway_url,
				'api_version'  => $api_version,
				'merchant_id'  => $merchant_id,
				'api_password' => self::encrypt_secret( $api_password ),
			)
		);

		return array(
			'gateway_url'  => $gateway_url,
			'api_version'  => $api_version,
			'merchant_id'  => $merchant_id,
			'api_password' => $api_password,
		);
	}

	/**
	 * Whether all required MPGS credentials are saved and non-empty.
	 *
	 * @return bool
	 */
	public static function mpgs_is_configured() {
		$cfg = self::get_mpgs();
		return '' !== $cfg['gateway_url']
			&& '' !== $cfg['merchant_id']
			&& '' !== $cfg['api_password'];
	}
}
