<?php
/**
 * wp-admin hub for LCCL programmes.
 *
 * @package LCCL_Donations_And_Events
 */

defined( 'ABSPATH' ) || exit;

/**
 * Top-level LCCL Programs menu, card landing, and blood-donation workspace.
 */
class LCCL_DE_Admin_Programs {

	/**
	 * Menu slug.
	 */
	const PAGE = 'lccl-de-programs';

	/**
	 * Blood donation programme query value.
	 */
	const PROGRAM_BLOOD = 'blood-donation';

	/**
	 * Hook menu and assets.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'redirect_legacy' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'wp_ajax_lccl_de_program_tab', array( __CLASS__, 'ajax_tab' ) );
	}

	/**
	 * Top-level menu.
	 */
	public static function menu() {
		add_menu_page(
			__( 'LCCL Programs', 'lccl-de' ),
			__( 'LCCL Programs', 'lccl-de' ),
			'manage_options',
			self::PAGE,
			array( __CLASS__, 'render' ),
			'dashicons-screenoptions',
			26
		);

		add_submenu_page(
			self::PAGE,
			__( 'LCCL Programs', 'lccl-de' ),
			__( 'LCCL Programs', 'lccl-de' ),
			'manage_options',
			self::PAGE,
			array( __CLASS__, 'render' )
		);
	}

	/**
	 * Send old Blood Donation Users / Notifications URLs to the new hub.
	 */
	public static function redirect_legacy() {
		if ( ! is_admin() || empty( $_GET['page'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : 'GET';
		if ( 'GET' !== $method ) {
			return;
		}

		$page = sanitize_key( wp_unslash( $_GET['page'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( 'lccl-de-blood-users' === $page ) {
			wp_safe_redirect( self::blood_url( self::query_without_page() ) );
			exit;
		}

		if ( 'lccl-de-blood-notify' === $page ) {
			$args         = self::query_without_page();
			$args['tab']  = 'notifications';
			wp_safe_redirect( self::blood_url( $args ) );
			exit;
		}
	}

	/**
	 * Styles for the hub and blood-donation workspace.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public static function assets( $hook ) {
		unset( $hook );

		if ( empty( $_GET['page'] ) || self::PAGE !== $_GET['page'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		wp_enqueue_style(
			'lccl-de-admin-programs',
			LCCL_DE_URL . 'assets/css/lccl-de-admin-programs.css',
			array(),
			LCCL_DE_VERSION
		);

		wp_enqueue_script(
			'lccl-de-admin-notify',
			LCCL_DE_URL . 'assets/js/lccl-de-admin-notify.js',
			array(),
			LCCL_DE_VERSION,
			true
		);

		wp_enqueue_script(
			'lccl-de-admin-programs',
			LCCL_DE_URL . 'assets/js/lccl-de-admin-programs.js',
			array( 'lccl-de-admin-notify' ),
			LCCL_DE_VERSION,
			true
		);

		wp_localize_script(
			'lccl-de-admin-programs',
			'lcclDePrograms',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'action'  => 'lccl_de_program_tab',
				'nonce'   => wp_create_nonce( 'lccl_de_program_tab' ),
			)
		);
	}

	/**
	 * Hub or a programme workspace.
	 */
	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage LCCL programs.', 'lccl-de' ) );
		}

		$program = isset( $_GET['program'] ) ? sanitize_key( wp_unslash( $_GET['program'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( self::PROGRAM_BLOOD === $program ) {
			self::render_blood();
			return;
		}

		include LCCL_DE_PATH . 'templates/admin-programs.php';
	}

	/**
	 * Blood donation users + notifications.
	 */
	private static function render_blood() {
		$tab = self::requested_tab();
		extract( self::tab_vars( $tab, true ), EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
		include LCCL_DE_PATH . 'templates/admin-program-blood.php';
	}

	/**
	 * HTML for one workspace tab, used by the page and by AJAX.
	 *
	 * @param string $tab users|notifications.
	 * @return string
	 */
	public static function tab_html( $tab ) {
		$tab = 'notifications' === $tab ? 'notifications' : 'users';
		extract( self::tab_vars( $tab, false ), EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract

		ob_start();
		if ( 'notifications' === $tab ) {
			include LCCL_DE_PATH . 'templates/admin-notifications.php';
		} else {
			include LCCL_DE_PATH . 'templates/admin-users.php';
		}

		return (string) ob_get_clean();
	}

	/**
	 * Fetch a tab without reloading wp-admin.
	 */
	public static function ajax_tab() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to manage LCCL programs.', 'lccl-de' ) ), 403 );
		}

		check_ajax_referer( 'lccl_de_program_tab', 'nonce' );

		$tab = isset( $_POST['tab'] ) ? sanitize_key( wp_unslash( $_POST['tab'] ) ) : 'users';
		if ( 'notifications' !== $tab ) {
			$tab = 'users';
		}

		wp_send_json_success(
			array(
				'tab'  => $tab,
				'html' => self::tab_html( $tab ),
			)
		);
	}

	/**
	 * Current tab from the query string.
	 *
	 * @return string
	 */
	private static function requested_tab() {
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'users'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return 'notifications' === $tab ? 'notifications' : 'users';
	}

	/**
	 * Variables the tab templates expect.
	 *
	 * @param string $tab      users|notifications.
	 * @param bool   $from_get Read action/message from the request.
	 * @return array
	 */
	private static function tab_vars( $tab, $from_get ) {
		$action  = 'list';
		$message = '';
		$error   = '';
		$edit    = null;

		if ( $from_get ) {
			$message = isset( $_GET['message'] ) ? sanitize_key( wp_unslash( $_GET['message'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$error   = isset( $_GET['error'] ) ? sanitize_text_field( wp_unslash( $_GET['error'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$action  = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : 'list'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$user_id = isset( $_GET['user_id'] ) ? (int) $_GET['user_id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

			if ( 'users' === $tab && 'edit' === $action && $user_id ) {
				$edit = LCCL_DE_Admin_Users::get_reviewer( $user_id );
				if ( ! $edit ) {
					$action  = 'list';
					$error   = __( 'That account is not a blood donation reviewer.', 'lccl-de' );
					$message = 'error';
				}
			}
		}

		return array(
			'tab'      => $tab,
			'action'   => $action,
			'message'  => $message,
			'error'    => $error,
			'edit'     => $edit,
			'settings' => LCCL_DE_Settings::get(),
			'log'      => LCCL_DE_Notify::log(),
			'smtp'     => class_exists( 'WPMailSMTP\Core' ) || defined( 'WPMS_PLUGIN_VER' ),
		);
	}

	/**
	 * Hub URL.
	 *
	 * @param array $args Query args.
	 * @return string
	 */
	public static function url( $args = array() ) {
		$args['page'] = self::PAGE;
		return add_query_arg( $args, admin_url( 'admin.php' ) );
	}

	/**
	 * Blood donation workspace URL.
	 *
	 * @param array $args Query args.
	 * @return string
	 */
	public static function blood_url( $args = array() ) {
		$args['program'] = self::PROGRAM_BLOOD;
		if ( empty( $args['tab'] ) ) {
			$args['tab'] = 'users';
		}
		return self::url( $args );
	}

	/**
	 * Current GET args without page, for legacy redirects.
	 *
	 * @return array
	 */
	private static function query_without_page() {
		$args = wp_unslash( $_GET ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		unset( $args['page'] );
		return is_array( $args ) ? $args : array();
	}
}
