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
	 * Hub SMS credentials tab.
	 */
	const TAB_SMS = 'sms';

	/**
	 * Hub Payment Gateway credentials tab.
	 */
	const TAB_GATEWAY = 'gateway';

	/**
	 * Hook menu and assets.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'redirect_legacy' ) );
		add_action( 'admin_init', array( __CLASS__, 'handle_gateway_post' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'wp_ajax_lccl_de_program_tab', array( __CLASS__, 'ajax_tab' ) );
		add_action( 'wp_ajax_lccl_de_test_gateway', array( __CLASS__, 'ajax_test_gateway' ) );
	}

	/**
	 * Top-level menu.
	 */
	public static function menu() {
		add_menu_page(
			__( 'LCCL Programs', 'lccl-de' ),
			__( 'LCCL Programs', 'lccl-de' ),
			'read',
			self::PAGE,
			array( __CLASS__, 'render' ),
			'dashicons-screenoptions',
			26
		);

		add_submenu_page(
			self::PAGE,
			__( 'LCCL Programs', 'lccl-de' ),
			__( 'LCCL Programs', 'lccl-de' ),
			'read',
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
			wp_safe_redirect( self::blood_url( self::query_without_page() ) );
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
	 * Hub, reviewers, SMS, or a programme workspace. Tabs stay visible.
	 */
	public static function render() {
		if ( ! current_user_can( 'read' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage LCCL programs.', 'lccl-de' ) );
		}

		$screen  = isset( $_GET['screen'] ) ? sanitize_key( wp_unslash( $_GET['screen'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$program = isset( $_GET['program'] ) ? sanitize_key( wp_unslash( $_GET['program'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tab     = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$tab     = self::normalize_tab( ( self::SCREEN_USERS === $screen ) ? self::TAB_USERS : $tab );
		$program = self::normalize_program_key( $program );

		if ( self::TAB_USERS === $tab || self::TAB_SMS === $tab || self::TAB_GATEWAY === $tab ) {
			$program = '';
		}

		$action  = 'list';
		$message = '';
		$error   = '';
		$edit    = null;
		$settings = array();
		$log      = array();
		$smtp     = false;
		$notify_action = '';

		if ( self::TAB_USERS === $tab ) {
			extract( self::users_vars( true ), EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
		} elseif ( self::TAB_SMS === $tab ) {
			extract( self::sms_vars( true ), EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
		} elseif ( self::TAB_GATEWAY === $tab ) {
			extract( self::gateway_vars( true ), EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
		} elseif ( '' !== $program ) {
			extract( self::tab_vars( 'notifications', true, $program ), EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
			$tab = self::TAB_PROGRAMS;
		}

		include LCCL_DE_PATH . 'templates/admin-programs.php';
	}

	/**
	 * HTML for one hub panel, used by AJAX.
	 *
	 * @param string $tab     programs|users|sms.
	 * @param string $program Programme key when opening a card.
	 * @return string
	 */
	public static function tab_html( $tab, $program = '' ) {
		$tab     = self::normalize_tab( $tab );
		$program = self::normalize_program_key( $program );

		if ( self::TAB_USERS === $tab || self::TAB_SMS === $tab || self::TAB_GATEWAY === $tab ) {
			$program = '';
		}

		ob_start();
		if ( self::TAB_USERS === $tab ) {
			extract( self::users_vars( false ), EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
			include LCCL_DE_PATH . 'templates/admin-users.php';
		} elseif ( self::TAB_SMS === $tab ) {
			extract( self::sms_vars( false ), EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
			include LCCL_DE_PATH . 'templates/admin-sms.php';
		} elseif ( self::TAB_GATEWAY === $tab ) {
			extract( self::gateway_vars( false ), EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
			include LCCL_DE_PATH . 'templates/admin-gateway.php';
		} elseif ( '' !== $program ) {
			extract( self::tab_vars( 'notifications', false, $program ), EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
			include LCCL_DE_PATH . 'templates/admin-program-workspace.php';
		} else {
			include LCCL_DE_PATH . 'templates/admin-programs-grid.php';
		}

		return (string) ob_get_clean();
	}

	/**
	 * Fetch a tab without reloading wp-admin.
	 */
	public static function ajax_tab() {
		if ( ! current_user_can( 'read' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to manage LCCL programs.', 'lccl-de' ) ), 403 );
		}

		check_ajax_referer( 'lccl_de_program_tab', 'nonce' );

		$tab     = isset( $_POST['tab'] ) ? sanitize_key( wp_unslash( $_POST['tab'] ) ) : self::TAB_PROGRAMS;
		$program = isset( $_POST['program'] ) ? sanitize_key( wp_unslash( $_POST['program'] ) ) : '';
		$tab     = self::normalize_tab( $tab );
		$program = ( self::TAB_PROGRAMS === $tab ) ? self::normalize_program_key( $program ) : '';

		wp_send_json_success(
			array(
				'tab'     => $tab,
				'program' => $program,
				'html'    => self::tab_html( $tab, $program ),
			)
		);
	}

	/**
	 * programs, users, or sms.
	 *
	 * @param string $tab Raw tab.
	 * @return string
	 */
	public static function normalize_tab( $tab ) {
		$tab = sanitize_key( (string) $tab );
		if ( self::TAB_USERS === $tab ) {
			return self::TAB_USERS;
		}
		if ( self::TAB_SMS === $tab ) {
			return self::TAB_SMS;
		}
		if ( self::TAB_GATEWAY === $tab ) {
			return self::TAB_GATEWAY;
		}

		return self::TAB_PROGRAMS;
	}

	/**
	 * Known programme key, or empty.
	 *
	 * @param string $program Raw key.
	 * @return string
	 */
	public static function normalize_program_key( $program ) {
		$program = sanitize_key( (string) $program );
		if ( in_array( $program, array( self::PROGRAM_BLOOD, self::PROGRAM_PROJECTS, self::PROGRAM_SPECTACLES ), true ) ) {
			return $program;
		}

		return '';
	}

	/**
	 * Label for a programme card or breadcrumb.
	 *
	 * @param string $program Programme key.
	 * @return string
	 */
	public static function program_label( $program ) {
		$program = self::normalize_program_key( $program );
		if ( self::PROGRAM_PROJECTS === $program ) {
			return __( 'Join Our Projects', 'lccl-de' );
		}
		if ( self::PROGRAM_SPECTACLES === $program ) {
			return __( 'Free Spectacles', 'lccl-de' );
		}
		if ( self::PROGRAM_BLOOD === $program ) {
			return __( 'Blood Donation', 'lccl-de' );
		}

		return '';
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
			'tab'           => self::TAB_PROGRAMS,
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
	 * Variables the shared SMS tab expects.
	 *
	 * @param bool $from_get Read flash from the request.
	 * @return array
	 */
	private static function sms_vars( $from_get ) {
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
			'tab'      => self::TAB_SMS,
			'program'  => '',
			'message'  => $message,
			'error'    => $error,
			'settings' => LCCL_DE_Settings::get(),
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
	 * Shared Dialog e-SMS credentials URL.
	 *
	 * @param array $args Query args.
	 * @return string
	 */
	public static function sms_url( $args = array() ) {
		$args['tab'] = self::TAB_SMS;
		unset( $args['program'], $args['screen'] );
		return self::url( $args );
	}

	/**
	 * Workspace URL for a programme.
	 *
	 * @param string $program Programme key.
	 * @param array  $args    Query args.
	 * @return string
	 */
	public static function program_url( $program, $args = array() ) {
		$program = LCCL_DE_Settings::normalize_program( $program );
		if ( self::PROGRAM_PROJECTS === $program ) {
			return self::projects_url( $args );
		}
		if ( self::PROGRAM_SPECTACLES === $program ) {
			return self::spectacles_url( $args );
		}

		return self::blood_url( $args );
	}

	/**
	 * Blood donation workspace URL.
	 *
	 * @param array $args Query args.
	 * @return string
	 */
	public static function blood_url( $args = array() ) {
		$args['program'] = self::PROGRAM_BLOOD;
		unset( $args['screen'], $args['tab'] );
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
		unset( $args['screen'], $args['tab'] );
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
		unset( $args['screen'], $args['tab'] );
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
		return self::program_url( $program, $args );
	}

	/**
	 * Public dashboard URL for a programme.
	 *
	 * @param string $program Programme key.
	 * @return string
	 */
	public static function program_dashboard_url( $program ) {
		$program = self::normalize_program_key( $program );
		if ( self::PROGRAM_PROJECTS === $program ) {
			return LCCL_DE_Roles::projects_dashboard_url();
		}
		if ( self::PROGRAM_SPECTACLES === $program ) {
			return LCCL_DE_Roles::spectacles_dashboard_url();
		}

		return LCCL_DE_Roles::dashboard_url();
	}

	/**
	 * Intro copy for a programme workspace.
	 *
	 * @param string $program Programme key.
	 * @return string
	 */
	public static function program_lede( $program ) {
		$program = self::normalize_program_key( $program );
		if ( self::PROGRAM_PROJECTS === $program ) {
			return __( 'Choose which emails and SMS go out after someone registers their interest. Place the [lccl_join_our_projects] shortcode on any page to show the registration form.', 'lccl-de' );
		}
		if ( self::PROGRAM_SPECTACLES === $program ) {
			return __( 'Choose which emails and SMS go out after someone registers a child. Place the [lccl_spectacles_registration] shortcode on any page to show the registration form.', 'lccl-de' );
		}

		return __( 'Choose which emails and SMS go out after someone signs up.', 'lccl-de' );
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

	// ------------------------------------------------------------------
	// Payment Gateway tab helpers
	// ------------------------------------------------------------------

	/**
	 * URL for the Payment Gateway tab.
	 *
	 * @param array $extra Extra query args.
	 * @return string
	 */
	public static function gateway_url( $extra = array() ) {
		return add_query_arg(
			array_merge( array( 'page' => self::PAGE, 'tab' => self::TAB_GATEWAY ), $extra ),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Variables the gateway template expects.
	 *
	 * @param bool $from_get Read message/error/subtab from $_GET.
	 * @return array
	 */
	private static function gateway_vars( $from_get ) {
		$message = '';
		$error   = '';
		$subtab  = LCCL_DE_Settings::PROFILE_DONATIONS;

		if ( $from_get ) {
			$message = isset( $_GET['message'] ) ? sanitize_key( wp_unslash( $_GET['message'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$error   = isset( $_GET['error'] ) ? sanitize_text_field( rawurldecode( wp_unslash( $_GET['error'] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( ! empty( $_GET['subtab'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$subtab = sanitize_key( wp_unslash( $_GET['subtab'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			}
		}

		if ( 'transactions' !== $subtab ) {
			$subtab = LCCL_DE_Settings::normalize_paycenter_profile( $subtab );
		}

		return array(
			'tab'      => self::TAB_GATEWAY,
			'subtab'   => $subtab,
			'profiles' => LCCL_DE_Settings::get_all_paycenter_profiles(),
			'message'  => $message,
			'error'    => $error,
		);
	}

	/**
	 * Handle POST from the Payment Gateway settings form.
	 */
	public static function handle_gateway_post() {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Handle CBC Paycenter save.
		if ( ! empty( $_POST['lccl_de_paycenter_save'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			check_admin_referer( 'lccl_de_paycenter_save', 'lccl_de_paycenter_nonce' );

			$profile = isset( $_POST['gateway_profile'] )
				? sanitize_key( wp_unslash( $_POST['gateway_profile'] ) )
				: LCCL_DE_Settings::PROFILE_DONATIONS;
			$profile = LCCL_DE_Settings::normalize_paycenter_profile( $profile );

			LCCL_DE_Settings::save_paycenter( wp_unslash( $_POST ), $profile ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

			wp_safe_redirect(
				self::gateway_url(
					array(
						'subtab'  => $profile,
						'message' => 'saved',
					)
				)
			);
			exit;
		}

		if ( empty( $_POST['lccl_de_mpgs_save'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return;
		}

		check_admin_referer( 'lccl_de_mpgs_save', 'lccl_de_mpgs_nonce' );

		$profile = isset( $_POST['gateway_profile'] ) ? sanitize_key( wp_unslash( $_POST['gateway_profile'] ) ) : LCCL_DE_Settings::PROFILE_DONATIONS; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$profile = LCCL_DE_Settings::normalize_mpgs_profile( $profile );

		LCCL_DE_Settings::save_mpgs( wp_unslash( $_POST ), $profile ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

		wp_safe_redirect(
			self::gateway_url(
				array(
					'subtab'  => $profile,
					'message' => 'saved',
				)
			)
		);
		exit;
	}

	/**
	 * AJAX test connection to Bancstac / Paycenter.
	 */
	public static function ajax_test_gateway() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'lccl-de' ) ), 403 );
		}

		check_ajax_referer( 'lccl_de_test_gateway', 'nonce' );

		$profile = isset( $_POST['profile'] ) ? sanitize_key( wp_unslash( $_POST['profile'] ) ) : LCCL_DE_Settings::PROFILE_MEMBERSHIP;
		$profile = LCCL_DE_Settings::normalize_paycenter_profile( $profile );

		$result = LCCL_DE_Paycenter_Client::test_connection( $profile );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => __( 'Connection successful! Bancstac gateway accepted credentials.', 'lccl-de' ) ) );
	}
}

