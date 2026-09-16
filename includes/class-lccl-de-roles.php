<?php
/**
 * Reviewer role and dashboard page.
 *
 * @package LCCL_Donations_And_Events
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers the blood donation reviewer role and its landing page.
 */
class LCCL_DE_Roles {

	/**
	 * WordPress role slug. Holds only view_lccl_submissions — no read.
	 */
	const ROLE = 'lccl_blood_donation_reviewer';

	/**
	 * Capability that unlocks the frontend dashboard and donor REST routes.
	 */
	const CAP = 'view_lccl_submissions';

	/**
	 * Bump when the role's capability set changes so existing accounts update.
	 */
	const VERSION = 1;

	/**
	 * Option that stores the installed role version.
	 */
	const OPTION = 'lccl_de_roles_version';

	/**
	 * Option that stores the frontend admin page ID.
	 */
	const PAGE_OPTION = 'lccl_de_admin_page_id';

	/**
	 * User meta: "1" means the reviewer cannot sign in.
	 */
	const DISABLED_META = 'lccl_de_reviewer_disabled';

	/**
	 * Install or upgrade the role if the stored version is behind, then the page.
	 *
	 * Must run on init or later — wp_insert_post needs rewrite rules.
	 */
	public static function maybe_install() {
		$installed = (int) get_option( self::OPTION, 0 );

		if ( $installed < self::VERSION ) {
			self::install();
		}

		self::ensure_page();
	}

	/**
	 * Create the role and strip read. Page creation happens on init.
	 */
	public static function install() {
		$role = get_role( self::ROLE );

		if ( ! $role ) {
			add_role(
				self::ROLE,
				'Blood Donation Reviewer',
				array(
					self::CAP => true,
				)
			);
			$role = get_role( self::ROLE );
		}

		if ( $role ) {
			$role->add_cap( self::CAP );
			$role->remove_cap( 'read' );
		}

		update_option( self::OPTION, self::VERSION );
	}

	/**
	 * Whether this account is a reviewer (and not a site admin).
	 *
	 * @param WP_User|int|null $user User or ID. Defaults to the current user.
	 * @return bool
	 */
	public static function user_is_reviewer( $user = null ) {
		$user = self::resolve_user( $user );

		if ( ! $user || ! $user->ID ) {
			return false;
		}

		return in_array( self::ROLE, (array) $user->roles, true );
	}

	/**
	 * Whether this account should be kept out of wp-admin.
	 *
	 * @param WP_User|int|null $user User or ID.
	 * @return bool
	 */
	public static function should_lock_admin( $user = null ) {
		$user = self::resolve_user( $user );

		if ( ! $user || ! $user->ID ) {
			return false;
		}

		if ( user_can( $user, 'manage_options' ) ) {
			return false;
		}

		return self::user_is_reviewer( $user ) || user_can( $user, self::CAP );
	}

	/**
	 * Whether the user may see donor data (reviewer or site admin).
	 *
	 * @param WP_User|int|null $user User or ID.
	 * @return bool
	 */
	public static function can_view_submissions( $user = null ) {
		$user = self::resolve_user( $user );

		if ( ! $user || ! $user->ID ) {
			return false;
		}

		if ( user_can( $user, 'manage_options' ) ) {
			return true;
		}

		if ( ! user_can( $user, self::CAP ) ) {
			return false;
		}

		return self::is_active( $user->ID );
	}

	/**
	 * Whether a reviewer account is allowed to authenticate.
	 *
	 * Site admins are always treated as active.
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public static function is_active( $user_id ) {
		$user_id = (int) $user_id;

		if ( $user_id <= 0 ) {
			return false;
		}

		if ( user_can( $user_id, 'manage_options' ) ) {
			return true;
		}

		return '1' !== get_user_meta( $user_id, self::DISABLED_META, true );
	}

	/**
	 * Frontend dashboard URL. Falls back to home if the page is missing.
	 *
	 * @return string
	 */
	public static function dashboard_url() {
		$page_id = self::ensure_page();

		if ( $page_id ) {
			$url = get_permalink( $page_id );
			if ( $url ) {
				return $url;
			}
		}

		return home_url( '/' );
	}

	/**
	 * Create or recover the page that hosts [lccl_blood_donation_admin].
	 *
	 * @return int Page ID or 0.
	 */
	public static function ensure_page() {
		$page_id = (int) get_option( self::PAGE_OPTION, 0 );

		if ( $page_id ) {
			$page = get_post( $page_id );
			if ( $page && 'page' === $page->post_type && 'trash' !== $page->post_status ) {
				return $page_id;
			}
		}

		$existing = get_posts(
			array(
				'post_type'      => 'page',
				'post_status'    => array( 'publish', 'private', 'draft' ),
				'posts_per_page' => 1,
				'name'           => 'blood-donation-admin',
			)
		);

		if ( ! empty( $existing ) ) {
			$page_id = (int) $existing[0]->ID;
			update_option( self::PAGE_OPTION, $page_id );
			return $page_id;
		}

		$page_id = wp_insert_post(
			array(
				'post_title'   => __( 'Blood Donation Admin', 'lccl-de' ),
				'post_name'    => 'blood-donation-admin',
				'post_status'  => 'publish',
				'post_type'    => 'page',
				'post_content' => '[lccl_blood_donation_admin]',
			),
			true
		);

		if ( is_wp_error( $page_id ) || ! $page_id ) {
			return 0;
		}

		update_option( self::PAGE_OPTION, (int) $page_id );

		return (int) $page_id;
	}

	/**
	 * Normalise a user argument to a WP_User or null.
	 *
	 * @param WP_User|int|null $user User, ID, or null for the current user.
	 * @return WP_User|null
	 */
	private static function resolve_user( $user ) {
		if ( $user instanceof WP_User ) {
			return $user;
		}

		if ( null === $user ) {
			if ( ! function_exists( 'wp_get_current_user' ) ) {
				return null;
			}

			$current = wp_get_current_user();
			return $current && $current->ID ? $current : null;
		}

		$loaded = get_userdata( (int) $user );
		return $loaded ? $loaded : null;
	}
}
