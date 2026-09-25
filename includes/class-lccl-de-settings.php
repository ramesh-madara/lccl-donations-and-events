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
	 * Option that stores legacy MPGS payment gateway credentials.
	 */
	const OPTION_MPGS = 'lccl_de_mpgs';

	/**
	 * Option that stores multi-merchant MPGS payment gateway profiles.
	 */
	const OPTION_MPGS_PROFILES = 'lccl_de_mpgs_profiles';

	/**
	 * Option that stores CBC Paycenter Web 4.0 gateway credentials.
	 */
	const OPTION_PAYCENTER = 'lccl_de_paycenter';

	/**
	 * Option that stores multi-client CBC Paycenter Web 4.0 profiles.
	 */
	const OPTION_PAYCENTER_PROFILES = 'lccl_de_paycenter_profiles';

	const PROFILE_DONATIONS  = 'donations';
	const PROFILE_MEMBERSHIP = 'membership';
	const PROFILE_PROJECT_1  = 'project_1';
	const PROFILE_PROJECT_2  = 'project_2';

	/**
	 * Option that stores Annual Membership fee schedule settings.
	 */
	const OPTION_MEMBERSHIP_FEES = 'lccl_de_membership_fees';

	/**
	 * Option that stores the list of projects for the sponsorship form.
	 */
	const OPTION_PROJECTS_LIST = 'lccl_de_projects_list';

	/**
	 * Option that stores revision history for membership fee changes.
	 */
	const OPTION_MEMBERSHIP_FEES_HISTORY = 'lccl_de_membership_fees_history';

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
	 * Known MPGS profiles and default metadata.
	 *
	 * @return array<string, array{key:string,default_label:string,description:string}>
	 */
	public static function known_mpgs_profiles() {
		return array(
			self::PROFILE_DONATIONS  => array(
				'key'           => self::PROFILE_DONATIONS,
				'default_label' => __( 'Donations', 'lccl-de' ),
				'description'   => __( 'Merchant account for public donations and donor contributions.', 'lccl-de' ),
			),
			self::PROFILE_MEMBERSHIP => array(
				'key'           => self::PROFILE_MEMBERSHIP,
				'default_label' => __( 'Member Fees', 'lccl-de' ),
				'description'   => __( 'Merchant account for club membership fee payments.', 'lccl-de' ),
			),
			self::PROFILE_PROJECT_1  => array(
				'key'           => self::PROFILE_PROJECT_1,
				'default_label' => __( 'Fundraisers', 'lccl-de' ),
				'description'   => __( 'Dedicated merchant account for club fundraisers.', 'lccl-de' ),
			),
			self::PROFILE_PROJECT_2  => array(
				'key'           => self::PROFILE_PROJECT_2,
				'default_label' => __( 'Future Project', 'lccl-de' ),
				'description'   => __( 'Dedicated merchant account reserved for future projects.', 'lccl-de' ),
			),
		);
	}

	/**
	 * Normalize a profile key, falling back to member payments.
	 *
	 * @param string $profile Candidate profile key.
	 * @return string
	 */
	public static function normalize_mpgs_profile( $profile ) {
		$profile = sanitize_key( (string) $profile );
		$known   = array_keys( self::known_mpgs_profiles() );
		if ( in_array( $profile, $known, true ) ) {
			return $profile;
		}

		return self::PROFILE_MEMBERSHIP;
	}

	/**
	 * Default / blank MPGS configuration for a profile.
	 *
	 * @param string $profile Profile key.
	 * @return array{label:string,enabled:int,gateway_url:string,api_version:int,merchant_id:string,api_password:string}
	 */
	private static function mpgs_defaults( $profile = self::PROFILE_MEMBERSHIP ) {
		$known = self::known_mpgs_profiles();
		$label = isset( $known[ $profile ]['default_label'] ) ? $known[ $profile ]['default_label'] : '';

		return array(
			'label'        => $label,
			'enabled'      => 1,
			'gateway_url'  => 'https://cbcmpgs.gateway.mastercard.com/',
			'api_version'  => 66,
			'merchant_id'  => '',
			'api_password' => '',
		);
	}

	/**
	 * Retrieve MPGS credentials for a profile from wp_options (api_password decrypted).
	 *
	 * @param string $profile Profile key (donations, membership, project_1, project_2).
	 * @return array{label:string,enabled:int,gateway_url:string,api_version:int,merchant_id:string,api_password:string}
	 */
	public static function get_mpgs( $profile = self::PROFILE_MEMBERSHIP ) {
		$profile = self::normalize_mpgs_profile( $profile );

		$stored_profiles = get_option( self::OPTION_MPGS_PROFILES, null );

		// Transparent auto-migration from legacy single-profile option.
		if ( ! is_array( $stored_profiles ) ) {
			$stored_profiles = array();
			$legacy = get_option( self::OPTION_MPGS, array() );
			if ( is_array( $legacy ) && ! empty( $legacy ) ) {
				$stored_profiles[ self::PROFILE_MEMBERSHIP ] = $legacy;
			}
			update_option( self::OPTION_MPGS_PROFILES, $stored_profiles );
		}

		$stored_item = isset( $stored_profiles[ $profile ] ) && is_array( $stored_profiles[ $profile ] )
			? $stored_profiles[ $profile ]
			: array();

		$defaults = self::mpgs_defaults( $profile );
		$cfg      = wp_parse_args( $stored_item, $defaults );

		$cfg['label']        = sanitize_text_field( (string) $cfg['label'] );
		$cfg['enabled']      = array_key_exists( 'enabled', $stored_item ) ? (int) ! empty( $stored_item['enabled'] ) : 1;
		$cfg['gateway_url']  = esc_url_raw( (string) $cfg['gateway_url'] );
		$cfg['api_version']  = (int) $cfg['api_version'];
		$cfg['merchant_id']  = sanitize_text_field( (string) $cfg['merchant_id'] );
		$cfg['api_password'] = self::decrypt_secret( isset( $stored_item['api_password'] ) ? (string) $stored_item['api_password'] : '' );

		return $cfg;
	}

	/**
	 * Persist MPGS credentials for a specific profile. api_password is AES-256-GCM encrypted.
	 *
	 * @param array  $input   Raw posted values.
	 * @param string $profile Target profile key.
	 * @return array Saved (decrypted) values.
	 */
	public static function save_mpgs( $input, $profile = self::PROFILE_MEMBERSHIP ) {
		if ( ! is_array( $input ) ) {
			$input = array();
		}

		$profile = self::normalize_mpgs_profile( $profile );
		$before  = self::get_mpgs( $profile );

		$label = array_key_exists( 'profile_label', $input )
			? sanitize_text_field( trim( (string) $input['profile_label'] ) )
			: $before['label'];

		$enabled = ! empty( $input['enabled'] ) ? 1 : 0;

		$gateway_url = array_key_exists( 'gateway_url', $input )
			? esc_url_raw( trim( (string) $input['gateway_url'] ) )
			: $before['gateway_url'];

		// Enforce HTTPS protocol
		if ( '' !== $gateway_url ) {
			if ( 0 !== stripos( $gateway_url, 'https://' ) ) {
				$gateway_url = preg_replace( '#^http://#i', 'https://', $gateway_url );
				if ( 0 !== stripos( $gateway_url, 'https://' ) ) {
					$gateway_url = 'https://' . ltrim( $gateway_url, '/' );
				}
			}
		}

		$api_version = array_key_exists( 'api_version', $input )
			? (int) $input['api_version']
			: $before['api_version'];

		$merchant_id = array_key_exists( 'merchant_id', $input )
			? sanitize_text_field( trim( (string) $input['merchant_id'] ) )
			: $before['merchant_id'];

		// Only update password if a non-empty value was posted.
		$api_password = $before['api_password'];
		if ( array_key_exists( 'api_password', $input ) ) {
			$posted = trim( (string) $input['api_password'] );
			if ( '' !== $posted ) {
				$api_password = $posted;
			}
		}

		$stored_profiles = get_option( self::OPTION_MPGS_PROFILES, array() );
		if ( ! is_array( $stored_profiles ) ) {
			$stored_profiles = array();
		}

		$stored_profiles[ $profile ] = array(
			'label'        => $label,
			'enabled'      => $enabled,
			'gateway_url'  => $gateway_url,
			'api_version'  => $api_version,
			'merchant_id'  => $merchant_id,
			'api_password' => self::encrypt_secret( $api_password ),
		);

		update_option( self::OPTION_MPGS_PROFILES, $stored_profiles );

		// Keep legacy single option in sync for membership profile.
		if ( self::PROFILE_MEMBERSHIP === $profile ) {
			update_option(
				self::OPTION_MPGS,
				array(
					'enabled'      => $enabled,
					'gateway_url'  => $gateway_url,
					'api_version'  => $api_version,
					'merchant_id'  => $merchant_id,
					'api_password' => self::encrypt_secret( $api_password ),
				)
			);
		}

		return array(
			'label'        => $label,
			'enabled'      => $enabled,
			'gateway_url'  => $gateway_url,
			'api_version'  => $api_version,
			'merchant_id'  => $merchant_id,
			'api_password' => $api_password,
		);
	}

	/**
	 * Whether credentials have been entered for a profile (irrespective of toggle state).
	 *
	 * @param string $profile Profile key.
	 * @return bool
	 */
	public static function mpgs_has_credentials( $profile = self::PROFILE_MEMBERSHIP ) {
		$cfg = self::get_mpgs( $profile );
		return '' !== $cfg['gateway_url']
			&& '' !== $cfg['merchant_id']
			&& '' !== $cfg['api_password'];
	}

	/**
	 * Whether all required MPGS credentials for a profile are saved and the route is enabled.
	 *
	 * @param string $profile Profile key.
	 * @return bool
	 */
	public static function mpgs_is_configured( $profile = self::PROFILE_MEMBERSHIP ) {
		$cfg = self::get_mpgs( $profile );
		return ! empty( $cfg['enabled'] )
			&& '' !== $cfg['gateway_url']
			&& '' !== $cfg['merchant_id']
			&& '' !== $cfg['api_password'];
	}

	/**
	 * Retrieve list of all known profiles with their configuration and state.
	 *
	 * @return array<string, array{key:string,label:string,default_label:string,description:string,is_configured:bool,config:array}>
	 */
	public static function get_all_mpgs_profiles() {
		$known        = self::known_mpgs_profiles();
		$old_defaults = array( 'Member Payments', 'Future Project 1', 'Future Project 2' );
		$profiles     = array();

		foreach ( $known as $key => $meta ) {
			$cfg   = self::get_mpgs( $key );
			$label = ( ! empty( $cfg['label'] ) && ! in_array( $cfg['label'], $old_defaults, true ) )
				? $cfg['label']
				: $meta['default_label'];

			$profiles[ $key ] = array(
				'key'             => $key,
				'label'           => $label,
				'default_label'   => $meta['default_label'],
				'description'     => $meta['description'],
				'has_credentials' => self::mpgs_has_credentials( $key ),
				'is_configured'   => self::mpgs_is_configured( $key ),
				'enabled'         => ! empty( $cfg['enabled'] ),
				'config'          => $cfg,
			);
		}

		return $profiles;
	}

	// ------------------------------------------------------------------
	// CBC Paycenter Web 4.0 credentials (Multi-client support)
	// ------------------------------------------------------------------

	/**
	 * Known CBC Paycenter profiles and default metadata.
	 *
	 * @return array<string, array{key:string,default_label:string,description:string}>
	 */
	public static function known_paycenter_profiles() {
		return array(
			self::PROFILE_DONATIONS  => array(
				'key'           => self::PROFILE_DONATIONS,
				'default_label' => __( 'Donations', 'lccl-de' ),
				'description'   => __( 'Client credentials for public donations and donor contributions.', 'lccl-de' ),
			),
			self::PROFILE_MEMBERSHIP => array(
				'key'           => self::PROFILE_MEMBERSHIP,
				'default_label' => __( 'Member Fees', 'lccl-de' ),
				'description'   => __( 'Client credentials for club membership fee payments.', 'lccl-de' ),
			),
			self::PROFILE_PROJECT_1  => array(
				'key'           => self::PROFILE_PROJECT_1,
				'default_label' => __( 'Fundraisers', 'lccl-de' ),
				'description'   => __( 'Dedicated client credentials for club fundraisers.', 'lccl-de' ),
			),
			self::PROFILE_PROJECT_2  => array(
				'key'           => self::PROFILE_PROJECT_2,
				'default_label' => __( 'Future Project', 'lccl-de' ),
				'description'   => __( 'Dedicated client credentials reserved for future projects.', 'lccl-de' ),
			),
		);
	}

	/**
	 * Normalize a Paycenter profile key, falling back to donations.
	 *
	 * @param string $profile Candidate profile key.
	 * @return string
	 */
	public static function normalize_paycenter_profile( $profile ) {
		$profile = sanitize_key( (string) $profile );
		$known   = array_keys( self::known_paycenter_profiles() );
		if ( in_array( $profile, $known, true ) ) {
			return $profile;
		}

		return self::PROFILE_DONATIONS;
	}

	/**
	 * Default / blank CBC Paycenter configuration for a profile.
	 *
	 * @param string $profile Profile key.
	 * @return array{label:string,enabled:int,endpoint:string,client_id:string,auth_token:string,hmac_secret:string,currency:string}
	 */
	private static function paycenter_defaults( $profile = self::PROFILE_MEMBERSHIP ) {
		$known = self::known_paycenter_profiles();
		$label = isset( $known[ $profile ]['default_label'] ) ? $known[ $profile ]['default_label'] : __( 'CBC Paycenter', 'lccl-de' );

		return array(
			'label'       => $label,
			'enabled'     => ( self::PROFILE_MEMBERSHIP === $profile ) ? 1 : 0,
			'endpoint'    => 'https://paycorp-cbc.prod.aws.paycorp.lk/rest/service/proxy/',
			'merchant_id' => '',
			'client_id'   => '',
			'auth_token'  => '',
			'hmac_secret' => '',
			'currency'    => 'LKR',
		);
	}

	/**
	 * Retrieve CBC Paycenter credentials from wp_options (auth_token & hmac_secret decrypted).
	 *
	 * @param string $profile Target profile key (donations, membership, project_1, project_2).
	 * @return array{label:string,enabled:int,endpoint:string,merchant_id:string,client_id:string,auth_token:string,hmac_secret:string,currency:string}
	 */
	public static function get_paycenter( $profile = self::PROFILE_MEMBERSHIP ) {
		$profile = self::normalize_paycenter_profile( $profile );

		$stored_profiles = get_option( self::OPTION_PAYCENTER_PROFILES, null );

		// Transparent auto-migration from legacy single-profile option.
		if ( ! is_array( $stored_profiles ) ) {
			$stored_profiles = array();
			$legacy = get_option( self::OPTION_PAYCENTER, array() );
			if ( is_array( $legacy ) && ! empty( $legacy ) ) {
				$stored_profiles[ self::PROFILE_MEMBERSHIP ] = $legacy;
			}
			update_option( self::OPTION_PAYCENTER_PROFILES, $stored_profiles );
		}

		$stored_item = isset( $stored_profiles[ $profile ] ) && is_array( $stored_profiles[ $profile ] )
			? $stored_profiles[ $profile ]
			: array();

		$defaults = self::paycenter_defaults( $profile );
		$cfg      = wp_parse_args( $stored_item, $defaults );

		$cfg['label']       = sanitize_text_field( (string) $cfg['label'] );
		$cfg['enabled']     = array_key_exists( 'enabled', $stored_item ) ? (int) ! empty( $stored_item['enabled'] ) : ( ( self::PROFILE_MEMBERSHIP === $profile ) ? 1 : 0 );
		$cfg['endpoint']    = esc_url_raw( (string) $cfg['endpoint'] );
		$cfg['merchant_id'] = sanitize_text_field( (string) $cfg['merchant_id'] );
		$cfg['client_id']   = sanitize_text_field( (string) $cfg['client_id'] );
		$cfg['auth_token']  = self::decrypt_secret( isset( $stored_item['auth_token'] ) ? (string) $stored_item['auth_token'] : '' );
		$cfg['hmac_secret'] = self::decrypt_secret( isset( $stored_item['hmac_secret'] ) ? (string) $stored_item['hmac_secret'] : '' );

		// Validate stored currency; fall back to LKR if not a known value.
		$allowed_currencies = array( 'LKR', 'USD' );
		$raw_currency       = strtoupper( sanitize_text_field( (string) $cfg['currency'] ) );
		$cfg['currency']    = in_array( $raw_currency, $allowed_currencies, true ) ? $raw_currency : 'LKR';

		return $cfg;
	}

	/**
	 * Persist CBC Paycenter credentials. auth_token and hmac_secret are AES-256-GCM encrypted.
	 *
	 * @param array  $input   Raw posted values.
	 * @param string $profile Target profile key.
	 * @return array Saved (decrypted) values.
	 */
	public static function save_paycenter( $input, $profile = self::PROFILE_MEMBERSHIP ) {
		if ( ! is_array( $input ) ) {
			$input = array();
		}

		$profile = self::normalize_paycenter_profile( $profile );
		$before  = self::get_paycenter( $profile );

		$label = array_key_exists( 'paycenter_label', $input )
			? sanitize_text_field( trim( (string) $input['paycenter_label'] ) )
			: ( array_key_exists( 'profile_label', $input ) ? sanitize_text_field( trim( (string) $input['profile_label'] ) ) : $before['label'] );

		$enabled = ( ! empty( $input['paycenter_enabled'] ) || ! empty( $input['enabled'] ) ) ? 1 : 0;

		$endpoint = array_key_exists( 'paycenter_endpoint', $input )
			? esc_url_raw( trim( (string) $input['paycenter_endpoint'] ) )
			: ( array_key_exists( 'endpoint', $input ) ? esc_url_raw( trim( (string) $input['endpoint'] ) ) : $before['endpoint'] );

		// Enforce HTTPS on the endpoint.
		if ( '' !== $endpoint && 0 !== stripos( $endpoint, 'https://' ) ) {
			$endpoint = preg_replace( '#^http://#i', 'https://', $endpoint );
			if ( 0 !== stripos( $endpoint, 'https://' ) ) {
				$endpoint = 'https://' . ltrim( $endpoint, '/' );
			}
		}

		$merchant_id = array_key_exists( 'paycenter_merchant_id', $input )
			? sanitize_text_field( trim( (string) $input['paycenter_merchant_id'] ) )
			: ( array_key_exists( 'merchant_id', $input ) ? sanitize_text_field( trim( (string) $input['merchant_id'] ) ) : $before['merchant_id'] );

		$client_id = array_key_exists( 'paycenter_client_id', $input )
			? sanitize_text_field( trim( (string) $input['paycenter_client_id'] ) )
			: ( array_key_exists( 'client_id', $input ) ? sanitize_text_field( trim( (string) $input['client_id'] ) ) : $before['client_id'] );

		// Only update auth_token if a non-empty value was posted.
		$auth_token  = $before['auth_token'];
		$posted_auth = isset( $input['paycenter_auth_token'] ) ? trim( (string) $input['paycenter_auth_token'] ) : ( isset( $input['auth_token'] ) ? trim( (string) $input['auth_token'] ) : '' );
		if ( '' !== $posted_auth ) {
			$auth_token = $posted_auth;
		}

		// Only update hmac_secret if a non-empty value was posted.
		$hmac_secret = $before['hmac_secret'];
		$posted_hmac = isset( $input['paycenter_hmac_secret'] ) ? trim( (string) $input['paycenter_hmac_secret'] ) : ( isset( $input['hmac_secret'] ) ? trim( (string) $input['hmac_secret'] ) : '' );
		if ( '' !== $posted_hmac ) {
			$hmac_secret = $posted_hmac;
		}

		$stored_profiles = get_option( self::OPTION_PAYCENTER_PROFILES, array() );
		if ( ! is_array( $stored_profiles ) ) {
			$stored_profiles = array();
		}

		// Sanitize and validate currency selection.
		$allowed_currencies = array( 'LKR', 'USD' );
		$before_currency    = isset( $before['currency'] ) ? $before['currency'] : 'LKR';
		$raw_currency       = isset( $input['paycenter_currency'] ) ? strtoupper( sanitize_text_field( trim( (string) $input['paycenter_currency'] ) ) ) : $before_currency;
		$currency           = in_array( $raw_currency, $allowed_currencies, true ) ? $raw_currency : 'LKR';

		$stored_profiles[ $profile ] = array(
			'label'       => $label,
			'enabled'     => $enabled,
			'endpoint'    => $endpoint,
			'merchant_id' => $merchant_id,
			'client_id'   => $client_id,
			'auth_token'  => self::encrypt_secret( $auth_token ),
			'hmac_secret' => self::encrypt_secret( $hmac_secret ),
			'currency'    => $currency,
		);

		update_option( self::OPTION_PAYCENTER_PROFILES, $stored_profiles );

		// Keep legacy single option in sync for membership profile.
		if ( self::PROFILE_MEMBERSHIP === $profile ) {
			update_option(
				self::OPTION_PAYCENTER,
				array(
					'label'       => $label,
					'enabled'     => $enabled,
					'endpoint'    => $endpoint,
					'merchant_id' => $merchant_id,
					'client_id'   => $client_id,
					'auth_token'  => self::encrypt_secret( $auth_token ),
					'hmac_secret' => self::encrypt_secret( $hmac_secret ),
					'currency'    => $currency,
				)
			);
		}

		return array(
			'label'       => $label,
			'enabled'     => $enabled,
			'endpoint'    => $endpoint,
			'merchant_id' => $merchant_id,
			'client_id'   => $client_id,
			'auth_token'  => $auth_token,
			'hmac_secret' => $hmac_secret,
			'currency'    => $currency,
		);
	}

	/**
	 * Whether credentials have been entered for a CBC Paycenter profile.
	 *
	 * @param string $profile Target profile key.
	 * @return bool
	 */
	public static function paycenter_has_credentials( $profile = self::PROFILE_MEMBERSHIP ) {
		$cfg = self::get_paycenter( $profile );
		return '' !== $cfg['endpoint']
			&& '' !== $cfg['client_id']
			&& '' !== $cfg['auth_token'];
	}

	/**
	 * Whether all required CBC Paycenter credentials are saved and route is enabled.
	 *
	 * @param string $profile Target profile key.
	 * @return bool
	 */
	public static function paycenter_is_configured( $profile = self::PROFILE_MEMBERSHIP ) {
		$cfg = self::get_paycenter( $profile );
		return ! empty( $cfg['enabled'] )
			&& '' !== $cfg['endpoint']
			&& '' !== $cfg['client_id']
			&& '' !== $cfg['auth_token'];
	}

	/**
	 * Retrieve list of all known Paycenter profiles with their configuration and state.
	 *
	 * @return array<string, array{key:string,label:string,default_label:string,description:string,is_configured:bool,has_credentials:bool,enabled:bool,config:array}>
	 */
	public static function get_all_paycenter_profiles() {
		$known    = self::known_paycenter_profiles();
		$profiles = array();

		foreach ( $known as $key => $meta ) {
			$cfg   = self::get_paycenter( $key );
			$label = ! empty( $cfg['label'] ) ? $cfg['label'] : $meta['default_label'];

			$profiles[ $key ] = array(
				'key'             => $key,
				'label'           => $label,
				'default_label'   => $meta['default_label'],
				'description'     => $meta['description'],
				'has_credentials' => self::paycenter_has_credentials( $key ),
				'is_configured'   => self::paycenter_is_configured( $key ),
				'enabled'         => ! empty( $cfg['enabled'] ),
				'config'          => $cfg,
			);
		}

		return $profiles;
	}

	// ------------------------------------------------------------------
	// Membership Fee Schedule & Revision History
	// ------------------------------------------------------------------

	/**
	 * Get current membership fee schedule settings.
	 *
	 * Defaults:
	 *  - Exchange rate: 1 USD = LKR 330.80
	 *  - Household / Main Member fee: USD 50.00
	 *  - Family Member fee: USD 25.00 per additional family member
	 *  - District payment: LKR 4,561.00 per member
	 *  - Club payment: LKR 6,000.00 per membership
	 *
	 * @return array{exchange_rate:float,principal_usd:float,family_usd:float,district_lkr:float,club_lkr:float}
	 */
	public static function get_membership_fees() {
		$defaults = array(
			'exchange_rate' => 330.80,
			'principal_usd' => 50.00,
			'family_usd'    => 25.00,
			'district_lkr'  => 4561.00,
			'club_lkr'      => 6000.00,
		);

		$saved = get_option( self::OPTION_MEMBERSHIP_FEES, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}

		return array(
			'exchange_rate' => isset( $saved['exchange_rate'] ) ? max( 0.01, (float) $saved['exchange_rate'] ) : $defaults['exchange_rate'],
			'principal_usd' => isset( $saved['principal_usd'] ) ? max( 0.00, (float) $saved['principal_usd'] ) : $defaults['principal_usd'],
			'family_usd'    => isset( $saved['family_usd'] ) ? max( 0.00, (float) $saved['family_usd'] ) : $defaults['family_usd'],
			'district_lkr'  => isset( $saved['district_lkr'] ) ? max( 0.00, (float) $saved['district_lkr'] ) : $defaults['district_lkr'],
			'club_lkr'      => isset( $saved['club_lkr'] ) ? max( 0.00, (float) $saved['club_lkr'] ) : $defaults['club_lkr'],
		);
	}

	/**
	 * Save membership fee schedule settings and log to revision history.
	 *
	 * @param array $input Raw POST or input array.
	 * @return array Saved values.
	 */
	public static function save_membership_fees( array $input ) {
		$current = self::get_membership_fees();

		$rate      = isset( $input['exchange_rate'] ) ? max( 0.01, (float) $input['exchange_rate'] ) : 330.80;
		$principal = isset( $input['household_fee_usd'] )
			? max( 0.00, (float) $input['household_fee_usd'] )
			: ( isset( $input['principal_usd'] ) ? max( 0.00, (float) $input['principal_usd'] ) : 50.00 );
		$family    = isset( $input['family_fee_usd'] )
			? max( 0.00, (float) $input['family_fee_usd'] )
			: ( isset( $input['family_usd'] ) ? max( 0.00, (float) $input['family_usd'] ) : 25.00 );
		$district  = isset( $input['district_fee_lkr'] )
			? max( 0.00, (float) $input['district_fee_lkr'] )
			: ( isset( $input['district_lkr'] ) ? max( 0.00, (float) $input['district_lkr'] ) : 4561.00 );
		$club      = isset( $input['club_fee_lkr'] )
			? max( 0.00, (float) $input['club_fee_lkr'] )
			: ( isset( $input['club_lkr'] ) ? max( 0.00, (float) $input['club_lkr'] ) : 6000.00 );
		$note      = isset( $input['change_note'] ) ? sanitize_text_field( wp_unslash( $input['change_note'] ) ) : '';

		$new_fees = array(
			'exchange_rate' => $rate,
			'principal_usd' => $principal,
			'family_usd'    => $family,
			'district_lkr'  => $district,
			'club_lkr'      => $club,
		);

		update_option( self::OPTION_MEMBERSHIP_FEES, $new_fees );

		// Check if any numbers actually changed or history is empty.
		$changed = false;
		foreach ( $new_fees as $k => $v ) {
			if ( abs( (float) $current[ $k ] - (float) $v ) > 0.0001 ) {
				$changed = true;
				break;
			}
		}

		$history = get_option( self::OPTION_MEMBERSHIP_FEES_HISTORY, array() );
		if ( ! is_array( $history ) ) {
			$history = array();
		}

		if ( $changed || empty( $history ) || '' !== $note ) {
			$user  = wp_get_current_user();
			$entry = array(
				'timestamp'     => current_time( 'mysql' ),
				'user_id'       => get_current_user_id(),
				'user_name'     => ( $user && $user->exists() ) ? $user->display_name : 'Admin',
				'exchange_rate' => $rate,
				'principal_usd' => $principal,
				'family_usd'    => $family,
				'district_lkr'  => $district,
				'club_lkr'      => $club,
				'note'          => $note,
			);

			array_unshift( $history, $entry );
			if ( count( $history ) > 50 ) {
				$history = array_slice( $history, 0, 50 );
			}
			update_option( self::OPTION_MEMBERSHIP_FEES_HISTORY, $history );
		}

		return $new_fees;
	}

	/**
	 * Get membership fee revision history.
	 *
	 * @return array
	 */
	public static function get_membership_fees_history() {
		$history = get_option( self::OPTION_MEMBERSHIP_FEES_HISTORY, array() );
		return is_array( $history ) ? $history : array();
	}

	// ------------------------------------------------------------------
	// Projects List Management
	// ------------------------------------------------------------------

	/**
	 * Get the list of projects for the sponsorship form.
	 *
	 * @return array<string, array{id:string,title:string,financial_goal:float,enabled:int}>
	 */
	public static function get_projects() {
		$defaults = array(
			'spectacles' => array(
				'id'             => 'spectacles',
				'title'          => __( '200 Spectacles for School Children', 'lccl-de' ),
				'financial_goal' => 1000000,
				'enabled'        => 1,
			),
			'cataract'   => array(
				'id'             => 'cataract',
				'title'          => __( 'Cataract Surgery Support', 'lccl-de' ),
				'financial_goal' => 750000,
				'enabled'        => 1,
			),
			'health'     => array(
				'id'             => 'health',
				'title'          => __( 'Community Health Programme', 'lccl-de' ),
				'financial_goal' => 500000,
				'enabled'        => 1,
			),
		);

		$saved = get_option( self::OPTION_PROJECTS_LIST, null );
		if ( ! is_array( $saved ) ) {
			return $defaults;
		}

		return $saved;
	}

	/**
	 * Save the list of projects.
	 *
	 * @param array $projects Array of projects.
	 */
	public static function save_projects( array $projects ) {
		update_option( self::OPTION_PROJECTS_LIST, $projects );
	}
}
