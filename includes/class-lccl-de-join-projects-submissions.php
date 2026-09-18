<?php
/**
 * Join Our Projects persistence helpers.
 *
 * @package LCCL_Donations_And_Events
 */

defined( 'ABSPATH' ) || exit;

/**
 * Stores and lists Join Our Projects registrations.
 */
class LCCL_DE_Join_Projects_Submissions {

	/**
	 * admin-post.php action name for the public submit.
	 */
	const ACTION = 'lccl_de_join_projects_register';

	/**
	 * Query arg used to pass a flash token back to the form page.
	 */
	const FLASH_QUERY = 'lccl_jp';

	/**
	 * Hook the public POST handler.
	 */
	public static function init() {
		add_action( 'admin_post_nopriv_' . self::ACTION, array( __CLASS__, 'handle' ) );
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'handle' ) );
	}

	/**
	 * Consume a one-time flash payload for the current request.
	 *
	 * @return array{success:bool,values:array,errors:array}
	 */
	public static function consume_flash() {
		$empty = array(
			'success' => false,
			'values'  => array(),
			'errors'  => array(),
		);

		if ( empty( $_GET[ self::FLASH_QUERY ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return $empty;
		}

		$token = sanitize_key( wp_unslash( $_GET[ self::FLASH_QUERY ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( '' === $token ) {
			return $empty;
		}

		$flash = get_transient( self::flash_key( $token ) );
		delete_transient( self::flash_key( $token ) );

		if ( ! is_array( $flash ) ) {
			return $empty;
		}

		return wp_parse_args( $flash, $empty );
	}

	/**
	 * Handle a registration POST.
	 */
	public static function handle() {
		$redirect = self::safe_redirect_url();

		if ( ! isset( $_POST['lccl_de_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['lccl_de_nonce'] ) ), self::ACTION ) ) {
			self::redirect_with_flash(
				$redirect,
				array(
					'success' => false,
					'values'  => array(),
					'errors'  => array(
						'form' => __( 'The form expired. Please try again.', 'lccl-de' ),
					),
				)
			);
		}

		if ( ! empty( $_POST['lccl_de_hp'] ) ) {
			self::redirect_with_flash( $redirect, array( 'success' => true, 'values' => array(), 'errors' => array() ) );
		}

		if ( self::is_rate_limited() ) {
			self::redirect_with_flash(
				$redirect,
				array(
					'success' => false,
					'values'  => array(),
					'errors'  => array(
						'form' => __( 'Too many attempts from this connection. Please wait a while and try again.', 'lccl-de' ),
					),
				)
			);
		}

		$values = self::sanitize_request();
		$errors = self::validate( $values );

		if ( ! empty( $errors ) ) {
			self::redirect_with_flash(
				$redirect,
				array(
					'success' => false,
					'values'  => $values,
					'errors'  => $errors,
				)
			);
		}

		$inserted = self::insert( $values );
		if ( ! $inserted ) {
			self::redirect_with_flash(
				$redirect,
				array(
					'success' => false,
					'values'  => $values,
					'errors'  => array(
						'form' => __( 'The registration could not be saved. Please try again.', 'lccl-de' ),
					),
				)
			);
		}

		LCCL_DE_Notify::after_registration( $values, $inserted, LCCL_DE_Admin_Programs::PROGRAM_PROJECTS );

		self::redirect_with_flash(
			$redirect,
			array(
				'success' => true,
				'values'  => array(),
				'errors'  => array(),
			)
		);
	}

	/**
	 * Pull and sanitise posted values.
	 *
	 * @return array
	 */
	public static function sanitize_request() {
		return self::sanitize_payload( wp_unslash( $_POST ) );
	}

	/**
	 * Sanitise a POST or JSON payload into stored field values.
	 *
	 * @param array $source Raw values.
	 * @return array
	 */
	public static function sanitize_payload( array $source ) {
		$first = self::clip_field( isset( $source['first_name'] ) ? $source['first_name'] : '', 100 );
		$last  = self::clip_field( isset( $source['last_name'] ) ? $source['last_name'] : '', 100 );
		$full  = self::clip_field( isset( $source['full_name'] ) ? $source['full_name'] : '', 191 );

		if ( '' === $full ) {
			$full = trim( $first . ' ' . $last );
		} elseif ( '' === $first && '' === $last ) {
			$parts = self::split_full_name( $full );
			$first = $parts[0];
			$last  = $parts[1];
		} else {
			$full = trim( $first . ' ' . $last );
		}

		$areas = self::sanitize_choice_list( isset( $source['interest_areas'] ) ? $source['interest_areas'] : array(), array_keys( LCCL_DE_Join_Projects_Form::interest_areas() ) );
		$types = self::sanitize_choice_list( isset( $source['project_types'] ) ? $source['project_types'] : array(), array_keys( LCCL_DE_Join_Projects_Form::project_types() ) );

		return array(
			'full_name'            => $full,
			'first_name'           => $first,
			'last_name'            => $last,
			'email'                => LCCL_DE_Blood_Donor_Submissions::sanitize_email_field( isset( $source['email'] ) ? $source['email'] : '' ),
			'phone'                => LCCL_DE_Blood_Donor_Submissions::sanitize_phone_field( isset( $source['phone'] ) ? $source['phone'] : '' ),
			'address'              => self::clip_field( isset( $source['address'] ) ? $source['address'] : '', 255 ),
			'city'                 => self::clip_field( isset( $source['city'] ) ? $source['city'] : '', 100 ),
			'postal_code'          => LCCL_DE_Blood_Donor_Submissions::sanitize_postal_code( isset( $source['postal_code'] ) ? $source['postal_code'] : '' ),
			'occupation'           => self::clip_field( isset( $source['occupation'] ) ? $source['occupation'] : '', 191 ),
			'organisation'         => self::clip_field( isset( $source['organisation'] ) ? $source['organisation'] : '', 191 ),
			'support_ways'         => self::sanitize_choice_list( isset( $source['support_ways'] ) ? $source['support_ways'] : array(), array_keys( LCCL_DE_Join_Projects_Form::support_ways() ) ),
			'support_ways_other'   => '',
			'volunteer_areas'      => self::sanitize_choice_list( isset( $source['volunteer_areas'] ) ? $source['volunteer_areas'] : array(), array_keys( LCCL_DE_Join_Projects_Form::volunteer_areas() ) ),
			'skills'               => self::clip_textarea( isset( $source['skills'] ) ? $source['skills'] : '', 2000 ),
			'availability'         => self::sanitize_choice_list( isset( $source['availability'] ) ? $source['availability'] : array(), array_keys( LCCL_DE_Join_Projects_Form::availability() ) ),
			'financial_support'    => self::sanitize_choice_list( isset( $source['financial_support'] ) ? $source['financial_support'] : array(), array_keys( LCCL_DE_Join_Projects_Form::financial_support() ) ),
			'contribution_amount'  => isset( $source['contribution_amount'] ) ? sanitize_key( $source['contribution_amount'] ) : '',
			'interest_areas'       => LCCL_DE_Join_Projects_Form::merge_interest_area_keys( $areas, $types ),
			'interest_areas_other' => '',
			'project_types'        => array(),
			'specific_idea'        => self::clip_textarea( isset( $source['specific_idea'] ) ? $source['specific_idea'] : '', 2000 ),
			'registering_as'       => isset( $source['registering_as'] ) ? sanitize_key( $source['registering_as'] ) : '',
			'company_name'         => self::clip_field( isset( $source['company_name'] ) ? $source['company_name'] : '', 191 ),
			'designation'          => self::clip_field( isset( $source['designation'] ) ? $source['designation'] : '', 191 ),
			'company_support'      => self::clip_textarea( isset( $source['company_support'] ) ? $source['company_support'] : '', 2000 ),
			'message'              => self::clip_textarea( isset( $source['message'] ) ? $source['message'] : '', 2000 ),
			'consent'              => ! empty( $source['consent'] ) ? 1 : 0,
		);
	}

	/**
	 * Validate sanitised values. Returns field => message.
	 *
	 * @param array $values          Sanitised input.
	 * @param bool  $require_consent Whether consent must be ticked (public form only).
	 * @return array
	 */
	public static function validate( array $values, $require_consent = true ) {
		$errors       = array();
		$required_msg = LCCL_DE_Blood_Donor_Submissions::required_field_message();

		foreach ( array( 'first_name', 'last_name', 'address', 'city', 'postal_code', 'email', 'phone' ) as $field ) {
			if ( '' === $values[ $field ] ) {
				$errors[ $field ] = $required_msg;
			}
		}

		if ( $require_consent && empty( $values['consent'] ) ) {
			$errors['consent'] = $required_msg;
		}

		if ( '' !== $values['phone'] && ! LCCL_DE_Blood_Donor_Submissions::is_valid_sl_phone( $values['phone'] ) ) {
			$errors['phone'] = LCCL_DE_Blood_Donor_Submissions::phone_error_message();
		}

		if ( '' !== $values['email'] && ! LCCL_DE_Blood_Donor_Submissions::is_valid_email_field( $values['email'] ) ) {
			$errors['email'] = __( 'Please enter a valid email address.', 'lccl-de' );
		}

		if ( '' !== $values['postal_code'] && ! LCCL_DE_Blood_Donor_Submissions::is_valid_postal_code( $values['postal_code'] ) ) {
			$errors['postal_code'] = LCCL_DE_Blood_Donor_Submissions::postal_error_message();
		}

		if ( empty( $values['support_ways'] ) || empty( $values['interest_areas'] ) ) {
			$choice_msg = __( 'Please select at least one support option and at least one project/service area.', 'lccl-de' );
			if ( empty( $values['support_ways'] ) ) {
				$errors['support_ways'] = $choice_msg;
			}
			if ( empty( $values['interest_areas'] ) ) {
				$errors['interest_areas'] = $choice_msg;
			}
		}

		$as_options = LCCL_DE_Join_Projects_Form::registering_as_options();
		if ( '' !== $values['registering_as'] && ! array_key_exists( $values['registering_as'], $as_options ) ) {
			$errors['registering_as'] = __( 'Please choose a valid option.', 'lccl-de' );
		}

		$amounts = LCCL_DE_Join_Projects_Form::contribution_amounts();
		if ( '' !== $values['contribution_amount'] && ! array_key_exists( $values['contribution_amount'], $amounts ) ) {
			$errors['contribution_amount'] = __( 'Please choose a valid option.', 'lccl-de' );
		}

		return $errors;
	}

	/**
	 * Paginated list for the review dashboard.
	 *
	 * @param array $args page, per_page, search.
	 * @return array{items:array,total:int,page:int,per_page:int,pages:int}
	 */
	public static function query( $args ) {
		global $wpdb;

		$table    = LCCL_DE_Schema::project_joins_table();
		$page     = isset( $args['page'] ) ? max( 1, (int) $args['page'] ) : 1;
		$per_page = isset( $args['per_page'] ) ? (int) $args['per_page'] : 20;
		$search   = isset( $args['search'] ) ? trim( (string) $args['search'] ) : '';

		if ( ! in_array( $per_page, array( 10, 20, 50 ), true ) ) {
			$per_page = 20;
		}

		$where  = array( '1=1' );
		$params = array();

		if ( '' !== $search ) {
			if ( ctype_digit( $search ) ) {
				$where[]  = 'id = %d';
				$params[] = (int) $search;
			} else {
				$like     = '%' . $wpdb->esc_like( $search ) . '%';
				$where[]  = '(full_name LIKE %s OR first_name LIKE %s OR last_name LIKE %s OR email LIKE %s OR phone LIKE %s OR city LIKE %s OR address LIKE %s)';
				$params[] = $like;
				$params[] = $like;
				$params[] = $like;
				$params[] = $like;
				$params[] = $like;
				$params[] = $like;
				$params[] = $like;
			}
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

		$offset      = ( $page - 1 ) * $per_page;
		$list_sql    = "SELECT id, full_name, first_name, last_name, email, phone, city, created_at FROM {$table} WHERE {$where_sql} ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d";
		$list_params = $params;
		$list_params[] = $per_page;
		$list_params[] = $offset;

		$rows = $wpdb->get_results( $wpdb->prepare( $list_sql, $list_params ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$items = array();
		foreach ( (array) $rows as $row ) {
			$items[] = self::present_list_row( $row );
		}

		return array(
			'items'    => $items,
			'total'    => $total,
			'page'     => $page,
			'per_page' => $per_page,
			'pages'    => $pages,
		);
	}

	/**
	 * One registration, or null.
	 *
	 * @param int $id Row ID.
	 * @return array|null
	 */
	public static function get( $id ) {
		global $wpdb;

		$id = (int) $id;
		if ( $id <= 0 ) {
			return null;
		}

		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . LCCL_DE_Schema::project_joins_table() . ' WHERE id = %d',
				$id
			),
			ARRAY_A
		);

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Delete one registration.
	 *
	 * @param int $id Row ID.
	 * @return bool
	 */
	public static function delete( $id ) {
		global $wpdb;

		$id = (int) $id;
		if ( $id <= 0 ) {
			return false;
		}

		$deleted = $wpdb->delete(
			LCCL_DE_Schema::project_joins_table(),
			array( 'id' => $id ),
			array( '%d' )
		);

		return (bool) $deleted;
	}

	/**
	 * Insert a valid registration.
	 *
	 * @param array $values Sanitised, validated values.
	 * @return int|false
	 */
	public static function insert( array $values ) {
		global $wpdb;

		$result = $wpdb->insert(
			LCCL_DE_Schema::project_joins_table(),
			array(
				'full_name'            => $values['full_name'],
				'first_name'           => $values['first_name'],
				'last_name'            => $values['last_name'],
				'email'                => $values['email'],
				'phone'                => $values['phone'],
				'address'              => '' !== $values['address'] ? $values['address'] : null,
				'city'                 => '' !== $values['city'] ? $values['city'] : null,
				'postal_code'          => '' !== $values['postal_code'] ? $values['postal_code'] : null,
				'occupation'           => '' !== $values['occupation'] ? $values['occupation'] : null,
				'organisation'         => '' !== $values['organisation'] ? $values['organisation'] : null,
				'support_ways'         => self::encode_list( $values['support_ways'] ),
				'support_ways_other'   => null,
				'volunteer_areas'      => self::encode_list( $values['volunteer_areas'] ),
				'skills'               => '' !== $values['skills'] ? $values['skills'] : null,
				'availability'         => self::encode_list( $values['availability'] ),
				'financial_support'    => self::encode_list( $values['financial_support'] ),
				'contribution_amount'  => '' !== $values['contribution_amount'] ? $values['contribution_amount'] : null,
				'interest_areas'       => self::encode_list( $values['interest_areas'] ),
				'interest_areas_other' => null,
				'project_types'        => self::encode_list( $values['project_types'] ),
				'specific_idea'        => '' !== $values['specific_idea'] ? $values['specific_idea'] : null,
				'registering_as'       => '' !== $values['registering_as'] ? $values['registering_as'] : null,
				'company_name'         => '' !== $values['company_name'] ? $values['company_name'] : null,
				'designation'          => '' !== $values['designation'] ? $values['designation'] : null,
				'company_support'      => '' !== $values['company_support'] ? $values['company_support'] : null,
				'message'              => '' !== $values['message'] ? $values['message'] : null,
				'consent'              => 1,
				'ip_address'           => self::request_ip(),
				'created_at'           => current_time( 'mysql' ),
			),
			array(
				'%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s',
				'%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s',
				'%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s',
			)
		);

		if ( false === $result ) {
			return false;
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Update a registration. Does not change consent, IP, or created_at.
	 *
	 * @param int   $id      Row ID.
	 * @param array $values  Sanitised, validated values.
	 * @param int   $user_id Acting administrator.
	 * @return bool
	 */
	public static function update( $id, array $values, $user_id ) {
		global $wpdb;

		$id      = (int) $id;
		$user_id = (int) $user_id;
		if ( $id <= 0 || $user_id <= 0 ) {
			return false;
		}

		$result = $wpdb->update(
			LCCL_DE_Schema::project_joins_table(),
			array(
				'full_name'           => $values['full_name'],
				'first_name'          => $values['first_name'],
				'last_name'           => $values['last_name'],
				'email'               => $values['email'],
				'phone'               => $values['phone'],
				'address'             => '' !== $values['address'] ? $values['address'] : null,
				'city'                => '' !== $values['city'] ? $values['city'] : null,
				'postal_code'         => '' !== $values['postal_code'] ? $values['postal_code'] : null,
				'occupation'          => '' !== $values['occupation'] ? $values['occupation'] : null,
				'organisation'        => '' !== $values['organisation'] ? $values['organisation'] : null,
				'support_ways'        => self::encode_list( $values['support_ways'] ),
				'volunteer_areas'     => self::encode_list( $values['volunteer_areas'] ),
				'skills'              => '' !== $values['skills'] ? $values['skills'] : null,
				'availability'        => self::encode_list( $values['availability'] ),
				'financial_support'   => self::encode_list( $values['financial_support'] ),
				'contribution_amount' => '' !== $values['contribution_amount'] ? $values['contribution_amount'] : null,
				'interest_areas'      => self::encode_list( $values['interest_areas'] ),
				'project_types'       => self::encode_list( $values['project_types'] ),
				'specific_idea'       => '' !== $values['specific_idea'] ? $values['specific_idea'] : null,
				'registering_as'      => '' !== $values['registering_as'] ? $values['registering_as'] : null,
				'company_name'        => '' !== $values['company_name'] ? $values['company_name'] : null,
				'designation'         => '' !== $values['designation'] ? $values['designation'] : null,
				'company_support'     => '' !== $values['company_support'] ? $values['company_support'] : null,
				'message'             => '' !== $values['message'] ? $values['message'] : null,
				'updated_at'          => current_time( 'mysql' ),
				'updated_by'          => $user_id,
			),
			array( 'id' => $id ),
			array(
				'%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s',
				'%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s',
				'%s', '%s', '%s', '%s', '%s', '%d',
			),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Table-row DTO. Expand fetches the rest on demand.
	 *
	 * @param array $row Database row.
	 * @return array
	 */
	public static function present_list_row( array $row ) {
		$created = isset( $row['created_at'] ) ? $row['created_at'] : '';
		$first   = isset( $row['first_name'] ) ? (string) $row['first_name'] : '';
		$last    = isset( $row['last_name'] ) ? (string) $row['last_name'] : '';
		$full    = isset( $row['full_name'] ) ? (string) $row['full_name'] : '';
		if ( '' === $first && '' === $last && '' !== $full ) {
			$parts = self::split_full_name( $full );
			$first = $parts[0];
			$last  = $parts[1];
		}
		if ( '' === $full ) {
			$full = trim( $first . ' ' . $last );
		}

		return array(
			'id'            => (int) $row['id'],
			'full_name'     => $full,
			'first_name'    => $first,
			'last_name'     => $last,
			'name'          => $full,
			'email'         => isset( $row['email'] ) ? (string) $row['email'] : '',
			'phone'         => isset( $row['phone'] ) ? (string) $row['phone'] : '',
			'city'          => isset( $row['city'] ) ? (string) $row['city'] : '',
			'created_at'    => $created,
			'created_label' => $created ? mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $created ) : '',
		);
	}

	/**
	 * Full detail DTO used after the expand request.
	 *
	 * @param array $row Database row.
	 * @return array
	 */
	public static function present( array $row ) {
		$updated        = ! empty( $row['updated_at'] ) ? $row['updated_at'] : '';
		$support        = self::decode_list( isset( $row['support_ways'] ) ? $row['support_ways'] : '' );
		$volunteer      = self::decode_list( isset( $row['volunteer_areas'] ) ? $row['volunteer_areas'] : '' );
		$availability   = self::decode_list( isset( $row['availability'] ) ? $row['availability'] : '' );
		$financial      = self::decode_list( isset( $row['financial_support'] ) ? $row['financial_support'] : '' );
		$areas          = LCCL_DE_Join_Projects_Form::merge_interest_area_keys(
			self::decode_list( isset( $row['interest_areas'] ) ? $row['interest_areas'] : '' ),
			self::decode_list( isset( $row['project_types'] ) ? $row['project_types'] : '' )
		);
		$as_key         = isset( $row['registering_as'] ) ? (string) $row['registering_as'] : '';
		$as_options     = LCCL_DE_Join_Projects_Form::registering_as_options();
		$amount_key     = isset( $row['contribution_amount'] ) ? (string) $row['contribution_amount'] : '';
		$amount_opts    = LCCL_DE_Join_Projects_Form::contribution_amounts();

		$support_items  = self::label_items( $support, LCCL_DE_Join_Projects_Form::support_ways() );
		$volunteer_items = self::label_items( $volunteer, LCCL_DE_Join_Projects_Form::volunteer_areas() );
		$availability_items = self::label_items( $availability, LCCL_DE_Join_Projects_Form::availability() );
		$financial_items = self::label_items( $financial, LCCL_DE_Join_Projects_Form::financial_support() );
		$area_items     = self::label_items( $areas, LCCL_DE_Join_Projects_Form::interest_areas() );

		$payload = self::present_list_row( $row );

		return array_merge(
			$payload,
			array(
				'address'                   => isset( $row['address'] ) ? (string) $row['address'] : '',
				'postal_code'               => isset( $row['postal_code'] ) ? (string) $row['postal_code'] : '',
				'occupation'                => isset( $row['occupation'] ) ? (string) $row['occupation'] : '',
				'organisation'              => isset( $row['organisation'] ) ? (string) $row['organisation'] : '',
				'support_ways'              => $support,
				'support_ways_items'        => $support_items,
				'support_ways_label'        => implode( ', ', $support_items ),
				'volunteer_areas'           => $volunteer,
				'volunteer_areas_items'     => $volunteer_items,
				'volunteer_areas_label'     => implode( ', ', $volunteer_items ),
				'skills'                    => isset( $row['skills'] ) ? (string) $row['skills'] : '',
				'availability'              => $availability,
				'availability_items'        => $availability_items,
				'availability_label'        => implode( ', ', $availability_items ),
				'financial_support'         => $financial,
				'financial_support_items'   => $financial_items,
				'financial_support_label'   => implode( ', ', $financial_items ),
				'contribution_amount'       => $amount_key,
				'contribution_amount_label' => ( $amount_key && isset( $amount_opts[ $amount_key ] ) ) ? $amount_opts[ $amount_key ] : $amount_key,
				'interest_areas'            => $areas,
				'interest_areas_items'      => $area_items,
				'interest_areas_label'      => implode( ', ', $area_items ),
				'specific_idea'             => isset( $row['specific_idea'] ) ? (string) $row['specific_idea'] : '',
				'registering_as'            => $as_key,
				'registering_as_label'      => ( $as_key && isset( $as_options[ $as_key ] ) ) ? $as_options[ $as_key ] : $as_key,
				'company_name'              => isset( $row['company_name'] ) ? (string) $row['company_name'] : '',
				'designation'               => isset( $row['designation'] ) ? (string) $row['designation'] : '',
				'company_support'           => isset( $row['company_support'] ) ? (string) $row['company_support'] : '',
				'message'                   => isset( $row['message'] ) ? (string) $row['message'] : '',
				'consent'                   => ! empty( $row['consent'] ) ? 1 : 0,
				'updated_at'                => $updated,
				'updated_label'             => $updated ? mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $updated ) : '',
				'updated_by'                => isset( $row['updated_by'] ) ? (int) $row['updated_by'] : 0,
				'updated_by_label'          => self::present_updater( isset( $row['updated_by'] ) ? $row['updated_by'] : 0 ),
			)
		);
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

		$name = trim( $user->first_name . ' ' . $user->last_name );
		if ( '' === $name ) {
			$name = $user->display_name;
		}

		return $name . ' (' . $user->user_login . ')';
	}

	/**
	 * Allowed checkbox values only, in posted order.
	 *
	 * @param mixed $raw     Posted list.
	 * @param array $allowed Allowed keys.
	 * @return string[]
	 */
	private static function sanitize_choice_list( $raw, array $allowed ) {
		if ( ! is_array( $raw ) ) {
			$raw = array();
		}

		$out = array();
		foreach ( $raw as $value ) {
			$key = sanitize_key( (string) $value );
			if ( in_array( $key, $allowed, true ) && ! in_array( $key, $out, true ) ) {
				$out[] = $key;
			}
		}

		return $out;
	}

	/**
	 * JSON-encode a list of keys for storage.
	 *
	 * @param array $list Keys.
	 * @return string
	 */
	private static function encode_list( array $list ) {
		$json = wp_json_encode( array_values( $list ) );
		return false === $json ? '[]' : $json;
	}

	/**
	 * Decode a stored JSON list.
	 *
	 * @param mixed $raw Stored value.
	 * @return string[]
	 */
	private static function decode_list( $raw ) {
		if ( is_array( $raw ) ) {
			return array_values( $raw );
		}

		$decoded = json_decode( (string) $raw, true );
		if ( ! is_array( $decoded ) ) {
			return array();
		}

		$out = array();
		foreach ( $decoded as $value ) {
			$key = sanitize_key( (string) $value );
			if ( '' !== $key ) {
				$out[] = $key;
			}
		}

		return $out;
	}

	/**
	 * Split a stored full name into first and last.
	 *
	 * @param string $full Full name.
	 * @return array{0:string,1:string}
	 */
	private static function split_full_name( $full ) {
		$full = trim( (string) $full );
		if ( '' === $full ) {
			return array( '', '' );
		}

		$pos = strpos( $full, ' ' );
		if ( false === $pos ) {
			return array( $full, '' );
		}

		return array(
			substr( $full, 0, $pos ),
			trim( substr( $full, $pos + 1 ) ),
		);
	}

	/**
	 * Labels for selected keys, in order.
	 *
	 * @param array $keys Selected keys.
	 * @param array $map  Key => label.
	 * @return string[]
	 */
	private static function label_items( array $keys, array $map ) {
		$labels = array();
		foreach ( $keys as $key ) {
			$labels[] = isset( $map[ $key ] ) ? $map[ $key ] : $key;
		}

		return $labels;
	}

	/**
	 * Join keys into a comma-separated label list.
	 *
	 * @param array $keys Selected keys.
	 * @param array $map  Key => label.
	 * @return string
	 */
	private static function labels_for( array $keys, array $map ) {
		return implode( ', ', self::label_items( $keys, $map ) );
	}

	/**
	 * Truncate a sanitised string to a column width.
	 *
	 * @param mixed $value Raw value.
	 * @param int   $max   Max characters.
	 * @return string
	 */
	private static function clip_field( $value, $max ) {
		$text = sanitize_text_field( (string) $value );
		if ( strlen( $text ) <= $max ) {
			return $text;
		}

		return substr( $text, 0, $max );
	}

	/**
	 * Truncate a sanitised textarea.
	 *
	 * @param mixed $value Raw value.
	 * @param int   $max   Max characters.
	 * @return string
	 */
	private static function clip_textarea( $value, $max ) {
		$text = sanitize_textarea_field( (string) $value );
		if ( strlen( $text ) <= $max ) {
			return $text;
		}

		return substr( $text, 0, $max );
	}

	/**
	 * Safe return URL after POST.
	 *
	 * @return string
	 */
	private static function safe_redirect_url() {
		if ( ! empty( $_POST['redirect_to'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$url = esc_url_raw( wp_unslash( $_POST['redirect_to'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			if ( $url ) {
				return $url;
			}
		}

		if ( ! empty( $_POST['_wp_http_referer'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$url = esc_url_raw( wp_unslash( $_POST['_wp_http_referer'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			if ( $url ) {
				return $url;
			}
		}

		$referer = wp_get_referer();
		if ( $referer ) {
			return $referer;
		}

		return LCCL_DE_Roles::join_form_url();
	}

	/**
	 * Store a flash and bounce back to the form.
	 *
	 * @param string $url     Redirect URL.
	 * @param array  $payload Flash payload.
	 */
	private static function redirect_with_flash( $url, array $payload ) {
		$token = wp_generate_password( 12, false, false );
		set_transient( self::flash_key( $token ), $payload, 10 * MINUTE_IN_SECONDS );

		wp_safe_redirect( add_query_arg( self::FLASH_QUERY, $token, $url ) );
		exit;
	}

	/**
	 * Transient key for a flash token.
	 *
	 * @param string $token Token.
	 * @return string
	 */
	private static function flash_key( $token ) {
		return 'lccl_de_jp_' . $token;
	}

	/**
	 * Whether this IP has submitted too often recently.
	 *
	 * @return bool
	 */
	private static function is_rate_limited() {
		$ip = self::request_ip();
		if ( '' === $ip ) {
			return false;
		}

		$key   = 'lccl_de_jp_rl_' . md5( $ip );
		$count = (int) get_transient( $key );
		if ( $count >= 8 ) {
			return true;
		}

		set_transient( $key, $count + 1, HOUR_IN_SECONDS );
		return false;
	}

	/**
	 * Sanitised remote address, IPv4 or IPv6.
	 *
	 * @return string
	 */
	private static function request_ip() {
		if ( empty( $_SERVER['REMOTE_ADDR'] ) ) {
			return '';
		}

		$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
	}
}
