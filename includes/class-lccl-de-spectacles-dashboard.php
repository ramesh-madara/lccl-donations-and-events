<?php
/**
 * Frontend Free Spectacles dashboard and REST API.
 *
 * @package LCCL_Donations_And_Events
 */

defined( 'ABSPATH' ) || exit;

/**
 * Shortcode shell plus spectacles read/write routes.
 *
 * Session login stays on LCCL_DE_Dashboard so review UIs share one cookie.
 */
class LCCL_DE_Spectacles_Dashboard {

	/**
	 * Shortcode tag.
	 */
	const SHORTCODE = 'lccl_spectacles_registration_admin';

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
			'lccl-de-spectacles-admin',
			LCCL_DE_URL . 'assets/js/lccl-de-spectacles-admin.js',
			array(),
			LCCL_DE_VERSION,
			true
		);

		$can_view     = LCCL_DE_Roles::can_view_submissions();
		$can_manage   = LCCL_DE_Roles::can_manage_donors();
		$user         = wp_get_current_user();
		$current_dash = 'spectacles';
		$script_data  = array(
			'restUrl'            => esc_url_raw( rest_url( self::REST_NS . '/' ) ),
			'nonce'              => wp_create_nonce( 'wp_rest' ),
			'loggedIn'           => $can_view ? 1 : 0,
			'canManage'          => $can_manage ? 1 : 0,
			'displayName'        => $can_view ? self::display_name( $user ) : '',
			'districts'          => array_keys( LCCL_DE_Spectacles_Form::districts() ),
			'perPage'            => 20,
			'perPages'           => array( 10, 20, 50 ),
			'ages'               => LCCL_DE_Spectacles_Form::ages(),
			'genders'            => LCCL_DE_Spectacles_Form::genders(),
			'grades'             => LCCL_DE_Spectacles_Form::grades(),
			'relationships'      => LCCL_DE_Spectacles_Form::relationships(),
			'yesNoUnsure'        => LCCL_DE_Spectacles_Form::yes_no_unsure(),
			'yesNo'              => LCCL_DE_Spectacles_Form::yes_no(),
			'lastEyeExams'       => LCCL_DE_Spectacles_Form::last_eye_exams(),
			'visionDifficulties' => LCCL_DE_Spectacles_Form::vision_difficulties(),
			'schoolLetters'      => LCCL_DE_Spectacles_Form::school_letters(),
		);

		wp_localize_script(
			'lccl-de-spectacles-admin',
			'lcclSra',
			$script_data
		);

		ob_start();
		include LCCL_DE_PATH . 'templates/spectacles-registration-admin.php';

		return ob_get_clean();
	}

	/**
	 * REST routes.
	 */
	public static function register_routes() {
		register_rest_route(
			self::REST_NS,
			'/spectacles',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'rest_list' ),
				'permission_callback' => array( 'LCCL_DE_Dashboard', 'rest_can_view' ),
				'args'                => array(
					'search'        => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'district'      => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'gender'        => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_key',
					),
					'grade'         => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_key',
					),
					'school_letter' => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_key',
					),
					'page'          => array(
						'type'              => 'integer',
						'default'           => 1,
						'sanitize_callback' => 'absint',
					),
					'per_page'      => array(
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
			'/spectacles/(?P<id>\d+)',
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

		register_rest_route(
			self::REST_NS,
			'/spectacles/(?P<id>\d+)/letter',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'rest_letter' ),
				'permission_callback' => array( 'LCCL_DE_Dashboard', 'rest_can_view' ),
				'args'                => array_merge(
					$id_args,
					array(
						'disposition' => array(
							'type'              => 'string',
							'enum'              => array( 'inline', 'attachment' ),
							'default'           => 'inline',
							'sanitize_callback' => 'sanitize_key',
						),
					)
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
		global $wpdb;

		$table         = LCCL_DE_Schema::spectacles_table();
		$search        = trim( (string) $request->get_param( 'search' ) );
		$district      = (string) $request->get_param( 'district' );
		$gender        = (string) $request->get_param( 'gender' );
		$grade         = (string) $request->get_param( 'grade' );
		$school_letter = (string) $request->get_param( 'school_letter' );
		$page          = max( 1, (int) $request->get_param( 'page' ) );
		$per_page      = (int) $request->get_param( 'per_page' );
		if ( ! in_array( $per_page, array( 10, 20, 50 ), true ) ) {
			$per_page = 20;
		}

		$where  = array( '1=1' );
		$params = array();

		if ( '' !== $search ) {
			$like     = '%' . $wpdb->esc_like( $search ) . '%';
			$where[]  = '(child_first_name LIKE %s OR child_last_name LIKE %s OR guardian_name LIKE %s OR phone LIKE %s OR email LIKE %s OR district LIKE %s OR school_name LIKE %s OR CONCAT(child_first_name, \' \', child_last_name) LIKE %s)';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
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

		if ( '' !== $gender ) {
			$where[]  = 'gender = %s';
			$params[] = $gender;
		}

		if ( '' !== $grade ) {
			$where[]  = 'grade = %s';
			$params[] = $grade;
		}

		if ( '' !== $school_letter ) {
			$where[]  = 'school_letter = %s';
			$params[] = $school_letter;
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
		$list_sql = "SELECT id, child_first_name, child_last_name, guardian_name, phone, email, district, school_name, grade, created_at
			FROM {$table}
			WHERE {$where_sql}
			ORDER BY created_at DESC, id DESC
			LIMIT %d OFFSET %d";

		$list_params   = $params;
		$list_params[] = $per_page;
		$list_params[] = $offset;

		$rows  = $wpdb->get_results( $wpdb->prepare( $list_sql, $list_params ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
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
	 * One registration.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function rest_one( WP_REST_Request $request ) {
		$row = self::get_row( (int) $request['id'] );
		if ( ! $row ) {
			return new WP_Error( 'lccl_de_missing', __( 'Registration not found.', 'lccl-de' ), array( 'status' => 404 ) );
		}

		return new WP_REST_Response( self::present_detail( $row ), 200 );
	}

	/**
	 * Update one registration. Site administrators only.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function rest_update( WP_REST_Request $request ) {
		$row = self::get_row( (int) $request['id'] );
		if ( ! $row ) {
			return new WP_Error( 'lccl_de_missing', __( 'Registration not found.', 'lccl-de' ), array( 'status' => 404 ) );
		}

		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = $request->get_params();
		}

		$values = LCCL_DE_Spectacles_Submissions::sanitize_payload( $params );
		$values['letter_file']      = isset( $row['letter_file'] ) ? (string) $row['letter_file'] : '';
		$values['letter_file_name'] = isset( $row['letter_file_name'] ) ? (string) $row['letter_file_name'] : '';
		$values['letter_mime']      = isset( $row['letter_mime'] ) ? (string) $row['letter_mime'] : '';

		$errors = LCCL_DE_Spectacles_Submissions::validate( $values, false, false );
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

		$saved = LCCL_DE_Spectacles_Submissions::update( (int) $row['id'], $values, get_current_user_id() );
		if ( ! $saved ) {
			return new WP_Error(
				'lccl_de_update',
				__( 'The registration could not be saved. Please try again.', 'lccl-de' ),
				array( 'status' => 500 )
			);
		}

		$fresh = self::get_row( (int) $row['id'] );
		if ( ! $fresh ) {
			return new WP_Error( 'lccl_de_missing', __( 'Registration not found.', 'lccl-de' ), array( 'status' => 404 ) );
		}

		return new WP_REST_Response( self::present_detail( $fresh ), 200 );
	}

	/**
	 * Delete one registration. Site administrators only.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function rest_delete( WP_REST_Request $request ) {
		global $wpdb;

		$row = self::get_row( (int) $request['id'] );
		if ( ! $row ) {
			return new WP_Error( 'lccl_de_missing', __( 'Registration not found.', 'lccl-de' ), array( 'status' => 404 ) );
		}

		$deleted = $wpdb->delete(
			LCCL_DE_Schema::spectacles_table(),
			array( 'id' => (int) $row['id'] ),
			array( '%d' )
		);

		if ( ! $deleted ) {
			return new WP_Error(
				'lccl_de_delete',
				__( 'The registration could not be deleted. Please try again.', 'lccl-de' ),
				array( 'status' => 500 )
			);
		}

		if ( ! empty( $row['letter_file'] ) ) {
			LCCL_DE_Spectacles_Submissions::delete_letter_file( $row['letter_file'] );
		}

		return new WP_REST_Response(
			array(
				'deleted' => true,
				'id'      => (int) $row['id'],
				'name'    => trim( $row['child_first_name'] . ' ' . $row['child_last_name'] ),
			),
			200
		);
	}

	/**
	 * Stream the stored school letter for preview or download.
	 *
	 * Reviewers must be signed in. Opening this URL in a new tab without a
	 * REST nonce returns 401 — the dashboard fetches it with X-WP-Nonce.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function rest_letter( WP_REST_Request $request ) {
		$row = self::get_row( (int) $request['id'] );
		if ( ! $row || empty( $row['letter_file'] ) ) {
			return new WP_Error( 'lccl_de_missing', __( 'School letter not found.', 'lccl-de' ), array( 'status' => 404 ) );
		}

		$path = LCCL_DE_Spectacles_Submissions::letter_path( $row['letter_file'] );
		if ( ! $path || ! file_exists( $path ) ) {
			return new WP_Error( 'lccl_de_missing', __( 'School letter not found.', 'lccl-de' ), array( 'status' => 404 ) );
		}

		$name = ! empty( $row['letter_file_name'] ) ? $row['letter_file_name'] : basename( $path );
		$kind = LCCL_DE_Spectacles_Submissions::letter_kind( $name, isset( $row['letter_mime'] ) ? $row['letter_mime'] : '' );
		$mimes = LCCL_DE_Spectacles_Submissions::allowed_letter_mimes();
		if ( '' === $kind || ! isset( $mimes[ $kind ] ) ) {
			return new WP_Error( 'lccl_de_missing', __( 'School letter not found.', 'lccl-de' ), array( 'status' => 404 ) );
		}

		$disposition = 'attachment' === $request->get_param( 'disposition' ) ? 'attachment' : 'inline';
		$filename    = sanitize_file_name( $name );
		if ( '' === $filename ) {
			$filename = 'school-letter.' . $kind;
		}

		header( 'Content-Type: ' . $mimes[ $kind ] );
		header( 'Content-Disposition: ' . $disposition . '; filename="' . $filename . '"' );
		header( 'Content-Length: ' . (string) filesize( $path ) );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Cache-Control: private, no-store' );
		readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
		exit;
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
				'name'        => __( 'LCCL Spectacles Registration Admin', 'lccl-de' ),
				'base'        => self::SHORTCODE,
				'category'    => __( 'LCCL', 'lccl-de' ),
				'description' => __( 'Login and dashboard for free spectacles reviewers.', 'lccl-de' ),
				'icon'        => 'icon-wpb-ui-separator',
				'params'      => array(),
			)
		);
	}

	/**
	 * List-row DTO.
	 *
	 * @param array $row Database row.
	 * @return array
	 */
	private static function present_list_row( array $row ) {
		$grades = LCCL_DE_Spectacles_Form::grades();

		return array(
			'id'            => (int) $row['id'],
			'child_first_name' => $row['child_first_name'],
			'child_last_name'  => $row['child_last_name'],
			'name'          => trim( $row['child_first_name'] . ' ' . $row['child_last_name'] ),
			'guardian_name' => $row['guardian_name'],
			'phone'         => $row['phone'],
			'email'         => $row['email'],
			'district'      => $row['district'],
			'school_name'   => $row['school_name'],
			'grade'         => $row['grade'],
			'grade_label'   => isset( $grades[ $row['grade'] ] ) ? $grades[ $row['grade'] ] : $row['grade'],
			'created_at'    => $row['created_at'],
			'created_label' => mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $row['created_at'] ),
		);
	}

	/**
	 * Detail DTO.
	 *
	 * @param array $row Database row.
	 * @return array
	 */
	private static function present_detail( array $row ) {
		$payload = self::present_list_row( $row );
		$updated = ! empty( $row['updated_at'] ) ? $row['updated_at'] : '';
		$vision  = LCCL_DE_Spectacles_Submissions::decode_list( isset( $row['vision_difficulties'] ) ? $row['vision_difficulties'] : '' );
		$map     = LCCL_DE_Spectacles_Form::vision_difficulties();
		$labels  = array();
		foreach ( $vision as $key ) {
			$labels[] = isset( $map[ $key ] ) ? $map[ $key ] : $key;
		}

		$more = array(
			'dob'                      => $row['dob'],
			'dob_label'                => $row['dob'] ? mysql2date( get_option( 'date_format' ), $row['dob'] ) : '',
			'age'                      => $row['age'],
			'age_label'                => self::label_of( LCCL_DE_Spectacles_Form::ages(), $row['age'] ),
			'gender'                   => $row['gender'],
			'gender_label'             => self::label_of( LCCL_DE_Spectacles_Form::genders(), $row['gender'] ),
			'school_area'              => $row['school_area'],
			'city'                     => $row['city'],
			'relationship'             => $row['relationship'],
			'relationship_label'       => self::label_of( LCCL_DE_Spectacles_Form::relationships(), $row['relationship'] ),
			'eye_exam'                 => $row['eye_exam'],
			'eye_exam_label'           => self::label_of( LCCL_DE_Spectacles_Form::yes_no_unsure(), $row['eye_exam'] ),
			'wear_spectacles'          => $row['wear_spectacles'],
			'wear_spectacles_label'    => self::label_of( LCCL_DE_Spectacles_Form::yes_no(), $row['wear_spectacles'] ),
			'difficulty_seeing'        => $row['difficulty_seeing'],
			'difficulty_seeing_label'  => self::label_of( LCCL_DE_Spectacles_Form::yes_no_unsure(), $row['difficulty_seeing'] ),
			'last_eye_exam'            => $row['last_eye_exam'],
			'last_eye_exam_label'      => self::label_of( LCCL_DE_Spectacles_Form::last_eye_exams(), $row['last_eye_exam'] ),
			'eye_condition'            => $row['eye_condition'],
			'eye_condition_label'      => self::label_of( LCCL_DE_Spectacles_Form::yes_no_unsure(), $row['eye_condition'] ),
			'eye_condition_details'    => $row['eye_condition_details'],
			'vision_difficulties'      => $vision,
			'vision_difficulty_labels' => $labels,
			'vision_other'             => $row['vision_other'],
			'school_letter'            => $row['school_letter'],
			'school_letter_label'      => self::label_of( LCCL_DE_Spectacles_Form::school_letters(), $row['school_letter'] ),
			'letter_file'              => $row['letter_file'],
			'letter_file_name'         => $row['letter_file_name'],
			'letter_type'              => LCCL_DE_Spectacles_Submissions::letter_kind(
				! empty( $row['letter_file_name'] ) ? $row['letter_file_name'] : $row['letter_file'],
				isset( $row['letter_mime'] ) ? $row['letter_mime'] : ''
			),
			'letter_url'               => ! empty( $row['letter_file'] ) ? rest_url( self::REST_NS . '/spectacles/' . (int) $row['id'] . '/letter' ) : '',
			'letter_view_url'          => '',
			'letter_download_url'      => '',
			'consent'                  => (int) $row['consent'],
			'updated_at'               => $updated,
			'updated_label'            => $updated ? mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $updated ) : '',
			'updated_by'               => isset( $row['updated_by'] ) ? (int) $row['updated_by'] : 0,
			'updated_by_label'         => self::present_updater( isset( $row['updated_by'] ) ? $row['updated_by'] : 0 ),
		);

		$token = LCCL_DE_Spectacles_Submissions::ensure_letter_token( $row );
		if ( '' !== $token ) {
			$more['letter_view_url']     = LCCL_DE_File_Viewer::url( 'spectacles', $token );
			$more['letter_download_url'] = LCCL_DE_File_Viewer::url( 'spectacles', $token, 'download' );
		}

		if ( current_user_can( 'manage_options' ) ) {
			$more['ip_address'] = $row['ip_address'];
		}

		return array_merge( $payload, $more );
	}

	/**
	 * Label from a value => label map.
	 *
	 * @param array  $map   Options.
	 * @param string $value Stored value.
	 * @return string
	 */
	private static function label_of( array $map, $value ) {
		return ( $value && isset( $map[ $value ] ) ) ? $map[ $value ] : (string) $value;
	}

	/**
	 * Load one row.
	 *
	 * @param int $id Row ID.
	 * @return array|null
	 */
	private static function get_row( $id ) {
		global $wpdb;

		$id = (int) $id;
		if ( $id <= 0 ) {
			return null;
		}

		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . LCCL_DE_Schema::spectacles_table() . ' WHERE id = %d',
				$id
			),
			ARRAY_A
		);

		return is_array( $row ) ? $row : null;
	}

	/**
	 * School letter payload for the reusable file viewer.
	 *
	 * @param string $token 32-character hex token.
	 * @return array|null
	 */
	public static function viewer_file( $token ) {
		$row = self::get_row_by_token( $token );
		if ( ! $row || empty( $row['letter_file'] ) ) {
			return null;
		}

		$path = LCCL_DE_Spectacles_Submissions::letter_path( $row['letter_file'] );
		$name = ! empty( $row['letter_file_name'] ) ? $row['letter_file_name'] : basename( (string) $path );
		$kind = LCCL_DE_Spectacles_Submissions::letter_kind( $name, isset( $row['letter_mime'] ) ? $row['letter_mime'] : '' );
		$mimes = LCCL_DE_Spectacles_Submissions::allowed_letter_mimes();
		if ( ! $path || ! file_exists( $path ) || '' === $kind || ! isset( $mimes[ $kind ] ) ) {
			return null;
		}

		$filename = sanitize_file_name( $name );
		if ( '' === $filename ) {
			$filename = 'school-letter.' . $kind;
		}

		$token = LCCL_DE_Spectacles_Submissions::ensure_letter_token( $row );

		return array(
			'source'   => 'spectacles',
			'token'    => $token,
			'path'     => $path,
			'name'     => $name,
			'filename' => $filename,
			'kind'     => $kind,
			'mime'     => $mimes[ $kind ],
		);
	}

	/**
	 * Load one row by its letter token.
	 *
	 * @param string $token 32-character hex token.
	 * @return array|null
	 */
	private static function get_row_by_token( $token ) {
		global $wpdb;

		$token = LCCL_DE_Spectacles_Submissions::sanitize_letter_token( $token );
		if ( '' === $token ) {
			return null;
		}

		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . LCCL_DE_Schema::spectacles_table() . ' WHERE letter_token = %s',
				$token
			),
			ARRAY_A
		);

		return is_array( $row ) ? $row : null;
	}

	/**
	 * "Full Name (username)" for the last editor.
	 *
	 * @param int $user_id User ID.
	 * @return string
	 */
	private static function present_updater( $user_id ) {
		$user_id = (int) $user_id;
		if ( $user_id <= 0 ) {
			return '';
		}

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return __( 'Unknown user', 'lccl-de' );
		}

		return self::display_name( $user ) . ' (' . $user->user_login . ')';
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
