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
	 * Current settings with defaults (all on).
	 *
	 * @return array{donor_sms:int,donor_email:int,admin_email:int,admin_address:string}
	 */
	public static function get() {
		$stored = get_option( self::OPTION, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		return wp_parse_args(
			$stored,
			array(
				'donor_sms'     => 1,
				'donor_email'   => 1,
				'admin_email'   => 1,
				'admin_address' => 'admin@colomboleads.org',
			)
		);
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
	 * @return array{donor_sms:int,donor_email:int,admin_email:int,admin_address:string}
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
	 * @return array{donor_sms:int,donor_email:int,admin_email:int,admin_address:string}
	 */
	public static function sanitize( $input ) {
		if ( ! is_array( $input ) ) {
			$input = array();
		}

		$address = isset( $input['admin_address'] ) ? sanitize_email( (string) $input['admin_address'] ) : '';
		if ( ! is_email( $address ) ) {
			$address = 'admin@colomboleads.org';
		}

		return array(
			'donor_sms'     => self::flag( isset( $input['donor_sms'] ) ? $input['donor_sms'] : 0 ),
			'donor_email'   => self::flag( isset( $input['donor_email'] ) ? $input['donor_email'] : 0 ),
			'admin_email'   => self::flag( isset( $input['admin_email'] ) ? $input['admin_email'] : 0 ),
			'admin_address' => $address,
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
	 * Staff inbox for the admin copy.
	 *
	 * @return string
	 */
	public static function admin_address() {
		$settings = self::get();
		$email    = isset( $settings['admin_address'] ) ? $settings['admin_address'] : '';
		return is_email( $email ) ? $email : 'admin@colomboleads.org';
	}

	/**
	 * Save toggles.
	 */
	public static function handle_post() {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( empty( $_POST['lccl_de_notify_save'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return;
		}

		if ( empty( $_GET['page'] ) || self::PAGE !== $_GET['page'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
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

		include LCCL_DE_PATH . 'templates/admin-notifications.php';
	}
}
