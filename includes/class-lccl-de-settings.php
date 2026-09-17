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
	 * Hook save handler. The menu lives on LCCL Programs.
	 */
	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'handle_post' ) );
	}

	/**
	 * Current settings with defaults (all on).
	 *
	 * @return array{donor_sms:int,donor_email:int,admin_email:int,admin_addresses:array,admin_address:string}
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
			)
		);

		$settings['admin_addresses'] = self::emails_from_stored( $stored );
		$settings['admin_address']   = isset( $settings['admin_addresses'][0] ) ? $settings['admin_addresses'][0] : '';

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
		$clean = self::sanitize( $input );
		update_option( self::OPTION, $clean );
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

		return array(
			'donor_sms'        => self::flag( isset( $input['donor_sms'] ) ? $input['donor_sms'] : 0 ),
			'donor_email'      => self::flag( isset( $input['donor_email'] ) ? $input['donor_email'] : 0 ),
			'admin_email'      => self::flag( isset( $input['admin_email'] ) ? $input['admin_email'] : 0 ),
			'admin_addresses'  => $addresses,
			'admin_address'    => isset( $addresses[0] ) ? $addresses[0] : '',
		);
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

			$ok = LCCL_DE_Notify::send_test( $to );

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
