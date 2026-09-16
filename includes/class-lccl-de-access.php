<?php
/**
 * Keep reviewers out of wp-admin.
 *
 * @package LCCL_Donations_And_Events
 */

defined( 'ABSPATH' ) || exit;

/**
 * Redirects, hides the admin bar, and blocks inactive reviewer logins.
 */
class LCCL_DE_Access {

	/**
	 * Hook lockdown filters.
	 */
	public static function init() {
		add_action( 'wp_loaded', array( __CLASS__, 'block_admin' ) );
		add_action( 'admin_page_access_denied', array( __CLASS__, 'block_admin' ) );
		add_filter( 'show_admin_bar', array( __CLASS__, 'hide_admin_bar' ) );
		add_filter( 'login_redirect', array( __CLASS__, 'login_redirect' ), 10, 3 );
		add_filter( 'wp_authenticate_user', array( __CLASS__, 'block_inactive' ), 10, 2 );
	}

	/**
	 * Send reviewers to the frontend dashboard instead of wp-admin.
	 *
	 * Runs on wp_loaded because menu.php dies with a 403 before admin_init.
	 */
	public static function block_admin() {
		if ( ! is_admin() ) {
			return;
		}

		if ( wp_doing_ajax() ) {
			return;
		}

		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return;
		}

		if ( ! is_user_logged_in() || ! LCCL_DE_Roles::should_lock_admin() ) {
			return;
		}

		wp_safe_redirect( LCCL_DE_Roles::dashboard_url() );
		exit;
	}

	/**
	 * Hide the toolbar for locked reviewer accounts.
	 *
	 * @param bool $show Current visibility.
	 * @return bool
	 */
	public static function hide_admin_bar( $show ) {
		if ( is_user_logged_in() && LCCL_DE_Roles::should_lock_admin() ) {
			return false;
		}

		return $show;
	}

	/**
	 * After wp-login.php, send reviewers to the dashboard.
	 *
	 * @param string           $redirect          Default redirect.
	 * @param string           $requested_redirect Requested redirect.
	 * @param WP_User|WP_Error $user              Authenticated user.
	 * @return string
	 */
	public static function login_redirect( $redirect, $requested_redirect, $user ) {
		unset( $requested_redirect );

		if ( $user instanceof WP_User && LCCL_DE_Roles::should_lock_admin( $user ) ) {
			return LCCL_DE_Roles::dashboard_url();
		}

		return $redirect;
	}

	/**
	 * Reject inactive reviewer accounts on any login path.
	 *
	 * @param WP_User|WP_Error $user     User or existing error.
	 * @param string           $password Submitted password.
	 * @return WP_User|WP_Error
	 */
	public static function block_inactive( $user, $password ) {
		unset( $password );

		if ( is_wp_error( $user ) || ! ( $user instanceof WP_User ) ) {
			return $user;
		}

		if ( LCCL_DE_Roles::user_is_reviewer( $user ) && ! LCCL_DE_Roles::is_active( $user->ID ) ) {
			return new WP_Error(
				'lccl_de_reviewer_inactive',
				__( 'This account is inactive. Contact an administrator.', 'lccl-de' )
			);
		}

		return $user;
	}
}
