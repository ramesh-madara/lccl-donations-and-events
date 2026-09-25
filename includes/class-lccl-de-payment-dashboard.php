<?php
/**
 * Frontend Payment Dashboard and REST API.
 *
 * Dedicated dashboard at /payment-dashboard to monitor all payments:
 *  - Online Donations
 *  - Project Sponsorships
 *  - Membership Fee Payments (Single & Family)
 *
 * Only users who can access wp-admin are permitted to access this dashboard.
 *
 * @package LCCL_Donations_And_Events
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handles the [lccl_payment_dashboard] shortcode and REST endpoints.
 */
class LCCL_DE_Payment_Dashboard {

	/**
	 * Shortcode tag.
	 */
	const SHORTCODE = 'lccl_payment_dashboard';

	/**
	 * REST namespace.
	 */
	const REST_NS = 'lccl-de/v1';

	/**
	 * Hook shortcode, REST, and page auto-creation.
	 */
	public static function init() {
		add_shortcode( self::SHORTCODE, array( __CLASS__, 'render' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
		add_action( 'vc_before_init', array( __CLASS__, 'map' ) );
		add_filter( 'rest_authentication_errors', array( __CLASS__, 'allow_public_session_cookie_mismatch' ), 101 );

		// Ensure the /payment-dashboard page exists.
		if ( ! is_admin() && (int) get_option( LCCL_DE_Roles::PAYMENT_DASHBOARD_PAGE_OPTION, 0 ) === 0 ) {
			LCCL_DE_Roles::ensure_payment_dashboard_page();
		}
	}

	/**
	 * Display name of the user.
	 *
	 * @param WP_User $user User instance.
	 * @return string
	 */
	public static function display_name( $user ) {
		if ( ! ( $user instanceof WP_User ) ) {
			return '';
		}

		$name = trim( $user->first_name . ' ' . $user->last_name );
		return '' !== $name ? $name : $user->display_name;
	}

	/**
	 * Check whether current user can access this payment dashboard.
	 * Only users who can access wp-admin are permitted.
	 *
	 * @param WP_User|int|null $user User or ID.
	 * @return bool
	 */
	public static function can_access( $user = null ) {
		return LCCL_DE_Roles::can_access_wp_admin( $user );
	}

	/**
	 * Render the payment dashboard shell.
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string
	 */
	public static function render( $atts ) {
		unset( $atts );

		nocache_headers();

		// Enqueue styles.
		wp_enqueue_style(
			'lccl-de-blood-donation-admin',
			LCCL_DE_URL . 'assets/css/lccl-de-blood-donation-admin.css',
			array(),
			LCCL_DE_VERSION
		);

		wp_enqueue_style(
			'lccl-de-payment-dashboard',
			LCCL_DE_URL . 'assets/css/lccl-de-payment-dashboard.css',
			array( 'lccl-de-blood-donation-admin' ),
			LCCL_DE_VERSION
		);

		// Enqueue script.
		wp_enqueue_script(
			'lccl-de-payment-dashboard',
			LCCL_DE_URL . 'assets/js/lccl-de-payment-dashboard.js',
			array(),
			LCCL_DE_VERSION,
			true
		);

		$user     = wp_get_current_user();
		$can_view = is_user_logged_in() && self::can_access( $user );

		$script_data = array(
			'restUrl'     => esc_url_raw( rest_url( self::REST_NS . '/payment-dashboard/' ) ),
			'nonce'       => wp_create_nonce( 'wp_rest' ),
			'loggedIn'    => $can_view ? 1 : 0,
			'displayName' => $can_view ? self::display_name( $user ) : '',
			'adminUrl'    => esc_url( admin_url( 'admin.php?page=lccl-de-programs&tab=gateway&subtab=transactions' ) ),
			'perPage'     => 20,
			'perPages'    => array( 10, 20, 50 ),
		);

		wp_localize_script(
			'lccl-de-payment-dashboard',
			'lcclPayDash',
			$script_data
		);

		ob_start();
		include LCCL_DE_PATH . 'templates/payment-dashboard.php';
		return (string) ob_get_clean();
	}

	/**
	 * Register REST API routes.
	 */
	public static function register_routes() {
		// Session / Authentication routes.
		register_rest_route(
			self::REST_NS,
			'/payment-dashboard/session',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'rest_login' ),
					'permission_callback' => '__return_true',
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( __CLASS__, 'rest_logout' ),
					'permission_callback' => array( __CLASS__, 'rest_can_view' ),
				),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'rest_session' ),
					'permission_callback' => '__return_true',
				),
			)
		);

		// Password reset route.
		register_rest_route(
			self::REST_NS,
			'/payment-dashboard/session/forgot',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'rest_forgot' ),
				'permission_callback' => '__return_true',
			)
		);

		// Transactions query route.
		register_rest_route(
			self::REST_NS,
			'/payment-dashboard/transactions',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'rest_transactions' ),
				'permission_callback' => array( __CLASS__, 'rest_can_view' ),
				'args'                => array(
					'tab'      => array(
						'type'              => 'string',
						'default'           => 'all',
						'sanitize_callback' => 'sanitize_key',
					),
					'status'   => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_key',
					),
					'search'   => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'page'     => array(
						'type'              => 'integer',
						'default'           => 1,
						'sanitize_callback' => 'absint',
					),
					'per_page' => array(
						'type'              => 'integer',
						'default'           => 20,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}

	/**
	 * Permission callback for authenticated payment dashboard routes.
	 *
	 * @return bool
	 */
	public static function rest_can_view() {
		return is_user_logged_in() && self::can_access();
	}

	/**
	 * Bypass cookie nonce checks on login / forgot password.
	 *
	 * @param WP_Error|null|true $result Auth result.
	 * @return WP_Error|null|true
	 */
	public static function allow_public_session_cookie_mismatch( $result ) {
		if ( ! is_wp_error( $result ) || 'rest_cookie_invalid_nonce' !== $result->get_error_code() ) {
			return $result;
		}

		$route = isset( $GLOBALS['wp']->query_vars['rest_route'] ) ? untrailingslashit( (string) $GLOBALS['wp']->query_vars['rest_route'] ) : '';
		if ( '' !== $route && '/' !== substr( $route, 0, 1 ) ) {
			$route = '/' . $route;
		}

		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : '';
		$login  = '/' . self::REST_NS . '/payment-dashboard/session';
		$forgot = '/' . self::REST_NS . '/payment-dashboard/session/forgot';

		if ( $login === $route && in_array( $method, array( 'GET', 'POST' ), true ) ) {
			return true;
		}
		if ( $forgot === $route && 'POST' === $method ) {
			return true;
		}

		return $result;
	}

	/**
	 * Session payload.
	 *
	 * @param WP_User $user Current user.
	 * @return array
	 */
	private static function session_payload( $user ) {
		return array(
			'logged_in'    => true,
			'display_name' => self::display_name( $user ),
			'nonce'        => wp_create_nonce( 'wp_rest' ),
		);
	}

	/**
	 * Check session status.
	 *
	 * @return WP_REST_Response
	 */
	public static function rest_session() {
		$user = wp_get_current_user();
		if ( ! is_user_logged_in() || ! self::can_access( $user ) ) {
			return new WP_REST_Response(
				array(
					'logged_in' => false,
					'nonce'     => wp_create_nonce( 'wp_rest' ),
				),
				200
			);
		}

		return new WP_REST_Response( self::session_payload( $user ), 200 );
	}

	/**
	 * Log in handler for the Payment Dashboard.
	 * Strictly verifies that the authenticated user can access wp-admin.
	 *
	 * @param WP_REST_Request $request REST Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function rest_login( WP_REST_Request $request ) {
		$hp = $request->get_param( 'lccl_de_hp' );
		if ( ! empty( $hp ) ) {
			return new WP_Error(
				'lccl_pay_login',
				__( 'Invalid username or password.', 'lccl-de' ),
				array( 'status' => 403 )
			);
		}

		$login    = sanitize_text_field( (string) $request->get_param( 'username' ) );
		$password = (string) $request->get_param( 'password' );

		if ( '' === $login || '' === $password ) {
			return new WP_Error(
				'lccl_pay_login',
				__( 'Please enter your username and password.', 'lccl-de' ),
				array( 'status' => 400 )
			);
		}

		if ( is_email( $login ) ) {
			$by_email = get_user_by( 'email', $login );
			if ( $by_email ) {
				$login = $by_email->user_login;
			}
		}

		add_action( 'set_logged_in_cookie', array( 'LCCL_DE_Dashboard', 'stash_logged_in_cookie' ) );

		$user = wp_signon(
			array(
				'user_login'    => $login,
				'user_password' => $password,
				'remember'      => true,
			),
			is_ssl()
		);

		if ( is_wp_error( $user ) ) {
			return new WP_Error(
				'lccl_pay_login',
				__( 'Invalid username or password.', 'lccl-de' ),
				array( 'status' => 403 )
			);
		}

		wp_set_current_user( $user->ID );

		// Strict access check: only users who can access wp-admin.
		if ( ! self::can_access( $user ) ) {
			wp_logout();
			wp_set_current_user( 0 );

			return new WP_Error(
				'lccl_pay_forbidden',
				__( 'Access restricted. Only users who can access wp-admin are permitted to view the Payment Dashboard.', 'lccl-de' ),
				array( 'status' => 403 )
			);
		}

		return new WP_REST_Response( self::session_payload( $user ), 200 );
	}

	/**
	 * Log out handler.
	 *
	 * @return WP_REST_Response
	 */
	public static function rest_logout() {
		wp_logout();
		wp_set_current_user( 0 );

		return new WP_REST_Response(
			array(
				'logged_in' => false,
				'nonce'     => wp_create_nonce( 'wp_rest' ),
			),
			200
		);
	}

	/**
	 * Forgot password handler.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function rest_forgot( WP_REST_Request $request ) {
		$login = sanitize_text_field( (string) $request->get_param( 'username' ) );

		if ( '' === $login ) {
			return new WP_Error(
				'lccl_pay_forgot',
				__( 'Please enter your username or email address.', 'lccl-de' ),
				array( 'status' => 400 )
			);
		}

		$user = is_email( $login ) ? get_user_by( 'email', $login ) : get_user_by( 'login', $login );
		if ( ! $user || ! ( $user instanceof WP_User ) ) {
			// Do not leak whether user exists.
			return new WP_REST_Response(
				array(
					'message' => __( 'If an account exists with that login, a reset link has been sent to the email address on file.', 'lccl-de' ),
					'nonce'   => wp_create_nonce( 'wp_rest' ),
				),
				200
			);
		}

		// Retrieve password and send email.
		require_once ABSPATH . 'wp-includes/pluggable.php';
		retrieve_password( $user->user_login );

		return new WP_REST_Response(
			array(
				'message' => __( 'If an account exists with that login, a reset link has been sent to the email address on file.', 'lccl-de' ),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
			),
			200
		);
	}

	/**
	 * Query payments across all routes according to tab, status, search, and pagination.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function rest_transactions( WP_REST_Request $request ) {
		global $wpdb;

		$payments_table     = LCCL_DE_Schema::payments_table();
		$sponsorships_table = LCCL_DE_Schema::sponsorships_table();
		$sponsorships_exist = LCCL_DE_Schema::sponsorships_exist();

		$union_sql = "
			(
				SELECT
					id,
					order_ref,
					member_first_name AS first_name,
					member_last_name AS last_name,
					member_email AS email,
					member_phone AS phone,
					membership_type AS tx_type,
					family_count,
					'' AS project,
					'' AS project_label,
					'' AS extra_message,
					amount_lkr,
					currency,
					status,
					session_id,
					gateway_receipt,
					gateway_response,
					ip_address,
					created_at,
					paid_at,
					'payments' AS source_table
				FROM `{$payments_table}`
			)
		";

		if ( $sponsorships_exist ) {
			$union_sql .= "
				UNION ALL
				(
					SELECT
						id,
						order_ref,
						first_name,
						last_name,
						email,
						phone,
						'sponsorship' AS tx_type,
						0 AS family_count,
						project,
						project_label,
						message AS extra_message,
						amount_lkr,
						currency,
						status,
						session_id,
						gateway_receipt,
						gateway_response,
						ip_address,
						created_at,
						paid_at,
						'sponsorships' AS source_table
					FROM `{$sponsorships_table}`
				)
			";
		}

		$tab      = sanitize_key( (string) $request->get_param( 'tab' ) );
		$status   = sanitize_key( (string) $request->get_param( 'status' ) );
		$search   = sanitize_text_field( (string) $request->get_param( 'search' ) );
		$page     = max( 1, absint( $request->get_param( 'page' ) ) );
		$per_page = max( 1, min( 100, absint( $request->get_param( 'per_page' ) ?: 20 ) ) );
		$offset   = ( $page - 1 ) * $per_page;

		// Overall Stats (unfiltered by status/search, or filtered by tab).
		$tab_stat_clause = '';
		if ( 'donation' === $tab ) {
			$tab_stat_clause = "WHERE tx_type = 'donation'";
		} elseif ( 'sponsorship' === $tab ) {
			$tab_stat_clause = "WHERE tx_type = 'sponsorship'";
		} elseif ( 'membership' === $tab ) {
			$tab_stat_clause = "WHERE tx_type IN ('member', 'family')";
		}

		$stats_row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			"SELECT
				COUNT(*) as total_count,
				COALESCE(SUM(CASE WHEN status = 'paid' AND (currency = 'LKR' OR currency IS NULL OR currency = '') THEN amount_lkr ELSE 0 END), 0) as total_paid_lkr,
				COALESCE(SUM(CASE WHEN status = 'paid' AND currency = 'USD' THEN amount_lkr ELSE 0 END), 0) as total_paid_usd,
				COUNT(CASE WHEN status = 'paid' THEN 1 END) as paid_count,
				COUNT(CASE WHEN status = 'failed' THEN 1 END) as failed_count,
				COUNT(CASE WHEN status IN ('pending', 'cancelled') THEN 1 END) as other_count
			FROM ({$union_sql}) AS all_tx {$tab_stat_clause}", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);

		// Build WHERE clause for the list table.
		$where_clauses = array( '1=1' );
		$params        = array();

		// Filter by Tab (Route).
		if ( 'donation' === $tab ) {
			$where_clauses[] = "tx_type = 'donation'";
		} elseif ( 'sponsorship' === $tab ) {
			$where_clauses[] = "tx_type = 'sponsorship'";
		} elseif ( 'membership' === $tab ) {
			$where_clauses[] = "tx_type IN ('member', 'family')";
		}

		// Filter by Status.
		if ( '' !== $status && in_array( $status, array( 'paid', 'failed', 'pending', 'cancelled' ), true ) ) {
			$where_clauses[] = 'status = %s';
			$params[]        = $status;
		}

		// Universal search.
		if ( '' !== $search ) {
			$like            = '%' . $wpdb->esc_like( $search ) . '%';
			$where_clauses[] = '(order_ref LIKE %s OR first_name LIKE %s OR last_name LIKE %s OR email LIKE %s OR phone LIKE %s OR gateway_receipt LIKE %s OR project_label LIKE %s)';
			$params[]        = $like;
			$params[]        = $like;
			$params[]        = $like;
			$params[]        = $like;
			$params[]        = $like;
			$params[]        = $like;
			$params[]        = $like;
		}

		$where_sql = implode( ' AND ', $where_clauses );

		// Count matching rows.
		if ( ! empty( $params ) ) {
			$total_items = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->prepare( "SELECT COUNT(*) FROM ({$union_sql}) AS all_tx WHERE {$where_sql}", $params ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			);
		} else {
			$total_items = (int) $wpdb->get_var( "SELECT COUNT(*) FROM ({$union_sql}) AS all_tx WHERE {$where_sql}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}

		$total_pages = max( 1, (int) ceil( $total_items / $per_page ) );

		// Fetch page items.
		$query_params   = $params;
		$query_params[] = $per_page;
		$query_params[] = $offset;

		$results = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT * FROM ({$union_sql}) AS all_tx WHERE {$where_sql} ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d",
				$query_params
			),
			ARRAY_A
		);

		// Format each item.
		$formatted = array();
		foreach ( (array) $results as $row ) {
			$gw_raw  = (string) $row['gateway_response'];
			$gw_json = json_decode( $gw_raw, true );

			$resp_code = '';
			$resp_text = '';
			if ( is_array( $gw_json ) ) {
				if ( isset( $gw_json['responseCode'] ) ) {
					$resp_code = (string) $gw_json['responseCode'];
				}
				if ( isset( $gw_json['responseText'] ) ) {
					$resp_text = (string) $gw_json['responseText'];
				}
			}

			$causes  = array();
			$message = (string) $row['extra_message'];
			if ( is_array( $gw_json ) ) {
				if ( ! empty( $gw_json['causes'] ) ) {
					$causes = (array) $gw_json['causes'];
				}
				if ( empty( $message ) && ! empty( $gw_json['message'] ) ) {
					$message = (string) $gw_json['message'];
				}
			}

			$formatted[] = array(
				'id'               => (int) $row['id'],
				'source_table'     => (string) $row['source_table'],
				'order_ref'        => (string) $row['order_ref'],
				'first_name'       => (string) $row['first_name'],
				'last_name'        => (string) $row['last_name'],
				'full_name'        => trim( $row['first_name'] . ' ' . $row['last_name'] ),
				'email'            => (string) $row['email'],
				'phone'            => (string) $row['phone'],
				'tx_type'          => (string) $row['tx_type'],
				'family_count'     => (int) $row['family_count'],
				'project'          => (string) $row['project'],
				'project_label'    => (string) $row['project_label'],
				'amount_lkr'       => (float) $row['amount_lkr'],
				'currency'         => ! empty( $row['currency'] ) ? strtoupper( (string) $row['currency'] ) : 'LKR',
				'amount_formatted' => number_format( (float) $row['amount_lkr'], 2 ),
				'status'           => (string) $row['status'],
				'session_id'       => (string) $row['session_id'],
				'gateway_receipt'  => (string) $row['gateway_receipt'],
				'resp_code'        => $resp_code,
				'resp_text'        => $resp_text,
				'ip_address'       => (string) $row['ip_address'],
				'created_at'       => (string) $row['created_at'],
				'created_fmt'      => mysql2date( 'd M Y, H:i', $row['created_at'] ),
				'paid_at'          => (string) $row['paid_at'],
				'paid_fmt'         => ! empty( $row['paid_at'] ) ? mysql2date( 'd M Y, H:i', $row['paid_at'] ) : '',
				'causes'           => $causes,
				'message'          => $message,
				'breakdown'        => isset( $gw_json['breakdown'] ) ? $gw_json['breakdown'] : null,
				'gateway_json'     => $gw_json,
				'gateway_raw'      => $gw_raw,
			);
		}

		return new WP_REST_Response(
			array(
				'items'       => $formatted,
				'total'       => $total_items,
				'total_pages' => $total_pages,
				'page'        => $page,
				'per_page'    => $per_page,
				'stats'       => array(
					'total_count'    => isset( $stats_row['total_count'] ) ? (int) $stats_row['total_count'] : 0,
					'total_paid_lkr' => isset( $stats_row['total_paid_lkr'] ) ? (float) $stats_row['total_paid_lkr'] : 0.00,
					'total_paid_usd' => isset( $stats_row['total_paid_usd'] ) ? (float) $stats_row['total_paid_usd'] : 0.00,
					'paid_count'     => isset( $stats_row['paid_count'] ) ? (int) $stats_row['paid_count'] : 0,
					'failed_count'   => isset( $stats_row['failed_count'] ) ? (int) $stats_row['failed_count'] : 0,
					'other_count'    => isset( $stats_row['other_count'] ) ? (int) $stats_row['other_count'] : 0,
				),
				'nonce'       => wp_create_nonce( 'wp_rest' ),
			),
			200
		);
	}

	/**
	 * Map shortcode to WPBakery Page Builder.
	 */
	public static function map() {
		if ( ! function_exists( 'vc_map' ) ) {
			return;
		}

		vc_map(
			array(
				'name'        => __( 'LCCL Payment Dashboard', 'lccl-de' ),
				'base'        => self::SHORTCODE,
				'category'    => __( 'LCCL', 'lccl-de' ),
				'description' => __( 'Frontend payment monitoring dashboard for wp-admin users.', 'lccl-de' ),
				'params'      => array(),
			)
		);
	}
}
