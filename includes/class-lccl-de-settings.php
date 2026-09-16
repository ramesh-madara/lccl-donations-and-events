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
	 * Hook menu and save handler.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 11 );
		add_action( 'admin_init', array( __CLASS__, 'handle_post' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
	}

	/**
	 * Notifications submenu.
	 */
	public static function menu() {
		add_submenu_page(
			LCCL_DE_Admin_Users::PAGE,
			__( 'Notifications', 'lccl-de' ),
			__( 'Notifications', 'lccl-de' ),
			'manage_options',
			self::PAGE,
			array( __CLASS__, 'render' )
		);
	}

	/**
	 * Assets for the add/remove email rows.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public static function assets( $hook ) {
		unset( $hook );

		if ( empty( $_GET['page'] ) || self::PAGE !== $_GET['page'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		wp_enqueue_script(
			'lccl-de-admin-notify',
			LCCL_DE_URL . 'assets/js/lccl-de-admin-notify.js',
			array(),
			LCCL_DE_VERSION,
			true
		);
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
			$email = sanitize_email( (string) $email );
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
	 * Save toggles or send a test email.
	 */
	public static function handle_post() {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( empty( $_GET['page'] ) || self::PAGE !== $_GET['page'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		if ( ! empty( $_POST['lccl_de_notify_test'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			check_admin_referer( self::NONCE );

			$to = isset( $_POST['test_email'] ) ? sanitize_email( wp_unslash( $_POST['test_email'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
			if ( ! is_email( $to ) ) {
				$saved = self::admin_addresses();
				$to    = isset( $saved[0] ) ? $saved[0] : '';
			}

			$ok = LCCL_DE_Notify::send_test( $to );

			wp_safe_redirect(
				add_query_arg(
					array(
						'page'    => self::PAGE,
						'message' => $ok ? 'test-ok' : 'test-fail',
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}

		if ( empty( $_POST['lccl_de_notify_save'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return;
		}

		check_admin_referer( self::NONCE );

		self::save( wp_unslash( $_POST ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => self::PAGE,
					'message' => 'saved',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Settings screen.
	 */
	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage these settings.', 'lccl-de' ) );
		}

		$settings = self::get();
		$message  = isset( $_GET['message'] ) ? sanitize_key( wp_unslash( $_GET['message'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$log      = LCCL_DE_Notify::log();
		$smtp     = class_exists( 'WPMailSMTP\Core' ) || defined( 'WPMS_PLUGIN_VER' );

		include LCCL_DE_PATH . 'templates/admin-notifications.php';
	}
}
