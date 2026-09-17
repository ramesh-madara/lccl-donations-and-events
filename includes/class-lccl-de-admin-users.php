<?php
/**
 * wp-admin CRUD for blood donation reviewer accounts.
 *
 * @package LCCL_Donations_And_Events
 */

defined( 'ABSPATH' ) || exit;

/**
 * Lists and edits only the reviewer role. Never offers a role dropdown.
 */
class LCCL_DE_Admin_Users {

	/**
	 * Menu slug.
	 */
	const PAGE = 'lccl-de-blood-users';

	/**
	 * Nonce action for all writes.
	 */
	const NONCE = 'lccl_de_manage_reviewer';

	/**
	 * Register the POST handler. The menu lives on LCCL Programs.
	 */
	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'handle_post' ) );
	}

	/**
	 * Create, update, toggle, or delete a reviewer.
	 */
	public static function handle_post() {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( empty( $_POST['lccl_de_user_action'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return;
		}

		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( LCCL_DE_Admin_Programs::PAGE !== $page && self::PAGE !== $page ) {
			return;
		}

		check_admin_referer( self::NONCE );

		$action = sanitize_key( wp_unslash( $_POST['lccl_de_user_action'] ) );

		if ( 'create' === $action ) {
			$result = self::create_user();
		} elseif ( 'update' === $action ) {
			$result = self::update_user();
		} elseif ( 'toggle' === $action ) {
			$result = self::toggle_user();
		} elseif ( 'delete' === $action ) {
			$result = self::delete_user();
		} else {
			return;
		}

		$redirect = LCCL_DE_Admin_Programs::blood_url(
			array(
				'tab'     => 'users',
				'message' => is_wp_error( $result ) ? 'error' : $result,
				'error'   => is_wp_error( $result ) ? rawurlencode( $result->get_error_message() ) : false,
			)
		);

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Legacy renderer — the workspace now lives under LCCL Programs.
	 */
	public static function render() {
		wp_safe_redirect( LCCL_DE_Admin_Programs::blood_url( array( 'tab' => 'users' ) ) );
		exit;
	}

	/**
	 * Reviewer accounts only.
	 *
	 * @return WP_User[]
	 */
	public static function get_reviewers() {
		return get_users(
			array(
				'role'    => LCCL_DE_Roles::ROLE,
				'orderby' => 'registered',
				'order'   => 'DESC',
			)
		);
	}

	/**
	 * A single reviewer, or null if the ID is not that role.
	 *
	 * @param int $user_id User ID.
	 * @return WP_User|null
	 */
	public static function get_reviewer( $user_id ) {
		$user = get_userdata( (int) $user_id );

		if ( ! $user || ! LCCL_DE_Roles::user_is_reviewer( $user ) ) {
			return null;
		}

		if ( user_can( $user, 'manage_options' ) ) {
			return null;
		}

		return $user;
	}

	/**
	 * Admin page URL helper.
	 *
	 * @param array $args Query args.
	 * @return string
	 */
	public static function url( $args = array() ) {
		$args['tab'] = isset( $args['tab'] ) ? $args['tab'] : 'users';
		return LCCL_DE_Admin_Programs::blood_url( $args );
	}

	/**
	 * Insert a reviewer.
	 *
	 * @return string|WP_Error Success code or error.
	 */
	private static function create_user() {
		$values = self::posted_fields( true );

		if ( is_wp_error( $values ) ) {
			return $values;
		}

		$user_id = wp_insert_user(
			array(
				'user_login'          => $values['user_login'],
				'user_email'          => $values['user_email'],
				'user_pass'           => $values['user_pass'],
				'first_name'          => $values['first_name'],
				'last_name'           => $values['last_name'],
				'display_name'        => trim( $values['first_name'] . ' ' . $values['last_name'] ),
				'role'                => LCCL_DE_Roles::ROLE,
				'show_admin_bar_front' => false,
			)
		);

		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}

		$created = get_userdata( $user_id );
		if ( $created && ! in_array( LCCL_DE_Roles::ROLE, (array) $created->roles, true ) ) {
			$created->set_role( LCCL_DE_Roles::ROLE );
		}

		update_user_meta( $user_id, LCCL_DE_Roles::DISABLED_META, $values['disabled'] ? '1' : '' );

		return 'created';
	}

	/**
	 * Update a reviewer. Username is not changed.
	 *
	 * @return string|WP_Error Success code or error.
	 */
	private static function update_user() {
		$user_id = isset( $_POST['user_id'] ) ? (int) $_POST['user_id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$target  = self::get_reviewer( $user_id );

		if ( ! $target ) {
			return new WP_Error( 'lccl_de_not_reviewer', __( 'That account is not a blood donation reviewer.', 'lccl-de' ) );
		}

		$values = self::posted_fields( false );

		if ( is_wp_error( $values ) ) {
			return $values;
		}

		if ( email_exists( $values['user_email'] ) && (int) email_exists( $values['user_email'] ) !== $user_id ) {
			return new WP_Error( 'lccl_de_email', __( 'That email is already in use.', 'lccl-de' ) );
		}

		$update = array(
			'ID'           => $user_id,
			'user_email'   => $values['user_email'],
			'first_name'   => $values['first_name'],
			'last_name'    => $values['last_name'],
			'display_name' => trim( $values['first_name'] . ' ' . $values['last_name'] ),
			'role'         => LCCL_DE_Roles::ROLE,
		);

		if ( '' !== $values['user_pass'] ) {
			$update['user_pass'] = $values['user_pass'];
		}

		$result = wp_update_user( $update );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		update_user_meta( $user_id, LCCL_DE_Roles::DISABLED_META, $values['disabled'] ? '1' : '' );

		return 'updated';
	}

	/**
	 * Flip the inactive flag.
	 *
	 * @return string|WP_Error
	 */
	private static function toggle_user() {
		$user_id = isset( $_POST['user_id'] ) ? (int) $_POST['user_id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$target  = self::get_reviewer( $user_id );

		if ( ! $target ) {
			return new WP_Error( 'lccl_de_not_reviewer', __( 'That account is not a blood donation reviewer.', 'lccl-de' ) );
		}

		$disabled = LCCL_DE_Roles::is_active( $user_id );
		update_user_meta( $user_id, LCCL_DE_Roles::DISABLED_META, $disabled ? '1' : '' );

		return $disabled ? 'deactivated' : 'activated';
	}

	/**
	 * Permanently delete a reviewer.
	 *
	 * @return string|WP_Error
	 */
	private static function delete_user() {
		$user_id = isset( $_POST['user_id'] ) ? (int) $_POST['user_id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$target  = self::get_reviewer( $user_id );

		if ( ! $target ) {
			return new WP_Error( 'lccl_de_not_reviewer', __( 'That account is not a blood donation reviewer.', 'lccl-de' ) );
		}

		if ( get_current_user_id() === $user_id ) {
			return new WP_Error( 'lccl_de_self', __( 'You cannot delete your own account from here.', 'lccl-de' ) );
		}

		require_once ABSPATH . 'wp-admin/includes/user.php';

		if ( ! wp_delete_user( $user_id ) ) {
			return new WP_Error( 'lccl_de_delete', __( 'The account could not be deleted.', 'lccl-de' ) );
		}

		return 'deleted';
	}

	/**
	 * Read and validate the shared form fields.
	 *
	 * @param bool $creating Whether password and username are required.
	 * @return array|WP_Error
	 */
	private static function posted_fields( $creating ) {
		$post = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

		$login     = isset( $post['user_login'] ) ? sanitize_user( $post['user_login'], true ) : '';
		$email_raw = isset( $post['user_email'] ) ? sanitize_text_field( $post['user_email'] ) : '';
		$email     = sanitize_email( $email_raw );
		$first     = isset( $post['first_name'] ) ? sanitize_text_field( $post['first_name'] ) : '';
		$last      = isset( $post['last_name'] ) ? sanitize_text_field( $post['last_name'] ) : '';
		$password  = isset( $post['user_pass'] ) ? (string) $post['user_pass'] : '';
		$disabled  = ! empty( $post['disabled'] );

		if ( $creating ) {
			if ( '' === $login || ! validate_username( $login ) ) {
				return new WP_Error( 'lccl_de_login', __( 'Please enter a valid username.', 'lccl-de' ) );
			}

			if ( username_exists( $login ) ) {
				return new WP_Error( 'lccl_de_login', __( 'That username is already taken.', 'lccl-de' ) );
			}

			if ( strlen( $password ) < 8 ) {
				return new WP_Error( 'lccl_de_pass', __( 'Password must be at least 8 characters.', 'lccl-de' ) );
			}
		} elseif ( '' !== $password && strlen( $password ) < 8 ) {
			return new WP_Error( 'lccl_de_pass', __( 'Password must be at least 8 characters.', 'lccl-de' ) );
		}

		if ( ! LCCL_DE_Blood_Donor_Submissions::is_valid_email_field( $email_raw ) || ! is_email( $email ) ) {
			return new WP_Error( 'lccl_de_email', __( 'Please enter a valid email address.', 'lccl-de' ) );
		}

		if ( $creating && email_exists( $email ) ) {
			return new WP_Error( 'lccl_de_email', __( 'That email is already in use.', 'lccl-de' ) );
		}

		if ( '' === $first || '' === $last ) {
			return new WP_Error( 'lccl_de_name', __( 'Please enter a first and last name.', 'lccl-de' ) );
		}

		return array(
			'user_login' => $login,
			'user_email' => $email,
			'first_name' => $first,
			'last_name'  => $last,
			'user_pass'  => $password,
			'disabled'   => $disabled,
		);
	}
}
