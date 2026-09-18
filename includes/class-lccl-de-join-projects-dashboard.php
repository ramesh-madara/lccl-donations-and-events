<?php
/**
 * Frontend Join Our Projects dashboard and REST API.
 *
 * @package LCCL_Donations_And_Events
 */

defined( 'ABSPATH' ) || exit;

/**
 * Shortcode shell plus project-join read/write routes.
 *
 * Session login stays on LCCL_DE_Dashboard so both review UIs share one cookie.
 */
class LCCL_DE_Join_Projects_Dashboard {

	/**
	 * Shortcode tag.
	 */
	const SHORTCODE = 'lccl_our_projects_admin';

	/**
	 * REST namespace. Same as blood donation so session routes are shared.
	 */
	const REST_NS = 'lccl-de/v1';

	/**
	 * Hook shortcode, REST, and WPBakery.
	 */
	public static function init() {
		add_shortcode( self::SHORTCODE, array( __CLASS__, 'render' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
		add_action( 'vc_before_init', array( __CLASS__, 'map' ) );
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
			'lccl-de-our-projects-admin',
			LCCL_DE_URL . 'assets/js/lccl-de-our-projects-admin.js',
			array(),
			LCCL_DE_VERSION,
			true
		);

		$can_view     = LCCL_DE_Roles::can_view_submissions();
		$can_manage   = LCCL_DE_Roles::can_manage_donors();
		$user         = wp_get_current_user();
		$current_dash = 'projects';
		$script_data  = array(
			'restUrl'              => esc_url_raw( rest_url( self::REST_NS . '/' ) ),
			'nonce'                => wp_create_nonce( 'wp_rest' ),
			'loggedIn'             => $can_view ? 1 : 0,
			'canManage'            => $can_manage ? 1 : 0,
			'displayName'          => $can_view ? self::display_name( $user ) : '',
			'perPage'              => 20,
			'perPages'             => array( 10, 20, 50 ),
			'supportWays'          => LCCL_DE_Join_Projects_Form::support_ways(),
			'volunteerAreas'       => LCCL_DE_Join_Projects_Form::volunteer_areas(),
			'availability'         => LCCL_DE_Join_Projects_Form::availability(),
			'financialSupport'     => LCCL_DE_Join_Projects_Form::financial_support(),
			'contributionAmounts'  => LCCL_DE_Join_Projects_Form::contribution_amounts(),
			'interestAreas'        => LCCL_DE_Join_Projects_Form::interest_areas(),
			'registeringAs'        => LCCL_DE_Join_Projects_Form::registering_as_options(),
		);

		wp_localize_script(
			'lccl-de-our-projects-admin',
			'lcclOpa',
			$script_data
		);

		ob_start();
		include LCCL_DE_PATH . 'templates/our-projects-admin.php';

		return ob_get_clean();
	}

	/**
	 * REST routes for Join Our Projects records.
	 */
	public static function register_routes() {
		register_rest_route(
			self::REST_NS,
			'/project-joins',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'rest_list' ),
				'permission_callback' => array( 'LCCL_DE_Dashboard', 'rest_can_view' ),
				'args'                => array(
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

		$id_args = array(
			'id' => array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
			),
		);

		register_rest_route(
			self::REST_NS,
			'/project-joins/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'rest_one' ),
					'permission_callback' => array( 'LCCL_DE_Dashboard', 'rest_can_view' ),
					'args'                => $id_args,
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( __CLASS__, 'rest_update' ),
					'permission_callback' => array( 'LCCL_DE_Dashboard', 'rest_can_manage' ),
					'args'                => $id_args,
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( __CLASS__, 'rest_delete' ),
					'permission_callback' => array( 'LCCL_DE_Dashboard', 'rest_can_manage' ),
					'args'                => $id_args,
				),
			)
		);
	}

	/**
	 * Paginated list.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function rest_list( WP_REST_Request $request ) {
		$result = LCCL_DE_Join_Projects_Submissions::query(
			array(
				'search'   => (string) $request->get_param( 'search' ),
				'page'     => (int) $request->get_param( 'page' ),
				'per_page' => (int) $request->get_param( 'per_page' ),
			)
		);

		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * One registration.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function rest_one( WP_REST_Request $request ) {
		$row = LCCL_DE_Join_Projects_Submissions::get( (int) $request['id'] );
		if ( ! $row ) {
			return new WP_Error( 'lccl_de_missing', __( 'Registration not found.', 'lccl-de' ), array( 'status' => 404 ) );
		}

		return new WP_REST_Response( LCCL_DE_Join_Projects_Submissions::present( $row ), 200 );
	}

	/**
	 * Update one registration. Site administrators only.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function rest_update( WP_REST_Request $request ) {
		$row = LCCL_DE_Join_Projects_Submissions::get( (int) $request['id'] );
		if ( ! $row ) {
			return new WP_Error( 'lccl_de_missing', __( 'Registration not found.', 'lccl-de' ), array( 'status' => 404 ) );
		}

		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = $request->get_params();
		}

		$values = LCCL_DE_Join_Projects_Submissions::sanitize_payload( $params );
		$errors = LCCL_DE_Join_Projects_Submissions::validate( $values, false );

		if ( ! empty( $errors ) ) {
			return new WP_Error(
				'lccl_de_invalid',
				__( 'Please correct the highlighted fields.', 'lccl-de' ),
				array(
					'status' => 400,
					'errors' => $errors,
				)
			);
		}

		$saved = LCCL_DE_Join_Projects_Submissions::update( (int) $row['id'], $values, get_current_user_id() );
		if ( ! $saved ) {
			return new WP_Error(
				'lccl_de_update',
				__( 'The registration could not be saved. Please try again.', 'lccl-de' ),
				array( 'status' => 500 )
			);
		}

		$fresh = LCCL_DE_Join_Projects_Submissions::get( (int) $row['id'] );
		if ( ! $fresh ) {
			return new WP_Error( 'lccl_de_missing', __( 'Registration not found.', 'lccl-de' ), array( 'status' => 404 ) );
		}

		return new WP_REST_Response( LCCL_DE_Join_Projects_Submissions::present( $fresh ), 200 );
	}

	/**
	 * Delete one registration. Site administrators only.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function rest_delete( WP_REST_Request $request ) {
		$row = LCCL_DE_Join_Projects_Submissions::get( (int) $request['id'] );
		if ( ! $row ) {
			return new WP_Error( 'lccl_de_missing', __( 'Registration not found.', 'lccl-de' ), array( 'status' => 404 ) );
		}

		if ( ! LCCL_DE_Join_Projects_Submissions::delete( (int) $row['id'] ) ) {
			return new WP_Error(
				'lccl_de_delete',
				__( 'The registration could not be deleted. Please try again.', 'lccl-de' ),
				array( 'status' => 500 )
			);
		}

		return new WP_REST_Response(
			array(
				'deleted' => true,
				'id'      => (int) $row['id'],
				'name'    => isset( $row['full_name'] ) ? (string) $row['full_name'] : '',
			),
			200
		);
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
				'name'        => __( 'LCCL Our Projects Admin', 'lccl-de' ),
				'base'        => self::SHORTCODE,
				'category'    => __( 'LCCL', 'lccl-de' ),
				'description' => __( 'Login and dashboard for Join Our Projects reviewers.', 'lccl-de' ),
				'icon'        => 'icon-wpb-ui-separator',
				'params'      => array(),
			)
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
}
