<?php
/**
 * Free Spectacles registration persistence and POST handling.
 *
 * @package LCCL_Donations_And_Events
 */

defined( 'ABSPATH' ) || exit;

/**
 * Validates, stores, and flashes the result of a spectacles registration.
 */
class LCCL_DE_Spectacles_Submissions {

	/**
	 * admin-post.php action name.
	 */
	const ACTION = 'lccl_de_spectacles_register';

	/**
	 * Query arg used to pass a flash token back to the form page.
	 */
	const FLASH_QUERY = 'lccl_de_sp';

	/**
	 * Upload subdirectory under uploads.
	 */
	const UPLOAD_DIR = 'lccl-spectacles';

	/**
	 * Maximum school-letter file size in bytes.
	 */
	const LETTER_MAX_BYTES = 10485760;

	/**
	 * Message shown when date of birth is missing or not a real past date.
	 *
	 * @return string
	 */
	public static function dob_error_message() {
		return __( 'Enter a valid date of birth.', 'lccl-de' );
	}

	/**
	 * Message shown when a school letter file is required but missing.
	 *
	 * @return string
	 */
	public static function letter_required_message() {
		return __( 'Please upload the school letter.', 'lccl-de' );
	}

	/**
	 * Message shown when the school letter is larger than 10 MB.
	 *
	 * @return string
	 */
	public static function letter_size_message() {
		return __( 'The school letter must be 10 MB or smaller.', 'lccl-de' );
	}

	/**
	 * Message shown when the school letter is the wrong file type.
	 *
	 * @return string
	 */
	public static function letter_type_message() {
		return __( 'Accepted formats: PDF, JPG, JPEG and PNG.', 'lccl-de' );
	}

	/**
	 * Hook the public and logged-in POST handlers.
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
		$upload = self::handle_letter_upload( $values['school_letter'] );
		if ( ! empty( $upload['error'] ) ) {
			$values['letter_file']      = '';
			$values['letter_file_name'] = '';
			$values['letter_mime']      = '';
			$errors                     = self::validate( $values );
			$errors['school_letter_file'] = $upload['error'];
			self::redirect_with_flash(
				$redirect,
				array(
					'success' => false,
					'values'  => $values,
					'errors'  => $errors,
				)
			);
		}

		$values['letter_file']      = $upload['file'];
		$values['letter_file_name'] = $upload['name'];
		$values['letter_mime']      = $upload['mime'];

		$errors = self::validate( $values );
		if ( ! empty( $errors ) ) {
			self::delete_letter_file( $values['letter_file'] );
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
			self::delete_letter_file( $values['letter_file'] );
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

		LCCL_DE_Notify::after_registration( $values, $inserted, LCCL_DE_Admin_Programs::PROGRAM_SPECTACLES );

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
		$post = wp_unslash( $_POST );

		return self::sanitize_payload( $post );
	}

	/**
	 * Pull and sanitise a JSON or form-style payload.
	 *
	 * @param array $source Raw field map.
	 * @return array
	 */
	public static function sanitize_payload( array $source ) {
		return array(
			'child_first_name'      => self::clip_field( isset( $source['child_first_name'] ) ? $source['child_first_name'] : '', 100 ),
			'child_last_name'       => self::clip_field( isset( $source['child_last_name'] ) ? $source['child_last_name'] : '', 100 ),
			'dob'                   => self::sanitize_date( isset( $source['dob'] ) ? $source['dob'] : '' ),
			'age'                   => isset( $source['age'] ) ? sanitize_text_field( (string) $source['age'] ) : '',
			'gender'                => isset( $source['gender'] ) ? sanitize_key( (string) $source['gender'] ) : '',
			'grade'                 => isset( $source['grade'] ) ? sanitize_key( (string) $source['grade'] ) : '',
			'school_name'           => self::clip_field( isset( $source['school_name'] ) ? $source['school_name'] : '', 191 ),
			'school_area'           => self::clip_field( isset( $source['school_area'] ) ? $source['school_area'] : '', 191 ),
			'district'              => isset( $source['district'] ) ? sanitize_text_field( (string) $source['district'] ) : '',
			'guardian_name'         => self::clip_field( isset( $source['guardian_name'] ) ? $source['guardian_name'] : '', 191 ),
			'phone'                 => LCCL_DE_Blood_Donor_Submissions::sanitize_phone_field( isset( $source['phone'] ) ? $source['phone'] : ( isset( $source['mobile'] ) ? $source['mobile'] : '' ) ),
			'city'                  => self::clip_field( isset( $source['city'] ) ? $source['city'] : ( isset( $source['city_area'] ) ? $source['city_area'] : '' ), 100 ),
			'relationship'          => isset( $source['relationship'] ) ? sanitize_key( (string) $source['relationship'] ) : '',
			'email'                 => LCCL_DE_Blood_Donor_Submissions::sanitize_email_field( isset( $source['email'] ) ? $source['email'] : '' ),
			'eye_exam'              => isset( $source['eye_exam'] ) ? sanitize_key( (string) $source['eye_exam'] ) : '',
			'wear_spectacles'       => isset( $source['wear_spectacles'] ) ? sanitize_key( (string) $source['wear_spectacles'] ) : '',
			'difficulty_seeing'     => isset( $source['difficulty_seeing'] ) ? sanitize_key( (string) $source['difficulty_seeing'] ) : '',
			'last_eye_exam'         => isset( $source['last_eye_exam'] ) ? sanitize_key( (string) $source['last_eye_exam'] ) : '',
			'eye_condition'         => isset( $source['eye_condition'] ) ? sanitize_key( (string) $source['eye_condition'] ) : '',
			'eye_condition_details' => isset( $source['eye_condition_details'] ) ? sanitize_textarea_field( (string) $source['eye_condition_details'] ) : '',
			'vision_difficulties'   => self::sanitize_keys( isset( $source['vision_difficulties'] ) ? $source['vision_difficulties'] : array(), array_keys( LCCL_DE_Spectacles_Form::vision_difficulties() ) ),
			'vision_other'          => self::clip_field( isset( $source['vision_other'] ) ? $source['vision_other'] : '', 255 ),
			'school_letter'         => isset( $source['school_letter'] ) ? sanitize_key( (string) $source['school_letter'] ) : '',
			'consent'               => ! empty( $source['consent'] ) ? 1 : 0,
			'letter_file'           => isset( $source['letter_file'] ) ? sanitize_text_field( (string) $source['letter_file'] ) : '',
			'letter_file_name'      => isset( $source['letter_file_name'] ) ? sanitize_file_name( (string) $source['letter_file_name'] ) : '',
			'letter_mime'           => isset( $source['letter_mime'] ) ? sanitize_text_field( (string) $source['letter_mime'] ) : '',
		);
	}

	/**
	 * Validate sanitised values. Returns field => message.
	 *
	 * @param array $values          Sanitised input.
	 * @param bool  $require_consent Whether consent must be ticked (public form only).
	 * @param bool  $require_letter  Whether a school-letter file is required when submitted herewith.
	 * @return array
	 */
	public static function validate( array $values, $require_consent = true, $require_letter = true ) {
		$errors       = array();
		$required_msg = LCCL_DE_Blood_Donor_Submissions::required_field_message();
		$required     = array(
			'child_first_name',
			'child_last_name',
			'dob',
			'gender',
			'grade',
			'school_name',
			'school_area',
			'district',
			'guardian_name',
			'phone',
			'city',
			'relationship',
			'eye_exam',
			'wear_spectacles',
			'difficulty_seeing',
			'eye_condition',
			'school_letter',
		);

		foreach ( $required as $field ) {
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
			$errors['email'] = LCCL_DE_Blood_Donor_Submissions::email_error_message();
		}

		if ( '' !== $values['dob'] && ! self::is_valid_dob( $values['dob'] ) ) {
			$errors['dob'] = self::dob_error_message();
		}

		self::assert_option( $errors, $values, 'age', LCCL_DE_Spectacles_Form::ages() );
		self::assert_option( $errors, $values, 'gender', LCCL_DE_Spectacles_Form::genders() );
		self::assert_option( $errors, $values, 'grade', LCCL_DE_Spectacles_Form::grades() );
		self::assert_option( $errors, $values, 'district', LCCL_DE_Spectacles_Form::districts() );
		self::assert_option( $errors, $values, 'relationship', LCCL_DE_Spectacles_Form::relationships() );
		self::assert_option( $errors, $values, 'eye_exam', LCCL_DE_Spectacles_Form::yes_no_unsure() );
		self::assert_option( $errors, $values, 'wear_spectacles', LCCL_DE_Spectacles_Form::yes_no() );
		self::assert_option( $errors, $values, 'difficulty_seeing', LCCL_DE_Spectacles_Form::yes_no_unsure() );
		self::assert_option( $errors, $values, 'last_eye_exam', LCCL_DE_Spectacles_Form::last_eye_exams() );
		self::assert_option( $errors, $values, 'eye_condition', LCCL_DE_Spectacles_Form::yes_no_unsure() );
		self::assert_option( $errors, $values, 'school_letter', LCCL_DE_Spectacles_Form::school_letters() );

		if ( empty( $values['vision_difficulties'] ) ) {
			$errors['vision_difficulties'] = __( 'Please select at least one vision difficulty.', 'lccl-de' );
		}

		if ( in_array( 'other', $values['vision_difficulties'], true ) && '' === $values['vision_other'] ) {
			$errors['vision_other'] = $required_msg;
		}

		if ( 'yes' === $values['eye_condition'] && '' === $values['eye_condition_details'] ) {
			$errors['eye_condition_details'] = $required_msg;
		}

		if ( $require_letter && 'submitted-herewith' === $values['school_letter'] && '' === $values['letter_file'] ) {
			$errors['school_letter_file'] = self::letter_required_message();
		}

		return $errors;
	}

	/**
	 * Insert a valid registration.
	 *
	 * @param array $values Sanitised, validated values.
	 * @return int|false Insert ID or false.
	 */
	public static function insert( array $values ) {
		global $wpdb;

		$result = $wpdb->insert(
			LCCL_DE_Schema::spectacles_table(),
			self::row_from_values( $values, true ),
			self::row_formats( true )
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

		$row = self::row_from_values( $values, false );
		$row['updated_at'] = current_time( 'mysql' );
		$row['updated_by'] = $user_id;

		$result = $wpdb->update(
			LCCL_DE_Schema::spectacles_table(),
			$row,
			array( 'id' => $id ),
			array_merge( self::row_formats( false ), array( '%s', '%d' ) ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Absolute path for a stored letter filename.
	 *
	 * @param string $file Stored basename.
	 * @return string
	 */
	public static function letter_path( $file ) {
		$file = basename( (string) $file );
		if ( '' === $file ) {
			return '';
		}

		$uploads = wp_upload_dir();
		if ( ! empty( $uploads['error'] ) ) {
			return '';
		}

		return trailingslashit( $uploads['basedir'] ) . self::UPLOAD_DIR . '/' . $file;
	}

	/**
	 * Remove a stored letter file.
	 *
	 * @param string $file Stored basename.
	 */
	public static function delete_letter_file( $file ) {
		$path = self::letter_path( $file );
		if ( $path && file_exists( $path ) ) {
			wp_delete_file( $path );
		}
	}

	/**
	 * Store a school letter when one was posted.
	 *
	 * @param string $school_letter Submission option.
	 * @return array{file:string,name:string,mime:string,error:string}
	 */
	public static function handle_letter_upload( $school_letter ) {
		$empty = array(
			'file'  => '',
			'name'  => '',
			'mime'  => '',
			'error' => '',
		);

		if ( empty( $_FILES['school_letter_file'] ) || ! is_array( $_FILES['school_letter_file'] ) ) {
			return $empty;
		}

		$file = $_FILES['school_letter_file']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( empty( $file['name'] ) || (int) $file['error'] === UPLOAD_ERR_NO_FILE ) {
			return $empty;
		}

		if ( 'submitted-herewith' !== $school_letter ) {
			return $empty;
		}

		if ( (int) $file['error'] !== UPLOAD_ERR_OK ) {
			$empty['error'] = __( 'The school letter could not be uploaded. Please try again.', 'lccl-de' );
			return $empty;
		}

		if ( (int) $file['size'] > self::LETTER_MAX_BYTES ) {
			$empty['error'] = self::letter_size_message();
			return $empty;
		}

		$check = wp_check_filetype_and_ext(
			$file['tmp_name'],
			$file['name'],
			array(
				'pdf'  => 'application/pdf',
				'jpg'  => 'image/jpeg',
				'jpeg' => 'image/jpeg',
				'png'  => 'image/png',
			)
		);

		$ext  = isset( $check['ext'] ) ? strtolower( (string) $check['ext'] ) : '';
		$mime = isset( $check['type'] ) ? (string) $check['type'] : '';
		if ( ! in_array( $ext, array( 'pdf', 'jpg', 'jpeg', 'png' ), true ) ) {
			$empty['error'] = self::letter_type_message();
			return $empty;
		}

		$uploads = wp_upload_dir();
		if ( ! empty( $uploads['error'] ) ) {
			$empty['error'] = __( 'The school letter could not be stored. Please try again.', 'lccl-de' );
			return $empty;
		}

		$dir = trailingslashit( $uploads['basedir'] ) . self::UPLOAD_DIR;
		if ( ! wp_mkdir_p( $dir ) ) {
			$empty['error'] = __( 'The school letter could not be stored. Please try again.', 'lccl-de' );
			return $empty;
		}

		$index = trailingslashit( $dir ) . 'index.php';
		if ( ! file_exists( $index ) ) {
			file_put_contents( $index, "<?php\n// Silence is golden.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}

		$stored = wp_unique_filename( $dir, wp_generate_password( 16, false, false ) . '.' . $ext );
		$dest   = trailingslashit( $dir ) . $stored;
		if ( ! move_uploaded_file( $file['tmp_name'], $dest ) ) {
			$empty['error'] = __( 'The school letter could not be stored. Please try again.', 'lccl-de' );
			return $empty;
		}

		return array(
			'file'  => $stored,
			'name'  => sanitize_file_name( (string) $file['name'] ),
			'mime'  => $mime,
			'error' => '',
		);
	}

	/**
	 * Encode vision difficulty keys for storage.
	 *
	 * @param array $keys Selected keys.
	 * @return string
	 */
	public static function encode_list( array $keys ) {
		$keys = array_values( array_filter( array_map( 'strval', $keys ) ) );
		return $keys ? wp_json_encode( $keys ) : '';
	}

	/**
	 * Decode stored vision difficulty keys.
	 *
	 * @param mixed $raw Stored JSON or CSV.
	 * @return array
	 */
	public static function decode_list( $raw ) {
		if ( is_array( $raw ) ) {
			return array_values( array_filter( array_map( 'sanitize_key', $raw ) ) );
		}

		$raw = trim( (string) $raw );
		if ( '' === $raw ) {
			return array();
		}

		$decoded = json_decode( $raw, true );
		if ( is_array( $decoded ) ) {
			return array_values( array_filter( array_map( 'sanitize_key', $decoded ) ) );
		}

		return array_values( array_filter( array_map( 'sanitize_key', explode( ',', $raw ) ) ) );
	}

	/**
	 * Database columns from sanitised values.
	 *
	 * @param array $values Sanitised values.
	 * @param bool  $create Include insert-only columns.
	 * @return array
	 */
	private static function row_from_values( array $values, $create ) {
		$row = array(
			'child_first_name'      => $values['child_first_name'],
			'child_last_name'       => $values['child_last_name'],
			'dob'                   => $values['dob'],
			'age'                   => '' !== $values['age'] ? $values['age'] : null,
			'gender'                => $values['gender'],
			'grade'                 => $values['grade'],
			'school_name'           => $values['school_name'],
			'school_area'           => $values['school_area'],
			'district'              => $values['district'],
			'guardian_name'         => $values['guardian_name'],
			'phone'                 => $values['phone'],
			'city'                  => $values['city'],
			'relationship'          => $values['relationship'],
			'email'                 => '' !== $values['email'] ? $values['email'] : null,
			'eye_exam'              => $values['eye_exam'],
			'wear_spectacles'       => $values['wear_spectacles'],
			'difficulty_seeing'     => $values['difficulty_seeing'],
			'last_eye_exam'         => '' !== $values['last_eye_exam'] ? $values['last_eye_exam'] : null,
			'eye_condition'         => $values['eye_condition'],
			'eye_condition_details' => '' !== $values['eye_condition_details'] ? $values['eye_condition_details'] : null,
			'vision_difficulties'   => self::encode_list( $values['vision_difficulties'] ),
			'vision_other'          => '' !== $values['vision_other'] ? $values['vision_other'] : null,
			'school_letter'         => $values['school_letter'],
			'letter_file'           => '' !== $values['letter_file'] ? $values['letter_file'] : null,
			'letter_file_name'      => '' !== $values['letter_file_name'] ? $values['letter_file_name'] : null,
			'letter_mime'           => '' !== $values['letter_mime'] ? $values['letter_mime'] : null,
		);

		if ( $create ) {
			$row['consent']    = 1;
			$row['ip_address'] = self::request_ip();
			$row['created_at'] = current_time( 'mysql' );
		}

		return $row;
	}

	/**
	 * wpdb formats matching row_from_values().
	 *
	 * @param bool $create Include insert-only columns.
	 * @return array
	 */
	private static function row_formats( $create ) {
		$formats = array(
			'%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s',
			'%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s',
			'%s', '%s', '%s', '%s', '%s', '%s',
		);

		if ( $create ) {
			$formats[] = '%d';
			$formats[] = '%s';
			$formats[] = '%s';
		}

		return $formats;
	}

	/**
	 * Keep allowed checkbox keys.
	 *
	 * @param mixed $raw     Posted list.
	 * @param array $allowed Allowed keys.
	 * @return array
	 */
	private static function sanitize_keys( $raw, array $allowed ) {
		if ( ! is_array( $raw ) ) {
			$raw = array();
		}

		$out = array();
		foreach ( $raw as $key ) {
			$key = sanitize_key( (string) $key );
			if ( in_array( $key, $allowed, true ) && ! in_array( $key, $out, true ) ) {
				$out[] = $key;
			}
		}

		return $out;
	}

	/**
	 * Y-m-d date, or empty.
	 *
	 * @param mixed $value Raw date.
	 * @return string
	 */
	private static function sanitize_date( $value ) {
		$value = sanitize_text_field( (string) $value );
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
			return '';
		}

		return $value;
	}

	/**
	 * Whether the date of birth is a real past date.
	 *
	 * @param string $value Y-m-d.
	 * @return bool
	 */
	private static function is_valid_dob( $value ) {
		$dt = date_create_from_format( 'Y-m-d', $value );
		if ( ! $dt || $dt->format( 'Y-m-d' ) !== $value ) {
			return false;
		}

		$today = current_time( 'Y-m-d' );
		return $value <= $today;
	}

	/**
	 * Mark an invalid optional/required select value.
	 *
	 * @param array  $errors Errors by field.
	 * @param array  $values Sanitised values.
	 * @param string $field  Field name.
	 * @param array  $map    Allowed value => label.
	 */
	private static function assert_option( array &$errors, array $values, $field, array $map ) {
		if ( '' === $values[ $field ] || isset( $errors[ $field ] ) ) {
			return;
		}

		if ( ! array_key_exists( $values[ $field ], $map ) ) {
			$errors[ $field ] = __( 'Please choose a valid option.', 'lccl-de' );
		}
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
	 * Transient key for a flash token.
	 *
	 * @param string $token Random token.
	 * @return string
	 */
	private static function flash_key( $token ) {
		return 'lccl_de_sp_flash_' . $token;
	}

	/**
	 * Store a flash payload and redirect back to the form.
	 *
	 * @param string $url   Safe redirect target.
	 * @param array  $flash Payload.
	 */
	private static function redirect_with_flash( $url, array $flash ) {
		$token = wp_generate_password( 12, false, false );
		set_transient( self::flash_key( $token ), $flash, 10 * MINUTE_IN_SECONDS );

		wp_safe_redirect( add_query_arg( self::FLASH_QUERY, $token, $url ) );
		exit;
	}

	/**
	 * Redirect target: the referring form page, or home.
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

		return home_url( '/' );
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

		$key   = 'lccl_de_sp_rl_' . md5( $ip );
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
