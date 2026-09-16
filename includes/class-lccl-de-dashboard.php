<?php
/**
 * Frontend blood donation dashboard and REST API.
 *
 * @package LCCL_Donations_And_Events
 */

defined( 'ABSPATH' ) || exit;

/**
 * Shortcode shell plus session and donor read routes.
 */
class LCCL_DE_Dashboard {

	/**
	 * Shortcode tag.
	 */
	const SHORTCODE = 'lccl_blood_donation_admin';

	/**
	 * REST namespace.
	 */
	const REST_NS = 'lccl-de/v1';

	/**
	 * Hook shortcode, REST, and WPBakery.
	 */
	public static function init() {
		add_shortcode( self::SHORTCODE, array( __CLASS__, 'render' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
		add_action( 'vc_before_init', array( __CLASS__, 'map' ) );
		add_filter( 'rest_authentication_errors', array( __CLASS__, 'allow_public_session_cookie_mismatch' ), 101 );
	}

	/**
	 * Render login or dashboard chrome. Data loads over REST after that.
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string
	 */
	public static function render( $atts ) {
		unset( $atts );

		nocache_headers();

		wp_enqueue_style(
			'lccl-de-blood-donation-admin',
			LCCL_DE_URL . 'assets/css/lccl-de-blood-donation-admin.css',
			array(),
			LCCL_DE_VERSION
		);

		wp_enqueue_script(
			'lccl-de-blood-donation-admin',
			LCCL_DE_URL . 'assets/js/lccl-de-blood-donation-admin.js',
			array(),
			LCCL_DE_VERSION,
			true
		);

		$can_view = LCCL_DE_Roles::can_view_submissions();
		$user     = wp_get_current_user();

		wp_localize_script(
			'lccl-de-blood-donation-admin',
			'lcclBda',
			array(
				'restUrl'     => esc_url_raw( rest_url( self::REST_NS . '/' ) ),
				'nonce'       => wp_create_nonce( 'wp_rest' ),
				'loggedIn'    => $can_view ? 1 : 0,
				'displayName' => $can_view ? self::display_name( $user ) : '',
				'districts'   => array_keys( LCCL_DE_Blood_Donor_Form::get_districts() ),
				'perPage'     => 20,
				'perPages'    => array( 10, 20, 50 ),
			)
		);

		$values = array();
		$errors = array();

		ob_start();
		include LCCL_DE_PATH . 'templates/blood-donation-admin.php';

		return ob_get_clean();
	}

	/**
	 * REST routes.
	 */
	public static function register_routes() {
		register_rest_route(
			self::REST_NS,
			'/session',
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

		register_rest_route(
			self::REST_NS,
			'/session/forgot',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'rest_forgot' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			self::REST_NS,
			'/donors',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'rest_donors' ),
				'permission_callback' => array( __CLASS__, 'rest_can_view' ),
				'args'                => array(
					'search'           => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'district'         => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'notify_campaigns' => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'page'             => array(
						'type'              => 'integer',
						'default'           => 1,
						'sanitize_callback' => 'absint',
					),
					'per_page'         => array(
						'type'              => 'integer',
						'default'           => 20,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			self::REST_NS,
			'/donors/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'rest_donor' ),
				'permission_callback' => array( __CLASS__, 'rest_can_view' ),
				'args'                => array(
					'id' => array(
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}

	/**
	 * Permission for authenticated dashboard routes.
	 *
	 * @return bool
	 */
	public static function rest_can_view() {
		return LCCL_DE_Roles::can_view_submissions();
	}

	/**
	 * Public session routes must still run when a leftover logged-in cookie
	 * is paired with a logged-out nonce (sign-out then sign-in without reload).
	 *
	 * Core REST cookie auth returns "Cookie check failed" in that case before
	 * our login callback can run.
	 *
	 * @param WP_Error|null|true $result Auth result.
	 * @return WP_Error|null|true
	 */
	public static function allow_public_session_cookie_mismatch( $result ) {
		if ( ! is_wp_error( $result ) || 'rest_cookie_invalid_nonce' !== $result->get_error_code() ) {
			return $result;
		}

		$route  = self::current_rest_route();
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : '';
		$login  = '/' . self::REST_NS . '/session';
		$forgot = '/' . self::REST_NS . '/session/forgot';

		if ( $login === $route && in_array( $method, array( 'GET', 'POST' ), true ) ) {
			return true;
		}

		if ( $forgot === $route && 'POST' === $method ) {
			return true;
		}

		return $result;
	}

	/**
	 * Current REST route, normalised with a leading slash.
	 *
	 * @return string
	 */
	private static function current_rest_route() {
		$route = '';

		if ( isset( $GLOBALS['wp']->query_vars['rest_route'] ) ) {
			$route = (string) $GLOBALS['wp']->query_vars['rest_route'];
		}

		$route = untrailingslashit( $route );
		if ( '' !== $route && '/' !== substr( $route, 0, 1 ) ) {
			$route = '/' . $route;
		}

		return $route;
	}

	/**
	 * Current session, or logged_in false.
	 *
	 * @return WP_REST_Response
	 */
	public static function rest_session() {
		if ( ! LCCL_DE_Roles::can_view_submissions() ) {
			return new WP_REST_Response(
				array(
					'logged_in' => false,
					'nonce'     => wp_create_nonce( 'wp_rest' ),
				),
				200
			);
		}

		return new WP_REST_Response( self::session_payload( wp_get_current_user() ), 200 );
	}

	/**
	 * Sign in with a WP username or email.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function rest_login( WP_REST_Request $request ) {
		if ( self::honeypot_triggered( $request ) ) {
			return new WP_Error(
				'lccl_de_login',
				__( 'Invalid username or password.', 'lccl-de' ),
				array( 'status' => 403 )
			);
		}

		if ( self::is_rate_limited( 'login' ) ) {
			return new WP_Error(
				'lccl_de_rate',
				__( 'Too many attempts from this connection. Please wait a while and try again.', 'lccl-de' ),
				array( 'status' => 429 )
			);
		}

		$login    = sanitize_text_field( (string) $request->get_param( 'username' ) );
		$password = (string) $request->get_param( 'password' );

		if ( '' === $login || '' === $password ) {
			return new WP_Error(
				'lccl_de_login',
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

		add_action( 'set_logged_in_cookie', array( __CLASS__, 'stash_logged_in_cookie' ) );

		$user = wp_signon(
			array(
				'user_login'    => $login,
				'user_password' => $password,
				'remember'      => true,
			),
			is_ssl()
		);

		if ( is_wp_error( $user ) ) {
			$code = $user->get_error_code();
			if ( 'lccl_de_reviewer_inactive' === $code ) {
				return new WP_Error( $code, $user->get_error_message(), array( 'status' => 403 ) );
			}

			return new WP_Error(
				'lccl_de_login',
				__( 'Invalid username or password.', 'lccl-de' ),
				array( 'status' => 403 )
			);
		}

		wp_set_current_user( $user->ID );

		if ( ! LCCL_DE_Roles::can_view_submissions( $user ) ) {
			wp_logout();
			return new WP_Error(
				'lccl_de_forbidden',
				__( 'This account cannot open the blood donation dashboard.', 'lccl-de' ),
				array( 'status' => 403 )
			);
		}

		return new WP_REST_Response( self::session_payload( $user ), 200 );
	}

	/**
	 * Make the auth cookie visible to nonce generation in this same request.
	 *
	 * setcookie() does not populate $_COOKIE until the next request, and
	 * wp_create_nonce() hashes the logged-in session token from that array.
	 *
	 * @param string $cookie Logged-in cookie value.
	 */
	public static function stash_logged_in_cookie( $cookie ) {
		$_COOKIE[ LOGGED_IN_COOKIE ] = $cookie;
	}

	/**
	 * End the WP session.
	 *
	 * @return WP_REST_Response
	 */
	public static function rest_logout() {
		wp_logout();
		wp_set_current_user( 0 );

		if ( isset( $_COOKIE[ LOGGED_IN_COOKIE ] ) ) {
			unset( $_COOKIE[ LOGGED_IN_COOKIE ] );
		}

		nocache_headers();

		$response = new WP_REST_Response(
			array(
				'logged_in' => false,
				'nonce'     => wp_create_nonce( 'wp_rest' ),
			),
			200
		);
		$response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0' );

		return $response;
	}

	/**
	 * Trigger the core lost-password email.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function rest_forgot( WP_REST_Request $request ) {
		if ( self::honeypot_triggered( $request ) ) {
			return new WP_REST_Response(
				array(
					'sent'    => true,
					'message' => __( 'If that account exists, a reset link is on its way.', 'lccl-de' ),
				),
				200
			);
		}

		if ( self::is_rate_limited( 'forgot' ) ) {
			return new WP_Error(
				'lccl_de_rate',
				__( 'Too many attempts from this connection. Please wait a while and try again.', 'lccl-de' ),
				array( 'status' => 429 )
			);
		}

		$login = sanitize_text_field( (string) $request->get_param( 'username' ) );
		if ( '' === $login ) {
			return new WP_Error(
				'lccl_de_forgot',
				__( 'Please enter your username or email.', 'lccl-de' ),
				array( 'status' => 400 )
			);
		}

		$result = retrieve_password( $login );

		if ( is_wp_error( $result ) ) {
			return new WP_REST_Response(
				array(
					'sent'    => true,
					'message' => __( 'If that account exists, a reset link is on its way.', 'lccl-de' ),
				),
				200
			);
		}

		return new WP_REST_Response(
			array(
				'sent'    => true,
				'message' => __( 'If that account exists, a reset link is on its way.', 'lccl-de' ),
			),
			200
		);
	}

	/**
	 * Paginated donor list.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function rest_donors( WP_REST_Request $request ) {
		global $wpdb;

		$table    = LCCL_DE_Schema::blood_donors_table();
		$search   = trim( (string) $request->get_param( 'search' ) );
		$district = (string) $request->get_param( 'district' );
		$notify   = (string) $request->get_param( 'notify_campaigns' );
		$page     = max( 1, (int) $request->get_param( 'page' ) );
		$per_page = (int) $request->get_param( 'per_page' );
		if ( ! in_array( $per_page, array( 10, 20, 50 ), true ) ) {
			$per_page = 20;
		}

		$where  = array( '1=1' );
		$params = array();

		if ( '' !== $search ) {
			$like     = '%' . $wpdb->esc_like( $search ) . '%';
			$where[]  = '(first_name LIKE %s OR last_name LIKE %s OR phone LIKE %s OR email LIKE %s OR CONCAT(first_name, \' \', last_name) LIKE %s)';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}

		if ( '' !== $district ) {
			$where[]  = 'district = %s';
			$params[] = $district;
		}

		if ( '0' === $notify || '1' === $notify ) {
			$where[]  = 'notify_campaigns = %d';
			$params[] = (int) $notify;
		}

		$where_sql = implode( ' AND ', $where );

		$count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
		$total     = (int) ( $params ? $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) ) : $wpdb->get_var( $count_sql ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$pages = $total > 0 ? (int) ceil( $total / $per_page ) : 0;
		if ( $pages > 0 && $page > $pages ) {
			$page = $pages;
		} elseif ( 0 === $pages ) {
			$page = 1;
		}

		$offset = ( $page - 1 ) * $per_page;

		$list_sql = "SELECT id, first_name, last_name, phone, email, district, blood_bank, blood_bank_label, notify_campaigns, contact_method, created_at
			FROM {$table}
			WHERE {$where_sql}
			ORDER BY created_at DESC, id DESC
			LIMIT %d OFFSET %d";

		$list_params   = $params;
		$list_params[] = $per_page;
		$list_params[] = $offset;

		$rows = $wpdb->get_results( $wpdb->prepare( $list_sql, $list_params ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$items = array();
		foreach ( (array) $rows as $row ) {
			$items[] = self::present_list_row( $row );
		}

		return new WP_REST_Response(
			array(
				'items'    => $items,
				'total'    => $total,
				'page'     => $page,
				'per_page' => $per_page,
				'pages'    => $pages,
			),
			200
		);
	}

	/**
	 * One donor for the detail pane.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function rest_donor( WP_REST_Request $request ) {
		global $wpdb;

		$id  = (int) $request['id'];
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . LCCL_DE_Schema::blood_donors_table() . ' WHERE id = %d',
				$id
			),
			ARRAY_A
		);

		if ( ! $row ) {
			return new WP_Error( 'lccl_de_missing', __( 'Registration not found.', 'lccl-de' ), array( 'status' => 404 ) );
		}

		return new WP_REST_Response( self::present_detail( $row ), 200 );
	}

	/**
	 * WPBakery element.
	 */
	public static function map() {
		if ( ! function_exists( 'vc_map' ) ) {
			return;
		}

		vc_map(
			array(
				'name'        => __( 'LCCL Blood Donation Admin', 'lccl-de' ),
				'base'        => self::SHORTCODE,
				'category'    => __( 'LCCL', 'lccl-de' ),
				'description' => __( 'Login and dashboard for blood donation reviewers.', 'lccl-de' ),
				'icon'        => 'icon-wpb-ui-separator',
				'params'      => array(),
			)
		);
	}

	/**
	 * Session JSON shared by login and GET /session.
	 *
	 * @param WP_User $user User.
	 * @return array
	 */
	private static function session_payload( WP_User $user ) {
		return array(
			'logged_in'    => true,
			'nonce'        => wp_create_nonce( 'wp_rest' ),
			'display_name' => self::display_name( $user ),
			'user_login'   => $user->user_login,
		);
	}

	/**
	 * Human name for the header.
	 *
	 * @param WP_User $user User.
	 * @return string
	 */
	private static function display_name( WP_User $user ) {
		$name = trim( $user->first_name . ' ' . $user->last_name );
		return '' !== $name ? $name : $user->display_name;
	}

	/**
	 * List-row DTO.
	 *
	 * @param array $row Database row.
	 * @return array
	 */
	private static function present_list_row( array $row ) {
		$methods = LCCL_DE_Blood_Donor_Form::get_contact_methods();

		return array(
			'id'               => (int) $row['id'],
			'first_name'       => $row['first_name'],
			'last_name'        => $row['last_name'],
			'name'             => trim( $row['first_name'] . ' ' . $row['last_name'] ),
			'phone'            => $row['phone'],
			'email'            => $row['email'],
			'district'         => $row['district'],
			'blood_bank'       => $row['blood_bank'],
			'blood_bank_label' => $row['blood_bank_label'],
			'notify_campaigns' => (int) $row['notify_campaigns'],
			'contact_method'   => $row['contact_method'],
			'contact_label'    => isset( $methods[ $row['contact_method'] ] ) ? $methods[ $row['contact_method'] ] : $row['contact_method'],
			'created_at'       => $row['created_at'],
			'created_label'    => mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $row['created_at'] ),
		);
	}

	/**
	 * Detail DTO. IP is only included for site admins.
	 *
	 * @param array $row Database row.
	 * @return array
	 */
	private static function present_detail( array $row ) {
		$prefs    = LCCL_DE_Blood_Donor_Form::get_donation_preferences();
		$history  = LCCL_DE_Blood_Donor_Form::get_donation_history_options();
		$payload  = self::present_list_row( $row );
		$more     = array(
			'address'                   => $row['address'],
			'city'                      => $row['city'],
			'postal_code'               => $row['postal_code'],
			'donation_preference'       => $row['donation_preference'],
			'donation_preference_label' => isset( $prefs[ $row['donation_preference'] ] ) ? $prefs[ $row['donation_preference'] ] : $row['donation_preference'],
			'donated_before'            => $row['donated_before'],
			'donated_before_label'      => isset( $history[ $row['donated_before'] ] ) ? $history[ $row['donated_before'] ] : $row['donated_before'],
			'consent'                   => (int) $row['consent'],
		);

		if ( current_user_can( 'manage_options' ) ) {
			$more['ip_address'] = $row['ip_address'];
		}

		return array_merge( $payload, $more );
	}

	/**
	 * Bots fill hidden fields.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool
	 */
	private static function honeypot_triggered( WP_REST_Request $request ) {
		$company = $request->get_param( 'lccl_de_hp' );
		return ! empty( $company );
	}

	/**
	 * IP rate limit for public session routes.
	 *
	 * @param string $bucket login or forgot.
	 * @return bool
	 */
	private static function is_rate_limited( $bucket ) {
		$ip = '';
		if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$candidate = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
			$ip        = filter_var( $candidate, FILTER_VALIDATE_IP ) ? $candidate : '';
		}

		if ( '' === $ip ) {
			return false;
		}

		$key   = 'lccl_de_' . $bucket . '_' . md5( $ip );
		$count = (int) get_transient( $key );
		if ( $count >= 8 ) {
			return true;
		}

		set_transient( $key, $count + 1, HOUR_IN_SECONDS );
		return false;
	}
}
