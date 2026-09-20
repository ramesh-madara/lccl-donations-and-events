<?php
/**
 * wp-admin hub for LCCL programmes.
 *
 * @package LCCL_Donations_And_Events
 */

defined( 'ABSPATH' ) || exit;

/**
 * Top-level LCCL Programs menu, card landing, reviewers, and programme workspaces.
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
	 * Join Our Projects programme query value.
	 */
	const PROGRAM_PROJECTS = 'our-projects';

	/**
	 * Free Spectacles programme query value.
	 */
	const PROGRAM_SPECTACLES = 'free-spectacles';

	/**
	 * Hub-level reviewers screen query value.
	 */
	const SCREEN_USERS = 'users';

	/**
	 * Hub Programs tab.
	 */
	const TAB_PROGRAMS = 'programs';

	/**
	 * Hub User Management tab.
	 */
	const TAB_USERS = 'users';

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
			wp_safe_redirect( self::users_url( self::query_without_page() ) );
			exit;
		}

		if ( 'lccl-de-blood-notify' === $page ) {
			$args        = self::query_without_page();
			$args['tab'] = 'notifications';
			wp_safe_redirect( self::blood_url( $args ) );
			exit;
		}

		if ( self::PAGE !== $page ) {
			return;
		}

		$program = isset( $_GET['program'] ) ? sanitize_key( wp_unslash( $_GET['program'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tab     = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$screen  = isset( $_GET['screen'] ) ? sanitize_key( wp_unslash( $_GET['screen'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( self::SCREEN_USERS === $screen ) {
			$args = wp_unslash( $_GET ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( ! is_array( $args ) ) {
				$args = array();
			}
			unset( $args['page'], $args['screen'], $args['program'] );
			wp_safe_redirect( self::users_url( $args ) );
			exit;
		}

		if ( self::PROGRAM_BLOOD === $program && self::TAB_USERS === $tab ) {
			$args = wp_unslash( $_GET ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( ! is_array( $args ) ) {
				$args = array();
			}
			unset( $args['page'], $args['program'], $args['tab'] );
			wp_safe_redirect( self::users_url( $args ) );
			exit;
		}
	}

	/**
	 * Styles for the hub and programme workspaces.
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
	 * Hub, reviewers, or a programme workspace.
	 */
	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage LCCL programs.', 'lccl-de' ) );
		}

		$screen  = isset( $_GET['screen'] ) ? sanitize_key( wp_unslash( $_GET['screen'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$program = isset( $_GET['program'] ) ? sanitize_key( wp_unslash( $_GET['program'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tab     = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( self::PROGRAM_BLOOD === $program ) {
			self::render_blood();
			return;
		}

		if ( self::PROGRAM_PROJECTS === $program ) {
			self::render_projects();
			return;
		}

		if ( self::PROGRAM_SPECTACLES === $program ) {
			self::render_spectacles();
			return;
		}

		$tab = ( self::SCREEN_USERS === $screen || self::TAB_USERS === $tab ) ? self::TAB_USERS : self::TAB_PROGRAMS;

		if ( self::TAB_USERS === $tab ) {
			extract( self::users_vars( true ), EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
		} else {
			$action  = 'list';
			$message = '';
			$error   = '';
			$edit    = null;
		}

		include LCCL_DE_PATH . 'templates/admin-programs.php';
	}

	/**
	 * Hub-level reviewer accounts.
	 */
	private static function render_users() {
		extract( self::users_vars( true ), EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
		include LCCL_DE_PATH . 'templates/admin-program-users.php';
	}

	/**
	 * Blood donation notifications.
	 */
	private static function render_blood() {
		extract( self::tab_vars( 'notifications', true, self::PROGRAM_BLOOD ), EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
		include LCCL_DE_PATH . 'templates/admin-program-blood.php';
	}

	/**
	 * Join Our Projects workspace.
	 */
	private static function render_projects() {
		extract( self::tab_vars( 'notifications', true, self::PROGRAM_PROJECTS ), EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
		include LCCL_DE_PATH . 'templates/admin-program-projects.php';
	}

	/**
	 * Free Spectacles workspace.
	 */
	private static function render_spectacles() {
		extract( self::tab_vars( 'notifications', true, self::PROGRAM_SPECTACLES ), EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
		include LCCL_DE_PATH . 'templates/admin-program-spectacles.php';
	}

	/**
	 * HTML for one hub tab, used by AJAX.
	 *
	 * @param string $tab programs|users.
	 * @return string
	 */
	public static function tab_html( $tab ) {
		$tab = self::TAB_USERS === $tab ? self::TAB_USERS : self::TAB_PROGRAMS;

		ob_start();
		if ( self::TAB_USERS === $tab ) {
			extract( self::users_vars( false ), EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
			include LCCL_DE_PATH . 'templates/admin-users.php';
		} else {
			include LCCL_DE_PATH . 'templates/admin-programs-grid.php';
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

		$tab = isset( $_POST['tab'] ) ? sanitize_key( wp_unslash( $_POST['tab'] ) ) : self::TAB_PROGRAMS;
		if ( self::TAB_USERS !== $tab ) {
			$tab = self::TAB_PROGRAMS;
		}

		wp_send_json_success(
			array(
				'tab'  => $tab,
				'html' => self::tab_html( $tab ),
			)
		);
	}

	/**
	 * Variables the reviewers templates expect.
	 *
	 * @param bool $from_get Read action/message from the request.
	 * @return array
	 */
	private static function users_vars( $from_get ) {
		$action  = 'list';
		$message = '';
		$error   = '';
		$edit    = null;

		if ( $from_get ) {
			$message = isset( $_GET['message'] ) ? sanitize_key( wp_unslash( $_GET['message'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$error   = isset( $_GET['error'] ) ? sanitize_text_field( wp_unslash( $_GET['error'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$action  = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : 'list'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$user_id = isset( $_GET['user_id'] ) ? (int) $_GET['user_id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

			if ( 'edit' === $action && $user_id ) {
				$edit = LCCL_DE_Admin_Users::get_reviewer( $user_id );
				if ( ! $edit ) {
					$action  = 'list';
					$error   = __( 'That account is not a program reviewer.', 'lccl-de' );
					$message = 'error';
				}
			}
		}

		return array(
			'action'  => $action,
			'message' => $message,
			'error'   => $error,
			'edit'    => $edit,
		);
	}

	/**
	 * Variables the notification template expects.
	 *
	 * @param string $tab      users|notifications.
	 * @param bool   $from_get Read action/message from the request.
	 * @param string $program  blood-donation|our-projects.
	 * @return array
	 */
	private static function tab_vars( $tab, $from_get, $program = self::PROGRAM_BLOOD ) {
		$program = LCCL_DE_Settings::normalize_program( $program );
		$message = '';
		$error   = '';

		if ( $from_get ) {
			$message = isset( $_GET['message'] ) ? sanitize_key( wp_unslash( $_GET['message'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$error   = isset( $_GET['error'] ) ? sanitize_text_field( wp_unslash( $_GET['error'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( $error ) {
				$error = rawurldecode( $error );
			}
		}

		return array(
			'tab'           => 'notifications' === $tab ? 'notifications' : 'users',
			'program'       => $program,
			'notify_action' => self::notify_url( $program ),
			'action'        => 'list',
			'message'       => $message,
			'error'         => $error,
			'edit'          => null,
			'settings'      => LCCL_DE_Settings::get( $program ),
			'log'           => LCCL_DE_Notify::log( $program ),
			'smtp'          => class_exists( 'WPMailSMTP\Core' ) || defined( 'WPMS_PLUGIN_VER' ),
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
	 * Hub-level reviewers URL.
	 *
	 * @param array $args Query args.
	 * @return string
	 */
	public static function users_url( $args = array() ) {
		$args['tab'] = self::TAB_USERS;
		unset( $args['program'], $args['screen'] );
		return self::url( $args );
	}

	/**
	 * Blood donation workspace URL.
	 *
	 * @param array $args Query args.
	 * @return string
	 */
	public static function blood_url( $args = array() ) {
		$args['program'] = self::PROGRAM_BLOOD;
		unset( $args['screen'] );
		return self::url( $args );
	}

	/**
	 * Join Our Projects workspace URL.
	 *
	 * @param array $args Query args.
	 * @return string
	 */
	public static function projects_url( $args = array() ) {
		$args['program'] = self::PROGRAM_PROJECTS;
		unset( $args['screen'] );
		return self::url( $args );
	}

	/**
	 * Free Spectacles workspace URL.
	 *
	 * @param array $args Query args.
	 * @return string
	 */
	public static function spectacles_url( $args = array() ) {
		$args['program'] = self::PROGRAM_SPECTACLES;
		unset( $args['screen'] );
		return self::url( $args );
	}

	/**
	 * Notifications POST/redirect URL for a programme.
	 *
	 * @param string $program blood-donation|our-projects.
	 * @param array  $args    Query args.
	 * @return string
	 */
	public static function notify_url( $program, $args = array() ) {
		$program = LCCL_DE_Settings::normalize_program( $program );
		if ( self::PROGRAM_PROJECTS === $program ) {
			return self::projects_url( $args );
		}

		if ( self::PROGRAM_SPECTACLES === $program ) {
			return self::spectacles_url( $args );
		}

		$args['tab'] = 'notifications';
		return self::blood_url( $args );
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
